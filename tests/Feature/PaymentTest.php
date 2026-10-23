<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\CashSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $desk;

    private User $manager;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->desk = User::factory()->receptionist()->create();
        $this->manager = User::factory()->create(['role' => 'manager']);

        // A checked-in guest whose bill is ETB 2000.
        $this->booking = Booking::factory()->for(Room::factory()->for(RoomType::factory()->create(['capacity' => 2])))->create([
            'status' => BookingStatus::CheckedIn,
            'check_in' => today()->subDay()->toDateString(),
            'check_out' => today()->addDay()->toDateString(),
            'total_amount' => 2000,
            'rate_breakdown' => [today()->subDay()->toDateString() => 1000, today()->toDateString() => 1000],
        ]);
    }

    private function pay(User $by, array $data, ?Booking $booking = null)
    {
        return $this->actingAs($by)->post(route('staff.payments.store', $booking ?? $this->booking), $data);
    }

    private function refund(User $by, array $data, ?Booking $booking = null)
    {
        return $this->actingAs($by)->post(route('staff.payments.refund', $booking ?? $this->booking), $data);
    }

    private function entry(array $overrides = []): Payment
    {
        return Payment::factory()->create($overrides + ['booking_id' => $this->booking->id]);
    }

    // ----- FR-20 / FR-21 payments and part payments ----------------------

    public function test_part_payments_reduce_the_balance(): void
    {
        $this->pay($this->desk, ['amount' => 500, 'method' => 'cash'])->assertSessionHas('status');
        $this->pay($this->desk, ['amount' => 700, 'method' => 'telebirr', 'reference' => 'TB-889211'])->assertSessionHas('status');

        $booking = $this->booking->fresh();
        $this->assertEqualsWithDelta(1200.0, $booking->paymentsNet(), 0.01);
        $this->assertEqualsWithDelta(1200.0, $booking->amountPaid(), 0.01);
        $this->assertEqualsWithDelta(800.0, $booking->balanceDue(), 0.01);

        $payment = Payment::query()->where('method', 'telebirr')->firstOrFail();
        $this->assertSame('TB-889211', $payment->reference);
        $this->assertSame($this->desk->id, $payment->created_by);
        $this->assertSame(PaymentKind::Payment, $payment->kind);
        $this->assertStringStartsWith('PAY-', $payment->number);
    }

    public function test_money_that_is_not_cash_needs_a_reference(): void
    {
        foreach (['bank_transfer', 'telebirr', 'card'] as $method) {
            $this->pay($this->desk, ['amount' => 100, 'method' => $method])->assertSessionHasErrors('reference', null, 'payment');
        }

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_a_payment_cannot_be_more_than_the_balance(): void
    {
        $this->pay($this->desk, ['amount' => 2500, 'method' => 'cash'])->assertSessionHas('error');

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_the_deposit_already_taken_counts_against_the_balance(): void
    {
        $this->booking->update(['deposit_amount' => 500]);

        $this->pay($this->desk, ['amount' => 1600, 'method' => 'cash'])->assertSessionHas('error');
        $this->pay($this->desk, ['amount' => 1500, 'method' => 'cash'])->assertSessionHas('status');

        $this->assertEqualsWithDelta(0.0, $this->booking->fresh()->balanceDue(), 0.01);
    }

    public function test_amount_and_method_are_checked(): void
    {
        $this->pay($this->desk, ['amount' => 0, 'method' => 'cash'])->assertSessionHasErrors('amount', null, 'payment');
        $this->pay($this->desk, ['amount' => -5, 'method' => 'cash'])->assertSessionHasErrors('amount', null, 'payment');
        $this->pay($this->desk, ['amount' => 100, 'method' => 'cheque'])->assertSessionHasErrors('method', null, 'payment');
    }

    public function test_a_cancelled_booking_takes_no_payment(): void
    {
        $booking = Booking::factory()->cancelled()->create();

        $this->pay($this->desk, ['amount' => 100, 'method' => 'cash'], $booking)->assertSessionHas('error');
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_a_guest_who_has_left_can_still_settle_the_bill_later(): void
    {
        $this->booking->update(['status' => BookingStatus::CheckedOut]);

        $this->pay($this->desk, ['amount' => 2000, 'method' => 'bank_transfer', 'reference' => 'CBE-5521'])->assertSessionHas('status');

        $this->assertEqualsWithDelta(0.0, $this->booking->fresh()->balanceDue(), 0.01);
    }

    public function test_guests_and_housekeeping_cannot_take_payments(): void
    {
        $this->pay(User::factory()->create(), ['amount' => 100, 'method' => 'cash'])->assertForbidden();
        $this->pay(User::factory()->create(['role' => 'housekeeping']), ['amount' => 100, 'method' => 'cash'])->assertForbidden();
        $this->pay(User::factory()->create(['role' => 'cashier']), ['amount' => 100, 'method' => 'cash'])->assertForbidden();
    }

    // ----- FR-22 other currencies ----------------------------------------

    public function test_a_foreign_payment_is_stored_with_its_rate_and_birr_value(): void
    {
        config(['payments.foreign_currencies' => ['USD']]);

        $this->pay($this->desk, ['amount' => 10, 'currency' => 'USD', 'exchange_rate' => 150, 'method' => 'cash'])->assertSessionHas('status');

        $payment = Payment::query()->firstOrFail();
        $this->assertSame('USD', $payment->currency);
        $this->assertEqualsWithDelta(10.0, (float) $payment->original_amount, 0.01);
        $this->assertEqualsWithDelta(150.0, (float) $payment->exchange_rate, 0.0001);
        $this->assertEqualsWithDelta(1500.0, (float) $payment->amount, 0.01);
        $this->assertEqualsWithDelta(500.0, $this->booking->fresh()->balanceDue(), 0.01);
    }

    public function test_a_foreign_payment_needs_a_rate_and_an_accepted_currency(): void
    {
        config(['payments.foreign_currencies' => ['USD']]);

        $this->pay($this->desk, ['amount' => 10, 'currency' => 'USD', 'method' => 'cash'])->assertSessionHasErrors('exchange_rate', null, 'payment');
        $this->pay($this->desk, ['amount' => 10, 'currency' => 'EUR', 'exchange_rate' => 160, 'method' => 'cash'])->assertSessionHasErrors('currency', null, 'payment');

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_only_birr_is_accepted_until_the_property_turns_other_currencies_on(): void
    {
        $this->pay($this->desk, ['amount' => 10, 'currency' => 'USD', 'exchange_rate' => 150, 'method' => 'cash'])
            ->assertSessionHasErrors('currency', null, 'payment');
    }

    // ----- FR-21 refunds -------------------------------------------------

    public function test_a_refund_needs_a_reason_and_reduces_what_was_paid(): void
    {
        $this->entry(['amount' => 1000, 'original_amount' => 1000]);

        $this->refund($this->desk, ['amount' => 300, 'method' => 'cash', 'reason' => ''])->assertSessionHasErrors('reason', null, 'refund');

        $this->refund($this->desk, ['amount' => 300, 'method' => 'cash', 'reason' => 'Guest left one night early'])->assertSessionHas('status');

        $refund = Payment::query()->where('kind', 'refund')->firstOrFail();
        $this->assertSame('Guest left one night early', $refund->reason);
        $this->assertEqualsWithDelta(700.0, $this->booking->fresh()->amountPaid(), 0.01);
        $this->assertEqualsWithDelta(1300.0, $this->booking->fresh()->balanceDue(), 0.01);
    }

    public function test_a_refund_cannot_be_more_than_the_guest_has_paid(): void
    {
        $this->entry(['amount' => 500, 'original_amount' => 500]);

        $this->refund($this->desk, ['amount' => 600, 'method' => 'cash', 'reason' => 'Mistake'])->assertSessionHas('error');

        $this->assertSame(0, Payment::query()->where('kind', 'refund')->count());
    }

    public function test_the_deposit_taken_with_the_booking_can_be_refunded(): void
    {
        $this->booking->update(['deposit_amount' => 800]);

        $this->refund($this->desk, ['amount' => 800, 'method' => 'cash', 'reason' => 'Booking cancelled'])->assertSessionHas('status');

        $this->assertEqualsWithDelta(0.0, $this->booking->fresh()->amountPaid(), 0.01);
    }

    public function test_a_cancelled_booking_can_still_be_refunded(): void
    {
        $booking = Booking::factory()->cancelled()->create(['deposit_amount' => 400]);

        $this->refund($this->desk, ['amount' => 400, 'method' => 'cash', 'reason' => 'Booking cancelled'], $booking)->assertSessionHas('status');
    }

    public function test_a_large_refund_needs_a_manager(): void
    {
        config(['payments.refund_approval_amount' => 1000]);
        $this->entry(['amount' => 2000, 'original_amount' => 2000]);

        $this->refund($this->desk, ['amount' => 1500, 'method' => 'cash', 'reason' => 'Overcharged'])->assertSessionHas('error');
        $this->assertSame(0, Payment::query()->where('kind', 'refund')->count());

        $this->refund($this->desk, ['amount' => 900, 'method' => 'cash', 'reason' => 'Overcharged'])->assertSessionHas('status');
        $this->refund($this->manager, ['amount' => 1100, 'method' => 'cash', 'reason' => 'Overcharged'])->assertSessionHas('status');

        $this->assertSame(2, Payment::query()->where('kind', 'refund')->count());
    }

    // ----- Voiding -------------------------------------------------------

    public function test_a_manager_can_void_a_wrong_payment_with_a_reason(): void
    {
        $payment = $this->entry(['amount' => 1000, 'original_amount' => 1000]);

        $this->actingAs($this->desk)->patch(route('staff.payments.void', $payment), ['reason' => 'Typed twice'])->assertForbidden();

        $this->actingAs($this->manager)->patch(route('staff.payments.void', $payment), ['reason' => ''])->assertSessionHasErrors('reason', null, 'void');

        $this->actingAs($this->manager)->patch(route('staff.payments.void', $payment), ['reason' => 'Typed twice'])->assertSessionHas('status');

        $payment->refresh();
        $this->assertTrue($payment->isVoided());
        $this->assertSame($this->manager->id, $payment->voided_by);
        $this->assertSame('Typed twice', $payment->void_reason);
        $this->assertEqualsWithDelta(0.0, $this->booking->fresh()->amountPaid(), 0.01);
        $this->assertSame(1, Payment::query()->count(), 'A voided entry stays in the books.');

        $this->actingAs($this->manager)->patch(route('staff.payments.void', $payment), ['reason' => 'Again'])->assertSessionHas('error');
    }

    public function test_a_refund_cannot_be_voided_if_the_guest_would_then_have_paid_more_than_the_bill(): void
    {
        $this->booking->update(['total_amount' => 1000, 'rate_breakdown' => [today()->subDay()->toDateString() => 1000]]);
        $this->entry(['amount' => 1000, 'original_amount' => 1000]);
        $refund = $this->entry(['amount' => 400, 'original_amount' => 400, 'kind' => PaymentKind::Refund, 'reason' => 'Test']);

        // Paid 1000 - 400 refunded = 600. Voiding the refund would make it 1000: fine. Add a second payment to push it over.
        $this->entry(['amount' => 400, 'original_amount' => 400]);

        $this->actingAs($this->manager)->patch(route('staff.payments.void', $refund), ['reason' => 'Wrong guest'])->assertSessionHas('error');
        $this->assertFalse($refund->fresh()->isVoided());
    }

    // ----- Where the money shows up --------------------------------------

    public function test_the_guest_bill_lists_payments_and_the_printouts_include_them(): void
    {
        $this->pay($this->desk, ['amount' => 700, 'method' => 'telebirr', 'reference' => 'TB-889211']);

        $this->actingAs($this->desk)->get(route('staff.folio.show', $this->booking))
            ->assertOk()
            ->assertSee('TB-889211')
            ->assertSee('Record a payment');

        $this->actingAs($this->desk)->get(route('staff.folio.receipt', $this->booking))->assertOk()->assertSee('Telebirr');
        $this->actingAs($this->desk)->get(route('staff.folio.invoice', $this->booking))->assertOk()->assertSee('Telebirr');
        $this->actingAs($this->desk)->get(route('staff.bookings.show', $this->booking))->assertOk()->assertSee('Telebirr');
    }

    public function test_a_voided_payment_is_not_printed_as_paid(): void
    {
        $this->entry(['method' => PaymentMethod::Card, 'reference' => 'CARD-1', 'voided_at' => now(), 'void_reason' => 'Wrong guest']);

        $this->actingAs($this->desk)->get(route('staff.folio.receipt', $this->booking))->assertOk()->assertDontSee('(Card)');
    }

    // ----- FR-23 daily cash summary --------------------------------------

    public function test_the_cash_summary_adds_up_per_user_and_per_method(): void
    {
        config(['payments.foreign_currencies' => ['USD']]);
        $other = User::factory()->receptionist()->create(['name' => 'Second Receptionist']);

        $this->entry(['created_by' => $this->desk->id, 'amount' => 1000, 'original_amount' => 1000, 'method' => PaymentMethod::Cash]);
        $this->entry(['created_by' => $this->desk->id, 'amount' => 300, 'original_amount' => 300, 'method' => PaymentMethod::Cash, 'kind' => PaymentKind::Refund, 'reason' => 'x']);
        $this->entry(['created_by' => $this->desk->id, 'amount' => 700, 'original_amount' => 700, 'method' => PaymentMethod::Telebirr, 'reference' => 'T1']);
        $this->entry(['created_by' => $this->desk->id, 'amount' => 1500, 'original_amount' => 10, 'currency' => 'USD', 'exchange_rate' => 150, 'method' => PaymentMethod::Cash]);
        $this->entry(['created_by' => $other->id, 'amount' => 200, 'original_amount' => 200, 'method' => PaymentMethod::Cash]);

        // Not counted: voided, and from another day.
        $this->entry(['created_by' => $this->desk->id, 'amount' => 9999, 'original_amount' => 9999, 'voided_at' => now(), 'void_reason' => 'x']);
        $this->entry(['created_by' => $this->desk->id, 'amount' => 8888, 'original_amount' => 8888, 'paid_at' => now()->subDays(3)]);

        $service = app(CashSummaryService::class);
        $day = $service->day($service->today());

        $this->assertCount(2, $day['rows']);

        $mine = $day['rows']->first(fn (array $row): bool => $row['user']?->id === $this->desk->id);
        $this->assertEqualsWithDelta(2200.0, $mine['in'], 0.01);          // 1000 + 700 + 1500
        $this->assertEqualsWithDelta(300.0, $mine['out'], 0.01);
        $this->assertEqualsWithDelta(1900.0, $mine['net'], 0.01);
        $this->assertEqualsWithDelta(2200.0, $mine['cash_net'], 0.01);    // 1000 - 300 + 1500 in cash
        $this->assertEqualsWithDelta(700.0, $mine['by_method']['telebirr']['net'], 0.01);
        $this->assertEqualsWithDelta(10.0, $mine['foreign_cash']['USD'], 0.01);

        $this->assertEqualsWithDelta(2100.0, $day['totals']['net'], 0.01); // 1900 + 200
        $this->assertCount(1, $day['voided']);

        // One user only.
        $only = $service->day($service->today(), $other->id);
        $this->assertCount(1, $only['rows']);
        $this->assertEqualsWithDelta(200.0, $only['totals']['net'], 0.01);
    }

    public function test_a_receptionist_sees_only_their_own_cash_and_a_manager_sees_everyones(): void
    {
        $other = User::factory()->receptionist()->create(['name' => 'Zed Colleague']);
        $this->entry(['created_by' => $this->desk->id, 'amount' => 111, 'original_amount' => 111]);
        $this->entry(['created_by' => $other->id, 'amount' => 222, 'original_amount' => 222]);

        $this->actingAs($this->desk)->get(route('staff.cash-summary', ['user' => $other->id]))
            ->assertOk()
            ->assertDontSee('Zed Colleague')
            ->assertSee('111.00');

        $this->actingAs($this->manager)->get(route('staff.cash-summary'))
            ->assertOk()
            ->assertSee('Zed Colleague')
            ->assertSee('222.00');

        $this->actingAs($this->manager)->get(route('staff.cash-summary.print'))->assertOk()->assertSee('Closing sheet');
    }

    public function test_guests_cannot_open_the_cash_summary(): void
    {
        $this->actingAs(User::factory()->create())->get(route('staff.cash-summary'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'cashier']))->get(route('staff.cash-summary'))->assertForbidden();
    }

    public function test_the_summary_day_follows_the_property_timezone(): void
    {
        config(['payments.timezone' => 'Africa/Addis_Ababa', 'app.timezone' => 'UTC']);

        // 22:30 UTC is 01:30 the next day in Addis Ababa (UTC+3).
        $this->travelTo(now('UTC')->setTime(22, 30));
        $this->entry(['amount' => 123, 'original_amount' => 123, 'paid_at' => now()]);

        $service = app(CashSummaryService::class);
        $addisDate = now('Africa/Addis_Ababa')->toDateString();
        $utcDate = now('UTC')->toDateString();

        $this->assertNotSame($addisDate, $utcDate);
        $this->assertEqualsWithDelta(123.0, $service->day($addisDate)['totals']['net'], 0.01);
        $this->assertEqualsWithDelta(0.0, $service->day($utcDate)['totals']['net'], 0.01);
    }
}
