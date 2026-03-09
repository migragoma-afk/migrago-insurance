<?php
$error = '';

if (is_user_logged_in()) {
    wp_safe_redirect(home_url('/dashboard'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrago_login_nonce'])) {
    if (!wp_verify_nonce(sanitize_text_field($_POST['migrago_login_nonce']), 'migrago_login')) {
        $error = __('انتهت صلاحية الجلسة، حاول مرة أخرى.', 'migrago-office');
    } else {
        $creds = [
            'user_login' => sanitize_user(wp_unslash($_POST['username'] ?? '')),
            'user_password' => (string) ($_POST['password'] ?? ''),
            'remember' => !empty($_POST['remember_me']),
        ];

        $user = wp_signon($creds, is_ssl());
        if (is_wp_error($user)) {
            $error = __('اسم المستخدم أو كلمة المرور غير صحيحة.', 'migrago-office');
        } else {
            wp_safe_redirect(home_url('/dashboard'));
            exit;
        }
    }
}
?>
<div class="mo-auth-card" dir="rtl">
    <h1>Migrago Office System</h1>
    <p class="mo-auth-subtitle">تسجيل الدخول الداخلي للفريق</p>

    <?php if ($error !== '') : ?>
        <p class="mo-error"><?php echo esc_html($error); ?></p>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(home_url('/login')); ?>" class="mo-auth-form">
        <?php wp_nonce_field('migrago_login', 'migrago_login_nonce'); ?>

        <label for="mo-username">اسم المستخدم</label>
        <input id="mo-username" type="text" name="username" autocomplete="username" required>

        <label for="mo-password">كلمة المرور</label>
        <input id="mo-password" type="password" name="password" autocomplete="current-password" required>

        <label class="mo-check">
            <input type="checkbox" name="remember_me" value="1">
            <span>تذكرني</span>
        </label>

        <button type="submit">دخول</button>
    </form>
</div>
