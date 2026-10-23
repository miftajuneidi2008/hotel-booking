<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // The day reception and the guest were last told that the guest stayed past check-out.
            // Used so the same overdue guest is announced once per day, not on every run.
            $table->date('overdue_notified_on')->nullable()->after('checked_out_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('overdue_notified_on');
        });
    }
};
