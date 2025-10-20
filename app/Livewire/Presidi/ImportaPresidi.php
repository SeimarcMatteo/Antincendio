<?php

namespace App\Livewire\Presidi;

use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use App\Models\{TipoEstintore, ImportPresidio, Presidio};

class ImportaPresidi extends Component
{
    use WithFileUploads;

    /* ------------------ PUBLIC STATE ------------------ */
    public $file;
    public int    $clienteId;
    public ?int   $sedeId = null;

    public array  $anteprima       = [];   // righe estratte dal docx
    public array  $presidiSalvati  = [];   // cache ImportPresidio
    public array  $selezionati     = [];   // [ "23", "27", ... ]
    public array $tipiEstintore = [];
    public string $filtroCategoria = '';

    /* ------------------ LIFECYCLE ------------------ */
    public function mount(int $clienteId, ?int $sedeId = null): void
    {
        $this->clienteId = $clienteId;
        $this->sedeId    = $sedeId;
        $this->tipiEstintore = TipoEstintore::orderBy('descrizione')
        ->pluck('descrizione', 'id')
        ->toArray();
        $this->caricaPresidiSalvati();
    }
    private static function normalizzaHeader(string $h): string
    {
        $h = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $h)));
        $map = [
            'N'                          => 'numero',
            'N.'                         => 'numero',
            'UBICAZIONE'                 => 'ubicazione',
            'TIPO CONTRATTO'             => 'tipo_contratto',
            'KG/LT'                      => 'kglt',
            'CLASSE ESTINGUENTE'         => 'classe',
            'ANNO ACQUISTO FULL'         => 'anno_acquisto',
            'SCADENZA PRESIDIO FULL'     => 'scadenza_presidio',
            'ANNO SERBATOIO'             => 'anno_serbatoio',
            'RIEMPIMENTO/ REVISIONE'     => 'riempimento_revisione',
            'RIEMPIMENTO/REVISIONE'      => 'riempimento_revisione',
            'COLLAUDO/ REVISIONE'        => 'collaudo_revisione',
            'COLLAUDO/REVISIONE'         => 'collaudo_revisione',
            'A TERRA'                    => 'anomalia_terra',
            'MANCA CARTELLO'             => 'anomalia_cartello',
            'NUMERAZ'                    => 'anomalia_numerazione',
        ];
        return $map[$h] ?? $h;
    }

    private static function parseDataCell(?string $txt): ?string
    {
        $txt = trim((string)$txt);
        if ($txt === '') return null;

        // mm.yyyy, m/yyyy, mm-yyyy, m-yyyy
        if (preg_match('/\b(\d{1,2})\s*[\.\/-]\s*(\d{4})\b/', $txt, $m)) {
            $mese = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            return Carbon::createFromFormat('Y-m-d', "{$m[2]}-{$mese}-01")->format('Y-m-d');
        }

        // solo anno
        if (preg_match('/\b(\d{4})\b/', $txt, $y)) {
            return Carbon::createFromDate((int)$y[1], 1, 1)->format('Y-m-d');
        }

        return null;
    }

    private function caricaPresidiSalvati(): void
    {
        $this->presidiSalvati = ImportPresidio::where('cliente_id', $this->clienteId)
            ->when($this->sedeId, fn ($q) => $q->where('sede_id', $this->sedeId))
            ->get()
            ->map(fn ($p) => $p->toArray())
            ->all();          // array “puro”
    }
    public function seleziona($id)
    {
        if (! in_array($id, $this->selezionati, true)) {
            $this->selezionati[]=$id;
        }else{
            $this->selezionati = array_values(
                array_diff($this->selezionati, [$id])
            );
        }
    }

    /* ---------------- SELEZIONE CHECKBOX -------------- */
    /** Master-checkbox */
    public function toggleSelectAll(): void
    {
        $ids  = collect($this->presidiSalvati)
               ->pluck('id')
               ->map(fn ($id) => (int) $id)
               ->all();

        $this->selezionati =
            count($this->selezionati) < count($ids) ? $ids : [];
    }
    /* ------------------ CRUD RAPIDI ------------------ */
    public function salvaModifiche(): void
    {
        foreach ($this->presidiSalvati as $row) {
            ImportPresidio::find($row['id'])?->update($row);
        }
        $this->dispatch('toast', type: 'success', message: 'Modifiche salvate');
    }

    /* =================================================
     *                 IMPORT DA .DOCX
     * ===============================================*/
   public function importa(): void
    {
        $this->validate(['file' => 'required|file|mimes:docx']);
        if (!$this->file->isValid()) {
            throw new \Exception('Upload non riuscito');
        }

        // ---- Util --------------------------------------------------------------
        $normHeader = function (string $h): string {
            $h = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $h)));
            $map = [
                'N'                          => 'numero',
                'N.'                         => 'numero',
                'UBICAZIONE'                 => 'ubicazione',
                'TIPO CONTRATTO'             => 'tipo_contratto',
                'KG/LT'                      => 'kglt',
                'CLASSE ESTINGUENTE'         => 'classe',
                'ANNO ACQUISTO FULL'         => 'anno_acquisto',
                'SCADENZA PRESIDIO FULL'     => 'scadenza_presidio',
                'ANNO SERBATOIO'             => 'anno_serbatoio',
                'RIEMPIMENTO/ REVISIONE'     => 'riempimento_revisione',
                'RIEMPIMENTO/REVISIONE'      => 'riempimento_revisione',
                'COLLAUDO/ REVISIONE'        => 'collaudo_revisione',
                'COLLAUDO/REVISIONE'         => 'collaudo_revisione',
                'A TERRA'                    => 'anomalia_terra',
                'MANCA CARTELLO'             => 'anomalia_cartello',
                'NUMERAZ'                    => 'anomalia_numerazione',
            ];
            return $map[$h] ?? $h;
        };
        $parseData = function (?string $txt): ?string {
            $txt = trim((string)$txt);
            if ($txt === '') return null;
            if (preg_match('/\b(\d{1,2})\s*[\.\/-]\s*(\d{4})\b/', $txt, $m)) {
                $mese = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                return \Illuminate\Support\Carbon::createFromFormat('Y-m-d', "{$m[2]}-{$mese}-01")->format('Y-m-d');
            }
            if (preg_match('/\b(\d{4})\b/', $txt, $y)) {
                return \Illuminate\Support\Carbon::createFromDate((int)$y[1], 1, 1)->format('Y-m-d');
            }
            return null;
        };
        $today = now()->startOfMonth();

        // Mesi preferiti cliente (accetta array JSON o CSV "1,3,6,...")
        $cliente = \App\Models\Cliente::find($this->clienteId);
        $mesiPref = [];
        if ($cliente) {
            $raw = $cliente->mesi_intervento ?? $cliente->mesi ?? null;
            if (is_string($raw)) {
                $mesiPref = collect(explode(',', $raw))
                    ->map(fn($x)=> (int)trim($x))->filter(fn($m)=>$m>=1 && $m<=12)->unique()->values()->all();
            } elseif (is_array($raw)) {
                $mesiPref = collect($raw)->map(fn($m)=>(int)$m)->filter(fn($m)=>$m>=1 && $m<=12)->unique()->values()->all();
            }
        }

        // Allinea ad uno dei mesi preferiti: il mese immediatamente precedente la scadenza,
        // ma non prima dell'oggi. Se nessun mese valido nel range, prende il primo >= oggi.
        $alignToPreferred = function (?string $due, array $months) use ($today): ?string {
            if (!$due) return null;
            $dueC   = \Illuminate\Support\Carbon::parse($due)->startOfMonth();
            $start  = $today->copy();

            // senza preferenze: prendo il mese prima della scadenza, ma >= oggi
            if (!count($months)) {
                $cand = $dueC->copy()->subMonth();
                if ($cand->lt($start)) $cand = $start;
                return $cand->format('Y-m-d');
            }

            // cerca l'ultimo mese preferito in [oggi, scadenza) (strettamente prima della scadenza)
            $candidates = [];
            $cur = $start->copy();
            while ($cur->lt($dueC) || $cur->equalTo($dueC)) {
                if ((int)$cur->month === (int)$dueC->month && (int)$cur->year === (int)$dueC->year) {
                    // non includere la scadenza stessa: "subito prima"
                    break;
                }
                if (in_array((int)$cur->month, $months, true)) {
                    $candidates[] = $cur->copy();
                }
                $cur->addMonth();
            }
            if ($candidates) {
                return end($candidates)->format('Y-m-d');
            }

            // nessun mese preferito nel range: scegli il primo mese preferito >= oggi (entro 24 mesi)
            $cur = $start->copy();
            for ($i=0; $i<24; $i++) {
                if (in_array((int)$cur->month, $months, true)) {
                    return $cur->format('Y-m-d');
                }
                $cur->addMonth();
            }
            return $start->format('Y-m-d');
        };
        // -----------------------------------------------------------------------

        // carica e parse
        $path = Storage::disk('local')->path($this->file->store('import_presidi', 'local'));
        $word = IOFactory::load($path);

        $this->anteprima = [];
        foreach ($word->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (!$element instanceof \PhpOffice\PhpWord\Element\Table) continue;

                $headersMap = null;

                foreach ($element->getRows() as $row) {
                    $cells = $row->getCells();
                    if (!count($cells)) continue;

                    $vals = array_map(fn($c) => self::cellText($c), $cells);

                    // riconosci header
                    if ($headersMap === null) {
                        $score = 0;
                        foreach ($vals as $v) {
                            $hv = $normHeader($v);
                            if (in_array($hv, [
                                'numero','ubicazione','tipo_contratto','kglt','classe',
                                'anno_acquisto','scadenza_presidio','anno_serbatoio',
                                'riempimento_revisione','collaudo_revisione'
                            ], true)) $score++;
                        }
                        if ($score >= 3) {
                            $headersMap = [];
                            foreach ($vals as $i => $v) $headersMap[$i] = $normHeader($v);
                            continue;
                        }
                    }
                    if ($headersMap === null) continue;

                    // mappa riga -> chiave
                    $r = [];
                    foreach ($vals as $i => $v) {
                        $k = $headersMap[$i] ?? "col_$i";
                        $r[$k] = $v;
                    }

                    // must-have: numero + almeno ubicazione/contratto
                    $numero = $r['numero'] ?? null;
                    if (!is_numeric($numero)) continue;

                    $ubic      = $r['ubicazione']      ?? '';
                    $contratto = $r['tipo_contratto']   ?? '';
                    $tipoRaw   = ($r['kglt'] ?? '') ?: ($r['classe'] ?? '');

                    // tipo estintore & kg
                    preg_match('/(\d+)\s*kg/i', $tipoRaw, $mKg);
                    $kg      = $mKg[1] ?? null;
                    $tipoEst = $kg
                        ? \App\Models\TipoEstintore::where('kg', $kg)
                            ->where('descrizione', 'like', '%Polv%')->first()
                        : null;
                    $classi  = $tipoEst?->classificazione;

                    // date specifiche
                    $dataAcquisto     = $parseData($r['anno_acquisto']     ?? null);
                    $scadPresidio     = $parseData($r['scadenza_presidio'] ?? null);
                    $dataSerb         = $parseData($r['anno_serbatoio']    ?? null);
                    $lastRiempOrRev   = $parseData($r['riempimento_revisione'] ?? null);   // info, non usata nel calcolo
                    $lastCollaudoRev  = $parseData($r['collaudo_revisione']   ?? null);   // info, non usata nel calcolo

                    // calcoli da serbatoio
                    $periodoRev    = self::pickPeriodoRevisione($dataSerb, $classi);
                    $scadRevisione = self::nextDueAfter($dataSerb, $periodoRev);
                    $scadCollaudo  = !empty($classi?->anni_collaudo)
                                    ? self::nextDueAfter($dataSerb, (int)$classi->anni_collaudo)
                                    : null;
                    $fineVita      = self::addYears($dataSerb, $classi?->anni_fine_vita);

                    // allineamento ai mesi preferiti (revisione/collaudo)
                    $revAligned = $alignToPreferred($scadRevisione, $mesiPref);
                    $colAligned = $alignToPreferred($scadCollaudo,  $mesiPref);

                    // scadenza “capolinea” per sostituzione (min di tutte le scadenze note)
                    $scadenzaAssoluta = self::minDate($scadRevisione, $scadCollaudo, $fineVita, $scadPresidio);
                    // data operativa (mese prima, ma non prima di oggi) rispettando i mesi preferiti
                    $dataSostituzione = $alignToPreferred($scadenzaAssoluta, $mesiPref);

                    // anomalie (scan testo riga)
                    $joinedUp = mb_strtoupper(implode(' ', $vals));
                    $flag1 = str_contains($joinedUp, 'CARTELLO');
                    $flag2 = str_contains($joinedUp, 'TERRA');
                    $flag3 = str_contains($joinedUp, 'NUMERAZ');

                    $this->anteprima[] = [
                        'categoria'         => 'Estintore',
                        'progressivo'       => (int)$numero,
                        'ubicazione'        => $ubic,
                        'tipo_contratto'    => $contratto,
                        'tipo_estintore'    => $tipoRaw,
                        'tipo_estintore_id' => $tipoEst?->id,

                        // NUOVI
                        'data_acquisto'     => $dataAcquisto,
                        'scadenza_presidio' => $scadPresidio,

                        // SERBATOIO-BASED
                        'data_serbatoio'    => $dataSerb,

                        // Scadenze “teoriche”
                        'data_revisione'    => $revAligned ?: $scadRevisione,
                        'data_collaudo'     => $colAligned ?: $scadCollaudo,
                        'data_fine_vita'    => $fineVita,

                        // Operativa (mese prima della scadenza MIN, rispettando mesi preferiti)
                        'data_sostituzione' => $dataSostituzione,

                        // Anomalie
                        'flag_anomalia1'    => $flag1,
                        'flag_anomalia2'    => $flag2,
                        'flag_anomalia3'    => $flag3,
                        'note'              => null,
                    ];
                }
            }
        }
    }


    /* ----------------- CONFERMA ANTEPRIMA ---------------- */
    public function conferma(): void
    {
        // validazione minima
        foreach ($this->anteprima as $i => $row) {
            if (!$row['data_serbatoio']) {
                $this->addError("anteprima.$i.data_serbatoio", 'Obbligatorio');
            }

            if (!$row['tipo_estintore_id']) {
                $this->addError("anteprima.$i.tipo_estintore_id", 'Seleziona il tipo');
            }
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            $this->dispatch('toast', type: 'error',
                            message: 'Correggi i campi evidenziati');
            return;
        }

        foreach ($this->anteprima as $d) {
            ImportPresidio::create($d + [
                'cliente_id' => $this->clienteId,
                'sede_id'    => $this->sedeId,
            ]);
        }

        $this->reset(['file', 'anteprima']);
        $this->caricaPresidiSalvati();
    }

    /* ---------------- IMPORT DEFINITIVO ------------------ */
    public function confermaImportazione(bool $tutti = false): void
    {
        /* 1️⃣  Assicuriamoci che tutte le modifiche sui campi
        presenti in $presidiSalvati siano salvate su DB       */
        $this->salvaModifiche();          // <-- aggiunto

        /* 2️⃣  Ora ricarichiamo i record già aggiornati
        e li spostiamo nella tabella definitiva              */
        $query = ImportPresidio::where('cliente_id', $this->clienteId)
                ->when($this->sedeId, fn ($q) => $q->where('sede_id', $this->sedeId));
                if (!$tutti) {      // import selezionati
                    $mancanti = ImportPresidio::whereIn('id', $this->selezionati)
                                 ->whereNull('tipo_estintore_id')->count();
                } else {            // importa tutti
                    $mancanti = ImportPresidio::where('cliente_id', $this->clienteId)
                                 ->when($this->sedeId, fn($q)=>$q->where('sede_id',$this->sedeId))
                                 ->whereNull('tipo_estintore_id')->count();
                }
                
                if ($mancanti) {
                    $this->dispatch('toast', type: 'error', message: 'Completa il Tipo Estintore prima di importare');
                    return;
                }
        $import = $tutti
            ? $query->get()
            : $query->whereIn('id', $this->selezionati)->get();

        foreach ($import as $p) {
            Presidio::updateOrCreate(
                [
                    'cliente_id' => $p->cliente_id,
                    'sede_id'    => $p->sede_id,
                    'categoria'  => $p->categoria,
                    'progressivo'=> $p->progressivo,
                ],
                $p->only([
                    'ubicazione','tipo_contratto','tipo_estintore','tipo_estintore_id',
                    'flag_anomalia1','flag_anomalia2','flag_anomalia3','note',
                    'data_serbatoio','data_revisione','data_collaudo',
                    'data_fine_vita','data_sostituzione',
                ])
            );

            $p->delete();                 // rimuove dalla tabella d’import
        }

        $this->caricaPresidiSalvati();    // refresh lista
        $this->selezionati = [];          // svuota check
        $this->dispatch('toast', type: 'success', message: 'Importazione completata');
    }


    /* ------------------------ HELPERS -------------------- */
    private static function cellText($cell): string
    {
        $txt = '';
        foreach ($cell->getElements() as $e) {
            if ($e instanceof Text)         $txt .= $e->getText();
            elseif ($e instanceof TextRun) {
                foreach ($e->getElements() as $t)
                    if ($t instanceof Text) $txt .= $t->getText();
            }
        }
        return trim($txt);
    }

    private static function estraiDateDaArray(array $chunks, int $max = 2): array
    {
        $out = [];
        foreach ($chunks as $txt) {
            // 08.2024 | 9/2025 | 10-2023
            if (preg_match_all('/(\d{1,2})\s*[.\/-]\s*(\d{4})/', $txt, $m)) {
                foreach ($m[0] as $k=>$full) {
                    $mese = str_pad($m[1][$k],2,'0',STR_PAD_LEFT);
                    $anno = $m[2][$k];
                    $out[] = Carbon::createFromFormat('m.Y', "$mese.$anno")
                                   ->startOfMonth()->format('Y-m-d');
                }
            }
            // anno isolato
            if (preg_match_all('/\b(\d{4})\b/', $txt, $y)) {
                foreach ($y[1] as $anno) {
                    $out[] = Carbon::createFromDate($anno,1,1)->format('Y-m-d');
                }
            }
            $out = array_unique($out);
            if (count($out) >= $max) break;
        }
        return array_slice($out, 0, $max);
    }

    private static function addYears(?string $d, ?int $y): ?string
    {
        return $d && $y ? Carbon::parse($d)->addYears($y)->format('Y-m-d') : null;
    }
    /* --------------------------------------------------
    *   CANCELLAZIONE SINGOLA
    * --------------------------------------------------*/

    public function eliminaRigaAnteprima(int $index): void
    {
        unset($this->anteprima[$index]);
        $this->anteprima = array_values($this->anteprima); // reindicizza
    }

    public function eliminaImportato(int $id): void
    {
        ImportPresidio::whereKey($id)->delete();
        $this->caricaPresidiSalvati();
        $this->selezionati = array_values(
            array_diff($this->selezionati, [$id])
        );
        
    }

    /* --------------------------------------------------
    *   CANCELLAZIONE MULTIPLA
    * --------------------------------------------------*/
     /** Cancella tutti quelli spuntati */
     public function eliminaSelezionati(): void
     {
         if (! count($this->selezionati)) return;
 
         ImportPresidio::whereIn('id', $this->selezionati)->delete();
         $this->caricaPresidiSalvati();
 
         $this->selezionati = [];
         $this->dispatch('toast', type: 'success', message: 'Presidi eliminati');
     }
    public function eliminaPresidio(int $id): void
    {
        // 1. cancella dal DB
        ImportPresidio::whereKey($id)->delete();

        // 2. ricarica la lista (così eviti problemi di indice)
        $this->caricaPresidiSalvati();

        // 3. rimuovi dai selezionati (se era spuntato)
        unset($this->selezionati[$id]);

        // 4. toast / evento frontend (opzionale)
        $this->dispatch('toast', type: 'success', message: 'Presidio eliminato');
    }

    /* ---------------------- RENDER ---------------------- */
    public function render()
    {
        return view('livewire.presidi.importa-presidi');
    }


private static function nextDueAfter(?string $start, ?int $periodYears, ?Carbon $today = null): ?string
{
    if (!$start || !$periodYears || $periodYears <= 0) return null;

    $today = $today ?: now()->startOfDay();
    $due   = Carbon::parse($start)->addYears($periodYears)->startOfMonth();

    // Se la prima scadenza è nel passato, somma multipli del periodo finché non supera "oggi"
    while ($due->lte($today)) {
        $due->addYears($periodYears);
    }
    return $due->format('Y-m-d');
}

private static function pickPeriodoRevisione(?string $dataSerbatoio, $classi): ?int
{
    if (!$classi) return null;
    $cutover = Carbon::create(2024, 8, 31)->endOfDay();

    $base = $dataSerbatoio ? Carbon::parse($dataSerbatoio) : null;
    // Se vuoi usare SEMPRE il "prima", lascia solo ->anni_revisione_prima
    if ($base && $base->greaterThan($cutover) && !empty($classi->anni_revisione_dopo)) {
        return (int) $classi->anni_revisione_dopo;
    }
    return (int) $classi->anni_revisione_prima;
}

private static function minDate(?string ...$dates): ?string
{
    $valid = array_filter($dates);
    if (!$valid) return null;
    return collect($valid)->min();
}

}