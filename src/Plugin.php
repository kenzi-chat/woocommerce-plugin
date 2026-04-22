<?php

declare(strict_types=1);

namespace Kenzi\Commerce;

use Kenzi\Chat\Settings as ChatSettings;

/**
 * Main plugin class for Kenzi Commerce.
 *
 * Registers a WooCommerce settings tab and an admin notice directing
 * users to enable commerce data sync.
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

        if (is_admin()) {
            $this->registerPluginLinks();
            $this->registerSettings();
            add_action('admin_init', [self::class, 'maybeEnsureWebhooks']);
            add_action('admin_init', [CredentialDelivery::class, 'maybeDeliver']);
            add_action('admin_notices', [$this, 'maybeShowUpgradeNotice']);
            add_action('wp_ajax_kenzi_commerce_enable', [$this, 'handleEnableAjax']);
        }
    }

    /**
     * Register webhooks on admin_init when conditions are met.
     *
     * Deferred from plugins_loaded to admin_init so that (a) WooCommerce
     * textdomains are loaded and (b) get_current_user_id() returns the
     * logged-in admin, which NativeWebhookManager needs for payload generation.
     */
    public static function maybeEnsureWebhooks(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        if (Settings::shouldWebhooksBeActive()) {
            Webhook\NativeWebhookManager::ensureWebhooks();
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

            $meta[] = '<a href="' . esc_url('https://wiki.kenzi.chat/integrations/woocommerce/') . '">' . __('Docs', 'kenzi-commerce') . '</a>';

            return $meta;
        }, 10, 2);
    }

    /**
     * Register the WooCommerce settings tab.
     */
    private function registerSettings(): void
    {
        add_filter('woocommerce_get_settings_pages', static function (array $settings): array {
            $settings[] = new Admin\SettingsPage();

            return $settings;
        });
    }

    /**
     * Show a notice linking to the settings tab if commerce is not yet enabled.
     */
    public function maybeShowUpgradeNotice(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        if (! ChatSettings::isConnected()) {
            return;
        }

        if (ChatSettings::hasCapability('commerce')) {
            return;
        }

        $settingsUrl = admin_url('admin.php?page=wc-settings&tab=kenzi-commerce');

        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong><?php esc_html_e('Kenzi Commerce', 'kenzi-commerce'); ?>:</strong>
                <?php esc_html_e(
                    'Enable commerce data sync to show order and customer data in your Kenzi inbox.',
                    'kenzi-commerce',
                ); ?>
                <a href="<?php echo esc_url($settingsUrl); ?>">
                    <?php esc_html_e('Go to settings', 'kenzi-commerce'); ?> &rarr;
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Clean up commerce resources when the Chat plugin disconnects.
     */
    public static function handleChatDisconnected(): void
    {
        CredentialDelivery::cleanup();
        Webhook\NativeWebhookManager::removeWebhooks();
    }

    /**
     * AJAX handler to add the 'commerce' capability after a successful upgrade.
     */
    public function handleEnableAjax(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'kenzi-commerce')); // wp_send_json_error calls wp_die — never returns
        }

        if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'kenzi_commerce_enable')) {
            wp_send_json_error(__('Invalid nonce', 'kenzi-commerce')); // wp_send_json_error calls wp_die — never returns
        }

        $capabilities = ChatSettings::getCapabilities();
        if (! in_array('commerce', $capabilities, true)) {
            $capabilities[] = 'commerce';
            ChatSettings::setCapabilities($capabilities);
        }

        wp_send_json_success();
    }

}
