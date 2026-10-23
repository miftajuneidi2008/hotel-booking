<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTaxSettingsRequest;
use App\Models\Setting;
use App\Services\SettingsService;
use App\Support\TaxCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * VAT, service charge and the seller details printed on invoices (owner only, see the manage-settings gate).
 * Saved values replace config/tax.php and the .env file (see SettingsService).
 */
class TaxSettingsController extends Controller
{
    public function edit(): View
    {
        $last = Setting::query()
            ->where('key', 'like', 'tax.%')
            ->with('updater')
            ->latest('updated_at')
            ->first();

        return view('admin.settings.tax', [
            'values' => [
                'vat_enabled' => (bool) config('tax.vat.enabled'),
                'vat_rate' => (float) config('tax.vat.rate'),
                'service_charge_enabled' => (bool) config('tax.service_charge.enabled'),
                'service_charge_rate' => (float) config('tax.service_charge.rate'),
                'vat_on_service_charge' => (bool) config('tax.service_charge.vat_applies'),
                'business_name' => config('tax.business.name'),
                'business_tin' => config('tax.business.tin'),
                'business_address' => config('tax.business.address'),
                'business_phone' => config('tax.business.phone'),
                'business_email' => config('tax.business.email'),
            ],
            'example' => TaxCalculator::fromConfig()->breakdown(1000.0),
            'last' => $last,
        ]);
    }

    public function update(UpdateTaxSettingsRequest $request, SettingsService $settings): RedirectResponse
    {
        $data = $request->validated();

        $settings->save([
            'tax.vat.enabled' => $request->boolean('vat_enabled'),
            'tax.vat.rate' => round((float) $data['vat_rate'], 2),
            'tax.service_charge.enabled' => $request->boolean('service_charge_enabled'),
            'tax.service_charge.rate' => round((float) $data['service_charge_rate'], 2),
            'tax.service_charge.vat_applies' => $request->boolean('vat_on_service_charge'),
            'tax.business.name' => $data['business_name'] ?? null,
            'tax.business.tin' => $data['business_tin'] ?? null,
            'tax.business.address' => $data['business_address'] ?? null,
            'tax.business.phone' => $data['business_phone'] ?? null,
            'tax.business.email' => $data['business_email'] ?? null,
        ], $request->user()->id);

        return redirect()->route('admin.settings.tax.edit')
            ->with('status', __('Tax settings saved. New invoices use these rates; invoices already issued do not change.'));
    }
}
