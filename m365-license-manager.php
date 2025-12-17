<?php
/**
 * Plugin Name: KB - macomp License Manager
 * Plugin URI: https://kb.macomp.co.il
 * Description: ניהול ומעקב אחר רישיונות Microsoft 365 עבור מספר Tenants
 * Version: 2.0.0
 * Author: O.K Software
 * Text Domain: m365-license-manager
 */

if (!defined('ABSPATH')) exit;

// הגדרת קבועים
define('M365_LM_VERSION', '1.0.1');
define('M365_LM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('M365_LM_PLUGIN_URL', plugin_dir_url(__FILE__));
if (!defined('M365_LM_DISPLAY_VERSION')) {
    define('M365_LM_DISPLAY_VERSION', '17.17.11');
}

// טעינת קבצים נדרשים
require_once M365_LM_PLUGIN_DIR . 'includes/class-database.php';
require_once M365_LM_PLUGIN_DIR . 'includes/class-api-connector.php';
require_once M365_LM_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once M365_LM_PLUGIN_DIR . 'includes/class-admin.php';

// Ensure AJAX handler for saving customers is registered even when the admin
// class hooks have not yet been instantiated (e.g., during admin-ajax calls).
add_action('wp_ajax_kbbm_save_customer', ['M365_LM_Admin', 'ajax_save_customer']);

add_action('admin_post_kbbm_download_script', 'kbbm_download_script_handler');
add_action('admin_post_nopriv_kbbm_download_script', 'kbbm_download_script_handler');

// קישורי ניווט בין סביבות שרת אמת ושרת טסט
if (!function_exists('kbbm_get_portal_urls')) {
    function kbbm_get_portal_urls() {
        $use_test_server = get_option('kbbm_use_test_server', '0');
        $is_test = $use_test_server === '1' || $use_test_server === 1 || $use_test_server === true;

        $base = $is_test ? 'https://kbtest.macomp.co.il/?page_id=' : 'https://kb.macomp.co.il/?page_id=';

        return array(
            'main'     => $base . '14296',
            'recycle'  => $base . '14291',
            'settings' => $base . '14292',
            'logs'     => $base . '14285',
            'alerts'   => $base . '14290',
        );
    }
}

// הפעלה והסרה
register_activation_hook(__FILE__, 'kb_billing_manager_activate');
register_deactivation_hook(__FILE__, 'm365_lm_deactivate');

// תיקון סכימה גם לאחר שדרוגים
add_action('admin_init', 'kb_billing_manager_maybe_install');

function kb_billing_manager_activate() {
    M365_LM_Database::create_tables();
    flush_rewrite_rules();
}

function kb_billing_manager_maybe_install() {
    // מריץ את יצירת/תיקון הטבלאות גם לאחר שדרוגים כדי להוסיף עמודות חסרות
    M365_LM_Database::create_tables();
}

// שמירה על תאימות לאחור
function m365_lm_activate() {
    kb_billing_manager_activate();
}

function m365_lm_deactivate() {
    flush_rewrite_rules();
}

// אתחול התוסף
add_action('plugins_loaded', 'm365_lm_init');
function m365_lm_init() {
    new M365_LM_Shortcodes();
    new M365_LM_Admin();
}
