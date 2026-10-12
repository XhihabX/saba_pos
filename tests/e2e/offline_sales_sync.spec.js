import { test, expect } from '@playwright/test';
import mysql from 'mysql2/promise';

/**
 * Playwright E2E Offline Sales Synchronization Test Suite
 *
 * Requirements:
 * 1. Cashier logs in, opens POS terminal.
 * 2. Simulates offline mode (context.setOffline(true)).
 * 3. Completes 3 sales offline:
 *    - Sale 1: Single item (Cash checkout)
 *    - Sale 2: Single item (Card checkout)
 *    - Sale 3: Multi-item (Split payment: Cash + Card)
 * 4. Reconnects network (context.setOffline(false)) and triggers sync.
 * 5. Asserts directly in MySQL using mysql2:
 *    - Exactly 3 orders created
 *    - Stock inventory decremented accurately
 *    - No duplicate orders
 * 6. Second sync trigger (asserts idempotency, database unchanged).
 * 7. Server stock deficit while offline (asserts sale accepted & flagged_for_review = 1).
 */

const dbConfig = {
  host: process.env.DB_HOST || '127.0.0.1',
  port: Number(process.env.DB_PORT) || 3306,
  user: process.env.DB_USERNAME || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_DATABASE || 'sabapos_test',
};

test.describe('Offline Sales Queue & MySQL Synchronization E2E Gate', () => {
  let connection;

  test.beforeAll(async () => {
    connection = await mysql.createConnection(dbConfig);
  });

  test.afterAll(async () => {
    if (connection) {
      await connection.end();
    }
  });

  test('Cashier offline sales, reconnection sync, idempotency & deficit review', async ({ page, context }) => {
    // -------------------------------------------------------------------------
    // Step 0: Fetch initial database state using mysql2
    // -------------------------------------------------------------------------
    const [cashierRows] = await connection.execute(
      'SELECT id, tenant_id, store_id FROM users WHERE email = ?',
      ['ci_cashier@iotpos.com']
    );
    expect(cashierRows.length).toBeGreaterThan(0);
    const cashierUser = cashierRows[0];

    const [tenantRows] = await connection.execute(
      'SELECT id FROM tenants WHERE code = ?',
      ['CI-TENANT-E2E']
    );
    expect(tenantRows.length).toBeGreaterThan(0);
    const tenantId = tenantRows[0].id;
    const storeId = cashierUser.store_id;

    // Fetch initial product stocks
    const [initialStockRows] = await connection.execute(
      `SELECT p.id as product_id, p.sku, s.quantity
       FROM products p
       JOIN stocks s ON s.product_id = p.id AND s.store_id = ?
       WHERE p.sku IN ('PW-MOUSE-01', 'PW-KEYBOARD-01', 'PW-HUB-01')`,
      [storeId]
    );

    const initialStocks = {};
    for (const row of initialStockRows) {
      initialStocks[row.sku] = Number(row.quantity);
    }
    expect(initialStocks['PW-MOUSE-01']).toBeDefined();
    expect(initialStocks['PW-KEYBOARD-01']).toBeDefined();
    expect(initialStocks['PW-HUB-01']).toBeDefined();

    const [initialOrderCountRows] = await connection.execute(
      'SELECT COUNT(*) as count FROM orders WHERE tenant_id = ? AND store_id = ?',
      [tenantId, storeId]
    );
    const initialOrderCount = Number(initialOrderCountRows[0].count);

    // -------------------------------------------------------------------------
    // Step 1: Cashier Login
    // -------------------------------------------------------------------------
    await page.goto('/login');
    await page.fill('input[type="email"]', 'ci_cashier@iotpos.com');
    await page.fill('input[type="password"]', 'password123');
    await page.click('button[type="submit"]');

    // Expect navigation to POS terminal
    await page.waitForURL('**/pos');
    await expect(page).toHaveURL(/.*\/pos/);

    // Verify products are loaded on POS catalog
    await expect(page.locator('text=Playwright Laser Mouse')).toBeVisible();
    await expect(page.locator('text=Playwright Mechanical Keyboard')).toBeVisible();
    await expect(page.locator('text=Playwright USB Hub 4-Port')).toBeVisible();

    // -------------------------------------------------------------------------
    // Step 2: Go Offline
    // -------------------------------------------------------------------------
    await context.setOffline(true);
    await page.evaluate(() => window.dispatchEvent(new Event('offline')));
    await expect(page.locator('text=Offline Mode')).toBeVisible();

    // -------------------------------------------------------------------------
    // Step 3: Sale 1 — Single Item Quick Cash Checkout (Playwright Laser Mouse)
    // -------------------------------------------------------------------------
    await page.click('text=Playwright Laser Mouse');
    await expect(page.locator('#btn-quick-cash')).toBeVisible();
    await page.click('#btn-quick-cash');

    // Verify order was queued offline
    await expect(page.locator('button:has-text("Sync (1)")')).toBeVisible({ timeout: 5000 });

    // -------------------------------------------------------------------------
    // Step 4: Sale 2 — Single Item Card Checkout (Playwright Mechanical Keyboard)
    // -------------------------------------------------------------------------
    await page.click('text=Playwright Mechanical Keyboard');
    await page.click('#btn-split-checkout');

    // Checkout modal opens
    await expect(page.locator('#btn-single-mode')).toBeVisible();
    await page.click('#btn-method-card');
    await page.click('#btn-confirm-checkout');

    // Verify queued count increases to 2
    await expect(page.locator('button:has-text("Sync (2)")')).toBeVisible({ timeout: 5000 });

    // -------------------------------------------------------------------------
    // Step 5: Sale 3 — Multi-Item Split Tender (Mouse + USB Hub)
    // -------------------------------------------------------------------------
    await page.click('text=Playwright Laser Mouse');
    await page.click('text=Playwright USB Hub 4-Port');
    await page.click('#btn-split-checkout');

    // Switch to Split Tender Mode
    await expect(page.locator('#btn-split-mode')).toBeVisible();
    await page.click('#btn-split-mode');
    await expect(page.locator('#btn-add-tender-line')).toBeVisible();

    // Submit split tender checkout
    await page.click('#btn-confirm-checkout');

    // Verify queued count increases to 3
    await expect(page.locator('button:has-text("Sync (3)")')).toBeVisible({ timeout: 5000 });

    // -------------------------------------------------------------------------
    // Step 6: Reconnect to Network & Trigger Synchronization
    // -------------------------------------------------------------------------
    await context.setOffline(false);
    await page.evaluate(() => window.dispatchEvent(new Event('online')));

    // Click Sync button or wait for auto-sync
    const syncButton = page.locator('button:has-text("Sync")');
    if (await syncButton.isVisible()) {
      await syncButton.click();
    }

    // Wait until offline queue is fully drained (Sync button disappears)
    await expect(page.locator('button:has-text("Sync")')).not.toBeVisible({ timeout: 15000 });

    // -------------------------------------------------------------------------
    // Step 7: MySQL 8.4 Direct Database Verification via mysql2
    // -------------------------------------------------------------------------
    const [finalOrderCountRows] = await connection.execute(
      'SELECT COUNT(*) as count FROM orders WHERE tenant_id = ? AND store_id = ?',
      [tenantId, storeId]
    );
    const finalOrderCount = Number(finalOrderCountRows[0].count);
    expect(finalOrderCount).toBe(initialOrderCount + 3);

    // Fetch the 3 newly synced orders
    const [syncedOrders] = await connection.execute(
      `SELECT id, invoice_no, payment_method, grand_total, paid_amount, idempotency_key, flagged_for_review
       FROM orders
       WHERE tenant_id = ? AND store_id = ?
       ORDER BY id DESC LIMIT 3`,
      [tenantId, storeId]
    );
    expect(syncedOrders.length).toBe(3);

    // Verify no duplicate idempotency keys
    const idempotencyKeys = syncedOrders.map(o => o.idempotency_key);
    const uniqueKeys = new Set(idempotencyKeys);
    expect(uniqueKeys.size).toBe(3);

    // Verify inventory deductions in stocks table
    // PW-MOUSE-01: sold 1 in Sale 1 + 1 in Sale 3 = 2 units total
    // PW-KEYBOARD-01: sold 1 in Sale 2 = 1 unit total
    // PW-HUB-01: sold 1 in Sale 3 = 1 unit total
    const [updatedStockRows] = await connection.execute(
      `SELECT p.sku, s.quantity
       FROM products p
       JOIN stocks s ON s.product_id = p.id AND s.store_id = ?
       WHERE p.sku IN ('PW-MOUSE-01', 'PW-KEYBOARD-01', 'PW-HUB-01')`,
      [storeId]
    );

    const updatedStocks = {};
    for (const row of updatedStockRows) {
      updatedStocks[row.sku] = Number(row.quantity);
    }

    expect(updatedStocks['PW-MOUSE-01']).toBe(initialStocks['PW-MOUSE-01'] - 2);
    expect(updatedStocks['PW-KEYBOARD-01']).toBe(initialStocks['PW-KEYBOARD-01'] - 1);
    expect(updatedStocks['PW-HUB-01']).toBe(initialStocks['PW-HUB-01'] - 1);

    // -------------------------------------------------------------------------
    // Step 8: Idempotency Verification (Second Sync Attempt)
    // -------------------------------------------------------------------------
    // Replay the first synced order's idempotency key via POS checkout
    const sampleOrder = syncedOrders[0];
    const replayResponse = await page.request.post('/pos/checkout', {
      data: {
        idempotency_key: sampleOrder.idempotency_key,
        client_uuid: sampleOrder.idempotency_key,
        store_id: storeId,
        items: [{ product_id: 1, quantity: 1, unit_price: 500, total: 500 }],
        subtotal: 500,
        paid_amount: 500,
        payment_method: 'cash',
        is_offline_sync: true,
        client_time: new Date().toISOString(),
      },
    });

    // Server must reject or return idempotent cached response without duplicate creation
    const [idempotentOrderCountRows] = await connection.execute(
      'SELECT COUNT(*) as count FROM orders WHERE tenant_id = ? AND store_id = ?',
      [tenantId, storeId]
    );
    expect(Number(idempotentOrderCountRows[0].count)).toBe(finalOrderCount);

    // -------------------------------------------------------------------------
    // Step 9: Offline Stock Deficit Review Flagging
    // -------------------------------------------------------------------------
    // Force inventory for USB Hub to 0 directly in MySQL
    await connection.execute(
      `UPDATE stocks s
       JOIN products p ON s.product_id = p.id
       SET s.quantity = 0
       WHERE p.sku = 'PW-HUB-01' AND s.store_id = ?`,
      [storeId]
    );

    // Go offline again
    await context.setOffline(true);
    await page.evaluate(() => window.dispatchEvent(new Event('offline')));

    // Sell 1 USB Hub while offline
    await page.click('text=Playwright USB Hub 4-Port');
    await page.click('#btn-quick-cash');
    await expect(page.locator('button:has-text("Sync (1)")')).toBeVisible({ timeout: 5000 });

    // Go online & sync
    await context.setOffline(false);
    await page.evaluate(() => window.dispatchEvent(new Event('online')));

    const syncBtn2 = page.locator('button:has-text("Sync")');
    if (await syncBtn2.isVisible()) {
      await syncBtn2.click();
    }
    await expect(page.locator('button:has-text("Sync")')).not.toBeVisible({ timeout: 15000 });

    // Verify 4th order created with flagged_for_review = 1 in MySQL
    const [deficitOrders] = await connection.execute(
      `SELECT id, invoice_no, flagged_for_review, review_reason
       FROM orders
       WHERE tenant_id = ? AND store_id = ?
       ORDER BY id DESC LIMIT 1`,
      [tenantId, storeId]
    );
    expect(deficitOrders.length).toBe(1);
    const deficitOrder = deficitOrders[0];
    expect(Boolean(deficitOrder.flagged_for_review)).toBe(true);
    expect(deficitOrder.review_reason).toContain('Offline sale synced with insufficient server stock');

    // Verify inventory decremented to negative deficit in MySQL
    const [finalHubStockRows] = await connection.execute(
      `SELECT s.quantity
       FROM products p
       JOIN stocks s ON s.product_id = p.id AND s.store_id = ?
       WHERE p.sku = 'PW-HUB-01'`,
      [storeId]
    );
    expect(Number(finalHubStockRows[0].quantity)).toBe(-1);
  });
});
