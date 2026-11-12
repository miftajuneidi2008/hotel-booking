<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row for every attempt a guest makes to pay a booking through Chapa.
        // The money itself is recorded in the normal payments table only after Chapa confirms it
        // (payment_id points to that row), so the guest bill, reports and cash summary stay correct.
        Schema::create('chapa_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();

            $table->string('tx_ref', 64)->unique();          // our reference, sent to Chapa
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('ETB');

            $table->string('status', 12)->default('pending')->index(); // pending | success | failed | review
            $table->string('chapa_reference', 100)->nullable(); // Chapa's own reference
            $table->string('channel', 40)->nullable();          // telebirr, card, ... as reported by Chapa
            $table->text('checkout_url')->nullable();
            $table->string('note', 255)->nullable();            // why a payment needs review
            $table->json('response')->nullable();               // last answer from Chapa's verify call
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapa_transactions');
    }
};
