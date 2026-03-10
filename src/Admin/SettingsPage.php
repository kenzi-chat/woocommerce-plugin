<?php

declare(strict_types=1);

namespace Kenzi\Commerce\Admin;

use Kenzi\Chat\Settings as ChatSettings;
use WC_Settings_Page;

/**
 * WooCommerce settings tab for Kenzi Commerce.
 *
 * Appears under WooCommerce > Settings > Kenzi Commerce.
 * Renders custom HTML sections for commerce upgrade and webhooks.
 */
class SettingsPage extends WC_Settings_Page
{
    public function __construct()
    {
        $this->id = 'kenzi-commerce';
        $this->label = __('Kenzi Commerce', 'kenzi-commerce');

        parent::__construct();
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
                        <p style="margin: 0; color: #2e7d32;">
                            <span class="dashicons dashicons-yes-alt" style="color: #2e7d32;"></span>
                            <?php esc_html_e('Commerce data sync is enabled.', 'kenzi-commerce'); ?>
                        </p>
                    <?php elseif ($isConnected): ?>
                        <p style="margin: 0 0 10px;">
                            <?php esc_html_e(
                                'Enable commerce data sync to show order and customer data in your Kenzi inbox.',
                                'kenzi-commerce',
                            ); ?>
                        </p>
                        <button type="button" class="button button-primary" id="kenzi-commerce-upgrade">
                            <?php esc_html_e('Enable Commerce', 'kenzi-commerce'); ?>
                        </button>
                    <?php else: ?>
                        <p style="margin: 0; color: #d63638;">
                            <?php esc_html_e(
                                'Connect your site to Kenzi first via the Kenzi Chat plugin settings.',
                                'kenzi-commerce',
                            ); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Webhooks', 'kenzi-commerce'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Order & Customer Sync', 'kenzi-commerce'); ?></th>
                <td>
                    <?php // TODO: Wire up webhook registration.
                    //
                    // The "Enable Webhooks" button should register WooCommerce webhooks
                    // for order and customer events (created, updated, deleted) that
                    // POST to the Kenzi backend. Implementation will include:
                    //
                    // 1. Register webhooks via WooCommerce REST API or wc_create_webhook()
                    // 2. Store webhook IDs in wp_options for later cleanup
                    // 3. Use the kenzi_secret from ChatSettings for webhook signing
                    // 4. Add a "Disable Webhooks" toggle once enabled
                    // 5. Clean up webhooks on plugin deactivation
                    ?>
                    <button type="button" class="button" id="kenzi-commerce-webhooks" disabled>
                        <?php esc_html_e('Enable Webhooks', 'kenzi-commerce'); ?>
                    </button>
                    <p class="description">
                        <?php esc_html_e(
                            'Register WooCommerce webhooks to sync order and customer data with Kenzi. (Coming soon)',
                            'kenzi-commerce',
                        ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php if ($isConnected && ! $isEnabled): ?>
            <?php $this->renderUpgradeScript(); ?>
        <?php endif; ?>
        <?php
    }

    /**
     * No WC settings fields to save — all persistence is via AJAX.
     */
    public function save(): void
    {
        // No-op: commerce upgrade is persisted via wp_ajax_kenzi_commerce_enable.
    }

    /**
     * Render the inline JS for the commerce upgrade popup flow.
     */
    private function renderUpgradeScript(): void
    {
        $upgradeUrl = ChatSettings::getUpgradeUrl();
        $connectUrl = ChatSettings::getConnectUrl();
        $storeUrl = home_url();
        $adminAjaxUrl = admin_url('admin-ajax.php');
        $wpNonce = wp_create_nonce('kenzi_commerce_enable');
        $settingsUrl = admin_url('admin.php?page=wc-settings&tab=kenzi-commerce');

        ?>
        <script>
        (function() {
            var currentNonce = null;

            document.getElementById('kenzi-commerce-upgrade')?.addEventListener('click', function() {
                var bytes = new Uint8Array(32);
                crypto.getRandomValues(bytes);
                currentNonce = Array.from(bytes, function(b) { return b.toString(16).padStart(2, '0'); }).join('');

                var upgradeUrl = <?php echo wp_json_encode($upgradeUrl); ?> + '?' + new URLSearchParams({
                    platform: 'wordpress',
                    store_key: <?php echo wp_json_encode($storeUrl); ?>,
                    origin: <?php echo wp_json_encode($storeUrl); ?>,
                    nonce: currentNonce,
                    capabilities: 'commerce'
                });

                var width = 500, height = 600;
                var left = Math.round((screen.width - width) / 2);
                var top = Math.round((screen.height - height) / 2);
                var popup = window.open(
                    upgradeUrl,
                    'kenzi_upgrade',
                    'popup,width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',scrollbars=yes,resizable=yes'
                );

                if (!popup || popup.closed) {
                    alert(<?php echo wp_json_encode(__('Popup was blocked. Please allow popups for this site.', 'kenzi-commerce')); ?>);
                    currentNonce = null;
                }
            });

            window.addEventListener('message', function(event) {
                var expectedOrigin = new URL(<?php echo wp_json_encode($connectUrl); ?>).origin;
                if (event.origin !== expectedOrigin) {
                    return;
                }

                if (event.data && event.data.type === 'kenzi_commerce_enabled') {
                    if (event.data.nonce !== currentNonce) {
                        alert(<?php echo wp_json_encode(__('Security validation failed. Please try again.', 'kenzi-commerce')); ?>);
                        return;
                    }

                    if (event.source && typeof event.source.close === 'function') {
                        event.source.close();
                    }
                    currentNonce = null;

                    fetch(<?php echo wp_json_encode($adminAjaxUrl); ?>, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'kenzi_commerce_enable',
                            _wpnonce: <?php echo wp_json_encode($wpNonce); ?>
                        })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(result) {
                        if (result.success) {
                            window.location.href = <?php echo wp_json_encode($settingsUrl); ?>;
                        } else {
                            alert(<?php echo wp_json_encode(__('Failed to save:', 'kenzi-commerce')); ?> + ' ' + (result.data || 'Unknown error'));
                        }
                    })
                    .catch(function() {
                        alert(<?php echo wp_json_encode(__('Failed to save. Please try again.', 'kenzi-commerce')); ?>);
                    });
                }
            });
        })();
        </script>
        <?php
    }
}
