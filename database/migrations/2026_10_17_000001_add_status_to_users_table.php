<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // A deactivated user cannot log in but keeps all history. Existing users stay active.
            $table->boolean('is_active')->default(true)->index()->after('role');

            // "Deleted" users are hidden, never removed: bookings, payments and bills point to them
            // (created_by, voided_by, ...), and business rule 7 says reports must stay correct.
            $table->timestamp('deleted_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn(['is_active', 'deleted_at']);
        });
    }
};
