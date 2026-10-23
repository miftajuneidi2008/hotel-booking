<?php

namespace Tests\Feature\Restaurant;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Enums\WalkInStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\FolioCharge;
use App\Models\MenuItem;
use App\Models\User;
use App\Models\WalkInBill;
use App\Services\RestaurantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Restaurant and extras, BRD section 5.7.
 */
class RestaurantTest extends TestCase
{
    use RefreshDatabase;

    private RestaurantService $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->restaurant = app(RestaurantService::class);
    }

    private function user(UserRole $role = UserRole::Cashier): User
    {
        return User::factory()->role($role)->create();
    }

    /** A guest in the house: 2 nights at ETB 1,800 = ETB 3,600. */
    private function checkedIn(): Booking
    {
        return Booking::factory()->create([
            'status' => BookingStatus::CheckedIn,
            'check_in' => today()->subDay()->toDateString(),
            'check_out' => today()->addDay()->toDateString(),
            'total_amount' => 3600,
            'rate_breakdown' => [
                today()->subDay()->toDateString() => 1800,
                today()->toDateString() => 1800,
            ],
            'checked_in_at' => now(),
        ]);
    }

    /** @return list<array{menu_item_id: int, quantity: int}> */
    private function lines(MenuItem $item, int $quantity): array
    {
        return [['menu_item_id' => $item->id, 'quantity' => $quantity]];
    }

    // ---- FR-27 menu ----------------------------------------------------------

    public function test_a_manager_adds_and_changes_menu_items(): void
    {
        $manager = $this->user(UserRole::Manager);

        $this->actingAs($manager)->post(route('staff.menu.store'), [
            'name' => 'Tibs',
            'category' => 'food',
            'price' => 320,
            'is_active' => 1,
        ])->assertRedirect(route('staff.menu.index'));

        $item = MenuItem::query()->where('name', 'Tibs')->firstOrFail();
        $this->assertSame('320.00', $item->price);

        $this->actingAs($manager)->put(route('staff.menu.update', $item), [
            'name' => 'Tibs',
            'category' => 'food',
            'price' => 350,
            'is_active' => 0,
        ])->assertRedirect(route('staff.menu.index'));

        $this->assertSame('350.00', $item->fresh()->price);
        $this->assertFalse($item->fresh()->is_active);
    }

    public function test_menu_names_must_be_unique_but_a_removed_name_can_come_back(): void
    {
        $manager = $this->user(UserRole::Owner);
        $item = MenuItem::factory()->create(['name' => 'Soup']);

        $this->actingAs($manager)->post(route('staff.menu.store'), ['name' => 'Soup', 'category' => 'food', 'price' => 50])
            ->assertSessionHasErrors('name');

        $this->actingAs($manager)->delete(route('staff.menu.destroy', $item))->assertRedirect();
        $this->assertSoftDeleted($item);

        $this->actingAs($manager)->post(route('staff.menu.store'), ['name' => 'Soup', 'category' => 'food', 'price' => 50])
            ->assertSessionDoesntHaveErrors();
    }

    public function test_only_owner_and_manager_change_the_menu(): void
    {
        $item = MenuItem::factory()->create();

        foreach ([UserRole::Receptionist, UserRole::Cashier, UserRole::Housekeeping, UserRole::Guest] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->post(route('staff.menu.store'), ['name' => 'X', 'category' => 'food', 'price' => 10])->assertForbidden();
            $this->actingAs($user)->put(route('staff.menu.update', $item), ['name' => 'X', 'category' => 'food', 'price' => 10])->assertForbidden();
            $this->actingAs($user)->delete(route('staff.menu.destroy', $item))->assertForbidden();
        }

        $this->assertNotSoftDeleted($item);
    }

    public function test_the_cashier_can_see_the_menu_but_a_guest_account_cannot(): void
    {
        MenuItem::factory()->create(['name' => 'Fresh juice']);

        $this->actingAs($this->user(UserRole::Cashier))->get(route('staff.menu.index'))->assertOk()->assertSee('Fresh juice');
        $this->actingAs($this->user(UserRole::Guest))->get(route('staff.menu.index'))->assertForbidden();
    }

    // ---- FR-28 room bill -----------------------------------------------------

    public function test_menu_items_are_charged_to_the_room_bill_at_the_menu_price(): void
    {
        $booking = $this->checkedIn();
        $coffee = MenuItem::factory()->create(['name' => 'Coffee', 'price' => 40]);
        $lunch = MenuItem::factory()->create(['name' => 'Lunch', 'price' => 300]);

        $charges = $this->restaurant->orderToRoom($booking, [
            ['menu_item_id' => $coffee->id, 'quantity' => 2],
            ['menu_item_id' => $lunch->id, 'quantity' => 1],
        ], $this->user());

        $this->assertCount(2, $charges);
        $this->assertSame(380.0, $booking->extrasTotal());
        $this->assertSame(3980.0, $booking->balanceDue());
        $this->assertDatabaseHas('folio_charges', ['booking_id' => $booking->id, 'description' => 'Coffee', 'quantity' => 2, 'amount' => 80]);
    }

    public function test_a_later_price_change_does_not_change_the_bill(): void
    {
        $booking = $this->checkedIn();
        $item = MenuItem::factory()->create(['name' => 'Dinner', 'price' => 350]);

        $this->restaurant->orderToRoom($booking, $this->lines($item, 1), $this->user());
        $item->update(['price' => 500]);

        $this->assertSame(3950.0, $booking->balanceDue());
        $this->assertSame('350.00', FolioCharge::query()->firstOrFail()->unit_price);
    }

    public function test_the_same_item_twice_is_added_up(): void
    {
        $booking = $this->checkedIn();
        $item = MenuItem::factory()->create(['price' => 50]);

        $charges = $this->restaurant->orderToRoom($booking, [
            ['menu_item_id' => $item->id, 'quantity' => 1],
            ['menu_item_id' => $item->id, 'quantity' => 2],
        ], $this->user());

        $this->assertCount(1, $charges);
        $this->assertSame(150.0, $booking->extrasTotal());
    }

    public function test_nothing_is_added_when_the_guest_is_not_checked_in(): void
    {
        $booking = Booking::factory()->create();
        $item = MenuItem::factory()->create();

        try {
            $this->restaurant->orderToRoom($booking, $this->lines($item, 1), $this->user());
            $this->fail('Charges need a guest who is checked in.');
        } catch (BookingException) {
            $this->assertSame(0, FolioCharge::query()->count());
        }
    }

    public function test_a_hidden_item_cannot_be_ordered_and_nothing_is_added(): void
    {
        $booking = $this->checkedIn();
        $visible = MenuItem::factory()->create();
        $hidden = MenuItem::factory()->hidden()->create();

        try {
            $this->restaurant->orderToRoom($booking, [
                ['menu_item_id' => $visible->id, 'quantity' => 1],
                ['menu_item_id' => $hidden->id, 'quantity' => 1],
            ], $this->user());
            $this->fail('A hidden item is not for sale.');
        } catch (BookingException) {
            $this->assertSame(0, FolioCharge::query()->count());
        }
    }

    public function test_an_empty_order_is_refused(): void
    {
        $this->expectException(BookingException::class);

        $this->restaurant->orderToRoom($this->checkedIn(), [], $this->user());
    }

    // ---- FR-28 walk-in bill --------------------------------------------------

    public function test_a_walk_in_bill_collects_items_and_a_total(): void
    {
        $user = $this->user();
        $beer = MenuItem::factory()->create(['name' => 'Beer', 'price' => 90]);
        $tea = MenuItem::factory()->create(['name' => 'Tea', 'price' => 30]);

        $bill = $this->restaurant->addToWalkIn(null, 'Table 4', $this->lines($beer, 2), $user);
        $this->assertSame('Table 4', $bill->customer_name);
        $this->assertSame(WalkInStatus::Open, $bill->status);
        $this->assertStringStartsWith('WI-', $bill->number);

        $bill = $this->restaurant->addToWalkIn($bill, null, $this->lines($tea, 1), $user);

        $this->assertSame('210.00', $bill->fresh()->total);
        $this->assertSame(2, $bill->lines()->count());
    }

    public function test_removing_a_line_updates_the_total_and_only_hides_the_line(): void
    {
        $user = $this->user();
        $item = MenuItem::factory()->create(['price' => 100]);
        $bill = $this->restaurant->addToWalkIn(null, null, $this->lines($item, 2), $user);

        $this->restaurant->removeLine($bill->lines()->firstOrFail());

        $this->assertSame('0.00', $bill->fresh()->total);
        $this->assertDatabaseCount('walk_in_bill_lines', 1);
        $this->assertSame(0, $bill->lines()->count());
    }

    public function test_a_cash_payment_closes_the_bill(): void
    {
        $user = $this->user();
        $bill = $this->restaurant->addToWalkIn(null, null, $this->lines(MenuItem::factory()->create(['price' => 100]), 1), $user);

        $paid = $this->restaurant->pay($bill, PaymentMethod::Cash, null, $user);

        $this->assertSame(WalkInStatus::Paid, $paid->status);
        $this->assertSame($user->id, $paid->paid_by);
        $this->assertNotNull($paid->paid_at);
    }

    public function test_a_card_or_telebirr_payment_needs_a_reference(): void
    {
        $user = $this->user();
        $bill = $this->restaurant->addToWalkIn(null, null, $this->lines(MenuItem::factory()->create(['price' => 100]), 1), $user);

        try {
            $this->restaurant->pay($bill, PaymentMethod::Telebirr, ' ', $user);
            $this->fail('A reference number is required.');
        } catch (BookingException) {
            $this->assertTrue($bill->fresh()->isOpen());
        }

        $paid = $this->restaurant->pay($bill, PaymentMethod::Telebirr, 'TB123', $user);
        $this->assertSame('TB123', $paid->payment_reference);
    }

    public function test_an_empty_bill_cannot_be_paid(): void
    {
        $bill = WalkInBill::factory()->create();

        $this->expectException(BookingException::class);

        $this->restaurant->pay($bill, PaymentMethod::Cash, null, $this->user());
    }

    public function test_a_closed_bill_cannot_get_more_items(): void
    {
        $user = $this->user();
        $item = MenuItem::factory()->create(['price' => 100]);
        $bill = $this->restaurant->addToWalkIn(null, null, $this->lines($item, 1), $user);
        $this->restaurant->pay($bill, PaymentMethod::Cash, null, $user);

        $this->expectException(BookingException::class);

        $this->restaurant->addToWalkIn($bill, null, $this->lines($item, 1), $user);
    }

    public function test_a_walk_in_bill_can_be_moved_to_a_room_bill(): void
    {
        $user = $this->user();
        $booking = $this->checkedIn();
        $item = MenuItem::factory()->create(['name' => 'Dinner', 'price' => 350]);
        $bill = $this->restaurant->addToWalkIn(null, null, $this->lines($item, 2), $user);

        $moved = $this->restaurant->moveToRoom($bill, $booking, $user);

        $this->assertSame(WalkInStatus::Transferred, $moved->status);
        $this->assertSame($booking->id, $moved->booking_id);
        $this->assertSame(700.0, $booking->extrasTotal());
        $this->assertSame(4300.0, $booking->balanceDue());
    }

    public function test_a_failed_move_leaves_the_walk_in_bill_open(): void
    {
        $user = $this->user();
        $notInHouse = Booking::factory()->create();
        $bill = $this->restaurant->addToWalkIn(null, null, $this->lines(MenuItem::factory()->create(['price' => 100]), 1), $user);

        try {
            $this->restaurant->moveToRoom($bill, $notInHouse, $user);
            $this->fail('The guest is not checked in.');
        } catch (BookingException) {
            $this->assertTrue($bill->fresh()->isOpen());
            $this->assertSame(0, FolioCharge::query()->count());
        }
    }

    public function test_an_open_bill_is_voided_with_a_reason_but_a_paid_bill_is_not(): void
    {
        $user = $this->user();
        $item = MenuItem::factory()->create(['price' => 100]);
        $open = $this->restaurant->addToWalkIn(null, null, $this->lines($item, 1), $user);

        try {
            $this->restaurant->void($open, ' ', $user);
            $this->fail('A reason is required.');
        } catch (BookingException) {
            $this->assertTrue($open->fresh()->isOpen());
        }

        $voided = $this->restaurant->void($open, 'Customer left', $user);
        $this->assertSame(WalkInStatus::Voided, $voided->status);
        $this->assertSame('Customer left', $voided->void_reason);

        $paid = $this->restaurant->addToWalkIn(null, null, $this->lines($item, 1), $user);
        $this->restaurant->pay($paid, PaymentMethod::Cash, null, $user);

        $this->expectException(BookingException::class);
        $this->restaurant->void($paid, 'Mistake', $user);
    }

    // ---- Screens and permissions ---------------------------------------------

    public function test_the_cashier_orders_to_a_room_bill_from_the_screen(): void
    {
        $cashier = $this->user();
        $booking = $this->checkedIn();
        $item = MenuItem::factory()->create(['name' => 'Breakfast', 'price' => 250]);

        $this->actingAs($cashier)->get(route('staff.orders.create'))->assertOk()->assertSee('Breakfast');

        $this->actingAs($cashier)->post(route('staff.orders.store'), [
            'target' => 'room',
            'booking_id' => $booking->id,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 2]],
        ])->assertRedirect(route('staff.folio.show', $booking));

        $this->assertSame(500.0, $booking->extrasTotal());
        $this->assertDatabaseHas('folio_charges', ['booking_id' => $booking->id, 'created_by' => $cashier->id]);
    }

    public function test_the_cashier_runs_a_walk_in_bill_from_order_to_receipt(): void
    {
        $cashier = $this->user();
        $item = MenuItem::factory()->create(['name' => 'Macchiato', 'price' => 60]);

        $this->actingAs($cashier)->post(route('staff.orders.store'), [
            'target' => 'walk_in',
            'customer_name' => 'Table 2',
            'items' => [['menu_item_id' => $item->id, 'quantity' => 3]],
        ])->assertRedirect();

        $bill = WalkInBill::query()->firstOrFail();
        $this->assertSame('180.00', $bill->total);

        $this->actingAs($cashier)->get(route('staff.walk-in-bills.index'))->assertOk()->assertSee('Table 2');
        $this->actingAs($cashier)->get(route('staff.walk-in-bills.show', $bill))->assertOk()->assertSee('Macchiato');

        $this->actingAs($cashier)->post(route('staff.walk-in-bills.pay', $bill), ['method' => 'cash'])->assertSessionHas('status');

        $this->actingAs($cashier)->get(route('staff.walk-in-bills.receipt', $bill))->assertOk()->assertSee('Macchiato')->assertSee($bill->number);
    }

    public function test_an_order_without_items_or_a_guest_is_refused(): void
    {
        $cashier = $this->user();

        $this->actingAs($cashier)->post(route('staff.orders.store'), ['target' => 'room', 'items' => []])
            ->assertSessionHasErrors(['items', 'booking_id']);
    }

    public function test_guest_accounts_and_housekeeping_cannot_sell(): void
    {
        $item = MenuItem::factory()->create();
        $bill = WalkInBill::factory()->create();

        foreach ([UserRole::Guest, UserRole::Housekeeping] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('staff.orders.create'))->assertForbidden();
            $this->actingAs($user)->get(route('staff.walk-in-bills.index'))->assertForbidden();
            $this->actingAs($user)->get(route('staff.walk-in-bills.show', $bill))->assertForbidden();
            $this->actingAs($user)->post(route('staff.orders.store'), [
                'target' => 'walk_in',
                'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
            ])->assertForbidden();
        }

        $this->assertSame(1, WalkInBill::query()->count());
    }
}
