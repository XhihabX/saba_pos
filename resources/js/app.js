import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

createInertiaApp({
  title: (title) => (title ? `${title} - Saba POS` : 'Saba POS - SaaS ERP'),
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
    const page = pages[`./Pages/${name}.vue`];
    if (!page) {
      console.error(`Inertia page component not found: ${name}`);
    }
    return page?.default || page;
  },
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) });
    app.use(plugin);

    const safeRoute = (name, params, absolute) => {
      let url = '';
      if (typeof window !== 'undefined' && typeof window.route === 'function') {
        try {
          url = window.route(name, params, absolute);
          if (typeof url === 'string' && url.startsWith('http')) {
            try {
              const parsed = new URL(url);
              url = parsed.pathname + parsed.search + parsed.hash;
            } catch (err) {}
          }
        } catch (e) {
          console.warn(`Ziggy route [${name}] error:`, e);
        }
      }
      if (!url) {
        const fallbackRoutes = {
          'landing': '/',
          'login': '/login',
          'register': '/register',
          'logout': '/logout',
          'superadmin.dashboard': '/super-admin/dashboard',
          'superadmin.tenants.store': '/super-admin/tenants',
          'superadmin.plans': '/super-admin/plans',
          'superadmin.stores': '/super-admin/stores',
          'superadmin.stores.store': '/super-admin/stores',
          'superadmin.users': '/super-admin/users',
          'superadmin.users.store': '/super-admin/users',
          'superadmin.transactions': '/super-admin/transactions',
          'superadmin.analytics': '/super-admin/analytics',
          'superadmin.auditlogs': '/super-admin/audit-logs',
          'superadmin.recyclebin': '/super-admin/recycle-bin',
          'superadmin.systemhealth': '/super-admin/system-health',
          'superadmin.announcements': '/super-admin/announcements',
          'superadmin.settings': '/super-admin/settings',
          'merchant.dashboard': '/merchant/dashboard',
          'merchant.stores': '/merchant/stores',
          'merchant.users': '/merchant/users',
          'merchant.suppliers': '/merchant/suppliers',
          'merchant.purchases': '/merchant/purchases',
          'merchant.subscription': '/merchant/subscription',
          'manager.dashboard': '/manager/dashboard',
          'manager.shifts': '/manager/shifts',
          'manager.transfers': '/manager/transfers',
          'manager.returns': '/manager/returns',
          'pos.index': '/pos',
          'products.index': '/products',
          'products.barcodes': '/products/barcodes',
          'reports.profit-loss': '/reports/profit-loss',
          'expenses.index': '/expenses',
          'sales.quotations': '/sales/quotations',
          'hrm.attendance': '/hrm/attendance',
        };
        url = fallbackRoutes[name] || ('/' + String(name || '').replace(/\./g, '/'));
      }
      if (typeof url === 'string' && !url.startsWith('/') && !url.startsWith('http')) {
        url = '/' + url;
      }
      return url;
    };

    app.config.globalProperties.route = safeRoute;
    app.mixin({
      methods: {
        route: safeRoute,
      }
    });

    app.mount(el);
  },
  progress: {
    color: '#059669',
    showSpinner: true,
  },
});
