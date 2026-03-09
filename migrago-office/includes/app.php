<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('template_redirect', 'migrago_office_force_application_mode');
add_action('admin_init', 'migrago_office_block_wp_admin_for_team');
add_filter('the_content', 'migrago_office_force_core_pages_content', 9);
add_action('admin_menu', 'migrago_office_cleanup_wp_admin_ui', 999);
add_action('wp_before_admin_bar_render', 'migrago_office_cleanup_admin_bar', 999);
add_action('admin_menu', 'migrago_office_register_templates_menu', 1000);

function migrago_office_force_application_mode(): void
{
    if (is_admin() || wp_doing_ajax()) {
        return;
    }

    $allowed = ['login', 'dashboard', 'track', 'receipt', 'contract'];
    $current = get_post_field('post_name', get_queried_object_id());

    $is_public_tracking = $current === 'track' && isset($_GET['code']) && sanitize_text_field($_GET['code']) !== '';

    if (!is_user_logged_in() && $current !== 'login' && !$is_public_tracking) {
        wp_safe_redirect(home_url('/login'));
        exit;
    }

    if (is_user_logged_in() && !in_array($current, $allowed, true)) {
        wp_safe_redirect(home_url('/dashboard'));
        exit;
    }
}

function migrago_office_block_wp_admin_for_team(): void
{
    if (wp_doing_ajax() || !is_user_logged_in()) {
        return;
    }

    // Never block real WordPress administrators.
    if (current_user_can('manage_options') || migrago_office_is_admin()) {
        return;
    }

    $user = wp_get_current_user();
    $is_team_member = in_array(MIGRAGO_OFFICE_ROLE_MEMBER, (array) ($user->roles ?? []), true);

    // Block only team members from wp-admin.
    if ($is_team_member) {
        wp_safe_redirect(home_url('/dashboard'));
        exit;
    }
}

function migrago_office_create_core_pages(): void
{
    $pages = [
        'login' => '[migrago_office_login]',
        'dashboard' => '[migrago_office_dashboard]',
        'track' => '[migrago_office_tracking]',
        'receipt' => '[migrago_office_receipt]',
        'contract' => '[migrago_office_contract]',
    ];

    foreach ($pages as $slug => $shortcode) {
        $existing = get_page_by_path($slug);
        if ($existing instanceof WP_Post) {
            if (trim((string) $existing->post_content) !== $shortcode) {
                wp_update_post([
                    'ID' => $existing->ID,
                    'post_content' => $shortcode,
                ]);
            }
            continue;
        }

        wp_insert_post([
            'post_title' => ucfirst($slug),
            'post_name' => $slug,
            'post_content' => $shortcode,
            'post_status' => 'publish',
            'post_type' => 'page',
        ]);
    }
}

function migrago_office_force_core_pages_content(string $content): string
{
    if (!is_page()) {
        return $content;
    }

    $slug = get_post_field('post_name', get_queried_object_id());

    if ($slug === 'login') {
        return do_shortcode('[migrago_office_login]');
    }
    if ($slug === 'dashboard') {
        return do_shortcode('[migrago_office_dashboard]');
    }
    if ($slug === 'track') {
        return do_shortcode('[migrago_office_tracking]');
    }
    if ($slug === 'receipt') {
        return do_shortcode('[migrago_office_receipt]');
    }
    if ($slug === 'contract') {
        return do_shortcode('[migrago_office_contract]');
    }

    return $content;
}

function migrago_office_register_shortcodes(): void
{
    add_shortcode('migrago_office_login', 'migrago_office_render_login');
    add_shortcode('migrago_office_dashboard', 'migrago_office_render_dashboard');
    add_shortcode('migrago_office_tracking', 'migrago_office_render_tracking');
    add_shortcode('migrago_office_receipt', 'migrago_office_render_receipt');
    add_shortcode('migrago_office_contract', 'migrago_office_render_contract');
    add_shortcode('migrago_dashboard_admin', 'migrago_office_render_dashboard_admin_shortcode');
    add_shortcode('migrago_dashboard_team', 'migrago_office_render_dashboard_team_shortcode');
}

function migrago_office_render_login(): string
{
    ob_start();
    include MIGRAGO_OFFICE_PATH . 'frontend/login.php';
    return (string) ob_get_clean();
}

function migrago_office_render_dashboard(): string
{
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/login'));
        exit;
    }

    ob_start();
    include migrago_office_is_admin() ? MIGRAGO_OFFICE_PATH . 'frontend/dashboard-admin.php' : MIGRAGO_OFFICE_PATH . 'frontend/dashboard-member.php';
    return (string) ob_get_clean();
}

function migrago_office_render_dashboard_admin_shortcode(): string
{
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/login'));
        exit;
    }
    if (!migrago_office_is_admin()) {
        return '<p>' . esc_html__('ليس لديك صلاحية لعرض لوحة المشرف.', 'migrago-office') . '</p>';
    }

    ob_start();
    include MIGRAGO_OFFICE_PATH . 'frontend/dashboard-admin.php';
    return (string) ob_get_clean();
}

function migrago_office_render_dashboard_team_shortcode(): string
{
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/login'));
        exit;
    }
    if (migrago_office_is_admin()) {
        return '<p>' . esc_html__('هذه اللوحة مخصصة للموظف.', 'migrago-office') . '</p>';
    }

    ob_start();
    include MIGRAGO_OFFICE_PATH . 'frontend/dashboard-member.php';
    return (string) ob_get_clean();
}

function migrago_office_render_receipt(): string
{
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/login'));
        exit;
    }

    ob_start();
    include MIGRAGO_OFFICE_PATH . 'frontend/receipt.php';
    return (string) ob_get_clean();
}


function migrago_office_render_contract(): string
{
    if (!is_user_logged_in()) {
        wp_safe_redirect(home_url('/login'));
        exit;
    }

    ob_start();
    include MIGRAGO_OFFICE_PATH . 'frontend/contract.php';
    return (string) ob_get_clean();
}

function migrago_office_cleanup_wp_admin_ui(): void
{
    if (!is_user_logged_in()) {
        return;
    }

    if (!migrago_office_is_admin()) {
        return;
    }

    // Keep only essential items for office operation.
    remove_menu_page('index.php');
    remove_menu_page('edit.php');
    remove_menu_page('edit-comments.php');
    remove_menu_page('themes.php');
    remove_menu_page('plugins.php');
    remove_menu_page('tools.php');
    remove_menu_page('options-general.php');
}

function migrago_office_cleanup_admin_bar(): void
{
    if (!is_user_logged_in() || !migrago_office_is_admin()) {
        return;
    }

    global $wp_admin_bar;
    if (!$wp_admin_bar) {
        return;
    }

    $wp_admin_bar->remove_menu('wp-logo');
    $wp_admin_bar->remove_menu('comments');
    $wp_admin_bar->remove_menu('new-content');
    $wp_admin_bar->remove_menu('updates');
}


function migrago_office_register_templates_menu(): void
{
    if (!current_user_can('manage_options') && !migrago_office_is_admin()) {
        return;
    }

    add_menu_page(
        __('Migrago Templates', 'migrago-office'),
        __('Migrago Templates', 'migrago-office'),
        'read',
        'migrago-office-templates',
        'migrago_office_render_templates_admin_page',
        'dashicons-media-code',
        58
    );
}

function migrago_office_render_templates_admin_page(): void
{
    if (!current_user_can('manage_options') && !migrago_office_is_admin()) {
        wp_die(esc_html__('ليس لديك صلاحية.', 'migrago-office'));
    }

    $message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrago_templates_nonce']) && wp_verify_nonce(sanitize_text_field($_POST['migrago_templates_nonce']), 'migrago_save_templates')) {
        $receipt_template = wp_kses_post(wp_unslash($_POST['receipt_template_html'] ?? ''));
        $receipt_css = wp_strip_all_tags(wp_unslash($_POST['receipt_document_css'] ?? ''));
        $contract_css = wp_strip_all_tags(wp_unslash($_POST['contract_document_css'] ?? ''));
        $contract_template_employment = wp_kses_post(wp_unslash($_POST['contract_full_template_employment'] ?? ''));
        $contract_template_study = wp_kses_post(wp_unslash($_POST['contract_full_template_study'] ?? ''));
        $contract_template_other = wp_kses_post(wp_unslash($_POST['contract_full_template_other'] ?? ''));

        update_option('migrago_office_receipt_template_html', $receipt_template);
        update_option('migrago_office_receipt_document_css', $receipt_css);
        update_option('migrago_office_contract_document_css', $contract_css);
        update_option('migrago_office_contract_full_template_employment', $contract_template_employment);
        update_option('migrago_office_contract_full_template_study', $contract_template_study);
        update_option('migrago_office_contract_full_template_other', $contract_template_other);
        $message = 'تم حفظ جميع إعدادات القوالب (HTML/CSS) بنجاح.';
    }

    $receipt_template = migrago_office_get_document_template('receipt');
    $receipt_css = migrago_office_document_style_css('receipt');
    $contract_css = migrago_office_document_style_css('contract');
    $contract_template_employment = migrago_office_get_full_contract_template('employment');
    $contract_template_study = migrago_office_get_full_contract_template('study');
    $contract_template_other = migrago_office_get_full_contract_template('other');
    ?>
    <div class="wrap" dir="rtl">
        <h1>تحرير قوالب المستندات (HTML + CSS + المحتوى القانوني)</h1>
        <?php if ($message !== '') : ?><div class="notice notice-success"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
        <p>يمكنك التحكم الكامل في التصميم والمحتوى من نفس الصفحة. استخدم المتغيرات: <code>{{client_name}}</code> <code>{{cin}}</code> <code>{{passport_number}}</code> <code>{{file_number}}</code> <code>{{service_type}}</code> <code>{{amount}}</code> <code>{{remaining}}</code> <code>{{stage_label}}</code> <code>{{tracking_url}}</code> <code>{{qr_image_tag}}</code> <code>{{company_name}}</code> ...</p>
        <p><strong>مهم:</strong> إذا كانت خدمة العميل <code>study</code> سيتم تصدير عقد الدراسة، وإذا كانت <code>employment</code> سيتم تصدير عقد العمل.</p>
        <form method="post">
            <?php wp_nonce_field('migrago_save_templates', 'migrago_templates_nonce'); ?>
            <h2>قالب التوصيل</h2>
            <textarea name="receipt_template_html" rows="16" style="width:100%;font-family:monospace;"><?php echo esc_textarea($receipt_template); ?></textarea>
            <h2 style="margin-top:20px;">قالب عقد العمل (HTML كامل)</h2>
            <textarea name="contract_full_template_employment" rows="16" style="width:100%;font-family:monospace;"><?php echo esc_textarea($contract_template_employment); ?></textarea>

            <h2 style="margin-top:20px;">قالب عقد الدراسة (HTML كامل)</h2>
            <textarea name="contract_full_template_study" rows="16" style="width:100%;font-family:monospace;"><?php echo esc_textarea($contract_template_study); ?></textarea>

            <h2 style="margin-top:20px;">قالب العقود الأخرى (HTML كامل)</h2>
            <textarea name="contract_full_template_other" rows="14" style="width:100%;font-family:monospace;"><?php echo esc_textarea($contract_template_other); ?></textarea>

            <h2 style="margin-top:20px;">CSS توصيل الدفع</h2>
            <textarea name="receipt_document_css" rows="8" style="width:100%;font-family:monospace;"><?php echo esc_textarea($receipt_css); ?></textarea>

            <h2 style="margin-top:20px;">CSS عقد الخدمة</h2>
            <textarea name="contract_document_css" rows="8" style="width:100%;font-family:monospace;"><?php echo esc_textarea($contract_css); ?></textarea>

            <p><button type="submit" class="button button-primary">حفظ القوالب</button></p>
        </form>
    </div>
    <?php
}
