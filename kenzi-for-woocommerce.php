<?php

/**
 * Plugin Name: Kenzi for WooCommerce
 * Plugin URI:  https://kenzi.chat/integrations/woocommerce
 * Description: Connect your WooCommerce store to Kenzi for customer messaging powered by real-time order and customer data.
 * Version:     0.1.0
 * Author:      Kenzi
 * Author URI:  https://kenzi.chat
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: kenzi-for-woocommerce
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * WC requires at least: 8.0
 * WC tested up to: 9.6
 *
 * @package Kenzi\WooCommerce
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('KENZI_WC_VERSION', '0.1.0');
define('KENZI_WC_PLUGIN_FILE', __FILE__);
define('KENZI_WC_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once __DIR__ . '/vendor/autoload.php';

use Kenzi\WooCommerce\Plugin;

add_action('plugins_loaded', static function (): void {
    if (! class_exists(\WooCommerce::class)) {
        add_action('admin_notices', static function (): void {
            echo '<div class="error"><p>';
            echo esc_html__('Kenzi for WooCommerce requires WooCommerce to be installed and active.', 'kenzi-for-woocommerce');
            echo '</p></div>';
        });

        return;
    }

    Plugin::instance()->init();
});

add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
