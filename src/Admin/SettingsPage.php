<?php

declare(strict_types=1);

namespace Kenzi\WooCommerce\Admin;

use WC_Settings_Page;

class SettingsPage extends WC_Settings_Page
{
    public function __construct()
    {
        $this->id = 'kenzi';
        $this->label = 'Kenzi';

        parent::__construct();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_settings(): array
    {
        return [
            [
                'title' => 'Kenzi Connection',
                'type' => 'title',
                'desc' => 'Connect your WooCommerce store to Kenzi for customer messaging.',
                'id' => 'kenzi_connection_options',
            ],
            [
                'title' => 'API Key',
                'desc' => 'Enter the API key from your Kenzi workspace settings.',
                'id' => 'kenzi_api_key',
                'type' => 'password',
                'css' => 'min-width: 400px;',
            ],
            [
                'type' => 'sectionend',
                'id' => 'kenzi_connection_options',
            ],
        ];
    }
}
