<?php

declare(strict_types=1);

namespace Kenzi\Commerce\Webhook;

use Kenzi\Chat\Settings as KenziSettings;
use Kenzi\Commerce\Settings;

/**
 * Manages native WooCommerce webhooks for Kenzi Commerce.
 *
 * Webhooks are registered automatically when the commerce capability is
 * active and a shared secret is available. Uses WooCommerce's built-in
 * webhook system — WooCommerce handles delivery, HMAC signing, retries,
 * and payload serialization.
 */
final class NativeWebhookManager
{
    /** @var list<string> */
    private const TOPICS = [
        'order.created',
        'order.updated',
        'order.deleted',
        'order.restored',
        'customer.created',
        'customer.updated',
        'customer.deleted',
    ];

    /**
     * Ensure webhooks are registered.
     *
     * Idempotent — skips if webhooks already exist. Returns false if
     * any webhook failed to register. Must be called from a context
     * where get_current_user_id() returns the logged-in admin —
     * WooCommerce uses this user to build the webhook payload.
     */
    public static function ensureWebhooks(): bool
    {
        $secret = KenziSettings::getSharedSecret();

        if ($secret === null) {
            return false;
        }

        $existingIds = Settings::getWebhookIds();

        if (count($existingIds) === count(self::TOPICS)) {
            // Verify all stored IDs still exist — any external deletion triggers full re-registration.
            $allExist = ! in_array(null, array_map('wc_get_webhook', array_map('intval', $existingIds)), true);

            if ($allExist) {
                return true;
            }
        }

        // Stale, partial, or externally-deleted — clean up before re-registering.
        if ($existingIds !== []) {
            self::removeWebhooks();
        }

        $deliveryUrl = self::getDeliveryUrl();
        $ids = [];

        foreach (self::TOPICS as $topic) {
            $webhook = new \WC_Webhook();
            $webhook->set_name('Kenzi ' . $topic);
            $webhook->set_user_id(get_current_user_id());
            $webhook->set_topic($topic);
            $webhook->set_secret($secret);
            $webhook->set_delivery_url($deliveryUrl);
            $webhook->set_status('active');
            $webhook->set_api_version('wp_api_v3');
            $webhook->save();

            if ($webhook->get_id()) {
                $ids[] = $webhook->get_id();
            }
        }

        Settings::setWebhookIds($ids);

        return count($ids) === count(self::TOPICS);
    }

    /**
     * Remove all Kenzi-managed native WC webhooks.
     */
    public static function removeWebhooks(): void
    {
        foreach (Settings::getWebhookIds() as $id) {
            $webhook = wc_get_webhook((int) $id);

            if ($webhook) {
                $webhook->delete(true);
            }
        }

        Settings::setWebhookIds([]);
    }

    private static function getDeliveryUrl(): string
    {
        return rtrim(KenziSettings::getAppBase(), '/') . '/webhooks/woo-commerce';
    }
}
