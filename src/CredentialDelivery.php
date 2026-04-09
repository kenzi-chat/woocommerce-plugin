<?php

declare(strict_types=1);

namespace Kenzi\Commerce;

use Kenzi\Chat\Settings as ChatSettings;

/**
 * Delivers WooCommerce REST API credentials to the Kenzi backend.
 *
 * After Kenzi Connect completes, the integration's credentials contain
 * placeholder values for `consumer_key` and `consumer_secret`. This class
 * generates a read-only WooCommerce REST API key and PATCHes the real
 * credentials to Kenzi, which triggers the order backfill once all
 * credential values are populated.
 *
 * Delivery runs on `admin_init` (idempotent) so it catches both the
 * initial connect and the upgrade flow on the next admin page load.
 *
 * Two options track state:
 * - `OPTION_API_KEY_ID` — persistent reference to the generated WC API key
 * - `OPTION_CREDENTIALS_DELIVERED` — whether the PATCH to Kenzi succeeded
 *
 * If delivery fails, the generated key is revoked and both options are
 * cleared so the next admin load retries from scratch. WooCommerce stores
 * consumer_key as a hash, so we can't recover the plain value for a retry —
 * regeneration is the only option.
 */
final class CredentialDelivery
{
    /**
     * Deliver credentials if conditions are met and not yet delivered.
     *
     * Safe to call on every admin page load — exits early when already
     * delivered or when prerequisites (connection, capability, secret)
     * are not met.
     */
    public static function maybeDeliver(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        if (! Settings::shouldWebhooksBeActive()) {
            return;
        }

        if (Settings::isCredentialsDelivered()) {
            return;
        }

        $keys = self::generateApiKey();

        if ($keys === null) {
            return;
        }

        Settings::setApiKeyId($keys['key_id']);

        if (! self::deliverToKenzi($keys['consumer_key'], $keys['consumer_secret'])) {
            // Can't retry without the plain consumer_key (WC stores a hash),
            // so revoke and let the next page load regenerate.
            self::revokeApiKey($keys['key_id']);
            Settings::cleanupCredentials();
        } else {
            Settings::setCredentialsDelivered(true);
        }
    }

    /**
     * Generate a read-only WooCommerce REST API key for Kenzi.
     *
     * @return array{key_id: int, consumer_key: string, consumer_secret: string}|null
     */
    private static function generateApiKey(): ?array
    {
        global $wpdb;

        $consumerKey = 'ck_' . wc_rand_hash();
        $consumerSecret = 'cs_' . wc_rand_hash();

        $wpdb->insert(
            $wpdb->prefix . 'woocommerce_api_keys',
            [
                'user_id' => get_current_user_id(),
                'description' => 'Kenzi Commerce',
                'permissions' => 'read',
                'consumer_key' => wc_api_hash($consumerKey),
                'consumer_secret' => $consumerSecret,
                'truncated_key' => substr($consumerKey, -7),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s'],
        );

        $keyId = (int) $wpdb->insert_id;

        if ($keyId === 0) {
            return null;
        }

        return [
            'key_id' => $keyId,
            'consumer_key' => $consumerKey,
            'consumer_secret' => $consumerSecret,
        ];
    }

    /**
     * PATCH credentials to the Kenzi backend.
     */
    private static function deliverToKenzi(string $consumerKey, string $consumerSecret): bool
    {
        $secret = ChatSettings::getSharedSecret();
        $baseUrl = ChatSettings::getAppBase();
        $instanceKey = ChatSettings::getInstanceKey();

        $url = rtrim($baseUrl, '/') . '/api/integrations/wordpress/' . urlencode($instanceKey) . '/credentials';

        $response = wp_remote_request($url, [
            'method' => 'PATCH',
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $secret,
            ],
            'body' => wp_json_encode([
                'consumer_key' => $consumerKey,
                'consumer_secret' => $consumerSecret,
            ]),
        ]);

        $code = wp_remote_retrieve_response_code($response);

        return ! is_wp_error($response) && $code === 200;
    }

    /**
     * Revoke a WooCommerce API key by ID.
     */
    public static function revokeApiKey(int $keyId): void
    {
        global $wpdb;

        $wpdb->delete(
            $wpdb->prefix . 'woocommerce_api_keys',
            ['key_id' => $keyId],
            ['%d'],
        );
    }

    /**
     * Revoke the stored API key and clear delivery state.
     *
     * Called when the user explicitly disconnects via the admin UI.
     */
    public static function cleanup(): void
    {
        $keyId = Settings::getApiKeyId();

        if ($keyId !== null) {
            self::revokeApiKey($keyId);
        }

        Settings::cleanupCredentials();
    }
}
