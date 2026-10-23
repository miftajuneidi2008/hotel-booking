<?php

namespace App\Models;

use App\Enums\IssuePriority;
use Database\Factories\MaintenanceIssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A maintenance note for a room (broken tap, light bulb, ...). Resolved, never deleted. */
#[Fillable([
    'room_id', 'description', 'priority', 'blocks_room', 'reported_by',
    'resolved_at', 'resolved_by', 'resolution_note',
])]
class MaintenanceIssue extends Model
{
    /** @use HasFactory<MaintenanceIssueFactory> */
    use HasFactory;

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'priority' => IssuePriority::class,
            'blocks_room' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('resolved_at');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
