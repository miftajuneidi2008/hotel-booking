<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('walk_in_bills', function (Blueprint $table) {
            // Set when the bill is paid. The rate and amount are kept so a later rate change never changes an old bill.
            $table->boolean('vat_applied')->default(false)->after('total');
            $table->decimal('vat_rate', 5, 2)->default(0)->after('vat_applied');
            $table->decimal('vat_amount', 10, 2)->default(0)->after('vat_rate');
        });
    }

    public function down(): void
    {
        Schema::table('walk_in_bills', function (Blueprint $table) {
            $table->dropColumn(['vat_applied', 'vat_rate', 'vat_amount']);
        });
    }
};
