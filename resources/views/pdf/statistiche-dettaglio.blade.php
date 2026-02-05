<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Dettaglio statistiche' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #0f172a; }
        h1 { font-size: 16px; margin: 0 0 6px 0; }
        .meta { font-size: 10px; color: #475569; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e2e8f0; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; }
        tbody tr:nth-child(even) { background: #f8fafc; }
    </style>
</head>
<body>
    <h1>{{ $title ?? 'Dettaglio statistiche' }}</h1>
    <div class="meta">Periodo: {{ $range[0] ?? '' }} → {{ $range[1] ?? '' }}</div>

    <table>
        <thead>
            <tr>
                @foreach(($headers ?? []) as $head)
                    <th>{{ $head }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach(($rows ?? []) as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
