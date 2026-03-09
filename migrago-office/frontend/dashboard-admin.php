<?php
$base_url = home_url('/dashboard');
$view = sanitize_key($_GET['view'] ?? 'stats');
$allowed_views = ['stats', 'add_client', 'add_staff', 'search'];
if (!in_array($view, $allowed_views, true)) {
    $view = 'stats';
}

$stats = migrago_office_analytics_snapshot();
$total_clients = (int) ($stats['total_clients'] ?? 0);
$staff_message = '';
$client_message = '';
$payment_message = '';
$stage_message = '';
$edit_message = '';
$search_term = sanitize_text_field($_GET['q'] ?? '');
$results = migrago_office_search_clients($search_term);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrago_staff_nonce']) && wp_verify_nonce(sanitize_text_field($_POST['migrago_staff_nonce']), 'migrago_add_staff')) {
    $staff_id = migrago_office_create_staff_user($_POST);
    $staff_message = $staff_id > 0 ? 'تم إنشاء الموظف بنجاح' : 'تعذر إنشاء الموظف';
    $view = 'add_staff';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrago_client_nonce']) && wp_verify_nonce(sanitize_text_field($_POST['migrago_client_nonce']), 'migrago_add_client')) {
    $client_id = migrago_office_create_client($_POST);
    if ($client_id > 0) {
        $file_number = migrago_office_get_client_file_number($client_id);
        $contract_url = migrago_office_get_client_contract_url($client_id);
        $receipt_url = migrago_office_get_client_receipt_url($client_id);
        $client_message = 'تم إنشاء العميل بنجاح. رقم الملف: <strong>' . esc_html($file_number) . '</strong> | <a href="' . esc_url($receipt_url) . '" target="_blank">تحميل/طباعة التوصيل</a> | <a href="' . esc_url($contract_url) . '" target="_blank">تحميل/طباعة عقد الخدمة</a>';
    } else {
        $client_message = 'فشل إنشاء العميل. تحقق من الحقول المطلوبة والصلاحيات ثم حاول مجدداً.';
    }
    $view = 'add_client';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrago_edit_client_nonce']) && wp_verify_nonce(sanitize_text_field($_POST['migrago_edit_client_nonce']), 'migrago_edit_client')) {
    $client_id = (int) ($_POST['client_id'] ?? 0);
    $updated = migrago_office_update_client($client_id, $_POST);
    $edit_message = $updated ? 'تم تحديث بيانات العميل بنجاح.' : 'تعذر تحديث بيانات العميل.';

    $view = 'search';
    $search_term = sanitize_text_field($_POST['search_term'] ?? '');
    $results = migrago_office_search_clients($search_term);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrago_stage_nonce']) && wp_verify_nonce(sanitize_text_field($_POST['migrago_stage_nonce']), 'migrago_update_stage')) {
    $client_id = (int) ($_POST['client_id'] ?? 0);
    $next_stage = sanitize_key($_POST['workflow_stage'] ?? '');
    $stage_note = sanitize_textarea_field($_POST['stage_note'] ?? '');
    $stages = migrago_office_workflow_stages();

    if ($client_id > 0 && isset($stages[$next_stage])) {
        update_post_meta($client_id, '_migrago_workflow_stage', $next_stage);
        update_post_meta($client_id, '_migrago_stage_note', $stage_note);
        update_post_meta($client_id, '_migrago_stage_updated_at', current_time('mysql'));
        update_post_meta($client_id, '_migrago_stage_updated_by', get_current_user_id());
        migrago_office_log_action('update_stage', 'client', $client_id, ['stage' => $next_stage, 'note' => $stage_note]);
        $stage_message = 'تم تحديث مرحلة الملف والملاحظات بنجاح.';
    }

    $view = 'search';
    $search_term = sanitize_text_field($_POST['search_term'] ?? '');
    $results = migrago_office_search_clients($search_term);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrago_payment_nonce']) && wp_verify_nonce(sanitize_text_field($_POST['migrago_payment_nonce']), 'migrago_add_payment')) {
    $client_id = (int) ($_POST['client_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $method = sanitize_text_field($_POST['method'] ?? 'cash');

    if ($client_id > 0 && $amount > 0) {
        migrago_office_add_payment($client_id, $amount, $method);
        $receipt_id = migrago_office_create_receipt($client_id, $amount, $method);
        if ($receipt_id > 0) {
            update_post_meta($client_id, '_migrago_last_receipt_id', $receipt_id);
        }
        $payment_message = $receipt_id > 0 ? 'تمت إضافة الدفعة. <a href="' . esc_url(add_query_arg('receipt_id', (string) $receipt_id, home_url('/receipt'))) . '" target="_blank">تحميل/طباعة التوصيل</a>' : 'تمت إضافة الدفعة';
    }

    $view = 'search';
    $search_term = sanitize_text_field($_POST['search_term'] ?? '');
    $results = migrago_office_search_clients($search_term);
}
?>
<div class="mo-dashboard-shell" dir="rtl">
    <header class="mo-simple-header">مرحباً بكم في إدارة شركة MIGRAGO — نتمنى لكم يوماً عملياً موفقاً ومليئاً بالإنجاز.</header>
    <div class="mo-layout">
        <aside class="mo-sidebar">
            <h2>لوحة المشرف</h2>
            <a href="<?php echo esc_url(add_query_arg(['view' => 'stats'], $base_url)); ?>" class="mo-nav-btn <?php echo $view === 'stats' ? 'is-active-nav' : ''; ?>">📊 الإحصائيات</a>
            <a href="<?php echo esc_url(add_query_arg(['view' => 'add_client'], $base_url)); ?>" class="mo-nav-btn <?php echo $view === 'add_client' ? 'is-active-nav' : ''; ?>">➕ إضافة عميل</a>
            <a href="<?php echo esc_url(add_query_arg(['view' => 'add_staff'], $base_url)); ?>" class="mo-nav-btn <?php echo $view === 'add_staff' ? 'is-active-nav' : ''; ?>">👥 إضافة موظف</a>
            <a href="<?php echo esc_url(add_query_arg(['view' => 'search'], $base_url)); ?>" class="mo-nav-btn <?php echo $view === 'search' ? 'is-active-nav' : ''; ?>">🔎 البحث المتقدم</a>
            <a href="<?php echo esc_url(wp_logout_url(home_url('/login'))); ?>" class="mo-nav-btn mo-nav-danger">🚪 تسجيل الخروج</a>
        </aside>

        <main class="mo-content">
            <?php if ($view === 'stats') : ?>
                <section class="mo-panel"><h1>الإحصائيات</h1><p>إجمالي العملاء: <strong><?php echo esc_html((string) $total_clients); ?></strong></p></section>
            <?php elseif ($view === 'add_client') : ?>
                <section class="mo-panel"><h1>إضافة عميل</h1><?php if ($client_message !== '') : ?><p class="mo-notice"><?php echo wp_kses_post($client_message); ?></p><?php endif; ?>
                    <form method="post" class="mo-form-grid"><?php wp_nonce_field('migrago_add_client', 'migrago_client_nonce'); ?>
                        <input name="full_name" placeholder="الاسم الكامل" required><input name="phone" placeholder="الهاتف" required><input name="email" type="email" placeholder="البريد"><input name="passport_number" placeholder="رقم جواز السفر"><input name="national_id" placeholder="رقم البطاقة الوطنية"><input name="birth_date" placeholder="تاريخ الازدياد (مثال: 24.11.2007)"><input name="birth_place" placeholder="مكان الازدياد"><textarea name="address" placeholder="العنوان الكامل"></textarea><select name="service_type"><?php foreach (migrago_office_service_types() as $type) : ?><option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($type); ?></option><?php endforeach; ?></select><textarea name="service_description" placeholder="وصف الخدمة"></textarea><input name="agreed_amount" type="number" step="0.01" placeholder="المبلغ المتفق عليه"><input name="first_payment" type="number" step="0.01" placeholder="الدفعة الأولى"><select name="payment_method"><?php foreach (migrago_office_payment_methods() as $method) : ?><option value="<?php echo esc_attr($method); ?>"><?php echo esc_html($method); ?></option><?php endforeach; ?></select><button type="submit">حفظ العميل</button></form>
                </section>
            <?php elseif ($view === 'add_staff') : ?>
                <section class="mo-panel"><h1>إضافة موظف</h1><?php if ($staff_message !== '') : ?><p class="mo-notice"><?php echo esc_html($staff_message); ?></p><?php endif; ?>
                    <form method="post" class="mo-form-grid"><?php wp_nonce_field('migrago_add_staff', 'migrago_staff_nonce'); ?><input name="full_name" placeholder="الاسم الكامل" required><input name="username" placeholder="اسم المستخدم" required><input name="password" type="password" placeholder="كلمة المرور" required><input name="email" type="email" placeholder="البريد الإلكتروني" required><input name="phone" placeholder="رقم الهاتف"><input name="national_id" placeholder="رقم البطاقة الوطنية للموظف"><input name="photo_url" placeholder="رابط صورة الموظف (اختياري)"><button type="submit">إنشاء حساب موظف</button></form>
                </section>
            <?php else : ?>
                <section class="mo-panel"><h1>البحث المتقدم عن عميل + الدفعات</h1><?php if ($payment_message !== '') : ?><p class="mo-notice"><?php echo wp_kses_post($payment_message); ?></p><?php endif; ?><?php if ($stage_message !== '') : ?><p class="mo-notice"><?php echo esc_html($stage_message); ?></p><?php endif; ?><?php if ($edit_message !== '') : ?><p class="mo-notice"><?php echo esc_html($edit_message); ?></p><?php endif; ?>
                    <form method="get" class="mo-inline-form"><input type="hidden" name="view" value="search"><input name="q" value="<?php echo esc_attr($search_term); ?>" placeholder="ابحث بالاسم/الهاتف/البطاقة/الجواز/رقم الملف/البريد"><button type="submit">بحث</button></form>
                    <div class="mo-notice">عدد النتائج: <?php echo esc_html((string) count($results)); ?><?php if ($search_term === '') : ?> (عرض جميع العملاء الأحدث)<?php endif; ?></div>
                    <?php if (empty($results)) : ?>
                        <p class="mo-empty-state">لا توجد نتائج مطابقة. جرّب البحث بالاسم أو الهاتف أو رقم الملف.</p>
                    <?php else : ?>
                        <div class="mo-results-grid">
                            <?php foreach ($results as $client) : ?>
                                <?php
                                $client_receipt_url = migrago_office_get_client_receipt_url((int) $client->ID);
                                $client_contract_url = migrago_office_get_client_contract_url((int) $client->ID);
                                $stage_note = (string) get_post_meta($client->ID, '_migrago_stage_note', true);
                                ?>
                                <article class="mo-result-card mo-result-card-pro">
                                    <div class="mo-client-head"><strong><?php echo esc_html($client->post_title); ?></strong><span class="mo-file-chip"><?php echo esc_html((string) get_post_meta($client->ID, '_migrago_file_number', true)); ?></span></div>
                                    <div class="mo-client-meta">الهاتف: <?php echo esc_html((string) get_post_meta($client->ID, '_migrago_phone', true)); ?> | البطاقة: <?php echo esc_html((string) get_post_meta($client->ID, '_migrago_national_id', true)); ?></div>
                                    <div class="mo-client-meta">المبلغ المتفق عليه: <?php echo esc_html((string) get_post_meta($client->ID, '_migrago_agreed_amount', true)); ?> | المتبقي: <?php echo esc_html((string) migrago_office_remaining_amount($client->ID)); ?></div>
                                    <details class="mo-client-editor"><summary>فتح الملف وتعديل البيانات</summary><form method="post" class="mo-form-grid"><?php wp_nonce_field('migrago_edit_client', 'migrago_edit_client_nonce'); ?><input type="hidden" name="search_term" value="<?php echo esc_attr($search_term); ?>"><input type="hidden" name="client_id" value="<?php echo esc_attr((string) $client->ID); ?>"><input name="full_name" value="<?php echo esc_attr($client->post_title); ?>" placeholder="الاسم الكامل" required><input name="phone" value="<?php echo esc_attr((string) get_post_meta($client->ID, '_migrago_phone', true)); ?>" placeholder="الهاتف"><input name="email" type="email" value="<?php echo esc_attr((string) get_post_meta($client->ID, '_migrago_email', true)); ?>" placeholder="البريد"><input name="national_id" value="<?php echo esc_attr((string) get_post_meta($client->ID, '_migrago_national_id', true)); ?>" placeholder="رقم البطاقة"><input name="passport_number" value="<?php echo esc_attr((string) get_post_meta($client->ID, '_migrago_passport_number', true)); ?>" placeholder="رقم الجواز"><input name="birth_date" value="<?php echo esc_attr((string) get_post_meta($client->ID, '_migrago_birth_date', true)); ?>" placeholder="تاريخ الازدياد"><input name="birth_place" value="<?php echo esc_attr((string) get_post_meta($client->ID, '_migrago_birth_place', true)); ?>" placeholder="مكان الازدياد"><textarea name="address" placeholder="العنوان الكامل"><?php echo esc_textarea((string) get_post_meta($client->ID, '_migrago_address', true)); ?></textarea><select name="service_type"><?php foreach (migrago_office_service_types() as $type) : ?><option value="<?php echo esc_attr($type); ?>" <?php selected((string) get_post_meta($client->ID, '_migrago_service_type', true), $type); ?>><?php echo esc_html($type); ?></option><?php endforeach; ?></select><textarea name="service_description" placeholder="وصف الخدمة"><?php echo esc_textarea((string) get_post_meta($client->ID, '_migrago_service_description', true)); ?></textarea><input name="agreed_amount" type="number" step="0.01" value="<?php echo esc_attr((string) get_post_meta($client->ID, '_migrago_agreed_amount', true)); ?>" placeholder="السعر المتفق عليه"><button type="submit">حفظ التعديلات</button></form></details>
                                    <div class="mo-client-actions"><a href="<?php echo esc_url($client_receipt_url); ?>" target="_blank">طباعة التوصيل</a><a href="<?php echo esc_url($client_contract_url); ?>" target="_blank">طباعة عقد الخدمة</a></div>
                                    <form method="post" class="mo-stage-form"><?php wp_nonce_field('migrago_update_stage', 'migrago_stage_nonce'); ?><input type="hidden" name="search_term" value="<?php echo esc_attr($search_term); ?>"><input type="hidden" name="client_id" value="<?php echo esc_attr((string) $client->ID); ?>"><select name="workflow_stage"><?php foreach (migrago_office_workflow_stages() as $stage_key => $stage_label) : ?><option value="<?php echo esc_attr($stage_key); ?>" <?php selected((string) get_post_meta($client->ID, '_migrago_workflow_stage', true), $stage_key); ?>><?php echo esc_html($stage_label); ?></option><?php endforeach; ?></select><textarea name="stage_note" placeholder="ملاحظة المرحلة (اختياري)"><?php echo esc_textarea($stage_note); ?></textarea><button type="submit">حفظ المرحلة والملاحظة</button></form>
                                    <form method="post" class="mo-inline-form mo-inline-form-payment"><?php wp_nonce_field('migrago_add_payment', 'migrago_payment_nonce'); ?><input type="hidden" name="search_term" value="<?php echo esc_attr($search_term); ?>"><input type="hidden" name="client_id" value="<?php echo esc_attr((string) $client->ID); ?>"><input name="amount" type="number" step="0.01" placeholder="إضافة مبلغ جديد" required><select name="method"><?php foreach (migrago_office_payment_methods() as $method) : ?><option value="<?php echo esc_attr($method); ?>"><?php echo esc_html($method); ?></option><?php endforeach; ?></select><button type="submit">إضافة دفعة</button></form>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
    </div>
    <footer class="mo-simple-footer">هذه المنصة تابعة حصرياً لإدارة شركة MIGRAGO وهي منصة داخلية محمية. المقر الاجتماعي: لالة اليقوت، شارع العرعار، الدار البيضاء، المغرب.</footer>
</div>
