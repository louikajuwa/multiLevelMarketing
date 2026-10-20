<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1f2937; font-size: 11px; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .meta { color: #6b7280; font-size: 10px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #f1f5f9; padding: 6px 8px; font-size: 9px; text-transform: uppercase; letter-spacing: .04em; color: #475569; border-bottom: 1px solid #cbd5e1; }
        td { padding: 6px 8px; border-bottom: 1px solid #eef2f7; }
        tr:nth-child(even) td { background: #fafbfc; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">{{ config('app.name') }} · generated {{ now()->toDayDateTimeString() }} · {{ count($rows) }} rows</div>
    @if (! empty($truncated))
        <div class="meta" style="color:#b45309">Truncated to the first {{ number_format($maxRows) }} rows — export Excel or CSV for the full dataset.</div>
    @endif
    <table>
        <thead><tr>@foreach ($columns as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ max(count($columns), 1) }}">No data.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
