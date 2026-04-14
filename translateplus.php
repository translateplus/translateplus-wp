<?php
/**
 * Plugin Name:       TranslatePlus – AI Translation for WordPress
 * Description:       AI-powered WordPress translation plugin with fast, cost-efficient multilingual support and automatic content translation.
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

require_once __DIR__ . '/src/Core/Container.php';
require_once __DIR__ . '/src/Core/Bootstrap.php';

\TranslatePlus\Core\Bootstrap::run(__FILE__);

register_activation_hook(__FILE__, array('\TranslatePlus\Core\Bootstrap', 'activate'));
register_deactivation_hook(__FILE__, array('\TranslatePlus\Core\Bootstrap', 'deactivate'));
