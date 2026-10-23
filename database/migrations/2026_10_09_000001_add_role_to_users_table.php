<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Everyone who registers on the website is a guest. Staff roles are set by the owner.
            $table->string('role', 20)->default('guest')->index()->after('password');
        });

        // The admin pages are now limited to owner/manager. Keep the existing first
        // account (the person who set the system up) as owner so nobody is locked out.
        if (($firstId = DB::table('users')->min('id')) !== null) {
            DB::table('users')->where('id', $firstId)->update(['role' => 'owner']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
