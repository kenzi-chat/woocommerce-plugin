<?php

/**
 * Plugin Name: Kenzi Commerce
 * Plugin URI:  https://kenzi.chat
 * Description: Enable commerce data sync between WooCommerce and Kenzi.
 * Version:     1.0.0
 * Author:      Kenzi
 * Author URI:  https://kenzi.chat
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kenzi-commerce
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Requires Plugins: kenzi-chat, woocommerce
 *
 * @package Kenzi\Commerce
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('KENZI_COMMERCE_VERSION', '1.0.0');
define('KENZI_COMMERCE_PLUGIN_FILE', __FILE__);

require_once __DIR__ . '/vendor/autoload.php';

use Kenzi\Commerce\Plugin;

add_action('plugins_loaded', static function (): void {
    Plugin::instance()->init();
});

// Declare HPOS compatibility before WooCommerce initializes.
add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// On deactivation, remove webhooks, revoke API key, and remove the 'commerce' capability.
register_deactivation_hook(__FILE__, static function (): void {
    \Kenzi\Commerce\CredentialDelivery::cleanup();
    \Kenzi\Commerce\Webhook\NativeWebhookManager::removeWebhooks();

    if (class_exists(\Kenzi\Chat\Settings::class)) {
        $capabilities = \Kenzi\Chat\Settings::getCapabilities();
        $capabilities = array_values(array_diff($capabilities, ['commerce']));
        \Kenzi\Chat\Settings::setCapabilities($capabilities);
    }
});
