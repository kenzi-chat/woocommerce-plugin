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
     * Remove all commerce plugin options.
     */
    public static function cleanup(): void
    {
        delete_option(self::OPTION_WEBHOOK_IDS);
    }
}
