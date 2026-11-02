<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A walk-in bill keeps its table number while it is open. When the bill is paid, voided or moved to a
        // room bill, the table is free again, so no extra step is needed for walk-in customers.
        Schema::table('walk_in_bills', function (Blueprint $table) {
            $table->string('table_number', 20)->nullable()->index()->after('customer_name');
        });

        // A hotel guest who orders at a table holds that table until the staff free it (or the hold runs out,
        // see config/restaurant.php). Room orders have no "open bill" to tell when the guest has left.
        Schema::create('room_table_holds', function (Blueprint $table) {
            $table->id();
            $table->string('table_number', 20)->index();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable()->index();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_table_holds');

        Schema::table('walk_in_bills', function (Blueprint $table) {
            $table->dropIndex(['table_number']);
            $table->dropColumn('table_number');
        });
    }
};
