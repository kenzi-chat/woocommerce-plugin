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

    /**
     * Initialize the plugin.
     *
     * Called on `plugins_loaded`. Since `Requires Plugins: kenzi-chat, woocommerce`
     * is declared in the plugin header, both dependencies are guaranteed active.
     */
    public function init(): void
    {
        if (is_admin()) {
            $this->registerSettings();
            add_action('admin_notices', [$this, 'maybeShowUpgradeNotice']);
            add_action('wp_ajax_kenzi_commerce_enable', [$this, 'handleEnableAjax']);
        }
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
     * AJAX handler to persist the commerce-enabled flag after a successful upgrade.
     */
    public function handleEnableAjax(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        if (! wp_verify_nonce($_POST['_wpnonce'] ?? '', 'kenzi_commerce_enable')) {
            wp_send_json_error('Invalid nonce');
        }

        $capabilities = ChatSettings::getCapabilities();
        if (! in_array('commerce', $capabilities, true)) {
            $capabilities[] = 'commerce';
            ChatSettings::setCapabilities($capabilities);
        }

        wp_send_json_success();
    }
}
