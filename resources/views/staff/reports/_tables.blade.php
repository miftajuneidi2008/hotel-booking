{{-- Every table of a report on screen. $report comes from App\Services\ReportService. --}}
@foreach ($report['tables'] as $table)
    <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
        <h3 class="px-6 pt-6 font-semibold text-gray-800">{{ __($table['title']) }}</h3>

        @if (count($table['rows']) === 0)
            <p class="px-6 py-8 text-sm text-gray-500">{{ __($table['empty']) }}</p>
        @else
            <table class="mt-3 min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                    <tr>
                        @foreach ($table['columns'] as $column)
                            <th class="px-6 py-3 whitespace-nowrap {{ $column['type'] === 'text' ? '' : 'text-right' }}">{{ __($column['label']) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-900">
                    @foreach ($table['rows'] as $row)
                        <tr>
                            @foreach ($table['columns'] as $i => $column)
                                @php($cell = $row[$i] ?? '')
                                <td class="px-6 py-3 {{ $column['type'] === 'text' ? '' : 'text-right whitespace-nowrap' }}">
                                    @if (is_array($cell))
                                        <a href="{{ $cell['url'] }}" class="text-indigo-600 hover:text-indigo-900">{{ $cell['text'] }}</a>
                                    @else
                                        {{ \App\Services\ReportService::format($cell, $column['type']) }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                @if ($table['footer'])
                    <tfoot class="bg-gray-50 font-semibold text-gray-900">
                        <tr>
                            @foreach ($table['columns'] as $i => $column)
                                <td class="px-6 py-3 {{ $column['type'] === 'text' ? '' : 'text-right whitespace-nowrap' }}">{{ \App\Services\ReportService::format($table['footer'][$i] ?? '', $column['type']) }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        @endif
    </div>
@endforeach
