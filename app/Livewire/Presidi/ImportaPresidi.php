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
use App\Services\Presidi\ProgressivoParser;

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
    private ?\Illuminate\Support\Collection $tipiCache = null;
// --- in cima alla classe ---
protected array $mesiPreferiti = [];

// --- in mount ---
public function mount(int $clienteId, ?int $sedeId = null): void
{
    $this->clienteId = $clienteId;
    $this->sedeId    = $sedeId;
    $this->tipiEstintore = TipoEstintore::orderBy('descrizione')
        ->pluck('descrizione', 'id')->toArray();
    $this->caricaPresidiSalvati();
    $this->mesiPreferiti = $this->caricaMesiPreferiti($this->clienteId);
}

private function loadTipiCache(): void
{
    if ($this->tipiCache) return;

    // prendi i campi che ti servono; se hai anche "tipo" (Polvere/CO2/Schiuma) usalo
    $this->tipiCache = \App\Models\TipoEstintore::query()
        ->select('id','descrizione','sigla','kg')   // aggiungi qui altri campi se li hai (es. 'tipo')
        ->orderBy('kg')->orderBy('id')
        ->get()
        ->map(function ($t) {
            $txt = strtoupper($t->descrizione.' '.$t->sigla);
            return [
                'id'          => $t->id,
                'kg'          => (int) $t->kg,
                'descrizione' => $t->descrizione,
                'sigla'       => $t->sigla,
                'agente'      => $this->detectAgent($txt), // deriviamo l’agente dal testo
                'full'        => $txt,
            ];
        });
}

/** Polvere / CO2 / Schiuma, estratti da una stringa generica */
private function detectAgent(string $txt): ?string
{
    $u = strtoupper($txt);
    if (preg_match('/\bCO\s*2\b|\bCO2\b|ANIDRIDE\s+CARBONICA/', $u)) return 'CO2';
    if (preg_match('/POLV|POLVER/', $u))                                  return 'POLVERE';
    if (preg_match('/SCHI|FOAM|AFFF/', $u))                                return 'SCHIUMA';
    return null;
}

private function detectCapacity(string $txt): ?int
{
    $u = strtoupper($txt);

    // 9 KG / 6 LT / 2L / 1,5 KG / KG 9 / LT. 6
    if (preg_match('/\b(\d{1,3})(?:[,.]\d+)?\s*(KG|KGS|KG\.|LT|L|LT\.)\b/u', $u, $m)) {
        return (int) $m[1];
    }
    if (preg_match('/\b(KG|KGS|KG\.)\s*(\d{1,3})(?:[,.]\d+)?\b/u', $u, $m)) {
        return (int) $m[2];
    }
    if (preg_match('/\b(LT|L|LT\.)\s*(\d{1,3})(?:[,.]\d+)?\b/u', $u, $m)) {
        return (int) $m[2];
    }

    // niente unità → non dedurre nulla
    return null;
}

/** Estrae una descrizione tipo (es. "200 LT SCHIUMA") dai valori riga. */
private static function extractTipoFromValues(array $vals): ?string
{
    foreach ($vals as $v) {
        $v = trim((string)$v);
        if ($v === '') continue;
        if (preg_match('/\b\d{1,3}\s*(KG|KGS|KG\.|LT|L|LT\.|LITRI)\b/i', $v)) {
            return $v;
        }
    }
    return null;
}

/** Riconosce estintori carrellati (>=100 LT o keyword CARRELLATO) */
public static function isCarrellatoText(?string $txt): bool
{
    $u = mb_strtoupper((string)$txt);
    if (str_contains($u, 'CARRELL')) return true;
    if (preg_match('/\b(\d{2,3})\s*(LT|L|LT\.|LITRI)\b/u', $u, $m)) {
        return (int)$m[1] >= 100;
    }
    return false;
}

/**
 * Prova a riconoscere il TipoEstintore dal testo della cella del DOCX (es. “6 Kg Polv”).
 * Ritorna l’ID migliore o null se non decide.
 */
private function guessTipoEstintoreId(string $raw): ?int
{
    $this->loadTipiCache();
    $u   = strtoupper($raw);
    $kg  = $this->detectCapacity($u);
    $ag  = $this->detectAgent($u);

    // 1) match pieno: agente + kg
    $cand = $this->tipiCache
        ->when($ag, fn($c) => $c->where('agente', $ag))
        ->when($kg, fn($c) => $c->where('kg', $kg));

    if ($cand->count() === 1) return $cand->first()['id'];
    if ($cand->count() > 1) {
        // tiebreak: descrizione che contiene sia agente che numero
        $best = $cand->firstWhere('full', fn($f) => str_contains($f, (string)$kg));
        return $best['id'] ?? $cand->first()['id'];
    }

    // 2) match “forte” solo su agente
    if ($ag) {
        $cand = $this->tipiCache->where('agente', $ag);
        if ($cand->count()) return $cand->first()['id'];
    }

    // 3) match solo su kg
    if ($kg) {
        $cand = $this->tipiCache->where('kg', $kg);
        if ($cand->count()) return $cand->first()['id'];
    }

    // 4) nulla di fatto
    return null;
}
// === UTIL PER MESI PREFERITI
private function caricaMesiPreferiti(int $clienteId): array
{
    $cliente = \App\Models\Cliente::find($clienteId);
    $sede    = $this->sedeId ? \App\Models\Sede::find($this->sedeId) : null;

    $candidates = [
        $sede?->mesi_visita ?? null,
        $cliente?->mesi_visita ?? null,
        $cliente?->mesi_intervento ?? null,
        $cliente?->mesi ?? null,
    ];
    foreach ($candidates as $raw) {
        $arr = $this->normalizeMonths($raw);
        if (!empty($arr)) return $arr;
    }
    return [];
}


private function normalizeMonths($raw): array
{
    if (!$raw) return [];
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $raw = $decoded;
        } else {
            $raw = array_map('trim', explode(',', $raw));
        }
    }
    if (is_array($raw)) {
        return collect($raw)
            ->map(fn($m)=>(int)$m)
            ->filter(fn($m)=>$m>=1 && $m<=12)
            ->unique()->sort()->values()->all();
    }
    return [];
}

private function ensureMesiPreferiti(): void
{
    if (!empty($this->mesiPreferiti)) return;
    $this->mesiPreferiti = $this->caricaMesiPreferiti($this->clienteId);
}


private function alignToPreferred(?string $due): ?string
{
    if (!$due) return null;
    $this->ensureMesiPreferiti();
    $months = $this->mesiPreferiti;
    $today  = now()->startOfMonth();
    $dueC   = Carbon::parse($due)->startOfMonth();

    if (!count($months)) {
        $cand = $dueC->copy()->subMonth();
        if ($cand->lt($today)) $cand = $today;
        return $cand->format('Y-m-d');
    }

    $candidates = [];
    $cur = $today->copy();
    while ($cur->lt($dueC)) { // strettamente prima della scadenza
        if (in_array((int)$cur->month, $months, true)) $candidates[] = $cur->copy();
        $cur->addMonth();
    }
    if ($candidates) return end($candidates)->format('Y-m-d');

    // fallback: primo mese preferito >= oggi entro 24 mesi
    $cur = $today->copy();
    for ($i=0;$i<24;$i++){
        if (in_array((int)$cur->month, $months, true)) return $cur->format('Y-m-d');
        $cur->addMonth();
    }
    return $today->format('Y-m-d');
}

// === RICALCOLO DATE PER UNA RIGA (anteprima o salvati)
private function ricalcolaDatePerRiga(array &$row): void
{
    $dataSerb = $row['data_serbatoio'] ?? null;
    $tipoId   = $row['tipo_estintore_id'] ?? null;
    $lastRev  = $row['data_ultima_revisione'] ?? null;
    if ($lastRev && Carbon::parse($lastRev)->startOfDay()->gt(now()->startOfDay())) {
        $lastRev = null;
        $row['data_ultima_revisione'] = null;
    }
    if (!$dataSerb || !$tipoId) {
        // se manca uno dei due, azzero le derivate (meglio esplicito)
        $row['data_revisione']    = null;
        $row['data_collaudo']     = null;
        $row['data_fine_vita']    = null;
        // la sostituzione operativa resta calcolata solo se c’è qualcosa
        $row['data_sostituzione'] = null;
        return;
    }

    $tipo = TipoEstintore::with('classificazione')->find($tipoId);
    $classi = $tipo?->classificazione;

    $isCarrellato = self::isCarrellatoText(($row['tipo_estintore'] ?? '') . ' ' . ($tipo?->descrizione ?? ''));

    if ($isCarrellato) {
        $periodoRev    = 5;
        $baseRevisione = $dataSerb;
        $scadRevisione = self::nextDueAfter($baseRevisione, $periodoRev);
        $scadCollaudo  = null;
        $fineVita      = null;
    } else {
        $periodoRev    = self::pickPeriodoRevisione(
            $dataSerb,
            $classi,
            $lastRev,
            $row['marca_serbatoio'] ?? null
        );
        $baseRevisione = $lastRev ?: $dataSerb;
        $scadRevisione = self::nextDueAfter($baseRevisione, $periodoRev);
        $scadCollaudo  = !empty($classi?->anni_collaudo) ? self::nextDueAfter($dataSerb, (int)$classi->anni_collaudo) : null;
        $fineVita      = self::addYears($dataSerb, $classi?->anni_fine_vita);
    }

    $row['data_revisione'] = $this->visitaOnOrBefore($scadRevisione) ?? $scadRevisione;

    $row['data_collaudo']  = $this->visitaOnOrBefore($scadCollaudo)  ?? $scadCollaudo;
    $row['data_fine_vita'] = $this->visitaOnOrBefore($fineVita)      ?? $fineVita;
    

    // “capolinea” per sostituzione = min scadenze + allineamento
    $scadenzaAssoluta = self::minDate(
        $scadRevisione, $scadCollaudo, $fineVita, $row['scadenza_presidio'] ?? null
    );
    $row['data_sostituzione'] = $this->visitaOnOrBefore($scadenzaAssoluta);

    }
// === Azione chiamata da Blade quando cambi tipo o serbatoio
public function ricalcola(string $scope, int $index): void
{
    if ($scope === 'anteprima' && isset($this->anteprima[$index])) {
        $row = $this->anteprima[$index];
        $this->ricalcolaDatePerRiga($row);
        $this->anteprima[$index] = $row;
    }
    if ($scope === 'salvati' && isset($this->presidiSalvati[$index])) {
        $row = $this->presidiSalvati[$index];
        $this->ricalcolaDatePerRiga($row);
        $this->presidiSalvati[$index] = $row;
    }
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

    private static function normHeaderIdranti(string $h): string
    {
        $h = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $h)));
        $map = [
            'N' => 'numero',
            'UBICAZIONE' => 'ubicazione',
            'NOTE' => 'note',
            '45' => 'idrante_45',
            '70' => 'idrante_70',
            'NASPO' => 'idrante_naspo',
            'SOPRA SUOLO' => 'sopra_suolo',
            'SOTTO SUOLO' => 'sotto_suolo',
            'MANCA CARTELLO' => 'anomalia_cartello',
            'MANCA LANCIA O DA SOSTITUIRE' => 'anomalia_lancia',
            'LASTRA S.CRASH DANNEGG O MANCANTE INDICARE MISURE' => 'anomalia_lastra',
        ];
        return $map[$h] ?? $h;
    }

    private static function normHeaderPorte(string $h): string
    {
        $h = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $h)));
        $map = [
            'N' => 'numero',
            'UBICAZIONE' => 'ubicazione',
            'MALFUNZIONAMENTI' => 'note',
            'ANTE (1 O 2)' => 'porta_tipo',
            'ANTE  (1 O 2)' => 'porta_tipo',
            'MANIGLIONE NON CE' => 'anomalia_maniglione',
            'TIRATA MOLLA' => 'anomalia_molla',
            'NUMERAZIONE' => 'anomalia_numerazione',
        ];
        return $map[$h] ?? $h;
    }

    private static function detectTableTypeFromRow(array $vals): ?string
    {
        $joined = mb_strtoupper(implode(' ', $vals));
        if (str_contains($joined, 'IDRANTI')) return 'idranti';
        if (str_contains($joined, 'PORTE')) return 'porte';
        if (str_contains($joined, 'NASPO') || str_contains($joined, 'SOPRA SUOLO') || str_contains($joined, 'SOTTO SUOLO') || str_contains($joined, 'ATTACCO VVF')) {
            return 'idranti';
        }
        if (str_contains($joined, 'MANIGLIONE') || str_contains($joined, 'TIRATA MOLLA') || str_contains($joined, 'MALFUNZIONAMENTI') || str_contains($joined, 'ANTE')) {
            return 'porte';
        }
        return null;
    }

    public static function parseDataCell(?string $txt): ?string
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

    public static function parseMarcaSerbatoio(?string $txt): ?string
    {
        $txt = trim((string)$txt);
        if ($txt === '') return null;
        if (preg_match('/^\s*([A-Z]{1,10})\b/u', mb_strtoupper($txt), $m)) {
            return $m[1];
        }
        return null;
    }

    private static function isMarcaMb(?string $marca): bool
    {
        return mb_strtoupper(trim((string)$marca)) === 'MB';
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
                'ANNO PRODUZIONE'            => 'anno_serbatoio',
                'RIEMPIMENTO/ REVISIONE'     => 'riempimento_revisione',
                'RIEMPIMENTO/REVISIONE'      => 'riempimento_revisione',
                'ULTIMA REVISIONE'           => 'riempimento_revisione',
                'COLLAUDO/ REVISIONE'        => 'collaudo_revisione',
                'COLLAUDO/REVISIONE'         => 'collaudo_revisione',
                'PROSSIMA REVISIONE'         => 'collaudo_revisione',
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

        // -----------------------------------------------------------------------

        // carica e parse
        $path = Storage::disk('local')->path($this->file->store('import_presidi', 'local'));
        $word = IOFactory::load($path);

        $this->anteprima = [];
        foreach ($word->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (!$element instanceof \PhpOffice\PhpWord\Element\Table) continue;

                $headersMap = null;

                $tableType = null;
                foreach ($element->getRows() as $row) {
                    $cells = $row->getCells();
                    if (!count($cells)) continue;

                    $vals = array_map(fn($c) => self::cellText($c), $cells);

                    if ($tableType === null) {
                        $tableType = self::detectTableTypeFromRow($vals);
                    }

                    // riconosci header
                    if ($headersMap === null) {
                        if ($tableType === 'idranti') {
                            $up = array_map(fn($v)=>mb_strtoupper(trim($v)), $vals);
                            $hasN = (bool) array_filter($up, fn($v)=>preg_match('/^N\\b|^N\\./', $v));
                            if ($hasN && in_array('UBICAZIONE', $up, true)) {
                                $headersMap = [];
                                foreach ($vals as $i => $v) $headersMap[$i] = self::normHeaderIdranti($v);
                                continue;
                            }
                        } elseif ($tableType === 'porte') {
                            $up = array_map(fn($v)=>mb_strtoupper(trim($v)), $vals);
                            $hasN = (bool) array_filter($up, fn($v)=>preg_match('/^N\\b|^N\\./', $v));
                            if ($hasN && in_array('UBICAZIONE', $up, true)) {
                                $headersMap = [];
                                foreach ($vals as $i => $v) $headersMap[$i] = self::normHeaderPorte($v);
                                continue;
                            }
                        } else {
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
                    }
                    if ($headersMap === null) continue;

                    // mappa riga -> chiave
                    $r = [];
                    foreach ($vals as $i => $v) {
                        $k = $headersMap[$i] ?? null;
                        if (!$k) {
                            $k = "col_$i";
                        }
                        $r[$k] = $v;
                    }

                    // must-have: progressivo con parte numerica
                    $numeroRaw = $r['numero'] ?? null;
                    $prog = ProgressivoParser::parse($numeroRaw);
                    if (!$prog) continue;

                    $ubic      = $r['ubicazione']      ?? '';
                    $contratto = $r['tipo_contratto']   ?? '';

                    if ($tableType === 'idranti') {
                        $note = $r['note'] ?? null;
                        $idrTipo = null;
                        $idrLen  = null;
                        if (!empty($r['idrante_45'] ?? null)) { $idrTipo = '45'; $idrLen = $r['idrante_45']; }
                        if (!empty($r['idrante_70'] ?? null)) { $idrTipo = '70'; $idrLen = $r['idrante_70']; }
                        if (!empty($r['idrante_naspo'] ?? null)) { $idrTipo = 'NASPO'; $idrLen = $r['idrante_naspo']; }
                        $sopra = !empty($r['sopra_suolo'] ?? null);
                        $sotto = !empty($r['sotto_suolo'] ?? null);
                        $flag1 = !empty($r['anomalia_cartello'] ?? null);
                        $flag2 = !empty($r['anomalia_lancia'] ?? null);
                        $flag3 = !empty($r['anomalia_lastra'] ?? null);

                        $this->anteprima[] = [
                            'categoria'         => 'Idrante',
                            'progressivo'       => $prog['label'],
                            'progressivo_num'   => $prog['num'],
                            'progressivo_suffix'=> $prog['suffix'],
                            'ubicazione'        => $ubic,
                            'tipo_contratto'    => $contratto,
                            'tipo_estintore'    => null,
                            'tipo_estintore_id' => null,
                            'idrante_tipo'      => $idrTipo,
                            'idrante_lunghezza' => $idrLen,
                            'idrante_sopra_suolo' => $sopra,
                            'idrante_sotto_suolo' => $sotto,
                            'note'              => $note,
                            'flag_anomalia1'    => $flag1,
                            'flag_anomalia2'    => $flag2,
                            'flag_anomalia3'    => $flag3,
                            'data_serbatoio'    => null,
                            'data_revisione'    => null,
                            'data_collaudo'     => null,
                            'data_fine_vita'    => null,
                            'data_sostituzione' => null,
                        ];
                        continue;
                    }

                    if ($tableType === 'porte') {
                        $note = $r['note'] ?? null;
                        $portaTipo = $r['porta_tipo'] ?? null;
                        $flag1 = !empty($r['anomalia_maniglione'] ?? null);
                        $flag2 = !empty($r['anomalia_molla'] ?? null);
                        $flag3 = !empty($r['anomalia_numerazione'] ?? null);
                        $contratto = $r['tipo_contratto'] ?? $contratto;

                        $this->anteprima[] = [
                            'categoria'         => 'Porta',
                            'progressivo'       => $prog['label'],
                            'progressivo_num'   => $prog['num'],
                            'progressivo_suffix'=> $prog['suffix'],
                            'ubicazione'        => $ubic,
                            'tipo_contratto'    => $contratto,
                            'tipo_estintore'    => null,
                            'tipo_estintore_id' => null,
                            'porta_tipo'        => $portaTipo,
                            'note'              => $note,
                            'flag_anomalia1'    => $flag1,
                            'flag_anomalia2'    => $flag2,
                            'flag_anomalia3'    => $flag3,
                            'data_serbatoio'    => null,
                            'data_revisione'    => null,
                            'data_collaudo'     => null,
                            'data_fine_vita'    => null,
                            'data_sostituzione' => null,
                        ];
                        continue;
                    }

                    $tipoRaw   = trim((($r['kglt'] ?? '') . ' ' . ($r['classe'] ?? '')));
                    if ($tipoRaw === '') {
                        $tipoRaw = self::extractTipoFromValues($vals) ?? '';
                    }
                    $joinedUp  = mb_strtoupper(implode(' ', $vals));
                    $isCarrellato = self::isCarrellatoText($tipoRaw . ' ' . $joinedUp);
                    
                    // prima prova col campo dedicato, poi — se vuoto — con tutta la riga
                    $tipoEstId = $this->guessTipoEstintoreId($tipoRaw !== '' ? $tipoRaw : $joinedUp);
                    $tipoEst   = $tipoEstId ? TipoEstintore::with('classificazione')->find($tipoEstId) : null;
                    $classi    = $tipoEst?->classificazione;

                    // date specifiche
                    $dataAcquisto     = $parseData($r['anno_acquisto']     ?? null);
                    $scadPresidio     = $parseData($r['scadenza_presidio'] ?? null);
                    $dataSerbatoioRaw = $r['anno_serbatoio'] ?? null;
                    $dataSerb         = $parseData($dataSerbatoioRaw);
                    $marcaSerbatoio   = self::parseMarcaSerbatoio($dataSerbatoioRaw);
                    $dataUltimaRevisione = self::parseDataCell($r['riempimento_revisione'] ?? null); // <- USATA per la prossima revisione
                    if ($dataUltimaRevisione && Carbon::parse($dataUltimaRevisione)->startOfDay()->gt(now()->startOfDay())) {
                        $dataUltimaRevisione = null;
                    }
                    $lastCollaudoRev     = self::parseDataCell($r['collaudo_revisione']   ?? null); // info (non usata)

                    if ($isCarrellato) {
                        $alt1 = self::parseDataCell($r['tipo_contratto'] ?? null);
                        $alt2 = self::parseDataCell($r['col_5'] ?? null);
                        $cands = array_filter([$dataSerb, $alt1, $alt2]);
                        if (!$dataSerb && $cands) {
                            sort($cands);
                            $dataSerb = $cands[0];
                        }
                        // se il campo "tipo_contratto" è in realtà una data, non tenerlo come contratto
                        if ($alt1) {
                            $contratto = '';
                        }
                        if ($alt1 && $alt2) {
                            $dataUltimaRevisione = null;
                        }
                    }
                    
                    // calcoli da serbatoio
                    if ($isCarrellato) {
                        $periodoRev = 5;
                        $baseRevisione = $dataSerb;
                        $scadRevisione = self::nextDueAfter($baseRevisione, $periodoRev);
                        $scadCollaudo = null;
                        $fineVita = null;
                    } else {
                        $periodoRev    = self::pickPeriodoRevisione($dataSerb, $classi, $dataUltimaRevisione, $marcaSerbatoio);
                        $baseRevisione = $dataUltimaRevisione ?: $dataSerb;
                        $scadRevisione = self::nextDueAfter($baseRevisione, $periodoRev);
                        $scadCollaudo  = !empty($classi?->anni_collaudo)
                                        ? self::nextDueAfter($dataSerb, (int)$classi->anni_collaudo)
                                        : null;
                        $fineVita      = self::addYears($dataSerb, $classi?->anni_fine_vita);
                    }

                    // allineamento ai mesi preferiti (revisione/collaudo)
                    $revAligned = $this->visitaOnOrBefore($scadRevisione);   // <= inclusiva
                    $colAligned = $this->visitaOnOrBefore($scadCollaudo);       // <= strettamente prima
                    $fineAligned= $this->visitaOnOrBefore($fineVita);
                    
                   
                    
                    // scadenza “capolinea” per sostituzione (min di tutte le scadenze note)
                    // data operativa (mese prima, ma non prima di oggi) rispettando i mesi preferiti
                    $scadenzaAssoluta = self::minDate($scadRevisione, $scadCollaudo, $fineVita, $scadPresidio);
                    $dataSostituzione = $this->visitaOnOrBefore($scadenzaAssoluta); // <-- inclusiva

                    // anomalie (scan testo riga)
                    $joinedUp = mb_strtoupper(implode(' ', $vals));
                    $flag1 = str_contains($joinedUp, 'CARTELLO');
                    $flag2 = str_contains($joinedUp, 'TERRA');
                    $flag3 = str_contains($joinedUp, 'NUMERAZ');

                    $noteExtra = null;
                    if (!empty($r['collaudo_revisione'] ?? null) && !self::parseDataCell($r['collaudo_revisione'])) {
                        $noteExtra = trim((string)$r['collaudo_revisione']);
                    }

                    $this->anteprima[] = [
                        'categoria'         => 'Estintore',
                        'progressivo'       => $prog['label'],
                        'progressivo_num'   => $prog['num'],
                        'progressivo_suffix'=> $prog['suffix'],
                        'ubicazione'        => $ubic,
                        'tipo_contratto'    => $contratto,
                        'tipo_estintore'    => $tipoRaw,
                        'tipo_estintore_id' => $tipoEst?->id,
                        
                        // NUOVI
                        'data_acquisto'     => $dataAcquisto,
                        'scadenza_presidio' => $scadPresidio,

                        // SERBATOIO-BASED
                        'data_serbatoio'    => $dataSerb,
                        'marca_serbatoio'   => $marcaSerbatoio,
                        'data_ultima_revisione'  => $dataUltimaRevisione, 
                        // Scadenze “teoriche”
                        'data_revisione'    => $revAligned ?? $scadRevisione,
                        'data_collaudo'     => $colAligned ?? $scadCollaudo,
                        'data_fine_vita'    => $fineAligned ?? $fineVita,
                        'data_sostituzione' => $dataSostituzione,

                        // Operativa (mese prima della scadenza MIN, rispettando mesi preferiti)
                        'data_sostituzione' => $dataSostituzione,

                        // Anomalie
                        'flag_anomalia1'    => $flag1,
                        'flag_anomalia2'    => $flag2,
                        'flag_anomalia3'    => $flag3,
                        'note'              => $noteExtra,
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
            if (($row['categoria'] ?? '') !== 'Estintore') continue;
            if (!$row['data_serbatoio']) {
                $this->addError("anteprima.$i.data_serbatoio", 'Obbligatorio');
            }

            if (!$row['tipo_estintore_id'] && !self::isCarrellatoText($row['tipo_estintore'] ?? '')) {
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
        $import = $tutti
            ? $query->get()
            : $query->whereIn('id', $this->selezionati)->get();

        $mancanti = $import->filter(function ($p) {
            return ($p->categoria === 'Estintore')
                && !$p->tipo_estintore_id
                && !self::isCarrellatoText($p->tipo_estintore ?? '');
        })->count();

        if ($mancanti) {
            $this->dispatch('toast', type: 'error', message: 'Completa il Tipo Estintore prima di importare');
            return;
        }

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
                    'flag_anomalia1','flag_anomalia2','flag_anomalia3','note','data_acquisto','scadenza_presidio', 
                    'data_serbatoio','data_revisione','data_collaudo',
                    'data_fine_vita','data_sostituzione','data_ultima_revisione','marca_serbatoio',
                    'idrante_tipo','idrante_lunghezza','idrante_sopra_suolo','idrante_sotto_suolo','porta_tipo',
                ])
            );

            $p->delete();                 // rimuove dalla tabella d’import
        }

        $this->caricaPresidiSalvati();    // refresh lista
        $this->selezionati = [];          // svuota check
        $this->dispatch('toast', type: 'success', message: 'Importazione completata');
        $this->dispatch('presidi-updated');
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

    public static function addYears(?string $d, ?int $y): ?string
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
        $this->selezionati = array_values(array_diff($this->selezionati, [$id]));

        // 4. toast / evento frontend (opzionale)
        $this->dispatch('toast', type: 'success', message: 'Presidio eliminato');
    }

    /* ---------------------- RENDER ---------------------- */
    public function render()
    {
        return view('livewire.presidi.importa-presidi');
    }


    private const CUTOFF = '2024-08-31';

    private static function periodYearsFor(string $dataSerbatoio, int $anniPrima, int $anniDopo): int
    {
        // Se il serbatoio è stato costruito entro il 31/08/2024 resta SEMPRE nel regime "prima".
        $cutoff = Carbon::parse(self::CUTOFF)->endOfDay();
        return Carbon::parse($dataSerbatoio)->lte($cutoff) ? $anniPrima : $anniDopo;
    }
    
    /**
     * Calcola la prossima scadenza di revisione > today.
     * Ritorna 'Y-m-d' (primo giorno del mese della scadenza).
     */
    public static function nextDueAfter(?string $start, ?int $periodYears, ?Carbon $today = null): ?string
    {
        if (!$start || !$periodYears || $periodYears <= 0) return null;
    
        $today = ($today ?? now())->startOfDay();
    
        // Prima scadenza dal serbatoio (allineata a inizio mese)
        $due = Carbon::parse($start)
            ->startOfMonth()
            ->addYears($periodYears);
    
        // Salta multipli del periodo finché non è > oggi
        while ($due->lte($today)) {
            $due->addYears($periodYears);
        }
    
        return $due->format('Y-m-d');
    }
    
    /**
     * Dato una scadenza, restituisce la "visita subito prima" (mese pianificato < mese scadenza).
     * Esempio: scadenza 2027-05-01 con visite [5,11] => 2026-11-01.
     */
    private static function previousVisitBefore(string $dueYmd, array $visitMonths): string
    {
        sort($visitMonths); // es. [5, 11]
        $due  = Carbon::parse($dueYmd);
        $year = $due->year;
        $m    = $due->month;
    
        $before = array_values(array_filter($visitMonths, fn($vm) => $vm < $m));
        if (!empty($before)) {
            $month = end($before);
        } else {
            $month = end($visitMonths);
            $year -= 1;
        }
    
        return Carbon::create($year, $month, 1)->format('Y-m-d');
    }
private function visitaOnOrBefore(?string $due): ?string
{
    if (!$due) return null;
    $this->ensureMesiPreferiti();
    $months = $this->mesiPreferiti ?? [];
        $dueC   = Carbon::parse($due)->startOfMonth();

        // Nessuna preferenza: per la revisione tieni il mese della scadenza
        if (!count($months)) {
            return $dueC->format('Y-m-d');
        }

        // Se il mese della scadenza è tra i mesi visita → usa quello
        if (in_array((int)$dueC->month, $months, true)) {
            return $dueC->format('Y-m-d');
        }

        // Altrimenti: mese visita immediatamente precedente
        return self::previousVisitBefore($dueC->format('Y-m-d'), $months);
    }
    public static function pickPeriodoRevisione(?string $dataSerbatoio, $classi, ?string $dataUltimaRevisione = null, ?string $marcaSerbatoio = null): ?int
    {
        if (!$classi) return null;
    
        $cutover = Carbon::parse(self::CUTOFF)->startOfDay();
        $after   = false;

        // Caso speciale: marca MB con serbatoio prima del cutoff → sempre "prima"
        if (self::isMarcaMb($marcaSerbatoio) && $dataSerbatoio) {
            if (Carbon::parse($dataSerbatoio)->startOfDay()->lt($cutover)) {
                return (int) $classi->anni_revisione_prima;
            }
        }
    
        if ($dataSerbatoio && Carbon::parse($dataSerbatoio)->startOfDay()->gte($cutover)) {
            $after = true;
        }
        if ($dataUltimaRevisione && Carbon::parse($dataUltimaRevisione)->startOfDay()->gte($cutover)) {
            $after = true;
        }
    
        if ($after && !empty($classi->anni_revisione_dopo)) {
            return (int) $classi->anni_revisione_dopo;
        }
        return (int) $classi->anni_revisione_prima;
    }
    

public static function minDate(?string ...$dates): ?string
{
    $valid = array_filter($dates);
    if (!$valid) return null;
    return collect($valid)->min();
}

}
