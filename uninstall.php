<?php

declare(strict_types=1);

/**
 * Kenzi Commerce uninstall handler.
 *
 * Runs when the plugin is deleted via the WordPress admin.
 * Removes commerce-specific options (webhook IDs) and the
 * 'commerce' capability from the Chat plugin's capabilities
 * list so it accurately reflects the installed state.
 *
 * The Chat plugin's own uninstall.php handles removing its
 * settings (including capabilities) when it is deleted.
 *
 * @see https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 *
 * @package Kenzi\Commerce
 */

// Abort if not called by WordPress during uninstall.
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

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
