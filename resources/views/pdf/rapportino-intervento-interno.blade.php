<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; line-height: 1.35; }
        header { border-bottom: 3px solid #b30000; margin-bottom: 20px; padding-bottom: 10px; }
        header h1 { color: #b30000; font-size: 20px; margin: 0; }
        header p { margin: 0; font-size: 12px; }
        .section { margin-top: 14px; page-break-inside: avoid; }
        .section.breakable { page-break-inside: auto; }
        h2 { color: #b30000; border-bottom: 1px solid #ddd; padding-bottom: 2px; margin: 0 0 8px 0; page-break-after: avoid; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; margin-bottom: 0; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; font-size: 12px; }
        th { background-color: #f4f4f4; font-weight: bold; text-transform: uppercase; }
        tr { page-break-inside: avoid; }
        thead { display: table-header-group; }
        .note { border: 1px dashed #aaa; padding: 10px; background-color: #fefefe; page-break-inside: avoid; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; background: #111; color: #fff; font-size: 11px; }
        .firma-wrap { margin-top: 18px; page-break-inside: avoid; }
        .firma { margin-top: 12px; text-align: center; }
        .firma label { font-weight: bold; display: block; margin-bottom: 10px; color: #444; }
        .firma img { max-width: 300px; border: 1px solid #ccc; }
        .dati-cliente { line-height: 1.4; page-break-inside: avoid; }
    </style>
</head>
<body>
    <header>
        <h1>ANTINCENDIO LUGHESE SRL</h1>
        <p>Via G. Ricci Curbastro 54/56 – 48020 S.Agata sul Santerno (RA)</p>
    </header>

    <div class="section">
        <h2>Rapportino di Intervento</h2>
        <div class="badge">USO INTERNO</div>

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
    </div>

    <div class="section">
    <h2>Dati Cliente</h2>
    <div class="dati-cliente">
        @php
            $richiedeIncassoTecnico = (bool) ($intervento->cliente->richiede_pagamento_manutentore ?? false);
            $formaPagamentoBusiness = trim((string) ($intervento->cliente->forma_pagamento_descrizione ?? ''));
            $metodoIncasso = mb_strtoupper(trim((string) ($intervento->pagamento_metodo ?? '')));
            $importoIncassato = $intervento->pagamento_importo;
        @endphp
        <strong>Ragione Sociale:</strong> {{ $intervento->cliente->nome }}<br>
        <strong>P.IVA:</strong> {{ $intervento->cliente->p_iva ?? '-' }}<br>
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
    </div>

    <div class="section breakable">
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
    </div>

    <div class="section breakable">
    <h2>Riepilogo Presidi Intervento (Senza Prezzi)</h2>
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
                <tr><td colspan="3">Nessun presidio nel riepilogo.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="note">Prezzi da ordine Business; per codici extra non presenti in ordine si usa il prezzo manuale inserito dal tecnico.</div>
    </div>

    @if(!empty($righeIntervento['missing_mapping'] ?? []))
        <div class="section breakable">
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
        </div>
    @endif

    @php
        $extraPresidiSummary = $extraPresidiSummary ?? ['rows' => [], 'has_pending_manual_prices' => false, 'pending_manual_prices' => [], 'totale_extra' => 0];
        $riepilogoEconomico = $riepilogoEconomico ?? [
            'totale_ordine_business' => 0,
            'extra_presidi' => 0,
            'extra_anomalie_riparate' => 0,
            'totale_aggiornato' => 0,
        ];
        $ordineTrovato = (bool) ($ordinePreventivo['found'] ?? false);
        $riepilogoSoloTotaleSenzaOrdine = $richiedeIncassoTecnico && !$ordineTrovato;
        $totaleSoloTotaleSenzaOrdine = $importoIncassato !== null
            ? (float) $importoIncassato
            : (float) ($riepilogoEconomico['totale_aggiornato'] ?? 0);
    @endphp

    @if(!$riepilogoSoloTotaleSenzaOrdine && !empty($extraPresidiSummary['rows'] ?? []))
        <div class="section breakable">
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
        </div>
    @endif

    <div class="section">
    <h2>Riepilogo Economico</h2>
    <div class="note">
        @if($riepilogoSoloTotaleSenzaOrdine)
            <strong>Totale intervento:</strong> € {{ number_format($totaleSoloTotaleSenzaOrdine, 2, ',', '.') }}
        @else
            <strong>Totale ordine Business:</strong> € {{ number_format((float)($riepilogoEconomico['totale_ordine_business'] ?? 0), 2, ',', '.') }}<br>
            <strong>Extra presidi:</strong> € {{ number_format((float)($riepilogoEconomico['extra_presidi'] ?? 0), 2, ',', '.') }}<br>
            <strong>Extra anomalie riparate:</strong> € {{ number_format((float)($riepilogoEconomico['extra_anomalie_riparate'] ?? 0), 2, ',', '.') }}<br>
            <strong>Totale intervento aggiornato:</strong> € {{ number_format((float)($riepilogoEconomico['totale_aggiornato'] ?? 0), 2, ',', '.') }}
        @endif
    </div>
    </div>

    @if(!$riepilogoSoloTotaleSenzaOrdine && ($extraPresidiSummary['has_pending_manual_prices'] ?? false) === true)
        <div class="section">
        <div class="note">
            <strong>Attenzione:</strong> alcuni extra presidi non hanno ancora un prezzo manuale assegnato.
        </div>
        </div>
    @endif

    <div class="section breakable">
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
            <strong>Totale Documento:</strong> € {{ number_format((float)($h['totale_documento'] ?? 0), 2, ',', '.') }}<br>
            <strong>Extra Presidi:</strong> € {{ number_format((float)($riepilogoEconomico['extra_presidi'] ?? 0), 2, ',', '.') }}<br>
            <strong>Extra Anomalie Riparate:</strong> € {{ number_format((float)($riepilogoEconomico['extra_anomalie_riparate'] ?? 0), 2, ',', '.') }}<br>
            <strong>Totale Intervento Aggiornato:</strong> € {{ number_format((float)($riepilogoEconomico['totale_aggiornato'] ?? 0), 2, ',', '.') }}
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
    </div>

    <div class="section">
    <h2>Riepilogo Anomalie</h2>
    <div class="note">
        <strong>Totale:</strong> {{ $anomalieRiepilogo['totale'] ?? 0 }}<br>
        <strong>Riparate:</strong> {{ $anomalieRiepilogo['riparate'] ?? 0 }}<br>
        <strong>Da preventivare:</strong> {{ $anomalieRiepilogo['preventivo'] ?? 0 }}
        @if(!$riepilogoSoloTotaleSenzaOrdine)
            <br><strong>Importo riparate:</strong> € {{ number_format((float)($anomalieRiepilogo['importo_riparate'] ?? 0), 2, ',', '.') }}<br>
            <strong>Importo preventivo:</strong> € {{ number_format((float)($anomalieRiepilogo['importo_preventivo'] ?? 0), 2, ',', '.') }}
        @endif
    </div>
    </div>
    @if(!empty($anomalieRiepilogo['dettaglio'] ?? []))
        <div class="section breakable">
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
        </div>
    @endif

    @php $noteIntervento = $intervento->note ?? $intervento->note_generali ?? null; @endphp
    @if($noteIntervento)
        <div class="section">
        <h2>Note Intervento</h2>
        <div class="note">{{ $noteIntervento }}</div>
        </div>
    @endif

    <div class="firma-wrap">
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
    </div>

</body>
</html>
