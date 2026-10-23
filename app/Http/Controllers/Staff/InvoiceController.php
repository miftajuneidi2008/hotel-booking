<?php

namespace App\Http\Controllers\Staff;

use App\Enums\InvoiceStatus;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\IssueInvoiceRequest;
use App\Http\Requests\VoidInvoiceRequest;
use App\Models\Booking;
use App\Models\Invoice;
use App\Services\InvoiceExporter;
use App\Services\InvoiceService;
use App\Support\PropertyTime;
use App\Support\XlsxWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Issue, list, print, void and export invoices (FR-33 to FR-35). */
class InvoiceController extends Controller
{
    /** Most invoices one export file may hold. */
    private const EXPORT_LIMIT = 5000;

    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly InvoiceExporter $exporter,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Invoice::class);

        $filters = $this->filters($request);
        $query = $this->query($filters);

        $issued = (clone $query)->where('status', InvoiceStatus::Issued->value);

        return view('staff.invoices.index', [
            'invoices' => (clone $query)->with('issuer')->latest('sequence')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'totals' => [
                'count' => (clone $issued)->count(),
                'subtotal' => round((float) (clone $issued)->sum('subtotal_amount'), 2),
                'service_charge' => round((float) (clone $issued)->sum('service_charge_amount'), 2),
                'vat' => round((float) (clone $issued)->sum('vat_amount'), 2),
                'total' => round((float) (clone $issued)->sum('total_amount'), 2),
            ],
            'statuses' => InvoiceStatus::cases(),
            'timezone' => PropertyTime::timezone(),
        ]);
    }

    /** The A4 invoice. */
    public function show(Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);

        return view('staff.folio.invoice', [
            'doc' => $this->invoices->document($invoice),
            'invoice' => $invoice,
            'bookingId' => $invoice->booking_id,
            'blocker' => null,
            'canIssue' => false,
            'printedBy' => request()->user()->name,
        ]);
    }

    /** Issue the invoice for a checked-out booking. */
    public function issue(IssueInvoiceRequest $request, Booking $booking): RedirectResponse
    {
        try {
            $invoice = $this->invoices->issue(
                $booking,
                $request->user(),
                $request->validated('customer_name'),
                $request->validated('customer_tin'),
            );
        } catch (BookingException $e) {
            return redirect()->route('staff.folio.invoice', $booking)->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('staff.invoices.show', $invoice)->with('status', 'Invoice '.$invoice->number.' issued.');
    }

    public function void(VoidInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        try {
            $this->invoices->void($invoice, $request->validated('reason'), $request->user());
        } catch (BookingException $e) {
            return redirect()->route('staff.invoices.show', $invoice)->with('error', $e->getMessage());
        }

        return redirect()->route('staff.invoices.show', $invoice)
            ->with('status', 'Invoice '.$invoice->number.' is voided. Its number will not be used again.');
    }

    /** FR-35: one invoice as structured data. */
    public function json(Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);

        return $this->download($this->exporter->toArray($invoice), 'invoice-'.$invoice->number.'.json');
    }

    /** The invoices of the current filter as an Excel file or one JSON file. */
    public function export(Request $request, string $format): Response|RedirectResponse
    {
        Gate::authorize('viewAny', Invoice::class);

        $invoices = $this->query($this->filters($request))
            ->with(['lines', 'issuer'])->orderBy('sequence')->limit(self::EXPORT_LIMIT)->get();

        if ($format === 'json') {
            return $this->download([
                'schema' => InvoiceExporter::SCHEMA,
                'generated_at' => now()->toIso8601String(),
                'invoices' => $invoices->map(fn (Invoice $i): array => $this->exporter->toArray($i))->all(),
            ], 'invoices-'.now()->format('Ymd-His').'.json');
        }

        $rows = [[
            ['v' => 'Invoice', 's' => 'bold'], ['v' => 'Date', 's' => 'bold'], ['v' => 'Status', 's' => 'bold'],
            ['v' => 'Booking', 's' => 'bold'], ['v' => 'Customer', 's' => 'bold'], ['v' => 'Customer TIN', 's' => 'bold'],
            ['v' => 'Business TIN', 's' => 'bold'], ['v' => 'Subtotal', 's' => 'bold'],
            ['v' => 'Service charge %', 's' => 'bold'], ['v' => 'Service charge', 's' => 'bold'],
            ['v' => 'VAT %', 's' => 'bold'], ['v' => 'VAT', 's' => 'bold'], ['v' => 'Total', 's' => 'bold'],
        ]];

        foreach ($invoices as $i) {
            $rows[] = [
                $i->number, PropertyTime::local($i->issued_at)->format('Y-m-d H:i'), $i->status->label(),
                $i->booking_reference, $i->customer_name, $i->customer_tin, $i->seller_tin,
                ['v' => (float) $i->subtotal_amount, 's' => 'money'],
                ['v' => (float) $i->service_charge_rate, 's' => 'decimal'], ['v' => (float) $i->service_charge_amount, 's' => 'money'],
                ['v' => (float) $i->vat_rate, 's' => 'decimal'], ['v' => (float) $i->vat_amount, 's' => 'money'],
                ['v' => (float) $i->total_amount, 's' => 'money'],
            ];
        }

        try {
            $file = XlsxWriter::build([[
                'name' => 'Invoices',
                'widths' => [14, 18, 10, 12, 28, 16, 16, 14, 16, 16, 8, 14, 14],
                'rows' => $rows,
            ]]);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return response($file, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="invoices-'.now()->format('Ymd-His').'.xlsx"',
        ]);
    }

    /** @return array{from: ?string, to: ?string, q: ?string, status: ?string} */
    private function filters(Request $request): array
    {
        $input = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(InvoiceStatus::class)],
        ]);

        $day = fn (?string $value): ?string => $value ? date('Y-m-d', strtotime($value)) : null;

        return [
            'from' => $day($input['from'] ?? null),
            'to' => $day($input['to'] ?? null),
            'q' => isset($input['q']) && trim($input['q']) !== '' ? trim($input['q']) : null,
            'status' => $input['status'] ?? null,
        ];
    }

    /**
     * @param  array{from: ?string, to: ?string, q: ?string, status: ?string}  $filters
     * @return Builder<Invoice>
     */
    private function query(array $filters): Builder
    {
        return Invoice::query()
            ->when($filters['from'] || $filters['to'], function (Builder $query) use ($filters): void {
                [$start, $end] = PropertyTime::range($filters['from'] ?? '2000-01-01', $filters['to'] ?? PropertyTime::today());
                $query->where('issued_at', '>=', $start)->where('issued_at', '<', $end);
            })
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['q'], function (Builder $query, string $q): void {
                $like = '%'.$q.'%';
                $query->where(fn (Builder $w) => $w->where('number', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('customer_tin', 'like', $like)
                    ->orWhere('booking_reference', 'like', $like));
            });
    }

    /** @param array<string, mixed> $data */
    private function download(array $data, string $filename): Response
    {
        return response(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
