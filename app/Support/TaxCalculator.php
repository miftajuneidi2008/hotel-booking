<?php

namespace App\Support;

/**
 * Splits a bill total that already includes service charge and VAT (config/tax.php) into
 * subtotal + service charge + VAT. The three parts always add up to the total to the cent.
 *
 * Works in whole cents so floating point errors cannot move the total.
 */
final class TaxCalculator
{
    /** @param array<string, mixed> $config The "tax" config array. */
    public function __construct(private readonly array $config) {}

    public static function fromConfig(): self
    {
        return new self((array) config('tax'));
    }

    /**
     * @return array{
     *     total: float, subtotal: float,
     *     service_rate: float, service_charge: float,
     *     vat_rate: float, vat: float, vat_includes_service_charge: bool
     * }
     */
    public function breakdown(float $total): array
    {
        $vatRate = (bool) ($this->config['vat']['enabled'] ?? false) ? max(0.0, (float) ($this->config['vat']['rate'] ?? 0)) : 0.0;
        $serviceRate = (bool) ($this->config['service_charge']['enabled'] ?? false) ? max(0.0, (float) ($this->config['service_charge']['rate'] ?? 0)) : 0.0;
        $vatOnService = (bool) ($this->config['service_charge']['vat_applies'] ?? true);

        $cents = (int) round($total * 100);
        $v = $vatRate / 100;
        $s = $serviceRate / 100;

        $vat = 0;
        $service = 0;

        if ($cents > 0 && ($v > 0 || $s > 0)) {
            if ($vatOnService) {
                // total = net * (1 + s) * (1 + v)
                $vat = (int) round($cents * $v / (1 + $v));
                $service = (int) round(($cents - $vat) * $s / (1 + $s));
            } else {
                // total = net * (1 + s + v)
                $net = $cents / (1 + $s + $v);
                $service = (int) round($net * $s);
                $vat = (int) round($net * $v);
            }
        }

        return [
            'total' => $cents / 100.0,
            'subtotal' => ($cents - $service - $vat) / 100.0,
            'service_rate' => $serviceRate,
            'service_charge' => $service / 100.0,
            'vat_rate' => $vatRate,
            'vat' => $vat / 100.0,
            'vat_includes_service_charge' => $vatOnService,
        ];
    }
}
