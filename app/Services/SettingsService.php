<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Settings the owner changes in the admin screens, stored in the database.
 *
 * Each setting is stored under the config key it replaces (for example "tax.vat.rate"), so the rest
 * of the code keeps reading config() and needs no change. A setting that was never saved keeps the
 * value from config/tax.php or the .env file.
 */
class SettingsService
{
    private const CACHE_KEY = 'property-settings';

    /** Config keys the admin screens may override. Any other key in the table is ignored. */
    public const KEYS = [
        'tax.vat.enabled',
        'tax.vat.rate',
        'tax.service_charge.enabled',
        'tax.service_charge.rate',
        'tax.service_charge.vat_applies',
        'tax.business.name',
        'tax.business.tin',
        'tax.business.address',
        'tax.business.phone',
        'tax.business.email',
    ];

    /** @return array<string, mixed> */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()
            ->get()
            ->mapWithKeys(fn (Setting $setting): array => [$setting->key => $setting->value])
            ->all());
    }

    /** Replace config values with the saved settings. Called once per request from AppServiceProvider. */
    public function applyToConfig(): void
    {
        try {
            foreach ($this->all() as $key => $value) {
                if (in_array($key, self::KEYS, true)) {
                    config([$key => $value]);
                }
            }
        } catch (Throwable) {
            // The settings table (or the cache table) does not exist yet, for example before
            // "php artisan migrate" has run. The values from config/tax.php stay in force.
        }
    }

    /**
     * @param  array<string, mixed>  $values  config key => value
     */
    public function save(array $values, ?int $userId): void
    {
        DB::transaction(function () use ($values, $userId): void {
            foreach ($values as $key => $value) {
                if (! in_array($key, self::KEYS, true)) {
                    continue;
                }

                Setting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value, 'updated_by' => $userId],
                );
            }
        });

        Cache::forget(self::CACHE_KEY);
    }
}
