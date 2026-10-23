<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 12)->unique();

            $table->foreignId('room_id')->constrained()->restrictOnDelete();

            // The website account that booked online (null for desk bookings of guests without an account).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // The staff member who created or cancelled the booking at the desk.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('source', 20)->index();
            $table->string('status', 20)->index();

            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedTinyInteger('guests');

            $table->string('guest_name', 150);
            $table->string('guest_phone', 30);
            $table->string('guest_email')->nullable();
            $table->text('notes')->nullable();

            // Price is frozen at booking time so later rate changes never change old bookings.
            $table->decimal('total_amount', 10, 2);
            $table->json('rate_breakdown');

            // Deposit (FR-08). Moves to a payments table when the payments module is built.
            $table->decimal('deposit_amount', 10, 2)->default(0);
            $table->string('deposit_method', 20)->nullable();
            $table->string('deposit_reference', 100)->nullable();

            $table->timestamp('checked_in_at')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 255)->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['room_id', 'check_in', 'check_out']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
