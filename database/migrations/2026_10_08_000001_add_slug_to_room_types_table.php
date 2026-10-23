<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->string('slug', 120)->nullable()->after('name');
        });

        $used = [];

        foreach (DB::table('room_types')->orderBy('id')->get(['id', 'name']) as $roomType) {
            $base = Str::slug($roomType->name) ?: 'room-type';
            $slug = $base;

            for ($suffix = 2; in_array($slug, $used, true); $suffix++) {
                $slug = "{$base}-{$suffix}";
            }

            $used[] = $slug;

            DB::table('room_types')->where('id', $roomType->id)->update(['slug' => $slug]);
        }

        Schema::table('room_types', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->dropUnique(['slug']);
        });

        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
