<?php

declare(strict_types=1);

/**
 * Kenzi Commerce uninstall handler.
 *
 * Runs when the plugin is deleted via the WordPress admin.
 * Removes commerce-specific options (webhook IDs, API keys) and the
 * 'commerce' capability from the Chat plugin's capabilities list so
 * it accurately reflects the installed state.
 *
 * On multisite, cleanup runs for every subsite — not just the main
 * site — because each subsite stores its own independent options via
 * `get_option()`.
 *
 * The Chat plugin's own uninstall.php handles removing its settings
 * (including capabilities) when it is deleted.
 *
 * @see https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 *
 * @package Kenzi\Commerce
 */

// Abort if not called by WordPress during uninstall.
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete all Kenzi Commerce data for the current site.
 */
function kenzi_commerce_delete_site_data(): void
{
    // Remove native WooCommerce webhooks managed by Kenzi.
    $webhookIds = get_option('kenzi_commerce_webhook_ids', []);

    if (is_array($webhookIds) && function_exists('wc_get_webhook')) {
        foreach ($webhookIds as $id) {
            $webhook = wc_get_webhook((int) $id);

            if ($webhook) {
                $webhook->delete(true);
            }
        }
    }

    delete_option('kenzi_commerce_webhook_ids');

    // Revoke the WooCommerce API key generated for Kenzi.
    $apiKeyId = get_option('kenzi_commerce_api_key_id');

    if (is_numeric($apiKeyId)) {
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'woocommerce_api_keys',
            ['key_id' => (int) $apiKeyId],
            ['%d'],
        );
    }

    delete_option('kenzi_commerce_api_key_id');
    delete_option('kenzi_commerce_credentials_delivered');

    // Remove the 'commerce' capability from the Chat plugin's capabilities list.
    // Uses get_option directly because the Chat plugin's autoloader may not be
    // available during uninstall.
    $raw = get_option('kenzi_capabilities', '');

    if (is_string($raw) && $raw !== '') {
        $capabilities = array_filter(array_map('trim', explode(',', $raw)));
        $capabilities = array_values(array_diff($capabilities, ['commerce']));

        if ($capabilities === []) {
            delete_option('kenzi_capabilities');
        } else {
            update_option('kenzi_capabilities', implode(',', $capabilities));
        }
    }
}

if (is_multisite()) {
    $sites = get_sites(['fields' => 'ids']);

    foreach ($sites as $blog_id) {
        switch_to_blog($blog_id);
        kenzi_commerce_delete_site_data();
        restore_current_blog();
    }
} else {
    kenzi_commerce_delete_site_data();
}
