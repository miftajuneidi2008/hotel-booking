<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FR-27: menu and service items with prices. Hidden with soft delete (business rule 7).
        // A price change never changes old bills (rule 2): charges copy the price when they are made.
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('category', 20)->index();
            $table->decimal('price', 10, 2);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        // FR-28: a bill for a customer who is not a hotel guest (restaurant or bar walk-in).
        Schema::create('walk_in_bills', function (Blueprint $table) {
            $table->id();
            $table->string('number', 12)->unique();
            $table->string('customer_name', 100)->nullable();   // a name or a table, for the staff
            $table->string('status', 12)->index();               // open | paid | transferred | voided
            $table->decimal('total', 10, 2)->default(0);

            $table->string('payment_method', 20)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();

            // Moved onto a hotel guest's room bill.
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('transferred_at')->nullable();
            $table->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('walk_in_bill_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('walk_in_bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 20);
            $table->string('description', 150);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('amount', 10, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walk_in_bill_lines');
        Schema::dropIfExists('walk_in_bills');
        Schema::dropIfExists('menu_items');
    }
};
