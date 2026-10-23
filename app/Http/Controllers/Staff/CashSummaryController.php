<?php

namespace App\Http\Controllers\Staff;

use App\Enums\PaymentMethod;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\CashSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Daily cash summary per user, for closing the day (FR-23).
 * A receptionist or restaurant cashier sees their own cash; the owner and managers can see everybody's.
 * Everyone can count their cash and close their own day; managers see the differences.
 */
class CashSummaryController extends Controller
{
    public function __construct(private readonly CashSummaryService $summary) {}

    public function index(Request $request): View
    {
        return view('staff.cash-summary.index', $this->data($request) + [
            'staff' => Gate::allows('viewAllSummaries', Payment::class) ? $this->summary->staff() : collect(),
        ]);
    }

    /** A clean page for printing the closing sheet. */
    public function print(Request $request): View
    {
        return view('staff.cash-summary.print', $this->data($request) + [
            'property' => config('hotel.name', config('app.name')),
            'printedBy' => $request->user()->name,
        ]);
    }

    /** Count the cash and close your own day. */
    public function close(Request $request): RedirectResponse
    {
        Gate::authorize('closeDay', Payment::class);

        $input = $request->validate([
            'date' => ['required', 'date'],
            'counted_cash' => ['required', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $date = date('Y-m-d', strtotime((string) $input['date']));

        try {
            $this->summary->close($request->user(), $date, (float) $input['counted_cash'], $input['note'] ?? null);
        } catch (BookingException $e) {
            return redirect()->route('staff.cash-summary', ['date' => $date])->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('staff.cash-summary', ['date' => $date])->with('status', __('The day is closed.'));
    }

    /** @return array<string, mixed> */
    private function data(Request $request): array
    {
        Gate::authorize('viewSummary', Payment::class);

        $input = $request->validate([
            'date' => ['nullable', 'date'],
            'user' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        $me = $request->user();
        $seesAll = Gate::allows('viewAllSummaries', Payment::class);
        $date = isset($input['date']) ? date('Y-m-d', strtotime((string) $input['date'])) : $this->summary->today();

        // Anyone below manager only ever sees their own cash.
        $userId = $seesAll ? ($input['user'] ?? null) : $me->id;
        $userId = $userId ? (int) $userId : null;

        $summary = $this->summary->day($date, $userId);

        // Closing is for your own cash: when you look at yourself (or at nobody in particular, as a non-manager).
        $viewingMe = ! $seesAll || $userId === $me->id;
        $myClosing = $viewingMe ? $this->summary->closingFor($me, $date) : null;

        // Money recorded after the person closed their day: the manager should know.
        $lateEntries = 0;

        if ($myClosing !== null) {
            $lateEntries = $summary['payments']->filter(fn (Payment $p): bool => $p->paid_at->gt($myClosing->closed_at))->count()
                + $summary['walk_ins']->filter(fn ($bill): bool => $bill->paid_at->gt($myClosing->closed_at))->count();
        }

        return [
            'summary' => $summary,
            'date' => $date,
            'userId' => $userId,
            'seesAll' => $seesAll,
            'timezone' => $this->summary->timezone(),
            'methods' => PaymentMethod::cases(),
            'closings' => $this->summary->closings($date, $userId),
            'myClosing' => $myClosing,
            'lateEntries' => $lateEntries,
            'canClose' => $viewingMe && $myClosing === null && $date <= $this->summary->today(),
        ];
    }
}
