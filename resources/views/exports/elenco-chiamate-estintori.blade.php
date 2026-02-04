<table>
    <thead>
        <tr>
            <th>Data</th>
            <th>Zona</th>
            <th>Cliente</th>
            <th>Tipo Estintore</th>
            <th>Revisioni</th>
            <th>Collaudi</th>
            <th>Fine Vita</th>
            <th>Totale</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($dati as $riga)
            <tr>
                <td>{{ \Carbon\Carbon::parse($riga['data'])->format('d/m/Y') }}</td>
                <td>{{ $riga['zona'] }}</td>
                <td>{{ $riga['cliente'] }}</td>
                <td>{{ $riga['tipo_estintore'] }}</td>
                <td>{{ $riga['revisione'] }}</td>
                <td>{{ $riga['collaudo'] }}</td>
                <td>{{ $riga['fine_vita'] }}</td>
                <td>{{ $riga['totale'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
