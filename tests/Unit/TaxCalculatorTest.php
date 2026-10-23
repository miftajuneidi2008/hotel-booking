<?php

namespace Tests\Unit;

use App\Support\TaxCalculator;
use PHPUnit\Framework\TestCase;

/** Section 5.9 (FR-33): the tax inside a bill total is split correctly and always adds up. */
class TaxCalculatorTest extends TestCase
{
    /** @return array<string, mixed> */
    private function config(bool $vat, bool $service, bool $vatOnService = true): array
    {
        return [
            'vat' => ['enabled' => $vat, 'rate' => 15],
            'service_charge' => ['enabled' => $service, 'rate' => 10, 'vat_applies' => $vatOnService],
        ];
    }

    public function test_vat_only(): void
    {
        $parts = (new TaxCalculator($this->config(true, false)))->breakdown(1150);

        $this->assertSame(1000.0, $parts['subtotal']);
        $this->assertSame(150.0, $parts['vat']);
        $this->assertSame(0.0, $parts['service_charge']);
    }

    public function test_service_charge_and_vat_on_top_of_it(): void
    {
        $parts = (new TaxCalculator($this->config(true, true)))->breakdown(1265);

        $this->assertSame(1000.0, $parts['subtotal']);
        $this->assertSame(100.0, $parts['service_charge']);
        $this->assertSame(165.0, $parts['vat']);
    }

    public function test_service_charge_and_vat_both_taken_from_the_price(): void
    {
        $parts = (new TaxCalculator($this->config(true, true, false)))->breakdown(1250);

        $this->assertSame(1000.0, $parts['subtotal']);
        $this->assertSame(100.0, $parts['service_charge']);
        $this->assertSame(150.0, $parts['vat']);
    }

    public function test_everything_off_leaves_the_total_untouched(): void
    {
        $parts = (new TaxCalculator($this->config(false, false)))->breakdown(1800);

        $this->assertSame(1800.0, $parts['subtotal']);
        $this->assertSame(0.0, $parts['vat']);
        $this->assertSame(0.0, $parts['service_charge']);
    }

    public function test_a_zero_total_has_no_tax(): void
    {
        $parts = (new TaxCalculator($this->config(true, true)))->breakdown(0);

        $this->assertSame(0.0, $parts['subtotal']);
        $this->assertSame(0.0, $parts['vat']);
    }

    public function test_the_parts_always_add_up_to_the_total(): void
    {
        foreach ([[true, false, true], [true, true, true], [true, true, false], [false, true, true]] as [$vat, $service, $onService]) {
            $calculator = new TaxCalculator($this->config($vat, $service, $onService));

            for ($cents = 1; $cents <= 100000; $cents += 37) {
                $parts = $calculator->breakdown($cents / 100);

                $this->assertEqualsWithDelta($cents / 100, $parts['subtotal'] + $parts['service_charge'] + $parts['vat'], 0.0001);
                $this->assertGreaterThanOrEqual(0.0, $parts['subtotal']);
            }
        }
    }
}
