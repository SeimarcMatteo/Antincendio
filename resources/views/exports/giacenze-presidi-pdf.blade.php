<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Giacenze Presidi</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; border: 1px solid #ccc; }
        th { background-color: #f87171; color: white; }
        .center { text-align: center; }
    </style>
</head>
<body>
    <h2 style="color: #b91c1c;">📦 Report Magazzino Presidi</h2>
    <table>
        <thead>
            <tr>
                <th>Categoria</th>
                <th>Tipo Estintore</th>
                <th class="center">Quantità</th>
            </tr>
        </thead>
        <tbody>
            @foreach($giacenze as $g)
                <tr>
                    <td>{{ $g->categoria }}</td>
                    <td>{{ $g->tipoEstintore->sigla ?? '-' }}</td>
                    <td class="center">{{ $g->quantita }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
