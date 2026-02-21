<?php

declare(strict_types=1);

namespace Kenzi\WooCommerce;

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
     * Called on `plugins_loaded` after confirming WooCommerce is active.
     */
    public function init(): void
    {
        $this->registerSettings();
    }

    private function registerSettings(): void
    {
        add_filter('woocommerce_get_settings_pages', static function (array $settings): array {
            $settings[] = new Admin\SettingsPage();

            return $settings;
        });
    }
}
