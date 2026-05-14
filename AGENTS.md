# AGENTS.md

This repository is the canonical source for the Kenzi Commerce WooCommerce
plugin.

## Ownership

- Package: `kenzi-chat/woocommerce-plugin`
- WordPress plugin slug: `kenzi-commerce`
- Primary launch channel: GitHub release ZIP
- Required companion plugins: `kenzi-chat`, `woocommerce`
- PHP floor: 8.1

Do not route changes through `kenzi-chat/web/platforms/woocommerce`. That path
is historical after the polyrepo split.

## Compatibility Rules

- Keep `Requires Plugins: kenzi-chat, woocommerce` in `kenzi-commerce.php`.
- Changes must support the WooCommerce host lines used by demo:
  - `woo104.demo.kenzi.chat`
  - `woo105.demo.kenzi.chat`
- If a WooCommerce release requires a newer Kenzi Chat base plugin, release the
  base plugin first and document the minimum compatible version.

## Development Commands

Run commands from the repository root.

```bash
composer install
composer validate --strict
composer lint
composer analyze
composer test
bash bin/build-zip.sh
```

## Release Policy

Customer releases are native `v*` tags from this repository. Release automation
must validate the exact tag commit before publishing a GitHub release.

For each release, the tag version without the `v` prefix must match:

- `Version:` in `kenzi-commerce.php`
- `KENZI_COMMERCE_VERSION` in `kenzi-commerce.php`

GitHub release ZIPs are the initial install channel. They do not provide native
WordPress auto-update. If a WordPress.org listing is approved later, add SVN
release automation before using that channel.
