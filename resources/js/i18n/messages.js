/**
 * IOT POS - Bilingual (English / Bengali) i18n Dictionary
 */

export const messages = {
  en: {
    app_title: "IOT POS & Enterprise ERP",
    pos_terminal: "POS Workstation",
    dashboard: "Dashboard",
    inventory: "Inventory",
    products: "Products & Stock",
    customers: "Customers",
    suppliers: "Suppliers",
    purchases: "Purchases",
    reports: "Reports & Analytics",
    settings: "Settings",
    expenses: "Expenses",
    quotations: "Quotations",
    shifts: "Shift Reconciliation",
    attendance: "Staff Attendance",
    super_admin: "Super Admin",
    merchant: "Merchant HQ",
    store_manager: "Store Manager",
    cashier: "Cashier",
    search_placeholder: "Search products (F1)...",
    checkout: "Checkout & Pay",
    subtotal: "Subtotal",
    discount: "Discount",
    tax_vat: "Tax / VAT",
    grand_total: "Grand Total",
    paid_amount: "Paid Amount",
    change_return: "Change Return",
    due_amount: "Due Amount",
    payment_method: "Payment Method",
    cash: "Cash",
    card: "Card",
    mobile_wallet: "bKash / Nagad",
    customer_credit: "Customer Credit (Baki)",
    park_order: "Park Order",
    resume_order: "Resume Order",
    print_receipt: "Print Receipt",
    open_shift: "Open Register Shift",
    close_shift: "Close Shift & Audit",
    bin_no: "BIN No",
    musak_63: "Mushak-6.3 Tax Invoice",
    export_csv: "Export CSV",
    sms_reminder: "Send SMS Reminder",
    credit_limit: "Credit Limit",
    online_mode: "Cloud Connected",
    offline_mode: "Offline Mode",
    sign_out: "Sign Out",
  },
  bn: {
    app_title: "আইওটি পস এবং এন্টারপ্রাইজ ইআরপি",
    pos_terminal: "পস কাউন্টার সেলস",
    dashboard: "ড্যাশবোর্ড",
    inventory: "ইনভентরি পণ্য ও স্টক",
    products: "পণ্য ও স্টক তালিকা",
    customers: "গ্রাহক বাকির খাতা",
    suppliers: "সরবরাহকারী দেনার খাতা",
    purchases: "পণ্য ক্রয় (পারচেজ)",
    reports: "ব্যবসায়িক রিপোর্ট",
    settings: "সিস্টেম সেটিংস",
    expenses: "দোকানের খরচ",
    quotations: "সেলস কোটেশন",
    shifts: "ক্যাশ রেজিস্টার শিফট",
    attendance: "স্টাফ হাজিরা",
    super_admin: "সুপার অ্যাডমিন",
    merchant: "মার্চেন্ট হেডকোয়ার্টার",
    store_manager: "স্টোর ম্যানেজার",
    cashier: "ক্যাশিয়ার",
    search_placeholder: "পণ্য খুঁজুন (F1)...",
    checkout: "পেমেন্ট গ্রহণ করুন",
    subtotal: "মোট মূল্য",
    discount: "মূল্য ছাড়",
    tax_vat: "ভ্যাট ও ট্যাক্স",
    grand_total: "সর্বমোট বিল",
    paid_amount: "গৃহীত টাকা",
    change_return: "ফেরতযোগ্য টাকা",
    due_amount: "বাকির পরিমাণ",
    payment_method: "পেমেন্টের মাধ্যম",
    cash: "নগদ টাকা (ক্যাশ)",
    card: "ব্যাংক কার্ড (POS)",
    mobile_wallet: "বিকাশ / নগদ / রকেট",
    customer_credit: "গ্রাহক বাকি (খাতা)",
    park_order: "অর্ডার হোল্ড করুন",
    resume_order: "হোল্ড অর্ডার আনুন",
    print_receipt: "ক্যাশ মেমো প্রিন্ট",
    open_shift: "ক্যাশ রেজিস্টার চালু",
    close_shift: "শিফট বন্ধ ও ক্যাশ অডিট",
    bin_no: "বিআইএন (BIN) নম্বর",
    musak_63: "মুসক-৬.৩ কর চালানপত্র",
    export_csv: "এক্সেল এক্সপোর্ট",
    sms_reminder: "এসএমএস রিমাইন্ডার পাঠান",
    credit_limit: "সর্বোচ্চ বাকি সীমা",
    online_mode: "অনলাইন কানেক্টেড",
    offline_mode: "অফলাইন মোড",
    sign_out: "লগ আউট",
  },
};

export const currentLang = () => {
  if (typeof window === 'undefined') return 'en';
  return localStorage.getItem('iot_pos_lang') || 'en';
};

export const setLang = (lang) => {
  if (typeof window === 'undefined') return;
  localStorage.setItem('iot_pos_lang', lang);
  window.location.reload();
};

export const t = (key) => {
  const lang = currentLang();
  return messages[lang]?.[key] || messages['en']?.[key] || key;
};
