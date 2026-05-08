<?php

declare(strict_types=1);

namespace Kenzi\Commerce;

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
     * Remove all commerce plugin options.
     */
    public static function cleanup(): void
    {
        delete_option(self::OPTION_WEBHOOK_IDS);
        delete_option(self::OPTION_API_KEY_ID);
    }
}
