<?php

// Admin abilities, grouped by section. The groups UI
// (dashboard/admins/rules create/edit) renders one checkbox section
// per top-level key via the <x-admin.rules> component, and
// AuthServiceProvider flattens the groups to define one Gate per
// ability — so every ability here must live inside a group, and no
// flat duplicate section is needed.

return [
    'dashboard' => [
        'dashboard.view' => 'مشاهدة لوحة التحكم',
    ],

    'products' => [
        'product.view' => 'مشاهدة المنتجات',
        'product.edit' => 'تعديل المنتجات',
        'product.create' => 'انشاء المنتجات',
        'product.delete' => 'حذف منتجات',
        'product.trash.view' => 'مشاهدة المحذوفات',
        'product.restore' => 'اعادة المنتجات من المحذوفات',
        'product.delete.forever' => 'حذف المنتجات للأبد من المحذوفات',
    ],

    'categories' => [
        'category.view' => 'مشاهدة الأقسام',
        'category.edit' => 'تعديل الأقسام',
        'category.create' => 'انشاء الأقسام',
        'category.delete' => 'حذف الأقسام',
    ],

    'companies' => [
        'company.view' => 'مشاهدة الشركات',
        'company.create' => 'انشاء الشركات',
        'company.edit' => 'تعديل الشركات',
        'company.delete' => 'حذف الشركات',
    ],

    'designs' => [
        'design.view' => 'مشاهدة البنرات',
        'design.create' => 'انشاء البنرات',
        'design.edit' => 'تعديل البنرات',
        'design.delete' => 'حذف البنرات',
    ],

    'admins' => [
        'admin.view' => 'مشاهدة المدراء',
        'admin.edit' => 'تعديل بيانات المدراء',
        'admin.create' => 'اضافة مدراء',
        'admin.delete' => 'حذف المدراء',
        'admin.change_password' => 'تغيير الرقم السري للمدراء'
    ],

    'clients' => [
        'client.view' => 'مشاهدة العملاء',
        'client.delete' => 'حذف عملاء',
        'client.edit' => 'تغيير الرقم السري للعملاء'
    ],

    'groups' => [
        'admin.group.view' => 'مشاهدة مجموعات',
        'admin.group.create' => 'اضافة مجموعات',
        'admin.group.edit' => 'تعديل مجموعات',
        'admin.group.delete' => 'حذف مجموعات',
    ],

    'orders' => [
        'order.view' => 'مشاهدة طلب',
        'order.edit' => 'تعديل حالة طلب',
        'order.delete' => 'حذف طلب',
    ],

    'return_orders' => [
        'return_order.view' => 'مشاهدة ارجاع الطلب',
        'return_order.delete' => 'حذف ارجاع الطلب',
    ],

    'news' => [
        'news.view' => 'النشرة البريدية',
        'news.send' => 'إرسال النشرة البريدية',
    ],

    'settings' => [
        'settings.edit' => 'اعدادات الموقع',
    ],

    'shipping' => [
        'shipping.edit' => 'اعدادات الشحن',
    ],

    'discount_code' => [
        'discount_code.view' => 'مشاهدة كود الخصم',
        'discount_code.edit' => 'تعديل حالة كود الخصم',
        'discount_code.delete' => 'حذف كود الخصم',
        'discount_code.create' => 'اضافة كود الخصم',
    ],

    'product_availability' => [
        'product_availability.view' => 'مشاهدة حالة التوفر',
        'product_availability.create' => 'اضافة حالة التوفر',
        'product_availability.edit' => 'تعديل  حالة التوفر',
        'product_availability.delete' => 'حذف حالة التوفر',
    ],

    'currencies' => [
        'currency.view' => 'مشاهدة العملات',
        'currency.create' => 'اضافة العملات',
        'currency.edit' => 'تعديل  العملات',
        'currency.delete' => 'حذف العملات',
    ],

    'countries' => [
        'country.view' => 'مشاهدة الدول',
        'country.create' => 'انشاء الدول',
        'country.edit' => 'تعديل  الدول',
        'country.delete' => 'حذف الدول',
    ],

    'colors' => [
        'color.view' => 'مشاهدة الالوان',
        'color.create' => 'انشاء الالوان',
        'color.edit' => 'تعديل الالوان',
        'color.delete' => 'حذف الالوان',
    ],

    'reports' => [
        'report.create' => 'انشاء تقرير',
    ],

    'payments' => [
        'payment.view' => 'مشاهدة المدفوعات',
    ],

    'bulk_orders' => [
        'bulk_order.view' => 'مشاهدة طلبات الجملة',
        'bulk_order.create' => 'انشاء طلب جملة',
        'bulk_order.edit' => 'تعديل طلب جملة',
        'bulk_order.delete' => 'حذف طلب جملة',
    ],

    'representatives_orders' => [
        'representative_order.view' => 'مشاهدة طلبات المناديب',
        'representative_order.create' => 'انشاء طلب مندوب',
        'representative_order.edit' => 'تعديل طلب مندوب',
        'representative_order.delete' => 'حذف طلب مندوب',
    ],

    'store_features' => [
        'store_feature.view' => 'مشاهدة ميزات المتجر',
        'store_feature.create' => 'انشاء ميزة متجر',
        'store_feature.edit' => 'تعديل ميزة متجر',
        'store_feature.delete' => 'حذف ميزة متجر',
    ],

    'pages' => [
        'page.view' => 'مشاهدة صفحات الموقع',
        'page.create' => 'انشاء صفحة',
        'page.edit' => 'تعديل صفحة',
        'page.delete' => 'حذف صفحة',
    ],

    'contact_us' => [
        'contact_us.view' => 'مشاهدة رسائل التواصل',
    ],

    'header_banner' => [
        'header_banner.view' => 'مشاهدة بانر الهيدر',
        'header_banner.edit' => 'تعديل بانر الهيدر',
    ],

    'banners' => [
        'banner.view' => 'مشاهدة البنرات',
        'banner.create' => 'انشاء بانر',
        'banner.edit' => 'تعديل بانر',
        'banner.delete' => 'حذف بانر',
    ],

    'header_text' => [
        'header_text.view' => 'مشاهدة نصوص الهيدر',
        'header_text.create' => 'انشاء نص هيدر',
        'header_text.edit' => 'تعديل نص هيدر',
        'header_text.delete' => 'حذف نص هيدر',
    ],

    'advertisements' => [
        'advertisement.view' => 'مشاهدة الشريط الإعلاني',
        'advertisement.create' => 'انشاء اعلان',
        'advertisement.edit' => 'تعديل اعلان',
        'advertisement.delete' => 'حذف اعلان',
    ],

    'choices' => [
        'choice.view' => 'مشاهدة الخيارات',
        'choice.create' => 'انشاء خيار',
        'choice.edit' => 'تعديل خيار',
        'choice.delete' => 'حذف خيار',
    ],

    'cities' => [
        'city.view' => 'مشاهدة المدن',
        'city.create' => 'انشاء مدينة',
        'city.edit' => 'تعديل مدينة',
        'city.delete' => 'حذف مدينة',
    ],

    'common_questions' => [
        'common_question.view' => 'مشاهدة الأسئلة الشائعة',
        'common_question.create' => 'انشاء سؤال شائع',
        'common_question.edit' => 'تعديل سؤال شائع',
        'common_question.delete' => 'حذف سؤال شائع',
    ],

    'notifications' => [
        'notification.view' => 'مشاهدة الإشعارات',
    ],

];
