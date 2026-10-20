<?php

namespace App\Http\Controllers;

use App\Exports\GenericExport;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    /** dompdf cannot stream, so PDFs are capped; Excel/CSV carry the full dataset. */
    private const PDF_MAX = 2000;

    public function __construct(private readonly ReportService $reports) {}

    public function index(): mixed
    {
        return view('reports.index', ['types' => ReportService::TYPES]);
    }

    public function export(Request $request): mixed
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(ReportService::TYPES)],
            'format' => ['required', Rule::in(['xlsx', 'csv', 'pdf'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        // Fail closed: a non-staff user with no partner sees nothing (not everything).
        $pid = $user->isStaff() ? null : ($user->partner?->id ?? -1);

        $report = $this->reports->build($validated['type'], $request->only('from', 'to'), $pid);
        $filename = $validated['type'].'-'.now()->format('Ymd');

        if ($validated['format'] === 'pdf') {
            $rows = (clone $report['query'])->limit(self::PDF_MAX)->get()->map($report['map'])->all();

            return Pdf::loadView('reports.pdf', [
                'title' => $report['title'],
                'columns' => $report['columns'],
                'rows' => $rows,
                'truncated' => count($rows) >= self::PDF_MAX,
                'maxRows' => self::PDF_MAX,
            ])->setPaper('a4', 'landscape')->download($filename.'.pdf');
        }

        $writer = $validated['format'] === 'csv' ? ExcelWriter::CSV : ExcelWriter::XLSX;

        return Excel::download(
            new GenericExport($report['columns'], $report['query'], $report['map']),
            $filename.'.'.$validated['format'],
            $writer,
        );
    }
}
