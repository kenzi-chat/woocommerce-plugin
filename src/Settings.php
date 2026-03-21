<?php

declare(strict_types=1);

namespace Kenzi\Commerce;

use Kenzi\Chat\Settings as ChatSettings;

/**
 * Settings helper for Kenzi Commerce plugin options.
 *
 * Centralizes wp_option keys and provides accessor methods
 * for all commerce-specific settings.
 */
final class Settings
{
    public const OPTION_WEBHOOK_IDS = 'kenzi_commerce_webhook_ids';
    public const OPTION_API_KEY_ID = 'kenzi_commerce_api_key_id';
    public const OPTION_CREDENTIALS_DELIVERED = 'kenzi_commerce_credentials_delivered';

    /**
     * Get the stored webhook IDs.
     *
     * @return list<int>
     */
    public static function getWebhookIds(): array
    {
        $ids = get_option(self::OPTION_WEBHOOK_IDS);

        return is_array($ids) ? $ids : [];
    }

    /**
     * Save webhook IDs.
     *
     * @param list<int> $ids
     */
    public static function setWebhookIds(array $ids): void
    {
        update_option(self::OPTION_WEBHOOK_IDS, $ids, false);
    }

    /**
     * Whether webhooks should be active.
     *
     * True when connected, commerce capability is enabled, and the
     * shared secret exists.
     */
    public static function shouldWebhooksBeActive(): bool
    {
        return ChatSettings::isConnected()
            && ChatSettings::hasCapability('commerce')
            && ChatSettings::getSharedSecret() !== null;
    }

    /**
     * Get the stored WooCommerce API key ID.
     */
    public static function getApiKeyId(): ?int
    {
        $id = get_option(self::OPTION_API_KEY_ID);

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Save the WooCommerce API key ID.
     */
    public static function setApiKeyId(?int $id): void
    {
        if ($id === null) {
            delete_option(self::OPTION_API_KEY_ID);
        } else {
            update_option(self::OPTION_API_KEY_ID, $id, false);
        }
    }

    /**
     * Whether credentials have been delivered to Kenzi.
     */
    public static function isCredentialsDelivered(): bool
    {
        return (bool) get_option(self::OPTION_CREDENTIALS_DELIVERED);
    }

    /**
     * Mark credentials as delivered.
     */
    public static function setCredentialsDelivered(bool $delivered): void
    {
        if ($delivered) {
            update_option(self::OPTION_CREDENTIALS_DELIVERED, '1', false);
        } else {
            delete_option(self::OPTION_CREDENTIALS_DELIVERED);
        }
    }

    /**
     * Remove credential delivery options.
     */
    public static function cleanupCredentials(): void
    {
        delete_option(self::OPTION_API_KEY_ID);
        delete_option(self::OPTION_CREDENTIALS_DELIVERED);
    }

    /**
     * Remove all commerce plugin options.
     */
    public static function cleanup(): void
    {
        delete_option(self::OPTION_WEBHOOK_IDS);
        self::cleanupCredentials();
    }
}
