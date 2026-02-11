<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 30px;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 13px;
            color: #222;
        }
        header {
            border-bottom: 3px solid #b30000;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }
        header h1 {
            color: #b30000;
            font-size: 20px;
            margin: 0;
        }
        header p {
            margin: 0;
            font-size: 12px;
        }
        h2 {
            color: #b30000;
            border-bottom: 1px solid #ddd;
            padding-bottom: 2px;
            margin-top: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 15px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 6px 8px;
            font-size: 12px;
        }
        th {
            background-color: #f8f8f8;
            font-weight: bold;
            text-transform: uppercase;
        }
        .note {
            border: 1px dashed #aaa;
            padding: 10px;
            background-color: #fefefe;
        }
        .firma {
            margin-top: 40px;
            text-align: center;
        }
        .firma label {
            font-weight: bold;
            display: block;
            margin-bottom: 10px;
            color: #444;
        }
        .firma img {
            max-width: 300px;
            border: 1px solid #ccc;
        }
        .dati-cliente {
            line-height: 1.4;
        }
    </style>
</head>
<body>

    {{-- Intestazione Azienda --}}
    <header>
        <h1>ANTINCENDIO LUGHESE SRL</h1>
        <p>Via G. Ricci Curbastro 54/56 – 48020 S.Agata sul Santerno (RA)</p>
    </header>

    <h2>Rapportino di Intervento</h2>

    {{-- Dati Intervento --}}
    <table>
        <tr>
            <th>Data Intervento</th>
            <td>{{ \Carbon\Carbon::parse($intervento->data_intervento)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <th>Tecnici</th>
            <td>{{ $intervento->tecnici->pluck('name')->join(', ') }}</td>
        </tr>
    </table>

    {{-- Dati Cliente --}}
    <h2>Dati Cliente</h2>
    <div class="dati-cliente">
        <strong>Ragione Sociale:</strong> {{ $intervento->cliente->nome }}<br>
        <strong>P.IVA:</strong> {{ $intervento->cliente->p_iva ?? '-' }}<br>
        <strong>Indirizzo:</strong>
        {{ $intervento->sede->indirizzo ?? $intervento->cliente->indirizzo }},
        {{ $intervento->sede->cap ?? $intervento->cliente->cap }}
        {{ $intervento->sede->citta ?? $intervento->cliente->citta }} ({{ $intervento->sede->provincia ?? $intervento->cliente->provincia }})<br>
        <strong>Sede:</strong> {{ $intervento->sede->nome ?? 'Sede Principale' }}
    </div>

    {{-- Presidi --}}
    <h2>Presidi Verificati</h2>
    <table>
        <thead>
            <tr>
                <th>Categoria</th>
                <th>Progressivo</th>
                <th>Ubicazione</th>
                <th>Esito</th>
                <th>Anomalie</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @foreach($intervento->presidiIntervento as $pi)
                <tr>
                    <td>{{ $pi->presidio->categoria }}</td>
                    <td>{{ $pi->presidio->progressivo }}</td>
                    <td>{{ $pi->presidio->ubicazione }}</td>
                    <td>{{ strtoupper($pi->esito) }}</td>
                    <td>
                        @php
                            $anomaliaItems = collect();
                            if (!empty($hasAnomaliaItemsTable)) {
                                $anomaliaItems = $pi->relationLoaded('anomalieItems')
                                    ? $pi->anomalieItems
                                    : $pi->anomalieItems()->with('anomalia')->get();
                            }
                        @endphp
                        @if($anomaliaItems->isNotEmpty())
                            @foreach($anomaliaItems as $item)
                                {{ $item->anomalia?->etichetta ?? '-' }} ({{ $item->riparata ? 'Riparata' : 'Preventivo' }})@if(!$loop->last), @endif
                            @endforeach
                        @else
                            @foreach($pi->anomalie as $anomalia)
                                {{ $anomalia->etichetta }}@if(!$loop->last), @endif
                            @endforeach
                        @endif
                    </td>
                    <td>{{ $pi->note }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Confronto Ordine Preventivo</h2>
    @if(!($ordinePreventivo['found'] ?? false))
        <div class="note">
            {{ $ordinePreventivo['error'] ?? 'Ordine preventivo non trovato.' }}
        </div>
    @else
        @php $h = $ordinePreventivo['header'] ?? []; @endphp
        <div class="note">
            <strong>Ordine:</strong> {{ ($h['tipork'] ?? '-') . '/' . ($h['serie'] ?? '-') . '/' . ($h['anno'] ?? '-') . '/' . ($h['numero'] ?? '-') }}<br>
            <strong>Data:</strong> {{ !empty($h['data']) ? \Carbon\Carbon::parse($h['data'])->format('d/m/Y') : '-' }}<br>
            <strong>Conto:</strong> {{ $h['conto'] ?? '-' }}<br>
            <strong>Totale Documento:</strong> € {{ number_format((float)($h['totale_documento'] ?? 0), 2, ',', '.') }}
        </div>

        <h2>Righe Ordine (Business)</h2>
        <table>
            <thead>
                <tr>
                    <th>Cod. Art.</th>
                    <th>Descrizione</th>
                    <th>Q.tà</th>
                    <th>Prezzo</th>
                    <th>Importo</th>
                </tr>
            </thead>
            <tbody>
                @forelse(($ordinePreventivo['rows'] ?? []) as $row)
                    <tr>
                        <td>{{ $row['codice_articolo'] }}</td>
                        <td>{{ $row['descrizione'] ?: '-' }}</td>
                        <td>{{ number_format((float)$row['quantita'], 2, ',', '.') }}</td>
                        <td>€ {{ number_format((float)$row['prezzo_unitario'], 2, ',', '.') }}</td>
                        <td>€ {{ number_format((float)$row['importo'], 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Nessuna riga ordine.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Righe Intervento (Confronto)</h2>
        <table>
            <thead>
                <tr>
                    <th>Cod. Art.</th>
                    <th>Descrizione</th>
                    <th>Q.tà</th>
                </tr>
            </thead>
            <tbody>
                @forelse(($righeIntervento['rows'] ?? []) as $row)
                    <tr>
                        <td>{{ $row['codice_articolo'] }}</td>
                        <td>{{ $row['descrizione'] ?: '-' }}</td>
                        <td>{{ number_format((float)$row['quantita'], 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">Nessuna riga intervento.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if(!empty($righeIntervento['missing_mapping'] ?? []))
            <h2>Presidi Senza Codice Articolo</h2>
            <table>
                <thead>
                    <tr>
                        <th>Categoria</th>
                        <th>Progressivo</th>
                        <th>Tipo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($righeIntervento['missing_mapping'] ?? []) as $row)
                        <tr>
                            <td>{{ $row['categoria'] }}</td>
                            <td>{{ $row['progressivo'] }}</td>
                            <td>{{ $row['tipo'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if($confrontoOrdine['ok'] ?? false)
            <div class="note"><strong>Esito confronto:</strong> Nessuna differenza tra ordine e intervento.</div>
        @else
            <h2>Differenze Ordine vs Intervento</h2>
            <table>
                <thead>
                    <tr>
                        <th>Tipo differenza</th>
                        <th>Cod. Art.</th>
                        <th>Descrizione</th>
                        <th>Q.tà ordine</th>
                        <th>Q.tà intervento</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($confrontoOrdine['solo_ordine'] ?? []) as $row)
                        <tr>
                            <td>Solo in ordine</td>
                            <td>{{ $row['codice_articolo'] }}</td>
                            <td>{{ $row['descrizione'] ?: '-' }}</td>
                            <td>{{ number_format((float)$row['quantita_ordine'], 2, ',', '.') }}</td>
                            <td>0,00</td>
                        </tr>
                    @endforeach
                    @foreach(($confrontoOrdine['solo_intervento'] ?? []) as $row)
                        <tr>
                            <td>Solo in intervento</td>
                            <td>{{ $row['codice_articolo'] }}</td>
                            <td>{{ $row['descrizione'] ?: '-' }}</td>
                            <td>0,00</td>
                            <td>{{ number_format((float)$row['quantita_intervento'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    @foreach(($confrontoOrdine['differenze_quantita'] ?? []) as $row)
                        <tr>
                            <td>Quantità diversa</td>
                            <td>{{ $row['codice_articolo'] }}</td>
                            <td>{{ $row['descrizione'] ?: '-' }}</td>
                            <td>{{ number_format((float)$row['quantita_ordine'], 2, ',', '.') }}</td>
                            <td>{{ number_format((float)$row['quantita_intervento'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    <h2>Riepilogo Anomalie</h2>
    <div class="note">
        <strong>Totale:</strong> {{ $anomalieRiepilogo['totale'] ?? 0 }}<br>
        <strong>Riparate:</strong> {{ $anomalieRiepilogo['riparate'] ?? 0 }}<br>
        <strong>Da preventivare:</strong> {{ $anomalieRiepilogo['preventivo'] ?? 0 }}
    </div>
    @if(!empty($anomalieRiepilogo['dettaglio'] ?? []))
        <table>
            <thead>
                <tr>
                    <th>Anomalia</th>
                    <th>Totale</th>
                    <th>Riparate</th>
                    <th>Preventivo</th>
                </tr>
            </thead>
            <tbody>
                @foreach(($anomalieRiepilogo['dettaglio'] ?? []) as $row)
                    <tr>
                        <td>{{ $row['etichetta'] }}</td>
                        <td>{{ $row['totale'] }}</td>
                        <td>{{ $row['riparate'] }}</td>
                        <td>{{ $row['preventivo'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Note generali --}}
    @if($intervento->note_generali)
        <h2>Note Generali</h2>
        <div class="note">{{ $intervento->note_generali }}</div>
    @endif

    {{-- Firma Cliente --}}
    <div class="firma">
        <label>Firma del Cliente per accettazione</label>
        @if($intervento->firma_cliente_base64)
            <img src="{{ $intervento->firma_cliente_base64 }}" alt="Firma Cliente">
        @else
            <p><em>Firma non disponibile</em></p>
        @endif
    </div>

</body>
</html>
