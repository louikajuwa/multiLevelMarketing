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
        $pid = (! $user->isStaff() && $user->partner) ? $user->partner->id : null;

        $report = $this->reports->build($validated['type'], $request->only('from', 'to'), $pid);
        $filename = $validated['type'].'-'.now()->format('Ymd');

        if ($validated['format'] === 'pdf') {
            return Pdf::loadView('reports.pdf', $report)
                ->setPaper('a4', 'landscape')
                ->download($filename.'.pdf');
        }

        $writer = $validated['format'] === 'csv' ? ExcelWriter::CSV : ExcelWriter::XLSX;

        return Excel::download(
            new GenericExport($report['columns'], $report['rows']),
            $filename.'.'.$validated['format'],
            $writer,
        );
    }
}
