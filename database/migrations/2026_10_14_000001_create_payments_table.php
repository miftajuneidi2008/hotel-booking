<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FR-20 to FR-22: every payment and refund on a guest bill. Rows are never edited or deleted:
        // a wrong entry is voided with a reason (business rules 3, 6 and 7).
        // Deposits and check-out payments recorded earlier stay in the bookings table and still count.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 12)->unique();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();

            $table->string('kind', 10)->index();      // payment | refund
            $table->string('method', 20);            // cash | bank_transfer | telebirr | card
            $table->string('reference', 100)->nullable();

            // What was handed over, in its own currency, and what it is worth in ETB.
            $table->string('currency', 3)->default('ETB');
            $table->decimal('original_amount', 12, 2);
            $table->decimal('exchange_rate', 14, 4)->default(1);
            $table->decimal('amount', 10, 2);         // ETB, always positive; "kind" gives the direction

            $table->string('reason', 255)->nullable(); // required for refunds

            // O3: every payment is linked to a user and a bill.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->index();

            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();

            $table->timestamps();

            $table->index(['booking_id', 'voided_at']);
            $table->index(['created_by', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
