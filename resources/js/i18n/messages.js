/**
 * IOT POS - International & Regional (Bengali / English) Bilingual i18n Dictionary
 */

export const messages = {
  en: {
    app_title: "IOT POS & Enterprise ERP",
    pos_terminal: "POS Terminal",
    dashboard: "Dashboard",
    inventory: "Inventory",
    customers: "Customers",
    suppliers: "Suppliers",
    reports: "Reports",
    settings: "Settings",
    search_placeholder: "Search products (F1)...",
    checkout: "Checkout",
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
    open_shift: "Open Shift",
    close_shift: "Close Shift",
    bin_no: "BIN No",
    musak_63: "Mushak-6.3 Tax Invoice",
    export_csv: "Export CSV",
    sms_reminder: "Send SMS Reminder",
    credit_limit: "Credit Limit",
    online_mode: "Cloud Connected",
    offline_mode: "Offline Mode",
  },
  bn: {
    app_title: "আইওটি পস এবং এন্টারপ্রাইজ ইআরপি",
    pos_terminal: "কাউন্টার বিক্রয় টার্মিনাল",
    dashboard: "ড্যাশবোর্ড",
    inventory: "পণ্য ও স্টক ইনভেন্টরি",
    customers: "গ্রাহক বাকির খাতা",
    suppliers: "সরবরাহকারী দেনার খাতা",
    reports: "ব্যবসায়িক রিপোর্ট",
    settings: "সিস্টেম সেটিংস",
    search_placeholder: "পণ্য খুঁজুন (F1)...",
    checkout: "পেমেন্ট সম্পন্ন করুন",
    subtotal: "মোট পণ্য মূল্য",
    discount: "মূল্য ছাড়",
    tax_vat: "ভ্যাট ও ট্যাক্স",
    grand_total: "সর্বমোট প্রদেয় মূল্য",
    paid_amount: "গ্রাহকের প্রদত্ত টাকা",
    change_return: "ফেরতযোগ্য টাকা",
    due_amount: "বাকির পরিমাণ",
    payment_method: "পেমেন্টের মাধ্যম",
    cash: "নগদ টাকা (ক্যাশ)",
    card: "ব্যাংক কার্ড (POS)",
    mobile_wallet: "বিকাশ / নগদ / রকেট",
    customer_credit: "গ্রাহক বাকি (খাতা)",
    park_order: "অর্ডার হোল্ড করুন",
    resume_order: "হোল্ড অর্ডার আনুন",
    print_receipt: "মেমো প্রিন্ট করুন",
    open_shift: "শিফট চালু করুন",
    close_shift: "শিফট বন্ধ ও হিসাব হিসাব",
    bin_no: "বিআইএন (BIN) নম্বর",
    musak_63: "মুসক-৬.৩ কর চালানপত্র",
    export_csv: "এক্সেল এক্সপোর্ট",
    sms_reminder: "এসএমএস রিমাইন্ডার পাঠান",
    credit_limit: "সর্বোচ্চ বাকি সীমা",
    online_mode: "অনলাইন কানেক্টেড",
    offline_mode: "অফলাইন মোড",
  },
};

export const currentLang = () => {
  return localStorage.getItem('iot_pos_lang') || 'en';
};

export const setLang = (lang) => {
  localStorage.setItem('iot_pos_lang', lang);
  window.location.reload();
};

export const t = (key) => {
  const lang = currentLang();
  return messages[lang]?.[key] || messages['en']?.[key] || key;
};
