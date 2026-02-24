<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Upsert default settings — idempotent (safe to run multiple times).
     */
    public function run(): void
    {
        $defaults = [
            [
                'key'         => 'cart_lock_duration_minutes',
                'value'       => '15',
                'type'        => 'integer',
                'description' => 'Minutes a pet stays locked in cart before auto-release',
            ],
            [
                'key'         => 'max_upload_size_mb',
                'value'       => '5',
                'type'        => 'integer',
                'description' => 'Maximum image upload size in megabytes',
            ],
            [
                'key'         => 'email_notifications_enabled',
                'value'       => 'true',
                'type'        => 'boolean',
                'description' => 'Master toggle — disabling this suppresses all transactional emails',
            ],
            [
                'key'         => 'maintenance_mode',
                'value'       => 'false',
                'type'        => 'boolean',
                'description' => 'Set to true to put the storefront into read-only mode',
            ],
            [
                'key'         => 'delivery_fee_default',
                'value'       => '100',
                'type'        => 'integer',
                'description' => 'Default delivery fee applied at checkout (BDT)',
            ],
        ];

        foreach ($defaults as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('System settings seeded.');
    }
}
