<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FR-16: extra charges on a guest bill. Room nights are not stored here: they come from
        // bookings.rate_breakdown, so changing or moving a stay keeps the bill correct.
        Schema::create('folio_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            // Set when this line was made by splitting another charge (FR-18).
            $table->foreignId('split_from_id')->nullable()->constrained('folio_charges')->restrictOnDelete();

            $table->string('category', 20)->index();
            $table->string('description', 150);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('amount', 10, 2);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // A charge is never deleted: it is voided with a reason (business rules 3 and 7).
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->boolean('void_was_split')->default(false);
            $table->foreignId('void_approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['booking_id', 'voided_at']);
        });

        // FR-17: discounts with a reason and an approval rule.
        Schema::create('folio_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();

            $table->string('type', 10);
            // The percent or the ETB amount the staff member entered.
            $table->decimal('value', 10, 2);
            // The discount in ETB, fixed when it was applied.
            $table->decimal('amount', 10, 2);
            $table->string('reason', 255);

            $table->boolean('needs_approval')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->foreignId('void_approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['booking_id', 'voided_at']);
        });

        // FR-18: history of charges moved to another guest bill.
        Schema::create('folio_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charge_id')->constrained('folio_charges')->restrictOnDelete();
            $table->foreignId('from_booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('to_booking_id')->constrained('bookings')->restrictOnDelete();
            $table->string('reason', 255);
            $table->foreignId('moved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folio_transfers');
        Schema::dropIfExists('folio_discounts');
        Schema::dropIfExists('folio_charges');
    }
};
