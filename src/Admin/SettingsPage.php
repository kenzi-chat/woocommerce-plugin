<?php

declare(strict_types=1);

namespace Kenzi\Commerce\Admin;

use Kenzi\Chat\Settings as ChatSettings;
use WC_Settings_Page;

/**
 * WooCommerce settings tab for Kenzi Commerce.
 *
 * Appears under WooCommerce > Settings > Kenzi Commerce.
 * Renders custom HTML sections for commerce upgrade status.
 */
class SettingsPage extends WC_Settings_Page
{
    public function __construct()
    {
        $this->id = 'kenzi-commerce';
        $this->label = __('Kenzi Commerce', 'kenzi-commerce');

        // Load admin assets only on WooCommerce settings pages.
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

        parent::__construct();
    }

    /**
     * Enqueue admin scripts and styles for this settings tab.
     */
    public function enqueueAdminAssets(string $hookSuffix): void
    {
        // Only load on the WooCommerce settings page.
        if ($hookSuffix !== 'woocommerce_page_wc-settings') {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab check
        if (! isset($_GET['tab']) || sanitize_key(wp_unslash($_GET['tab'])) !== $this->id) {
            return;
        }

        $isEnabled = ChatSettings::hasCapability('commerce');
        $isConnected = ChatSettings::isConnected();

        wp_enqueue_style(
            'kenzi-commerce-admin',
            plugins_url('assets/css/admin.css', KENZI_COMMERCE_PLUGIN_FILE),
            [],
            KENZI_COMMERCE_VERSION,
        );

        // Enqueue admin JS for the upgrade flow (connected but commerce not yet enabled).
        if ($isConnected && ! $isEnabled) {
            wp_enqueue_script(
                'kenzi-commerce-admin',
                plugins_url('assets/js/admin-upgrade.js', KENZI_COMMERCE_PLUGIN_FILE),
                [],
                KENZI_COMMERCE_VERSION,
                ['in_footer' => true],
            );

            wp_localize_script('kenzi-commerce-admin', 'kenziCommerceAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'connectUrl' => ChatSettings::getConnectUrl(),
                'storeUrl' => home_url(),
                'instanceKey' => ChatSettings::getInstanceKey(),
                'adminUrl' => admin_url(),
                'settingsUrl' => admin_url('admin.php?page=wc-settings&tab=kenzi-commerce'),
                'nonces' => [
                    'enable' => wp_create_nonce('kenzi_commerce_enable'),
                    // The upgrade flow rotates the shared secret, so credentials must be updated.
                    'saveConnection' => wp_create_nonce('kenzi_save_connection'),
                ],
                'i18n' => [
                    'popupBlocked' => __('Popup was blocked. Please allow popups for this site.', 'kenzi-commerce'),
                    'securityFailed' => __('Security validation failed. Please try again.', 'kenzi-commerce'),
                    'saveFailed' => __('Failed to save:', 'kenzi-commerce'),
                    'saveFailedRetry' => __('Failed to save. Please try again.', 'kenzi-commerce'),
                ],
            ]);
        }
    }

    /**
     * Render the settings page with custom HTML sections.
     */
    public function output(): void
    {
        $isEnabled = ChatSettings::hasCapability('commerce');
        $isConnected = ChatSettings::isConnected();

        ?>
        <h2><?php esc_html_e('Commerce Data Sync', 'kenzi-commerce'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Status', 'kenzi-commerce'); ?></th>
                <td>
                    <?php if ($isEnabled): ?>
                        <p class="kenzi-commerce-status-enabled">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e('Commerce data sync is enabled.', 'kenzi-commerce'); ?>
                        </p>
                    <?php elseif ($isConnected): ?>
                        <p class="kenzi-commerce-status-prompt">
                            <?php esc_html_e(
                                'Enable commerce data sync to show order and customer data in your Kenzi inbox.',
                                'kenzi-commerce',
                            ); ?>
                        </p>
                        <button type="button" class="button button-primary" id="kenzi-commerce-upgrade">
                            <?php esc_html_e('Enable Commerce', 'kenzi-commerce'); ?>
                        </button>
                    <?php else: ?>
                        <p class="kenzi-commerce-status-disconnected">
                            <?php esc_html_e(
                                'Connect your site to Kenzi first via the Kenzi Chat plugin settings.',
                                'kenzi-commerce',
                            ); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <?php if ($isEnabled): ?>
        <h2><?php esc_html_e('Webhooks', 'kenzi-commerce'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Order Sync', 'kenzi-commerce'); ?></th>
                <td>
                    <p class="kenzi-commerce-status-enabled">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e(
                            'Order webhooks are registered automatically. Created and updated events are sent to Kenzi via HMAC-signed webhooks.',
                            'kenzi-commerce',
                        ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php endif; ?>
        <?php
    }

    /**
     * No-op — commerce settings are persisted via AJAX, not WC form saves.
     */
    public function save(): void
    {
    }
}
