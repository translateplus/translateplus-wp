<?php
/**
 * Plugin Name:       TranslatePlus
 * Description:       Translation API integration for WordPress.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            TranslatePlus
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       translateplus
 *
 * @package TranslatePlus
 */

if (! defined('ABSPATH')) {
    exit;
}

define('TRANSLATEPLUS_PATH', plugin_dir_path(__FILE__));
define('TRANSLATEPLUS_FILE', __FILE__);
define('TRANSLATEPLUS_URL', plugin_dir_url(__FILE__));
define('TRANSLATEPLUS_VERSION', '2.0.0');

require_once TRANSLATEPLUS_PATH . 'includes/class-translate-languages.php';
require_once TRANSLATEPLUS_PATH . 'includes/class-translate-api.php';
require_once TRANSLATEPLUS_PATH . 'includes/class-translate-settings.php';
require_once TRANSLATEPLUS_PATH . 'includes/class-editor-submitbox.php';
require_once TRANSLATEPLUS_PATH . 'includes/class-translate-now-ajax.php';
require_once TRANSLATEPLUS_PATH . 'includes/class-translation-group.php';
require_once TRANSLATEPLUS_PATH . 'includes/class-translate-frontend-lang-dropdown.php';
require_once TRANSLATEPLUS_PATH . 'includes/class-translate-nav-menu-meta-box.php';
require_once TRANSLATEPLUS_PATH . 'includes/class-translate-admin-list.php';
require_once TRANSLATEPLUS_PATH . 'includes/class-translate-auto-sync.php';
require_once TRANSLATEPLUS_PATH . 'includes/class-translate-rewrites.php';
require_once TRANSLATEPLUS_PATH . 'includes/class-translate-browser-lang-redirect.php';
require_once TRANSLATEPLUS_PATH . 'includes/class-translate-query-main-en.php';

register_activation_hook(TRANSLATEPLUS_FILE, array('TranslatePlus_Rewrites', 'activate'));
register_deactivation_hook(
    TRANSLATEPLUS_FILE,
    static function (): void {
        TranslatePlus_Rewrites::deactivate();
        TranslatePlus_Auto_Sync::on_deactivate();
    }
);

add_action('plugins_loaded', static function (): void {
    TranslatePlus_Rewrites::init();
    TranslatePlus_Frontend_Lang_Dropdown::init();
    TranslatePlus_Translation_Group::register_frontend_hooks();
    TranslatePlus_Browser_Lang_Redirect::init();
    TranslatePlus_Query_Main_En::init();
    TranslatePlus_Auto_Sync::init();
    if (is_admin()) {
        TranslatePlus_Translation_Group::init();
        TranslatePlus_Editor_Submitbox::init();
        TranslatePlus_Translate_Now_Ajax::init();
        TranslatePlus_Settings::init();
        TranslatePlus_Nav_Menu_Meta_Box::init();
        TranslatePlus_Admin_List::init();
    }
});
