<?php

namespace Tests\Feature\Folio;

use App\Enums\BookingStatus;
use App\Enums\DiscountType;
use App\Enums\UserRole;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\FolioCharge;
use App\Models\FolioTransfer;
use App\Models\User;
use App\Services\FolioService;
use App\Services\StayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guest bill (folio), BRD section 5.4.
 */
class FolioTest extends TestCase
{
    use RefreshDatabase;

    private FolioService $folio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->folio = app(FolioService::class);

        config([
            'folio.discount_approval.percent' => 10,
            'folio.discount_approval.amount' => 1000,
            'folio.void_approval_amount' => 500,
        ]);
    }

    /** A guest who is in the house: 2 nights at ETB 1,800 = ETB 3,600. */
    private function checkedIn(array $overrides = []): Booking
    {
        return Booking::factory()->create(array_merge([
            'status' => BookingStatus::CheckedIn,
            'check_in' => today()->subDay()->toDateString(),
            'check_out' => today()->addDay()->toDateString(),
            'total_amount' => 3600,
            'rate_breakdown' => [
                today()->subDay()->toDateString() => 1800,
                today()->toDateString() => 1800,
            ],
            'checked_in_at' => now(),
        ], $overrides));
    }

    private function user(UserRole $role): User
    {
        return User::factory()->role($role)->create();
    }

    private function charge(Booking $booking, float $price = 200, int $quantity = 1): FolioCharge
    {
        return $this->folio->addCharge($booking, [
            'category' => 'food',
            'description' => 'Dinner',
            'quantity' => $quantity,
            'unit_price' => $price,
        ], $this->user(UserRole::Receptionist));
    }

    // ---- FR-16 extra charges -------------------------------------------------

    public function test_a_charge_is_added_to_the_balance(): void
    {
        $booking = $this->checkedIn();

        $charge = $this->charge($booking, 150, 2);

        $this->assertSame('300.00', $charge->amount);
        $this->assertSame(3900.0, $booking->balanceDue());
        $this->assertSame(3900.0, $booking->folioTotal());
    }

    public function test_charges_need_a_guest_who_is_checked_in(): void
    {
        $this->expectException(BookingException::class);

        $this->charge(Booking::factory()->create());
    }

    public function test_voiding_needs_a_reason_and_removes_the_charge_from_the_bill(): void
    {
        $booking = $this->checkedIn();
        $charge = $this->charge($booking, 200);
        $user = $this->user(UserRole::Receptionist);

        try {
            $this->folio->voidCharge($charge, '  ', $user);
            $this->fail('A reason is required.');
        } catch (BookingException) {
            $this->assertSame(3800.0, $booking->balanceDue());
        }

        $this->folio->voidCharge($charge, 'Wrong room', $user);

        $this->assertSame(3600.0, $booking->balanceDue());
        $this->assertNotNull($charge->fresh()->voided_at);
        $this->assertSame($user->id, $charge->fresh()->voided_by);
        // The record stays: nothing is deleted.
        $this->assertDatabaseCount('folio_charges', 1);
    }

    public function test_voiding_a_big_charge_needs_a_manager(): void
    {
        $booking = $this->checkedIn();
        $charge = $this->charge($booking, 800);

        try {
            $this->folio->voidCharge($charge, 'Mistake', $this->user(UserRole::Receptionist));
            $this->fail('A receptionist must not void a charge over the limit.');
        } catch (BookingException) {
            $this->assertNull($charge->fresh()->voided_at);
        }

        $manager = $this->user(UserRole::Manager);
        $this->folio->voidCharge($charge, 'Mistake', $manager);

        $this->assertSame($manager->id, $charge->fresh()->void_approved_by);
    }

    // ---- FR-17 discounts -----------------------------------------------------

    public function test_a_small_discount_needs_no_approval(): void
    {
        $booking = $this->checkedIn();

        $discount = $this->folio->applyDiscount($booking, DiscountType::Percent, 5, 'Loyal guest', $this->user(UserRole::Receptionist));

        $this->assertSame('180.00', $discount->amount);
        $this->assertFalse($discount->needs_approval);
        $this->assertNull($discount->approved_by);
        $this->assertSame(3420.0, $booking->balanceDue());
    }

    public function test_a_discount_over_the_limit_needs_a_manager_who_is_saved_as_approver(): void
    {
        $booking = $this->checkedIn();

        try {
            $this->folio->applyDiscount($booking, DiscountType::Percent, 20, 'Complaint', $this->user(UserRole::Receptionist));
            $this->fail('A receptionist must not give a discount over the limit.');
        } catch (BookingException) {
            $this->assertSame(0, $booking->folioDiscounts()->count());
        }

        $manager = $this->user(UserRole::Manager);
        $discount = $this->folio->applyDiscount($booking, DiscountType::Percent, 20, 'Complaint', $manager);

        $this->assertTrue($discount->needs_approval);
        $this->assertSame($manager->id, $discount->approved_by);
        $this->assertSame(2880.0, $booking->balanceDue());
    }

    public function test_small_discounts_cannot_be_stacked_to_get_around_the_limit(): void
    {
        $booking = $this->checkedIn();
        $receptionist = $this->user(UserRole::Receptionist);

        $this->folio->applyDiscount($booking, DiscountType::Percent, 8, 'First', $receptionist);

        $this->expectException(BookingException::class);
        $this->folio->applyDiscount($booking, DiscountType::Percent, 8, 'Second', $receptionist);
    }

    public function test_a_discount_needs_a_reason(): void
    {
        $this->expectException(BookingException::class);

        $this->folio->applyDiscount($this->checkedIn(), DiscountType::Fixed, 50, ' ', $this->user(UserRole::Manager));
    }

    public function test_discounts_cannot_be_more_than_the_bill(): void
    {
        $this->expectException(BookingException::class);

        $this->folio->applyDiscount($this->checkedIn(), DiscountType::Fixed, 5000, 'Too much', $this->user(UserRole::Owner));
    }

    public function test_a_discount_cannot_take_the_bill_below_what_the_guest_already_paid(): void
    {
        $booking = $this->checkedIn(['deposit_amount' => 3000]);

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Refund');

        $this->folio->applyDiscount($booking, DiscountType::Fixed, 900, 'Goodwill', $this->user(UserRole::Manager));
    }

    public function test_removing_a_discount_that_needed_approval_needs_a_manager(): void
    {
        $booking = $this->checkedIn();
        $discount = $this->folio->applyDiscount($booking, DiscountType::Percent, 20, 'Complaint', $this->user(UserRole::Manager));

        try {
            $this->folio->voidDiscount($discount, 'Entered twice', $this->user(UserRole::Receptionist));
            $this->fail('A receptionist must not remove a manager-approved discount.');
        } catch (BookingException) {
            $this->assertNull($discount->fresh()->voided_at);
        }

        $this->folio->voidDiscount($discount, 'Entered twice', $this->user(UserRole::Manager));

        $this->assertSame(3600.0, $booking->balanceDue());
    }

    // ---- FR-18 move or split a charge ----------------------------------------

    public function test_a_charge_can_be_moved_to_another_guest_bill(): void
    {
        $from = $this->checkedIn();
        $to = $this->checkedIn();
        $charge = $this->charge($from, 600);

        $this->folio->transferCharge($charge, $to, 600, 'Paid by the other guest', $this->user(UserRole::Receptionist));

        $this->assertSame($to->id, $charge->fresh()->booking_id);
        $this->assertSame(3600.0, $from->balanceDue());
        $this->assertSame(4200.0, $to->balanceDue());
        $this->assertSame(1, FolioTransfer::query()->count());
    }

    public function test_a_charge_can_be_split_and_the_two_bills_still_add_up(): void
    {
        $from = $this->checkedIn();
        $to = $this->checkedIn();
        $charge = $this->charge($from, 600);

        $this->folio->transferCharge($charge, $to, 200, 'Share the dinner', $this->user(UserRole::Receptionist));

        $this->assertTrue($charge->fresh()->isVoided());
        $this->assertTrue($charge->fresh()->void_was_split);
        $this->assertSame(400.0, $from->extrasTotal());
        $this->assertSame(200.0, $to->extrasTotal());
        // Two bills of ETB 3,600 plus the one ETB 600 charge, however it is shared.
        $this->assertSame(7800.0, $from->balanceDue() + $to->balanceDue());
    }

    public function test_a_charge_cannot_be_moved_to_a_guest_who_has_left(): void
    {
        $from = $this->checkedIn();
        $gone = $this->checkedIn(['status' => BookingStatus::CheckedOut]);
        $charge = $this->charge($from, 100);

        $this->expectException(BookingException::class);

        $this->folio->transferCharge($charge, $gone, 100, 'Wrong guest', $this->user(UserRole::Receptionist));
    }

    // ---- Business rule 4: extras must be paid at check-out -------------------

    public function test_check_out_includes_the_extra_charges_in_the_unpaid_balance(): void
    {
        $booking = $this->checkedIn([
            'check_out' => today()->toDateString(),
            'total_amount' => 1800,
            'rate_breakdown' => [today()->subDay()->toDateString() => 1800],
            'deposit_amount' => 1800,
        ]);
        $this->charge($booking, 200);

        $receptionist = $this->user(UserRole::Receptionist);

        try {
            app(StayService::class)->checkOut($booking, ['amount' => 0], null, $receptionist);
            $this->fail('The guest still owes the extra charge.');
        } catch (BookingException $e) {
            $this->assertStringContainsString('unpaid', $e->getMessage());
        }

        app(StayService::class)->checkOut($booking, ['amount' => 200, 'method' => 'cash'], null, $receptionist);

        $this->assertSame(BookingStatus::CheckedOut, $booking->fresh()->status);
        $this->assertSame(0.0, $booking->fresh()->balanceDue());
    }

    public function test_the_bill_is_closed_after_check_out(): void
    {
        $booking = $this->checkedIn(['status' => BookingStatus::CheckedOut]);

        $this->expectException(BookingException::class);

        $this->charge($booking);
    }

    // ---- Screens, permissions and printing -----------------------------------

    public function test_the_receptionist_can_open_the_bill_and_add_a_charge(): void
    {
        $booking = $this->checkedIn();
        $user = $this->user(UserRole::Receptionist);

        $this->actingAs($user)->get(route('staff.folio.show', $booking))->assertOk()->assertSee($booking->reference);

        $this->actingAs($user)->post(route('staff.folio.charges.store', $booking), [
            'category' => 'laundry',
            'description' => 'Two shirts',
            'quantity' => 2,
            'unit_price' => 75,
        ])->assertRedirect(route('staff.folio.show', $booking));

        $this->assertDatabaseHas('folio_charges', [
            'booking_id' => $booking->id,
            'description' => 'Two shirts',
            'amount' => 150,
            'created_by' => $user->id,
        ]);
    }

    public function test_the_cashier_can_add_a_charge_but_not_give_discounts_or_void(): void
    {
        $booking = $this->checkedIn();
        $charge = $this->charge($booking);
        $cashier = $this->user(UserRole::Cashier);

        $this->actingAs($cashier)->get(route('staff.folios.index'))->assertOk();

        $this->actingAs($cashier)->post(route('staff.folio.charges.store', $booking), [
            'category' => 'food',
            'description' => 'Lunch',
            'quantity' => 1,
            'unit_price' => 120,
        ])->assertRedirect();

        $this->assertDatabaseHas('folio_charges', ['description' => 'Lunch']);

        $this->actingAs($cashier)->post(route('staff.folio.discounts.store', $booking), [
            'type' => 'fixed', 'value' => 50, 'reason' => 'Test',
        ])->assertForbidden();

        $this->actingAs($cashier)->patch(route('staff.folio.charges.void', $charge), ['reason' => 'Test'])->assertForbidden();
        $this->assertNull($charge->fresh()->voided_at);
    }

    public function test_a_guest_account_cannot_see_a_bill(): void
    {
        $booking = $this->checkedIn();

        $this->actingAs($this->user(UserRole::Guest))->get(route('staff.folio.show', $booking))->assertForbidden();
    }

    public function test_the_receipt_and_the_invoice_print_without_voided_lines(): void
    {
        $booking = $this->checkedIn();
        $user = $this->user(UserRole::Receptionist);

        $kept = $this->folio->addCharge($booking, ['category' => 'food', 'description' => 'Kept soup', 'quantity' => 1, 'unit_price' => 100], $user);
        $voided = $this->folio->addCharge($booking, ['category' => 'food', 'description' => 'Voided steak', 'quantity' => 1, 'unit_price' => 100], $user);
        $this->folio->voidCharge($voided, 'Wrong table', $user);

        foreach (['staff.folio.receipt', 'staff.folio.invoice'] as $route) {
            $this->actingAs($user)->get(route($route, $booking))
                ->assertOk()
                ->assertSee($kept->description)
                ->assertDontSee('Voided steak');
        }
    }
}
