<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    /** The list of reports this user is allowed to open. */
    public function index(Request $request)
    {
        $groups = $this->reports->groups($request->user());
        abort_if(empty($groups), 403);

        return view('reports.index', compact('groups'));
    }

    public function show(Request $request, string $key)
    {
        [$definition, $filters] = $this->prepare($request, $key);

        return view('reports.show', [
            'key' => $key,
            'definition' => $definition,
            'fields' => $this->reports->fields($key),
            'filters' => $filters,
            'report' => $this->reports->run($key, $filters),
        ]);
    }

    public function pdf(Request $request, string $key)
    {
        [$definition, $filters] = $this->prepare($request, $key);
        $this->audit($definition, 'PDF');

        return Pdf::loadView('reports.pdf', [
            'definition' => $definition,
            'filterText' => $this->reports->describeFilters($key, $filters),
            'report' => $this->reports->run($key, $filters),
            'company' => \App\Models\Setting::get('company_name', config('app.name')),
        ])->setPaper('a4', 'landscape')->setOption('defaultFont', 'DejaVu Sans')->download($this->fileName($key, 'pdf'));
    }

    public function csv(Request $request, string $key)
    {
        [$definition, $filters] = $this->prepare($request, $key);
        $report = $this->reports->run($key, $filters);
        $filterText = $this->reports->describeFilters($key, $filters);
        $this->audit($definition, 'CSV');

        return response()->streamDownload(function () use ($definition, $filterText, $report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // byte order mark, so Excel reads Bangla text correctly

            fputcsv($out, [$definition['title']]);
            if ($filterText !== '') {
                fputcsv($out, [$filterText]);
            }
            fputcsv($out, []);
            fputcsv($out, array_column($report['columns'], 'label'));

            foreach ($report['rows'] as $row) {
                fputcsv($out, array_map(fn ($column) => $this->csvCell($column, $row[$column['key']] ?? null), $report['columns']));
            }

            if ($report['totals']) {
                $first = true;
                fputcsv($out, array_map(function ($column) use ($report, &$first) {
                    if ($first) {
                        $first = false;

                        return 'Total';
                    }

                    return isset($report['totals'][$column['key']]) ? $this->csvCell($column, $report['totals'][$column['key']]) : '';
                }, $report['columns']));
            }

            fclose($out);
        }, $this->fileName($key, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------

    /** Finds the report, checks the user's permission and cleans the filters. */
    private function prepare(Request $request, string $key): array
    {
        $definitions = $this->reports->definitions();
        abort_unless(isset($definitions[$key]), 404);
        abort_unless($request->user()->hasPermission($definitions[$key]['permission']), 403);

        return [$definitions[$key], $this->reports->filters($key, $request->query())];
    }

    private function csvCell(array $column, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $text = match ($column['type']) {
            'money' => number_format((float) $value, 2, '.', ''),
            'number' => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.'),
            'status' => ucwords(str_replace('_', ' ', (string) $value)),
            default => (string) $value,
        };

        // A cell that starts with = + - or @ could be run as a formula when the file is opened in Excel.
        if (in_array($column['type'], ['text', 'status'], true) && preg_match('/^[=+\-@]/', $text)) {
            $text = "'" . $text;
        }

        return $text;
    }

    private function fileName(string $key, string $extension): string
    {
        return str_replace('.', '-', $key) . '-' . Carbon::now()->format('Ymd') . '.' . $extension;
    }

    private function audit(array $definition, string $format): void
    {
        AuditService::log('exported', 'reports', "{$definition['title']} was exported as {$format}");
    }
}
