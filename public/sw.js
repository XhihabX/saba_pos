const CACHE_NAME = 'saba-pos-v2';
const OFFLINE_SYNC_TAG = 'saba-pos-offline-sync';
const DB_NAME = 'SabaPosDB';
const DB_VERSION = 2;

// Static assets to pre-cache on install
const STATIC_ASSETS = ['/pos'];

// =====================================================================
// IndexedDB Helpers (used inside Service Worker for Background Sync)
// =====================================================================
function openIDB() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onerror = () => reject(req.error);
    req.onsuccess = () => resolve(req.result);
    req.onupgradeneeded = (e) => {
      const db = e.target.result;
      if (!db.objectStoreNames.contains('offline_orders')) {
        db.createObjectStore('offline_orders', { keyPath: 'id' });
      }
      if (!db.objectStoreNames.contains('products_cache')) {
        db.createObjectStore('products_cache', { keyPath: 'id' });
      }
      if (!db.objectStoreNames.contains('app_config')) {
        db.createObjectStore('app_config', { keyPath: 'key' });
      }
    };
  });
}

function idbGetAll(storeName) {
  return openIDB().then(db => new Promise((resolve, reject) => {
    const tx = db.transaction(storeName, 'readonly');
    const req = tx.objectStore(storeName).getAll();
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  }));
}

function idbGet(storeName, key) {
  return openIDB().then(db => new Promise((resolve, reject) => {
    const tx = db.transaction(storeName, 'readonly');
    const req = tx.objectStore(storeName).get(key);
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  }));
}

function idbDelete(storeName, key) {
  return openIDB().then(db => new Promise((resolve, reject) => {
    const tx = db.transaction(storeName, 'readwrite');
    const req = tx.objectStore(storeName).delete(key);
    req.onsuccess = () => resolve();
    req.onerror = () => reject(req.error);
  }));
}

// =====================================================================
// Install: Pre-cache static assets
// =====================================================================
self.addEventListener('install', event => {
  console.log('[SW] Installing Saba POS Service Worker...');
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache =>
      Promise.allSettled(STATIC_ASSETS.map(url => cache.add(url).catch(() => {})))
    )
  );
});

// =====================================================================
// Activate: Clean stale caches, claim clients immediately
// =====================================================================
self.addEventListener('activate', event => {
  console.log('[SW] Activating Saba POS Service Worker...');
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// =====================================================================
// Fetch: Offline-first strategy
// =====================================================================
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // Never intercept POST / non-GET requests here
  if (request.method !== 'GET') return;

  // ---- Strategy 1: Cache-first for Vite build assets (JS/CSS/fonts) ----
  if (url.pathname.startsWith('/build/')) {
    event.respondWith(
      caches.match(request).then(cached => {
        if (cached) return cached;
        return fetch(request).then(response => {
          if (response.ok) {
            caches.open(CACHE_NAME).then(c => c.put(request, response.clone()));
          }
          return response;
        }).catch(() => new Response('Offline', { status: 503 }));
      })
    );
    return;
  }

  // ---- Strategy 2: Network-first with cache fallback for /pos page ----
  if (url.pathname === '/pos' && request.headers.get('Accept')?.includes('text/html')) {
    event.respondWith(
      fetch(request)
        .then(response => {
          if (response.ok) {
            caches.open(CACHE_NAME).then(c => c.put(request, response.clone()));
          }
          return response;
        })
        .catch(() => {
          return caches.match(request).then(cached => {
            if (cached) return cached;
            return new Response(
              '<html><body style="font-family:sans-serif;padding:2rem;background:#0f172a;color:#e2e8f0"><h1>🛒 Saba POS - Offline Mode</h1><p>The terminal is offline. Please open the POS while connected to Internet at least once to cache it for offline use.</p></body></html>',
              { headers: { 'Content-Type': 'text/html' } }
            );
          });
        })
    );
    return;
  }
});

// =====================================================================
// Background Sync: Process queued offline orders
// =====================================================================
self.addEventListener('sync', event => {
  if (event.tag === OFFLINE_SYNC_TAG) {
    console.log('[SW] Background sync triggered: processing offline order queue...');
    event.waitUntil(processOfflineOrders());
  }
});

async function processOfflineOrders() {
  let orders;
  try {
    orders = await idbGetAll('offline_orders');
  } catch (e) {
    console.error('[SW] Failed to read offline_orders from IndexedDB:', e);
    return;
  }

  if (!orders || orders.length === 0) {
    console.log('[SW] No offline orders to sync.');
    return;
  }

  console.log(`[SW] Syncing ${orders.length} offline order(s)...`);

  // Read CSRF token stored by the POS page
  let csrfToken = '';
  try {
    const config = await idbGet('app_config', 'csrf_token');
    csrfToken = config?.value || '';
  } catch (_) {}

  let syncedCount = 0;
  const failedOrders = [];

  for (const order of orders) {
    try {
      // Strip internal fields before sending
      const { id: offlineId, _csrf, ...payload } = order;

      const response = await fetch('/pos/checkout', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken || _csrf || '',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
      });

      if (response.ok) {
        const data = await response.json().catch(() => ({}));
        await idbDelete('offline_orders', offlineId);
        syncedCount++;

        // Notify all open POS tabs about successful sync
        const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        clients.forEach(client => {
          client.postMessage({
            type: 'ORDER_SYNCED',
            orderId: offlineId,
            invoiceNo: data.invoice_no,
            grandTotal: data.grand_total,
          });
        });
      } else {
        console.warn(`[SW] Order ${offlineId} rejected by server: HTTP ${response.status}`);
        failedOrders.push(order);
      }
    } catch (networkError) {
      console.warn(`[SW] Order ${order.id} failed (network error):`, networkError);
      failedOrders.push(order);
      // Re-throw so Background Sync API knows to retry
      throw networkError;
    }
  }

  // Notify tabs about sync completion summary
  if (syncedCount > 0) {
    const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    clients.forEach(client => {
      client.postMessage({
        type: 'SYNC_COMPLETE',
        syncedCount,
        failedCount: failedOrders.length,
      });
    });
  }
}

// =====================================================================
// Message Handler: Communication from POS tab → Service Worker
// =====================================================================
self.addEventListener('message', event => {
  if (event.data?.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
  // Manual sync trigger from the terminal UI
  if (event.data?.type === 'TRIGGER_SYNC') {
    processOfflineOrders().catch(e => console.error('[SW] Manual sync failed:', e));
  }
});
