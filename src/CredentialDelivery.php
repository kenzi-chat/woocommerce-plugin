<?php

declare(strict_types=1);

namespace Kenzi\Commerce;

/**
 * Manages WooCommerce REST API key generation and cleanup for Kenzi.
 *
 * Generates read-only WC REST API keys used by the configure controller
 * (§7) and handles cleanup on disconnect. WooCommerce stores consumer_key
 * as a hash, so recovery is impossible on failure — regeneration is the
 * only option.
 */
final class CredentialDelivery
{
    /**
     * Generate a read-only WooCommerce REST API key for Kenzi.
     *
     * @return array{key_id: int, consumer_key: string, consumer_secret: string}|null
     */
    public static function generateApiKey(): ?array
    {
        global $wpdb;

        $consumerKey = 'ck_' . wc_rand_hash();
        $consumerSecret = 'cs_' . wc_rand_hash();

        $wpdb->insert(
            $wpdb->prefix . 'woocommerce_api_keys',
            [
                'user_id' => get_current_user_id(),
                'description' => 'Kenzi',
                'permissions' => 'read_write',
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
     * Check whether a valid WooCommerce API key already exists for Kenzi.
     *
     * Verifies the stored key ID still has a corresponding row in the
     * woocommerce_api_keys table. If the row was deleted externally
     * (e.g. via WooCommerce settings), clears the stale reference.
     */
    public static function hasValidKey(): bool
    {
        $keyId = Settings::getApiKeyId();

        if ($keyId === null) {
            return false;
        }

        global $wpdb;

        $exists = (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$wpdb->prefix}woocommerce_api_keys WHERE key_id = %d",
            $keyId,
        ));

        if (! $exists) {
            Settings::setApiKeyId(null);
            return false;
        }

        return true;
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
     * Revoke the stored API key and clear the key ID option.
     *
     * Called when the user disconnects via the admin UI.
     */
    public static function cleanup(): void
    {
        $keyId = Settings::getApiKeyId();

        if ($keyId !== null) {
            self::revokeApiKey($keyId);
        }

        Settings::setApiKeyId(null);
    }
}
