<?php

declare(strict_types=1);

namespace Kenzi\Commerce;

use Kenzi\Chat\Settings as KenziSettings;

/**
 * Main plugin class for Kenzi Commerce.
 */
final class Plugin
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    // Prevent cloning and unserialization of the singleton.
    private function __clone()
    {
    }

    public function __wakeup(): void
    {
        throw new \RuntimeException('Cannot unserialize singleton');
    }

    /**
     * Initialize the plugin.
     *
     * Called on `plugins_loaded`. Since `Requires Plugins: kenzi-chat, woocommerce`
     * is declared in the plugin header, both dependencies are guaranteed active.
     */
    public function init(): void
    {
        add_action('kenzi_chat_disconnected', [self::class, 'handleChatDisconnected']);
        add_filter('kenzi_configure_config', [self::class, 'extendConfigurePayload']);

        if (is_admin()) {
            $this->registerPluginLinks();
        }
    }

    /**
     * Register plugin row meta links (Docs link on plugins page).
     */
    private function registerPluginLinks(): void
    {
        $pluginBasename = plugin_basename(KENZI_COMMERCE_PLUGIN_FILE);

        add_filter('plugin_row_meta', static function (array $meta, string $file) use ($pluginBasename): array {
            if ($file !== $pluginBasename) {
                return $meta;
            }

            $meta[] = '<a href="' . esc_url('https://wiki.kenzi.chat/integrations/woocommerce/') . '">' . esc_html__('Docs', 'kenzi-commerce') . '</a>';

            return $meta;
        }, 10, 2);
    }

    /**
     * Extend the configure config payload with WooCommerce credentials.
     *
     * Hooked into `kenzi_configure_config` (fired by the WordPress
     * plugin's POST /kenzi/configure). Mints a WC REST API key
     * and webhook subscriptions, then merges them into the config.
     *
     * @param array<string, mixed> $config Base config from the WordPress plugin.
     * @return array<string, mixed>|\WP_Error
     */
    public static function extendConfigurePayload(array $config): array|\WP_Error
    {
        // Only mint commerce artifacts if the commerce grant is active.
        if (! KenziSettings::hasGrant('commerce')) {
            return $config;
        }

        // Override rest_url with the WordPress REST base.
        // Use rest_url() instead of home_url('/wp-json') so the URL works
        // regardless of the site's permalink settings. Without pretty
        // permalinks, rest_url() returns the index.php?rest_route= form.
        // The WooCommerce API version (/wc/v3) is appended by the Elixir client.
        $config['rest_url'] = rest_url();

        // Mint credentials only if none exist yet. WooCommerce hashes the
        // consumer_key on insert, so plaintext is only available at mint
        // time — we send it to Kenzi once and skip on subsequent configures.
        if (! CredentialDelivery::hasValidKey()) {
            $keys = CredentialDelivery::generateApiKey();

            if ($keys === null) {
                return new \WP_Error('mint_failed', 'Failed to generate WooCommerce API key.', ['status' => 500]);
            }

            Settings::setApiKeyId($keys['key_id']);

            $config['consumer_key'] = $keys['consumer_key'];
            $config['consumer_secret'] = $keys['consumer_secret'];
        }

        // Ensure webhook subscriptions (already idempotent).
        if (! Webhook\NativeWebhookManager::ensureWebhooks()) {
            return new \WP_Error('webhook_failed', 'Failed to register WooCommerce webhooks.', ['status' => 500]);
        }
        $config['webhook_ids'] = array_map('strval', Settings::getWebhookIds());

        return $config;
    }

    /**
     * Clean up commerce resources when the Chat plugin disconnects.
     */
    public static function handleChatDisconnected(): void
    {
        CredentialDelivery::cleanup();
        Webhook\NativeWebhookManager::removeWebhooks();
    }
}
