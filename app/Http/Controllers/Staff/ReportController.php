<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\CashSummaryService;
use App\Services\ReportService;
use App\Support\PropertyTime;
use App\Support\XlsxWriter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * Reports, section 5.8: daily report, occupancy and income for a period, and the report by user.
 * Owner and manager only (the routes use the manage-property gate). Each report has a screen, a print page
 * (the browser's "Save as PDF" gives the PDF) and an Excel download.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('staff.reports.daily');
    }

    public function show(Request $request, string $kind): View
    {
        [$report, $filters] = $this->build($request, $kind);

        return view('staff.reports.show', [
            'kind' => $kind,
            'report' => $report,
            'filters' => $filters,
            'staff' => $kind === 'users' ? app(CashSummaryService::class)->staff() : collect(),
            'timezone' => PropertyTime::timezone(),
        ]);
    }

    /** A clean page for printing or saving as PDF. */
    public function print(Request $request, string $kind): View
    {
        [$report] = $this->build($request, $kind);

        return view('staff.reports.print', [
            'report' => $report,
            'property' => config('hotel.name', config('app.name')),
            'printedBy' => $request->user()->name,
        ]);
    }

    public function xlsx(Request $request, string $kind): Response|RedirectResponse
    {
        [$report, $filters] = $this->build($request, $kind);

        try {
            $file = XlsxWriter::build($this->reports->sheets($report));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $name = match ($kind) {
            'daily' => 'daily-report-'.$filters['date'],
            'range' => 'occupancy-income-'.$filters['from'].'-to-'.$filters['to'],
            default => 'report-by-user-'.$filters['from'].'-to-'.$filters['to'],
        };

        return response($file, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$name.'.xlsx"',
        ]);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function build(Request $request, string $kind): array
    {
        Gate::authorize('manage-property');

        $input = $request->validate([
            'date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'user' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        $day = fn (?string $value): ?string => $value ? date('Y-m-d', strtotime($value)) : null;
        $today = PropertyTime::today();

        if ($kind === 'daily') {
            $date = $day($input['date'] ?? null) ?? $today;

            return [$this->reports->daily($date), ['date' => $date]];
        }

        $from = $day($input['from'] ?? null);
        $to = $day($input['to'] ?? null) ?? max($today, $from ?? $today);
        $from ??= CarbonImmutable::parse($to)->startOfMonth()->toDateString();

        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) >= ReportService::MAX_DAYS) {
            throw ValidationException::withMessages(['to' => 'Choose a period of '.ReportService::MAX_DAYS.' days or less.']);
        }

        if ($kind === 'range') {
            return [$this->reports->range($from, $to), ['from' => $from, 'to' => $to]];
        }

        $userId = ! empty($input['user']) ? (int) $input['user'] : null;

        return [$this->reports->byUser($from, $to, $userId), ['from' => $from, 'to' => $to, 'user' => $userId]];
    }
}
