<?php

namespace App\Services\Presidi;

use App\Models\Cliente;
use App\Models\Presidio;
use App\Models\Sede;
use App\Models\TipoEstintore;
use App\Models\TipoPresidio;
use App\Livewire\Presidi\ImportaPresidi;
use App\Services\Presidi\ProgressivoParser;
use Illuminate\Support\Carbon;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;

class DocxPresidiImporter
{
    private int $clienteId;
    private ?int $sedeId;
    private string $azione;
    private array $mesiPreferiti = [];
    private ?\Illuminate\Support\Collection $tipiCache = null;
    private array $existingKeys = [];

    public function __construct(int $clienteId, ?int $sedeId = null, string $azione = 'skip_duplicates')
    {
        $this->clienteId = $clienteId;
        $this->sedeId = $sedeId;
        $this->azione = $azione;
        $this->mesiPreferiti = $this->caricaMesiPreferiti();
        $this->loadExistingKeys();
    }

    public function importFromPath(string $path): array
    {
        $word = IOFactory::load($path);
        $importati = 0;
        $saltati = 0;

        foreach ($word->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (!$element instanceof Table) continue;

                $headersMap = null;
                $tableType = null;

                foreach ($element->getRows() as $row) {
                    $cells = $row->getCells();
                    if (!count($cells)) continue;

                    $vals = array_map(fn($c) => self::cellText($c), $cells);

                    if ($tableType === null) {
                        $tableType = self::detectTableTypeFromRow($vals);
                    }

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
                                $hv = self::normalizzaHeader($v);
                                if (in_array($hv, [
                                    'numero','ubicazione','tipo_contratto','kglt','classe',
                                    'anno_acquisto','scadenza_presidio','anno_serbatoio',
                                    'riempimento_revisione','collaudo_revisione'
                                ], true)) {
                                    $score++;
                                }
                            }
                            if ($score >= 3) {
                                $headersMap = [];
                                foreach ($vals as $i => $v) $headersMap[$i] = self::normalizzaHeader($v);
                                continue;
                            }
                        }
                    }
                    if ($headersMap === null) continue;

                    $r = [];
                    foreach ($vals as $i => $v) {
                        $k = $headersMap[$i] ?? null;
                        if (!$k) {
                            $k = "col_$i";
                        }
                        $r[$k] = $v;
                    }

                    $numeroRaw = $r['numero'] ?? null;
                    $prog = ProgressivoParser::parse($numeroRaw);
                    if (!$prog) continue;

                    $ubic      = $r['ubicazione'] ?? '';
                    $contratto = $r['tipo_contratto'] ?? '';

                    if ($tableType === 'idranti') {
                        if ($this->shouldSkipExisting('Idrante', $prog['label'])) {
                            $saltati++;
                            continue;
                        }
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

                        if ($idrTipo) {
                            TipoPresidio::firstOrCreate([
                                'categoria' => 'Idrante',
                                'nome' => mb_strtoupper(trim((string) $idrTipo)),
                            ]);
                        }

                        $sedeId = $this->resolveSedeId();
                        Presidio::updateOrCreate(
                            [
                                'cliente_id' => $this->clienteId,
                                'sede_id'    => $sedeId,
                                'categoria'  => 'Idrante',
                                'progressivo'=> $prog['label'],
                            ],
                            [
                                'ubicazione'        => $ubic,
                                'tipo_contratto'    => $contratto,
                                'idrante_tipo'      => $idrTipo,
                                'idrante_lunghezza' => $idrLen,
                                'idrante_sopra_suolo'=> $sopra,
                                'idrante_sotto_suolo'=> $sotto,
                                'flag_anomalia1'    => $flag1,
                                'flag_anomalia2'    => $flag2,
                                'flag_anomalia3'    => $flag3,
                                'note'              => $note,
                            ]
                        );
                        $this->markExisting('Idrante', $prog['label']);
                        $importati++;
                        continue;
                    }

                    if ($tableType === 'porte') {
                        if ($this->shouldSkipExisting('Porta', $prog['label'])) {
                            $saltati++;
                            continue;
                        }
                        $note = $r['note'] ?? null;
                        $portaTipo = $r['porta_tipo'] ?? null;
                        $flag1 = !empty($r['anomalia_maniglione'] ?? null);
                        $flag2 = !empty($r['anomalia_molla'] ?? null);
                        $flag3 = !empty($r['anomalia_numerazione'] ?? null);
                        $contratto = $r['tipo_contratto'] ?? $contratto;

                        if ($portaTipo) {
                            TipoPresidio::firstOrCreate([
                                'categoria' => 'Porta',
                                'nome' => mb_strtoupper(trim((string) $portaTipo)),
                            ]);
                        }

                        $sedeId = $this->resolveSedeId();
                        Presidio::updateOrCreate(
                            [
                                'cliente_id' => $this->clienteId,
                                'sede_id'    => $sedeId,
                                'categoria'  => 'Porta',
                                'progressivo'=> $prog['label'],
                            ],
                            [
                                'ubicazione'        => $ubic,
                                'tipo_contratto'    => $contratto,
                                'porta_tipo'        => $portaTipo,
                                'flag_anomalia1'    => $flag1,
                                'flag_anomalia2'    => $flag2,
                                'flag_anomalia3'    => $flag3,
                                'note'              => $note,
                            ]
                        );
                        $this->markExisting('Porta', $prog['label']);
                        $importati++;
                        continue;
                    }

                    $tipoRaw  = trim((($r['kglt'] ?? '') . ' ' . ($r['classe'] ?? '')));
                    if ($tipoRaw === '') {
                        $tipoRaw = self::extractTipoFromValues($vals) ?? '';
                    }
                    $joinedUp = mb_strtoupper(implode(' ', $vals));
                    $isCarrellato = ImportaPresidi::isCarrellatoText($tipoRaw . ' ' . $joinedUp);

                    $tipoEstId = $this->guessTipoEstintoreId($tipoRaw !== '' ? $tipoRaw : $joinedUp);
                    if ($isCarrellato && !$tipoEstId) {
                        $cap = $this->detectCapacity($tipoRaw !== '' ? $tipoRaw : $joinedUp);
                        if ($cap) {
                            $tipoEstId = TipoEstintore::where('kg', $cap)
                                ->where('sigla', 'like', 'ESSI%')
                                ->value('id');
                        }
                    }
                    $tipoEst   = $tipoEstId ? TipoEstintore::with('classificazione')->find($tipoEstId) : null;
                    $classi    = $tipoEst?->classificazione;

                    $dataAcquisto      = self::parseData($r['anno_acquisto'] ?? null);
                    $scadPresidio      = self::parseData($r['scadenza_presidio'] ?? null);
                    $dataSerbatoioRaw  = $r['anno_serbatoio'] ?? null;
                    $dataSerb          = self::parseData($dataSerbatoioRaw);
                    $marcaSerbatoio    = ImportaPresidi::parseMarcaSerbatoio($dataSerbatoioRaw);
                    $dataUltimaRev     = ImportaPresidi::parseDataCell($r['riempimento_revisione'] ?? null);
                    if ($dataUltimaRev && Carbon::parse($dataUltimaRev)->startOfDay()->gt(now()->startOfDay())) {
                        $dataUltimaRev = null;
                    }

                    if ($isCarrellato) {
                        $alt1 = ImportaPresidi::parseDataCell($r['tipo_contratto'] ?? null);
                        $alt2 = ImportaPresidi::parseDataCell($r['col_5'] ?? null);
                        $cands = array_filter([$dataSerb, $alt1, $alt2]);
                        if (!$dataSerb && $cands) {
                            sort($cands);
                            $dataSerb = $cands[0];
                        }
                        if ($alt1) {
                            $contratto = '';
                        }
                        if ($alt1 && $alt2) {
                            $dataUltimaRev = null;
                        }
                    }

                    if (!$dataSerb || (!$tipoEstId && !$isCarrellato)) {
                        $saltati++;
                        continue;
                    }

                    if ($isCarrellato) {
                        $periodoRev    = 5;
                        $baseRevisione = $dataSerb;
                        $scadRevisione = ImportaPresidi::nextDueAfter($baseRevisione, $periodoRev);
                        $scadCollaudo  = null;
                        $fineVita      = null;
                    } else {
                        $periodoRev    = ImportaPresidi::pickPeriodoRevisione($dataSerb, $classi, $dataUltimaRev, $marcaSerbatoio);
                        $baseRevisione = $dataUltimaRev ?: $dataSerb;
                        $scadRevisione = ImportaPresidi::nextDueAfter($baseRevisione, $periodoRev);
                        $scadCollaudo  = !empty($classi?->anni_collaudo)
                            ? ImportaPresidi::nextDueAfter($dataSerb, (int)$classi->anni_collaudo)
                            : null;
                        $fineVita      = ImportaPresidi::addYears($dataSerb, $classi?->anni_fine_vita);
                    }

                    $revAligned  = self::visitaOnOrBeforeWithMonths($scadRevisione, $this->mesiPreferiti);
                    $colAligned  = self::visitaOnOrBeforeWithMonths($scadCollaudo, $this->mesiPreferiti);
                    $fineAligned = self::visitaOnOrBeforeWithMonths($fineVita, $this->mesiPreferiti);

                    $scadenzaAssoluta = ImportaPresidi::minDate($scadRevisione, $scadCollaudo, $fineVita, $scadPresidio);
                    $dataSostituzione = self::visitaOnOrBeforeWithMonths($scadenzaAssoluta, $this->mesiPreferiti);

                    $flag1 = str_contains($joinedUp, 'CARTELLO');
                    $flag2 = str_contains($joinedUp, 'TERRA');
                    $flag3 = str_contains($joinedUp, 'NUMERAZ');

                    $sedeId = $this->resolveSedeId();

                    if ($this->shouldSkipExisting('Estintore', $prog['label'])) {
                        $saltati++;
                        continue;
                    }

                    $noteExtra = null;
                    if (!empty($r['collaudo_revisione'] ?? null) && !ImportaPresidi::parseDataCell($r['collaudo_revisione'])) {
                        $noteExtra = trim((string)$r['collaudo_revisione']);
                    }

                    Presidio::updateOrCreate(
                        [
                            'cliente_id' => $this->clienteId,
                            'sede_id'    => $sedeId,
                            'categoria'  => 'Estintore',
                            'progressivo'=> $prog['label'],
                        ],
                        [
                            'ubicazione'           => $ubic,
                            'tipo_contratto'       => $contratto,
                            'tipo_estintore'       => $tipoRaw,
                            'tipo_estintore_id'    => $tipoEstId,
                            'flag_anomalia1'       => $flag1,
                            'flag_anomalia2'       => $flag2,
                            'flag_anomalia3'       => $flag3,
                            'note'                 => $noteExtra,
                            'data_acquisto'        => $dataAcquisto,
                            'scadenza_presidio'    => $scadPresidio,
                            'data_serbatoio'       => $dataSerb,
                            'marca_serbatoio'      => $marcaSerbatoio,
                            'data_ultima_revisione'=> $dataUltimaRev,
                            'data_revisione'       => $revAligned ?? $scadRevisione,
                            'data_collaudo'        => $colAligned ?? $scadCollaudo,
                            'data_fine_vita'       => $fineAligned ?? $fineVita,
                            'data_sostituzione'    => $dataSostituzione,
                        ]
                    );

                    $this->markExisting('Estintore', $prog['label']);
                    $importati++;
                }
            }
        }

        return ['importati' => $importati, 'saltati' => $saltati];
    }

    private function loadExistingKeys(): void
    {
        if ($this->azione !== 'skip_duplicates') return;

        $rows = Presidio::query()
            ->select('categoria', 'progressivo')
            ->where('cliente_id', $this->clienteId)
            ->when($this->sedeId === null, fn($q) => $q->whereNull('sede_id'))
            ->when($this->sedeId !== null, fn($q) => $q->where('sede_id', $this->sedeId))
            ->get();

        foreach ($rows as $r) {
            $cat = (string)$r->categoria;
            $prog = (string)$r->progressivo;
            $this->existingKeys[$cat][$prog] = true;
        }
    }

    private function shouldSkipExisting(string $categoria, string $progressivo): bool
    {
        if ($this->azione !== 'skip_duplicates') return false;
        return !empty($this->existingKeys[$categoria][$progressivo]);
    }

    private function markExisting(string $categoria, string $progressivo): void
    {
        if ($this->azione !== 'skip_duplicates') return;
        $this->existingKeys[$categoria][$progressivo] = true;
    }

    private function resolveSedeId(): ?int
    {
        if ($this->sedeId) return $this->sedeId;

        $sede = Sede::where('cliente_id', $this->clienteId)->first();
        if ($sede) return $sede->id;

        // Sede principale = sede_id NULL
        return null;
    }

    private function caricaMesiPreferiti(): array
    {
        $cliente = Cliente::find($this->clienteId);
        $sede    = $this->sedeId ? Sede::find($this->sedeId) : null;

        $candidates = [
            $sede?->mesi_visita ?? null,
            $cliente?->mesi_visita ?? null,
            $cliente?->mesi_intervento ?? null,
            $cliente?->mesi ?? null,
        ];
        foreach ($candidates as $raw) {
            $arr = self::normalizeMonths($raw);
            if (!empty($arr)) return $arr;
        }
        return [];
    }

    private static function normalizeMonths($raw): array
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

    private static function visitaOnOrBeforeWithMonths(?string $due, array $months): ?string
    {
        if (!$due) return null;
        $dueC = Carbon::parse($due)->startOfMonth();
        if (!count($months)) return $dueC->format('Y-m-d');
        if (in_array((int)$dueC->month, $months, true)) return $dueC->format('Y-m-d');

        sort($months);
        $year = $dueC->year;
        $m = $dueC->month;
        $before = array_values(array_filter($months, fn($vm) => $vm < $m));
        if (!empty($before)) {
            $month = end($before);
        } else {
            $month = end($months);
            $year -= 1;
        }
        return Carbon::create($year, $month, 1)->format('Y-m-d');
    }

    private static function parseData(?string $txt): ?string
    {
        $txt = trim((string)$txt);
        if ($txt === '') return null;
        if (preg_match('/\b(\d{1,2})\s*[\.\/-]\s*(\d{4})\b/', $txt, $m)) {
            $mese = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            return Carbon::createFromFormat('Y-m-d', "{$m[2]}-{$mese}-01")->format('Y-m-d');
        }
        if (preg_match('/\b(\d{4})\b/', $txt, $y)) {
            return Carbon::createFromDate((int)$y[1], 1, 1)->format('Y-m-d');
        }
        return null;
    }

    private static function cellText($cell): string
    {
        $txt = '';
        foreach ($cell->getElements() as $e) {
            if ($e instanceof Text) $txt .= $e->getText();
            elseif ($e instanceof TextRun) {
                foreach ($e->getElements() as $t)
                    if ($t instanceof Text) $txt .= $t->getText();
            }
        }
        return trim($txt);
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
            'ANNO PRODUZIONE'            => 'anno_serbatoio',
            'RIEMPIMENTO/ REVISIONE'     => 'riempimento_revisione',
            'RIEMPIMENTO/REVISIONE'      => 'riempimento_revisione',
            'ULTIMA REVISIONE'           => 'riempimento_revisione',
            'COLLAUDO/ REVISIONE'        => 'collaudo_revisione',
            'COLLAUDO/REVISIONE'         => 'collaudo_revisione',
            'PROSSIMA REVISIONE'         => 'collaudo_revisione',
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

    private function loadTipiCache(): void
    {
        if ($this->tipiCache) return;
        $this->tipiCache = TipoEstintore::query()
            ->select('id','descrizione','sigla','kg')
            ->orderBy('kg')->orderBy('id')
            ->get()
            ->map(function ($t) {
                $txt = strtoupper($t->descrizione.' '.$t->sigla);
                return [
                    'id'          => $t->id,
                    'kg'          => (int) $t->kg,
                    'descrizione' => $t->descrizione,
                    'sigla'       => $t->sigla,
                    'agente'      => $this->detectAgent($txt),
                    'full'        => $txt,
                ];
            });
    }

    private function detectAgent(string $txt): ?string
    {
        $u = strtoupper($txt);
        if (preg_match('/\bCO\s*2\b|\bCO2\b|ANIDRIDE\s+CARBONICA/', $u)) return 'CO2';
        if (preg_match('/POLV|POLVER/', $u)) return 'POLVERE';
        if (preg_match('/SCHI|FOAM|AFFF/', $u)) return 'SCHIUMA';
        return null;
    }

    private function detectCapacity(string $txt): ?int
    {
        $u = strtoupper($txt);
        if (preg_match('/\b(\d{1,3})(?:[,.]\d+)?\s*(KG|KGS|KG\.|LT|L|LT\.)\b/u', $u, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\b(KG|KGS|KG\.)\s*(\d{1,3})(?:[,.]\d+)?\b/u', $u, $m)) {
            return (int) $m[2];
        }
        if (preg_match('/\b(LT|L|LT\.)\s*(\d{1,3})(?:[,.]\d+)?\b/u', $u, $m)) {
            return (int) $m[2];
        }
        return null;
    }

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

    private function guessTipoEstintoreId(string $raw): ?int
    {
        $this->loadTipiCache();
        $u   = strtoupper($raw);
        $kg  = $this->detectCapacity($u);
        $ag  = $this->detectAgent($u);

        $cand = $this->tipiCache
            ->when($ag, fn($c) => $c->where('agente', $ag))
            ->when($kg, fn($c) => $c->where('kg', $kg));

        if ($cand->count() === 1) return $cand->first()['id'];
        if ($cand->count() > 1) {
            $best = $cand->firstWhere('full', fn($f) => str_contains($f, (string)$kg));
            return $best['id'] ?? $cand->first()['id'];
        }

        if ($ag) {
            $cand = $this->tipiCache->where('agente', $ag);
            if ($cand->count()) return $cand->first()['id'];
        }

        if ($kg) {
            $cand = $this->tipiCache->where('kg', $kg);
            if ($cand->count()) return $cand->first()['id'];
        }

        return null;
    }
}
