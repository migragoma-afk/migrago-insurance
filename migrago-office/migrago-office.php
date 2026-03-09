<?php
/**
 * Plugin Name: Migrago Office System
 * Description: Internal office application mode for Migrago.
 * Version: 0.1.0
 * Author: Migrago
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MIGRAGO_OFFICE_PATH', plugin_dir_path(__FILE__));
define('MIGRAGO_OFFICE_URL', plugin_dir_url(__FILE__));

define('MIGRAGO_OFFICE_ROLE_ADMIN', 'migrago_office_admin');
define('MIGRAGO_OFFICE_ROLE_MEMBER', 'migrago_office_member');

// Simplified include structure: business logic + app routing/UI wiring.
require_once MIGRAGO_OFFICE_PATH . 'includes/core.php';
require_once MIGRAGO_OFFICE_PATH . 'includes/app.php';

register_activation_hook(__FILE__, 'migrago_office_activate');

function migrago_office_activate(): void
{
    migrago_office_register_roles();
    migrago_office_sync_roles_capabilities();
    migrago_office_register_post_types();
    migrago_office_create_core_pages();
    flush_rewrite_rules();
}

add_action('init', 'migrago_office_sync_roles_capabilities');
add_action('init', 'migrago_office_register_post_types');
add_action('init', 'migrago_office_register_shortcodes');
add_action('wp_enqueue_scripts', 'migrago_office_enqueue_assets');

function migrago_office_enqueue_assets(): void
{
    if (is_page(['login', 'dashboard', 'track', 'receipt', 'contract'])) {
        wp_enqueue_style('migrago-office-style', MIGRAGO_OFFICE_URL . 'assets/style.css', [], '0.1.0');
        wp_enqueue_script('migrago-office-app', MIGRAGO_OFFICE_URL . 'assets/app.js', [], '0.1.0', true);
    }
}
