<?php

declare(strict_types=1);

namespace Kenzi\Commerce\Webhook;

use Kenzi\Chat\Settings as ChatSettings;
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
    ];

    /**
     * Ensure webhooks are registered.
     *
     * Idempotent — skips if webhooks already exist. Safe to call on
     * every admin load. Must be called from `admin_init` or later so
     * that get_current_user_id() returns the logged-in admin — WooCommerce
     * uses this user to build the webhook payload via its internal REST API.
     */
    public static function ensureWebhooks(): void
    {
        if (Settings::getWebhookIds() !== []) {
            return;
        }

        $deliveryUrl = self::getDeliveryUrl();
        $secret = ChatSettings::getSharedSecret();
        $ids = [];

        foreach (self::TOPICS as $topic) {
            $webhook = new \WC_Webhook();
            $webhook->set_name('Kenzi: ' . $topic);
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
        return rtrim(ChatSettings::getAppBase(), '/') . '/webhooks/woo-commerce';
    }
}
