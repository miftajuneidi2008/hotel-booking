<?php

namespace App\Http\Controllers\Staff;

use App\Enums\IssuePriority;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReportIssueRequest;
use App\Http\Requests\ResolveIssueRequest;
use App\Models\MaintenanceIssue;
use App\Models\Room;
use App\Services\HousekeepingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Maintenance issue notes for rooms (FR-26). */
class MaintenanceIssueController extends Controller
{
    public function __construct(private readonly HousekeepingService $housekeeping) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', MaintenanceIssue::class);

        $showResolved = $request->query('show') === 'resolved';

        $issues = MaintenanceIssue::query()
            ->with(['room', 'reporter', 'resolver'])
            ->when($showResolved, fn ($q) => $q->whereNotNull('resolved_at')->latest('resolved_at'), fn ($q) => $q->open()->latest())
            ->limit(100)
            ->get();

        if (! $showResolved) {
            // Urgent first, then oldest first.
            $issues = $issues
                ->sortBy(fn (MaintenanceIssue $i): array => [$i->priority === IssuePriority::Urgent ? 0 : 1, $i->created_at->getTimestamp()])
                ->values();
        }

        return view('staff.housekeeping.issues', [
            'issues' => $issues,
            'showResolved' => $showResolved,
            'openCount' => MaintenanceIssue::query()->open()->count(),
        ]);
    }

    public function store(ReportIssueRequest $request, Room $room): RedirectResponse
    {
        $blocks = $request->boolean('blocks_room');

        try {
            $this->housekeeping->reportIssue($room, $request->validated(), $blocks, $request->user());
        } catch (BookingException $e) {
            return redirect()->route('housekeeping.rooms.show', $room)->withInput()->with('error', $e->getMessage());
        }

        $message = "Issue saved for room {$room->number}.";

        if ($blocks) {
            $upcoming = $this->housekeeping->upcomingBookings($room);
            $message = "Room {$room->number} is out of service.".($upcoming > 0
                ? " {$upcoming} upcoming ".str('booking')->plural($upcoming).' for this room will need another room.'
                : '');
        }

        return redirect()->route('housekeeping.rooms.show', $room)->with('status', $message);
    }

    public function resolve(ResolveIssueRequest $request, MaintenanceIssue $issue): RedirectResponse
    {
        try {
            $this->housekeeping->resolveIssue($issue, $request->validated('note'), $request->user());
        } catch (BookingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'The issue is marked fixed.');
    }
}
