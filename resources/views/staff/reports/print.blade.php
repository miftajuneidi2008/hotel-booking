<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __($report['title']) }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { font-family: ui-sans-serif, system-ui, sans-serif; color: #111; margin: 0; padding: 2rem 1rem; background: #f3f4f6; }
        .sheet { max-width: 70rem; margin: 0 auto; background: #fff; padding: 2rem; border-radius: .5rem; }
        h1 { font-size: 1.4rem; margin: 0; }
        h2 { font-size: 1rem; margin: 1.75rem 0 .5rem; }
        p { margin: .25rem 0; }
        table { width: 100%; border-collapse: collapse; font-size: .8rem; }
        td, th { padding: .3rem .4rem; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        td.r, th.r { text-align: right; white-space: nowrap; }
        th { font-size: .72rem; color: #6b7280; font-weight: 600; }
        tfoot td { font-weight: 700; border-top: 1px solid #9ca3af; }
        .muted { color: #6b7280; font-size: .8rem; }
        .summary { width: auto; margin-top: 1rem; }
        .summary td { padding-right: 2rem; }
        .actions { max-width: 70rem; margin: 0 auto 1rem; display: flex; gap: 1rem; align-items: center; }
        button { font: inherit; padding: .5rem 1rem; border: 0; border-radius: .375rem; background: #111827; color: #fff; cursor: pointer; }
        a { color: #374151; }
        ul { padding-left: 1.1rem; }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { padding: 0; border-radius: 0; max-width: none; }
            .actions { display: none; }
            tr { break-inside: avoid; }
            @page { size: A4 landscape; margin: 12mm; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">{{ __('Print / save as PDF') }}</button>
        <a href="javascript:window.close()">{{ __('Close') }}</a>
    </div>

    <main class="sheet">
        <h1>{{ $property }}</h1>
        <p><strong>{{ __($report['title']) }}</strong></p>
        <p class="muted">{{ $report['subtitle'] }}</p>

        <table class="summary">
            <tbody>
                @foreach ($report['summary'] as [$label, $value])
                    <tr><td>{{ __($label) }}</td><td><strong>{{ $value }}</strong></td></tr>
                @endforeach
            </tbody>
        </table>

        @foreach ($report['tables'] as $table)
            <h2>{{ __($table['title']) }}</h2>

            @if (count($table['rows']) === 0)
                <p class="muted">{{ __($table['empty']) }}</p>
            @else
                <table>
                    <thead>
                        <tr>
                            @foreach ($table['columns'] as $column)
                                <th class="{{ $column['type'] === 'text' ? '' : 'r' }}">{{ __($column['label']) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($table['rows'] as $row)
                            <tr>
                                @foreach ($table['columns'] as $i => $column)
                                    <td class="{{ $column['type'] === 'text' ? '' : 'r' }}">{{ \App\Services\ReportService::format($row[$i] ?? '', $column['type']) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    @if ($table['footer'])
                        <tfoot>
                            <tr>
                                @foreach ($table['columns'] as $i => $column)
                                    <td class="{{ $column['type'] === 'text' ? '' : 'r' }}">{{ \App\Services\ReportService::format($table['footer'][$i] ?? '', $column['type']) }}</td>
                                @endforeach
                            </tr>
                        </tfoot>
                    @endif
                </table>
            @endif
        @endforeach

        <ul class="muted" style="margin-top:1.5rem">
            @foreach ($report['notes'] as $note)
                <li>{{ __($note) }}</li>
            @endforeach
        </ul>

        <p class="muted">{{ __('Printed by :name on :date', ['name' => $printedBy, 'date' => now()->timezone(\App\Support\PropertyTime::timezone())->format('M j, Y H:i')]) }}</p>
    </main>
</body>
</html>
