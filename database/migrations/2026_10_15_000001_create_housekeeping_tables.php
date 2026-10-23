<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FR-24 / FR-25: who changed the status of a room, and when. Rows are never edited or deleted.
        Schema::create('room_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->string('note', 255)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['room_id', 'created_at']);
        });

        // FR-26: maintenance issue notes for a room. An issue is resolved, never deleted.
        Schema::create('maintenance_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->string('description', 500);
            $table->string('priority', 10)->default('normal');
            // True when the room was taken out of service because of this issue.
            $table->boolean('blocks_room')->default(false);

            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('resolved_at')->nullable()->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution_note', 255)->nullable();

            $table->timestamps();

            $table->index(['room_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_issues');
        Schema::dropIfExists('room_status_changes');
    }
};
