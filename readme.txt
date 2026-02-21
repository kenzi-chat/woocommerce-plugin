=== Kenzi for WooCommerce ===
Contributors: kenzichat
Tags: customer messaging, live chat, support, ecommerce, crm
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.1.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Connect your WooCommerce store to Kenzi for customer messaging powered by real-time order and customer data.

== Description ==

Kenzi for WooCommerce bridges your store with the Kenzi customer messaging platform. When a customer reaches out, your support team instantly sees their order history, fulfillment status, and contact details — right inside the conversation.

**Features:**

* Automatic order and customer sync via WooCommerce webhooks
* Real-time order event notifications (created, updated, fulfilled)
* Customer context sidebar in Kenzi conversations
* Simple API key setup — no OAuth complexity
* Compatible with WooCommerce HPOS (High-Performance Order Storage)

== Installation ==

1. Upload the `kenzi-for-woocommerce` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Go to WooCommerce > Settings > Kenzi
4. Enter the API key from your Kenzi workspace settings
5. Save — webhooks are registered automatically

== Frequently Asked Questions ==

= Where do I get an API key? =

Log in to your Kenzi workspace at app.kenzi.chat, go to Settings > Integrations, and generate a WooCommerce API key.

= What data is synced? =

Orders, customers, and products are synced when events occur (creation, updates). Historical data is backfilled on first connection.

== Changelog ==

= 0.1.0 =
* Initial release: settings page, webhook registration, order/customer/product sync
