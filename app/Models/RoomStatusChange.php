<?php

namespace App\Models;

use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One change of a room's status made through housekeeping. Never edited or deleted. */
#[Fillable(['room_id', 'from_status', 'to_status', 'note', 'changed_by'])]
class RoomStatusChange extends Model
{
    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'from_status' => RoomStatus::class,
            'to_status' => RoomStatus::class,
        ];
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
