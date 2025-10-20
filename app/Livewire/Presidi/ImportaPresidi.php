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

        // carica e parse
        $path   = Storage::disk('local')->path(
                    $this->file->store('import_presidi', 'local'));
        $word   = IOFactory::load($path);
        $this->anteprima = [];

        foreach ($word->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (!$element instanceof Table) continue;

                foreach ($element->getRows() as $row) {
                    $cells = $row->getCells();
                    if (count($cells) < 4) continue;

                    // celle principali
                    $c0 = self::cellText($cells[0]);
                    $c1 = self::cellText($cells[1]);
                    $c2 = self::cellText($cells[2]);
                    $c3 = self::cellText($cells[3]);

                    if (!is_numeric($c0) || !$c2) continue;  // non è riga presidio

                    // tipo estintore & classificazione
                    preg_match('/(\d+)\s*kg/i', $c3, $mKg);
                    $kg       = $mKg[1] ?? null;
                    $tipoEst  = $kg
                        ? TipoEstintore::where('kg', $kg)
                              ->where('descrizione', 'like', '%Polv%')->first()
                        : null;
                    $classi   = $tipoEst?->classificazione;

                    // date
                    $dateChunks = array_map(fn($c) => self::cellText($c),
                                            array_slice($cells, 4));
                    $dates      = self::estraiDateDaArray($dateChunks, 2);
                    $dataSerb   = $dates[0] ?? null;
                    $dataColl   = $dates[1] ?? null;

                    // flag anomalie
                    $joined     = collect($cells)->reduce(
                        fn($agg, $c) => $agg.' '.self::cellText($c), '');

                    $this->anteprima[] = [
                        'categoria'         => 'Estintore',
                        'progressivo'       => (int)$c0,
                        'ubicazione'        => $c1,
                        'tipo_contratto'    => $c2,
                        'tipo_estintore'    => $c3,
                        'tipo_estintore_id' => $tipoEst?->id,
                        'data_serbatoio'    => $dataSerb,
                        'data_revisione'    => self::addYears($dataSerb, $classi?->anni_revisione_prima),
                        'data_collaudo'     => $dataColl,
                        'data_fine_vita'    => self::addYears($dataSerb, $classi?->anni_fine_vita),
                        'data_sostituzione' => null,
                        'flag_anomalia1'    => str_contains($joined, 'CARTELLO'),
                        'flag_anomalia2'    => str_contains($joined, 'TERRA'),
                        'flag_anomalia3'    => str_contains($joined, 'NUMERAZ'),
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
}
