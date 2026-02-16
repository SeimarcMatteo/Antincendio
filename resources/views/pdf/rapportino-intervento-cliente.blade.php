<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 28px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #222; }
        header { border-bottom: 2px solid #b30000; margin-bottom: 18px; padding-bottom: 10px; }
        header h1 { color: #b30000; font-size: 20px; margin: 0; }
        header p { margin: 0; font-size: 12px; }
        h2 { color: #b30000; border-bottom: 1px solid #ddd; padding-bottom: 2px; margin-top: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 15px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; font-size: 12px; }
        th { background-color: #f8f8f8; font-weight: bold; text-transform: uppercase; }
        .note { border: 1px solid #ddd; padding: 10px; background-color: #fefefe; }
        .firma { margin-top: 36px; text-align: center; }
        .firma label { font-weight: bold; display: block; margin-bottom: 10px; color: #444; }
        .firma img { max-width: 300px; border: 1px solid #ccc; }
        .dati-cliente { line-height: 1.4; }
    </style>
</head>
<body>
    <header>
        <h1>ANTINCENDIO LUGHESE SRL</h1>
        <p>Via G. Ricci Curbastro 54/56 – 48020 S.Agata sul Santerno (RA)</p>
    </header>

    <h2>Rapportino Intervento Cliente</h2>

    <table>
        <tr>
            <th>Data Intervento</th>
            <td>{{ \Carbon\Carbon::parse($intervento->data_intervento)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <th>Tecnici</th>
            <td>{{ $intervento->tecnici->pluck('name')->join(', ') }}</td>
        </tr>
        <tr>
            <th>Durata Effettiva</th>
            <td>{{ (int)($intervento->durata_effettiva ?? 0) }} minuti</td>
        </tr>
    </table>

    <h2>Dati Cliente</h2>
    <div class="dati-cliente">
        @php
            $richiedeIncassoTecnico = (bool) ($intervento->cliente->richiede_pagamento_manutentore ?? false);
            $formaPagamentoBusiness = trim((string) ($intervento->cliente->forma_pagamento_descrizione ?? ''));
            $metodoIncasso = mb_strtoupper(trim((string) ($intervento->pagamento_metodo ?? '')));
            $importoIncassato = $intervento->pagamento_importo;
        @endphp
        <strong>Ragione Sociale:</strong> {{ $intervento->cliente->nome }}<br>
        <strong>Indirizzo:</strong>
        {{ $intervento->sede->indirizzo ?? $intervento->cliente->indirizzo }},
        {{ $intervento->sede->cap ?? $intervento->cliente->cap }}
        {{ $intervento->sede->citta ?? $intervento->cliente->citta }} ({{ $intervento->sede->provincia ?? $intervento->cliente->provincia }})<br>
        <strong>Sede:</strong> {{ $intervento->sede->nome ?? 'Sede Principale' }}<br>
        @if($richiedeIncassoTecnico)
            <strong>Forma pagamento:</strong> ALLA CONSEGNA (incasso da manutentore)<br>
            <strong>Metodo incasso:</strong> {{ $metodoIncasso !== '' ? $metodoIncasso : 'NON INDICATO' }}<br>
            <strong>Importo incassato:</strong>
            @if($importoIncassato !== null)
                € {{ number_format((float) $importoIncassato, 2, ',', '.') }}
            @else
                NON INDICATO
            @endif
        @else
            <strong>Forma pagamento:</strong> {{ $formaPagamentoBusiness !== '' ? $formaPagamentoBusiness : '-' }}
        @endif
    </div>

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
                                {{ $item->anomalia?->etichetta ?? '-' }}@if(!$loop->last), @endif
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

    @php
        $extraPresidiSummary = $extraPresidiSummary ?? ['rows' => [], 'has_pending_manual_prices' => false, 'pending_manual_prices' => [], 'totale_extra' => 0];
        $riepilogoEconomico = $riepilogoEconomico ?? [
            'totale_ordine_business' => 0,
            'extra_presidi' => 0,
            'extra_anomalie_riparate' => 0,
            'totale_aggiornato' => 0,
        ];
    @endphp

    <h2>Riepilogo Economico</h2>
    <div class="note">
        <strong>Totale ordine Business:</strong> € {{ number_format((float)($riepilogoEconomico['totale_ordine_business'] ?? 0), 2, ',', '.') }}<br>
        <strong>Extra presidi:</strong> € {{ number_format((float)($riepilogoEconomico['extra_presidi'] ?? 0), 2, ',', '.') }}<br>
        <strong>Extra anomalie riparate:</strong> € {{ number_format((float)($riepilogoEconomico['extra_anomalie_riparate'] ?? 0), 2, ',', '.') }}<br>
        <strong>Totale intervento aggiornato:</strong> € {{ number_format((float)($riepilogoEconomico['totale_aggiornato'] ?? 0), 2, ',', '.') }}
    </div>

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
    @endif

    @if(!empty($extraPresidiSummary['rows'] ?? []))
        <h2>Extra Presidi</h2>
        <table>
            <thead>
                <tr>
                    <th>Cod. Art.</th>
                    <th>Descrizione</th>
                    <th>Q.tà Extra</th>
                    <th>Prezzo Unit.</th>
                    <th>Importo Extra</th>
                    <th>Fonte</th>
                </tr>
            </thead>
            <tbody>
                @foreach(($extraPresidiSummary['rows'] ?? []) as $row)
                    <tr>
                        <td>{{ $row['codice_articolo'] }}</td>
                        <td>{{ $row['descrizione'] ?: '-' }}</td>
                        <td>{{ number_format((float)($row['quantita_extra'] ?? 0), 2, ',', '.') }}</td>
                        <td>
                            @if(($row['prezzo_unitario'] ?? null) !== null)
                                € {{ number_format((float)$row['prezzo_unitario'], 2, ',', '.') }}
                            @else
                                DA DEFINIRE
                            @endif
                        </td>
                        <td>
                            @if(($row['importo_extra'] ?? null) !== null)
                                € {{ number_format((float)$row['importo_extra'], 2, ',', '.') }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if(($row['prezzo_source'] ?? '') === 'ordine')
                                Ordine Business
                            @elseif(($row['prezzo_source'] ?? '') === 'manuale')
                                Inserito tecnico
                            @else
                                Prezzo richiesto
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(($extraPresidiSummary['has_pending_manual_prices'] ?? false) === true)
        <div class="note">
            <strong>Attenzione:</strong> alcuni extra presidi non hanno ancora un prezzo manuale assegnato.
        </div>
    @endif

    <h2>Riepilogo Anomalie</h2>
    <div class="note">
        <strong>Totale:</strong> {{ $anomalieRiepilogo['totale'] ?? 0 }}<br>
        <strong>Riparate:</strong> {{ $anomalieRiepilogo['riparate'] ?? 0 }}<br>
        <strong>Da preventivare:</strong> {{ $anomalieRiepilogo['preventivo'] ?? 0 }}<br>
        <strong>Importo riparate:</strong> € {{ number_format((float)($anomalieRiepilogo['importo_riparate'] ?? 0), 2, ',', '.') }}<br>
        <strong>Importo preventivo:</strong> € {{ number_format((float)($anomalieRiepilogo['importo_preventivo'] ?? 0), 2, ',', '.') }}<br>
        <strong>Totale intervento aggiornato:</strong> € {{ number_format((float)($riepilogoEconomico['totale_aggiornato'] ?? 0), 2, ',', '.') }}
    </div>

    @if(!empty($anomalieRiepilogo['dettaglio'] ?? []))
        <table>
            <thead>
                <tr>
                    <th>Anomalia</th>
                    <th>Prezzo</th>
                    <th>Totale</th>
                    <th>Riparate</th>
                    <th>Preventivo</th>
                    <th>Imp. Riparate</th>
                    <th>Imp. Preventivo</th>
                </tr>
            </thead>
            <tbody>
                @foreach(($anomalieRiepilogo['dettaglio'] ?? []) as $row)
                    <tr>
                        <td>{{ $row['etichetta'] }}</td>
                        <td>€ {{ number_format((float)($row['prezzo'] ?? 0), 2, ',', '.') }}</td>
                        <td>{{ $row['totale'] }}</td>
                        <td>{{ $row['riparate'] }}</td>
                        <td>{{ $row['preventivo'] }}</td>
                        <td>€ {{ number_format((float)($row['importo_riparate'] ?? 0), 2, ',', '.') }}</td>
                        <td>€ {{ number_format((float)($row['importo_preventivo'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @php $noteIntervento = $intervento->note ?? $intervento->note_generali ?? null; @endphp
    @if($noteIntervento)
        <h2>Note Intervento</h2>
        <div class="note">{{ $noteIntervento }}</div>
    @endif

    <div class="firma">
        <label>Firma del Cliente per accettazione</label>
        @if($intervento->firma_cliente_base64)
            <img src="{{ $intervento->firma_cliente_base64 }}" alt="Firma Cliente">
        @else
            <p><em>Firma non disponibile</em></p>
        @endif
    </div>

    <div class="firma">
        <label>
            Firma Tecnico Chiusura
            @if(!empty($tecnicoChiusura?->name))
                - {{ $tecnicoChiusura->name }}
            @endif
        </label>
        @if(!empty($tecnicoChiusura?->firma_tecnico_base64))
            <img src="{{ $tecnicoChiusura->firma_tecnico_base64 }}" alt="Firma Tecnico">
        @else
            <p><em>Firma tecnico non disponibile</em></p>
        @endif
    </div>
</body>
</html>
