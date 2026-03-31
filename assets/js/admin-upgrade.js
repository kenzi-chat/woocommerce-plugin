/**
 * Kenzi Commerce admin — upgrade popup flow.
 *
 * Opens a Kenzi Connect popup to add the 'commerce' capability to an
 * existing workspace connection. On success, updates the shared secret
 * and enables the commerce capability flag via sequential AJAX calls.
 *
 * Configuration is provided via wp_localize_script as `window.kenziCommerceAdmin`.
 *
 * @package Kenzi\Commerce
 */
(function () {
    'use strict';

    const config = window.kenziCommerceAdmin || {};

    // Cryptographic nonce for postMessage replay protection.
    let currentNonce = null;

    /**
     * Open the Kenzi Connect popup for commerce capability upgrade.
     *
     * The popup communicates back via postMessage with type 'kenzi_connected'.
     * On success, we persist the 'commerce' capability flag via AJAX.
     */
    document.getElementById('kenzi-commerce-upgrade')?.addEventListener('click', () => {
        const bytes = new Uint8Array(32);
        crypto.getRandomValues(bytes);
        currentNonce = Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');

        const upgradeUrl = config.connectUrl + '?' + new URLSearchParams({
            platform: 'wordpress',
            instance_key: config.instanceKey,
            origin: config.storeUrl,
            nonce: currentNonce,
            api_url: config.storeUrl,
            capabilities: 'commerce',
            admin_url: config.adminUrl,
        });

        const width = 500;
        const height = 600;
        const left = Math.round((screen.width - width) / 2);
        const top = Math.round((screen.height - height) / 2);
        const popup = window.open(
            upgradeUrl,
            'kenzi_upgrade',
            `popup,width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`,
        );

        if (!popup || popup.closed) {
            alert(config.i18n.popupBlocked);
            currentNonce = null;
        }
    });

    /**
     * Listen for postMessage from the Kenzi upgrade popup.
     *
     * Validates the message origin against the expected Kenzi server origin,
     * verifies the cryptographic nonce to prevent replay attacks, then
     * persists the commerce capability flag via AJAX.
     */
    window.addEventListener('message', (event) => {
        // Only accept messages from the Kenzi server origin.
        const expectedOrigin = new URL(config.connectUrl).origin;
        if (event.origin !== expectedOrigin) {
            return;
        }

        if (event.data?.type === 'kenzi_connected') {
            // Verify the nonce matches what we generated for this popup session.
            if (event.data.nonce !== currentNonce) {
                alert(config.i18n.securityFailed);
                return;
            }

            // Close the popup window now that upgrade is confirmed.
            if (event.source && typeof event.source.close === 'function') {
                event.source.close();
            }
            currentNonce = null;

            // Update the stored credentials first (the shared secret is rotated
            // during Connect), then enable the commerce capability.
            fetch(config.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'kenzi_save_connection',
                    _wpnonce: config.nonces.saveConnection,
                    workspace_id: event.data.workspace_id || '',
                    workspace_name: event.data.workspace_name || '',
                    secret: event.data.shared_secret || '',
                    integration_id: String(event.data.integration_id || ''),
                }),
            })
                .then(r => r.json())
                .then(result => {
                    if (!result.success) {
                        alert(config.i18n.saveFailed + ' ' + (result.data || 'Unknown error'));
                        return;
                    }
                    // Credentials saved — enable the commerce capability.
                    return fetch(config.ajaxUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'kenzi_commerce_enable',
                            _wpnonce: config.nonces.enable,
                        }),
                    });
                })
                .then(r => r?.json())
                .then(result => {
                    if (result?.success) {
                        window.location.href = config.settingsUrl;
                    } else if (result) {
                        alert(config.i18n.saveFailed + ' ' + (result.data || 'Unknown error'));
                    }
                })
                .catch(() => alert(config.i18n.saveFailedRetry));
        }
    });

})();
