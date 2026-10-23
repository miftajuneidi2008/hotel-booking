<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A group or company booking: one customer, several rooms, one set of dates.
        // Each room is still a normal booking, so overlap checks, cancelling and
        // reports keep working exactly as before.
        Schema::create('booking_groups', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 12)->unique();
            $table->string('name', 150);
            $table->string('contact_name', 150)->nullable();
            $table->string('contact_phone', 30);
            $table->string('contact_email')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('booking_group_id')->nullable()->after('user_id')
                ->constrained('booking_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_group_id');
        });

        Schema::dropIfExists('booking_groups');
    }
};
