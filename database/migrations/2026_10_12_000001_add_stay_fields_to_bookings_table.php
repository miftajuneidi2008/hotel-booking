<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // FR-11: ID or passport saved at check-in.
            $table->string('id_type', 20)->nullable()->after('guest_email');
            $table->string('id_number', 50)->nullable()->after('id_type');
            $table->string('nationality', 60)->nullable()->after('id_number');
            $table->foreignId('checked_in_by')->nullable()->after('checked_in_at')->constrained('users')->nullOnDelete();

            // FR-13: payment taken at check-out. Moves to the payments table with section 5.5.
            $table->decimal('settlement_amount', 10, 2)->default(0)->after('deposit_reference');
            $table->string('settlement_method', 20)->nullable()->after('settlement_amount');
            $table->string('settlement_reference', 100)->nullable()->after('settlement_method');

            $table->timestamp('checked_out_at')->nullable()->after('checked_in_by');
            $table->foreignId('checked_out_by')->nullable()->after('checked_out_at')->constrained('users')->nullOnDelete();

            // Business rule 4: leaving with an unpaid balance needs a manager and a reason.
            $table->decimal('checkout_unpaid_amount', 10, 2)->default(0)->after('checked_out_by');
            $table->string('checkout_override_reason', 255)->nullable()->after('checkout_unpaid_amount');
            $table->foreignId('checkout_approved_by')->nullable()->after('checkout_override_reason')->constrained('users')->nullOnDelete();
        });

        // FR-12: history of room changes during a stay.
        Schema::create('room_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_room_id')->constrained('rooms')->restrictOnDelete();
            $table->foreignId('to_room_id')->constrained('rooms')->restrictOnDelete();
            $table->date('moved_on');
            $table->string('reason', 255);
            $table->foreignId('moved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_moves');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checkout_approved_by');
            $table->dropConstrainedForeignId('checked_out_by');
            $table->dropConstrainedForeignId('checked_in_by');
            $table->dropColumn([
                'id_type', 'id_number', 'nationality',
                'settlement_amount', 'settlement_method', 'settlement_reference',
                'checked_out_at', 'checkout_unpaid_amount', 'checkout_override_reason',
            ]);
        });
    }
};
