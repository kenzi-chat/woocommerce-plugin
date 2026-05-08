# Kenzi Commerce (WooCommerce)

Commerce data sync between WooCommerce and Kenzi. Companion plugin to [Kenzi Chat](https://kenzi.chat) — requires the `kenzi-chat` WordPress plugin for the initial Connect flow.

- **Commerce Upgrade** — Settings tab and upgrade flow to add `commerce` capability to an existing Kenzi Chat connection
- **Credential Delivery** — Generates WooCommerce REST API keys and delivers them to the Kenzi backend
- **Order Webhooks** — Registers native WooCommerce webhooks (`order.created`, `order.updated`) for real-time sync

## Kenzi Integration Contract

### Integration Model

**1 integration per WordPress site.** Each WordPress installation (or each subsite in a multisite network) maps to exactly one Kenzi integration record. In multisite, each subsite has its own database tables, REST API endpoint, and API credentials — they are separate systems from Kenzi's perspective.

### Integration Key (instance_key)

The integration key is derived from `home_url()` with the scheme stripped and trailing slashes removed. This key uniquely identifies the WordPress site and is used by Kenzi to route incoming webhooks and credential deliveries.

| Setup | `home_url()` | `instance_key` |
|-------|-------------|----------------|
| Single site | `https://mystore.com` | `mystore.com` |
| Subdomain multisite | `https://shop.example.com` | `shop.example.com` |
| Subdirectory multisite | `https://example.com/store2` | `example.com/store2` |

PHP derivation:

```php
$instanceKey = preg_replace('#^https?://#', '', rtrim(home_url(), '/'));
```

### Kenzi Connect Parameters

Both the initial Connect (from `kenzi-chat`) and the commerce upgrade (from this plugin) open the Kenzi Connect popup with these parameters:

| Param | Value | Source |
|-------|-------|--------|
| `platform` | `wordpress` | Hardcoded |
| `instance_key` | Site identifier (no scheme) | `home_url()` with scheme stripped |
| `api_url` | Full WooCommerce REST API v3 base URL, no trailing slash | `rtrim(rest_url('wc/v3'), '/')` |
| `nonce` | Random UUID | `crypto.randomUUID()` |
| `origin` | Site URL | `home_url()` |
| `capabilities` | `commerce` | Hardcoded (commerce upgrade) |
| `admin_url` | WordPress admin URL, no trailing slash | `rtrim(admin_url(), '/')` |

The `api_url` is stored by Kenzi in `integration.meta["api_url"]` and used directly as the base URL for WooCommerce REST API calls. The WooCommerce plugin overrides `api_url` to `home_url('/wp-json/wc/v3')` via the `kenzi_configure_config` filter; widget-only installs use `home_url('/wp-json')`.

### Credential Delivery

After Connect completes, the plugin generates WooCommerce REST API keys and delivers them to:

```
PATCH /api/integrations/wordpress/{instance_key}/credentials
Authorization: Bearer {shared_secret}
```

Where `{instance_key}` is the scheme-stripped key (matching the integration key stored by Kenzi).

### Webhook Resolution

WooCommerce delivers webhooks with a native `X-WC-Webhook-Source` header set to `home_url('/')`. Kenzi extracts the integration key by stripping the scheme and trailing slashes from this URL, then looks up the integration by `(:wordpress, key)`.

| Header | Purpose |
|--------|---------|
| `X-WC-Webhook-Source` | Site URL — used by Kenzi to resolve the integration |
| `X-WC-Webhook-Signature` | HMAC-SHA256 of body, signed with `shared_secret` |
| `X-WC-Webhook-Topic` | Event name (e.g., `order.created`) |

The `shared_secret` used for HMAC verification is the same secret returned by the Connect flow and stored in the `kenzi-chat` plugin's settings.

## Requirements

- PHP 8.1+
- WordPress 6.7+
- WooCommerce 10.4+ (tested on 10.4.3 and 10.5.2)
- [Kenzi Chat](https://kenzi.chat) WordPress plugin (must be connected)

## Installation

This plugin requires the `kenzi-chat` WordPress plugin to be installed and connected first. Once active:

1. Install and activate the plugin
2. Navigate to WooCommerce > Settings > Kenzi Commerce
3. Click "Enable Commerce Sync" to run the upgrade Connect flow
4. The plugin automatically generates API keys and registers webhooks

## Testing

```bash
cd platforms/woocommerce
composer install
vendor/bin/phpunit
```
