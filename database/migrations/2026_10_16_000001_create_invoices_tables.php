<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FR-33 to FR-35: an invoice is a frozen copy of the guest bill at the moment it is issued
        // (business rule 2). Rows are never edited or deleted: a wrong invoice is voided with a reason,
        // and its number is never used again.
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sequence')->unique();
            $table->string('number', 40)->unique();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->string('status', 10)->index();            // issued | voided

            $table->timestamp('issued_at')->index();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();

            // Seller, copied from config/tax.php when issued.
            $table->string('seller_name', 150);
            $table->string('seller_tin', 30);
            $table->string('seller_address', 255)->nullable();
            $table->string('seller_phone', 40)->nullable();
            $table->string('seller_email', 150)->nullable();

            // Customer. The TIN is optional.
            $table->string('customer_name', 150);
            $table->string('customer_tin', 30)->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->string('customer_email', 150)->nullable();

            // The stay, as text and dates, so it stays right if the booking changes later.
            $table->string('booking_reference', 12);
            $table->string('room_label', 120);
            $table->date('stay_check_in');
            $table->date('stay_check_out');
            $table->unsignedSmallInteger('nights');
            $table->unsignedTinyInteger('guests');

            $table->string('currency', 3)->default('ETB');

            // Bill: room nights + extras = gross; minus discounts = total (what the guest pays, tax included).
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);

            // The tax inside the total. subtotal + service charge + VAT = total.
            $table->string('tax_mode', 12)->default('inclusive');
            $table->decimal('subtotal_amount', 12, 2);
            $table->decimal('service_charge_rate', 5, 2)->default(0);
            $table->decimal('service_charge_amount', 12, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->boolean('vat_includes_service_charge')->default(true);

            // For information: what the guest had paid when the invoice was issued.
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('balance_due', 12, 2)->default(0);

            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();

            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->string('type', 10);                       // room | charge | discount
            $table->string('category', 20)->nullable();
            $table->string('description', 255);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('amount', 12, 2);                 // negative for a discount
            $table->timestamps();

            $table->unique(['invoice_id', 'line_no']);
        });

        // FR-34: one row that is locked while a number is taken, so two people issuing at the same
        // moment can never get the same number.
        Schema::create('invoice_counters', function (Blueprint $table) {
            $table->string('name', 30)->primary();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        DB::table('invoice_counters')->insert(['name' => 'invoice', 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_counters');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
