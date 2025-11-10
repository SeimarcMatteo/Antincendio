<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ClassificazioneEstintore;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class Presidio extends Model
{
    use HasFactory;

    protected $table = 'presidi';

    protected $fillable = [
        'cliente_id', 'sede_id', 'categoria', 'progressivo',
        'ubicazione', 'tipo_contratto', 'tipo_estintore_id',
        'data_serbatoio', 'data_ultima_revisione',
        'data_revisione', 'data_collaudo',
        'data_fine_vita', 'data_sostituzione',
        'flag_anomalia1', 'flag_anomalia2', 'flag_anomalia3',
        'note', 'flag_preventivo', 'data_acquisto', 'scadenza_presidio',
    ];

    // Cutoff per cambiare il periodo di revisione
    private const CUTOFF = '2024-08-01';

    protected static function booted()
    {
        static::saving(function (Presidio $presidio) {
            $presidio->loadMissing('tipoEstintore', 'cliente', 'sede');
            $presidio->calcolaScadenze();
        });
    }

    /* ====================== RELAZIONI ====================== */

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }

    public function storico()
    {
        return $this->hasMany(PresidioStorico::class);
    }

    public function tipoEstintore()
    {
        return $this->belongsTo(TipoEstintore::class, 'tipo_estintore_id');
    }

    /* ====================== SCOPE/UTILITY ====================== */

    public static function prossimoProgressivo($clienteId, $sedeId, $categoria)
    {
        $ultimoProgressivo = self::where('cliente_id', $clienteId)
            ->where('sede_id', $sedeId)
            ->where('categoria', $categoria)
            ->max('progressivo');

        return $ultimoProgressivo ? $ultimoProgressivo + 1 : 1;
    }

    public function scopeAttivoProgressivo($query, $clienteId, $sedeId, $categoria, $progressivo)
    {
        return $query->where('cliente_id', $clienteId)
            ->where('sede_id', $sedeId)
            ->where('categoria', $categoria)
            ->where('progressivo', $progressivo)
            ->where('attivo', true);
    }

    /* ====================== LOGICHE DATA ====================== */

    /**
     * Calcolo completo delle scadenze con la stessa logica dell'import:
     * - periodo revisione deciso da data_serbatoio vs cutoff
     * - base revisione = data_ultima_revisione se presente, altrimenti data_serbatoio
     * - collaudo/fine vita da data_serbatoio
     * - allineamento a "visita subito prima" (mesi sede/cliente) oppure mese precedente.
     */
    public function calcolaScadenze(): void
    {
        Log::info('[CALCOLO SCADENZE] Avvio per presidio ID ' . ($this->id ?? 'new'));
        Log::info('[CALCOLO SCADENZE][INPUT]', [
            'data_serbatoio'        => $this->data_serbatoio,
            'data_ultima_revisione' => $this->data_ultima_revisione,
            'mesi_visita'           => $this->getMesiVisita(),
          ]);

        if (!$this->tipoEstintore || !$this->tipoEstintore->classificazione) {
            Log::warning('[CALCOLO SCADENZE] Tipo estintore o classificazione mancanti');
            return;
        }

        $classi = $this->tipoEstintore->classificazione;

        // Se mancano TUTTE e due le basi, non posso calcolare
        if (!$this->data_serbatoio && !$this->data_ultima_revisione) {
            Log::warning('[CALCOLO SCADENZE] Mancano sia data_serbatoio sia data_ultima_revisione');
            return;
        }

        $dataSerb = $this->data_serbatoio ? Carbon::parse($this->data_serbatoio)->startOfMonth() : null;
        $lastRev  = $this->data_ultima_revisione ? Carbon::parse($this->data_ultima_revisione)->startOfMonth() : null;

        // 1) Periodo revisione determinato dal serbatoio (se manca, assumo "PRIMA" come fallback sicuro)
        $periodoRev = $this->pickPeriodoRevisione($dataSerb, $classi);

        // 2) Revisione: base = ultima revisione (se esiste) altrimenti serbatoio
        $baseRevisione = $lastRev ?: $dataSerb; // almeno una delle due è presente (testato sopra)
        $scadRevisione = $this->nextDueAfter($baseRevisione, $periodoRev);
       

        // 3) Collaudo/Fine vita: da serbatoio
        $scadCollaudo = null;
        if (!empty($classi->anni_collaudo) && $dataSerb) {
            $scadCollaudo = $this->nextDueAfter($dataSerb, (int) $classi->anni_collaudo);
        }

        $fineVita = null;
        if (!empty($classi->anni_fine_vita) && $dataSerb) {
            $fineVita = $dataSerb->copy()->addYears((int) $classi->anni_fine_vita)->format('Y-m-d');
        }

        // 4) Allineamento alle visite (mese precedente o mese preferito prima della scadenza)
        $mesiVisita = $this->getMesiVisita(); // array di int [1..12]

        $this->data_revisione = $this->visitaOnOrBefore($scadRevisione, $mesiVisita) ?? $scadRevisione;
        $this->data_collaudo  = $this->visitaOnOrBefore($scadCollaudo,  $mesiVisita) ?? $scadCollaudo;
        $this->data_fine_vita = $this->visitaOnOrBefore($fineVita,      $mesiVisita) ?? $fineVita;

        // 5) Sostituzione: visita prima della scadenza minima tra revisione/collaudo/fine vita/scadenza_presidio
        $scadenzaAssoluta = $this->minDate(
            $scadRevisione,
            $scadCollaudo,
            $fineVita,
            $this->scadenza_presidio
        );
        $this->data_sostituzione = $this->visitaOnOrBefore($scadenzaAssoluta, $mesiVisita);

        Log::info('[CALCOLO SCADENZE] OK', [
            'revisione' => $this->data_revisione,
            'collaudo'  => $this->data_collaudo,
            'fine_vita' => $this->data_fine_vita,
            'sostituz.' => $this->data_sostituzione,
        ]);
                 
    }

    /**
     * Determina il periodo revisione in anni in base alla data serbatoio vs cutoff.
     * Se data_serbatoio manca, usa anni_revisione_prima come fallback conservativo.
     */
    private function pickPeriodoRevisione(?Carbon $dataSerb, ClassificazioneEstintore $classi): ?int
    {
        $cutover = Carbon::parse(self::CUTOFF)->startOfDay();

        $after = false;

        // condizione su data serbatoio
        if ($dataSerb && $dataSerb->gte($cutover)) {
            $after = true;
        }

        // condizione su data ultima revisione
        if ($this->data_ultima_revisione) {
            $lastRev = Carbon::parse($this->data_ultima_revisione)->startOfDay();
            if ($lastRev->gte($cutover)) {
                $after = true;
            }
        }

        if ($after && !empty($classi->anni_revisione_dopo)) {
            return (int) $classi->anni_revisione_dopo;
        }

        return (int) $classi->anni_revisione_prima;
    }


    /**
     * Prossima scadenza > oggi partendo da $start e saltando multipli di $periodYears.
     * Ritorna 'Y-m-d'.
     */
    private function nextDueAfter(?Carbon $start, ?int $periodYears): ?string
    {
        if (!$start || !$periodYears || $periodYears <= 0) return null;

        $today = now()->startOfDay();
        $due   = $start->copy()->startOfMonth()->addYears($periodYears);

        while ($due->lte($today)) {
            $due->addYears($periodYears);
        }
        return $due->format('Y-m-d');
    }

    /**
     * Allinea la data alla "visita subito prima".
     * - Se ci sono mesi preferiti, usa previousVisitBefore().
     * - Se non ci sono mesi, ritorna semplicemente il mese precedente alla scadenza.
     */
    private function visitaPrimaDi(?string $dueYmd, array $mesiVisita): ?string
    {
        if (!$dueYmd) return null;
        if (!count($mesiVisita)) {
            return Carbon::parse($dueYmd)->subMonth()->startOfMonth()->format('Y-m-d');
        }
        return $this->previousVisitBefore($dueYmd, $mesiVisita);
    }

    /**
     * Dato una scadenza, restituisce la "visita subito prima" (mese pianificato < mese scadenza).
     * Esempio: scadenza 2027-05-01 con visite [5,11] => 2026-11-01.
     */
    private function previousVisitBefore(string $dueYmd, array $visitMonths): string
    {
        sort($visitMonths); // es. [5, 11]
        $due  = Carbon::parse($dueYmd);
        $year = $due->year;
        $m    = $due->month;

        $before = array_values(array_filter($visitMonths, fn ($vm) => $vm < $m));
        if (!empty($before)) {
            $month = end($before);
        } else {
            $month = end($visitMonths);
            $year -= 1;
        }

        return Carbon::create($year, $month, 1)->format('Y-m-d');
    }

    private function minDate(?string ...$dates): ?string
    {
        $valid = array_filter($dates);
        if (!$valid) return null;
        return collect($valid)->min();
    }

    /**
     * Estrae i mesi di visita dalla sede o dal cliente (supporta JSON o CSV o array).
     * Ordina, filtra 1..12 e rende un array unico.
     */
    private function visitaOnOrBefore(?string $dueYmd, array $mesiVisita): ?string
    {
        if (!$dueYmd) return null;
        $due = Carbon::parse($dueYmd)->startOfMonth();

        if (!count($mesiVisita)) {
            // Nessuna preferenza → per la revisione tieni il mese della scadenza
            return $due->format('Y-m-d');
        }

        if (in_array((int)$due->month, $mesiVisita, true)) {
            return $due->format('Y-m-d');
        }

        // Se il mese della scadenza non è tra i mesi visita → prendi quello precedente
        return $this->previousVisitBefore($due->format('Y-m-d'), $mesiVisita);
    }

    private function getMesiVisita(): array
    {
        $candidates = [
            $this->sede->mesi_visita ?? null,
            $this->cliente->mesi_visita ?? null,
            $this->cliente->mesi_intervento ?? null,
            $this->cliente->mesi ?? null,
        ];

        foreach ($candidates as $raw) {
            $months = $this->normalizeMonths($raw);
            if (!empty($months)) return $months;
        }
        return [];
    }

    private function normalizeMonths($raw): array
    {
        if (!$raw) return [];

        if (is_string($raw)) {
            // prova JSON
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $raw = $decoded;
            } else {
                // CSV "5,11" ecc.
                $raw = array_map('trim', explode(',', $raw));
            }
        }

        if (is_array($raw)) {
            return collect($raw)
                ->map(fn ($m) => (int) $m)
                ->filter(fn ($m) => $m >= 1 && $m <= 12)
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        return [];
    }

    /* ====================== (OPZ) API COMPAT PRE-ESISTENTE ====================== */

    /**
     * Versione "utility" compat: se vuoi riusarli altrove.
     * Mantengo la firma originale ma ora valuta anche l'ultima revisione se impostata sull'istanza.
     */
    public static function calcolaDateEstintore($dataProduzione, $classificazioneId)
    {
        $classificazione = ClassificazioneEstintore::findOrFail($classificazioneId);
        $dataProduzione  = Carbon::parse($dataProduzione)->startOfMonth();
        $cutoff          = Carbon::parse(self::CUTOFF)->endOfDay();

        $anniRevisione = $dataProduzione->gt($cutoff)
            ? ($classificazione->anni_revisione_dopo ?: $classificazione->anni_revisione_prima)
            : $classificazione->anni_revisione_prima;

        // Se l'istanza corrente ha una ultima revisione, usala come base
        $istanza = new static();
        $baseRev = $istanza->data_ultima_revisione
            ? Carbon::parse($istanza->data_ultima_revisione)->startOfMonth()
            : $dataProduzione;
        
        $dataRevisione = $baseRev->copy()->addYears($anniRevisione);
        $dataCollaudo  = $classificazione->anni_collaudo ? $dataProduzione->copy()->addYears($classificazione->anni_collaudo) : null;
        $dataFineVita  = $dataProduzione->copy()->addYears($classificazione->anni_fine_vita);

        $minima = collect([$dataRevisione, $dataCollaudo, $dataFineVita])
            ->filter()
            ->sort()
            ->first();

        return [
            'data_revisione'     => $dataRevisione->format('Y-m-d'),
            'data_collaudo'      => $dataCollaudo?->format('Y-m-d'),
            'data_fine_vita'     => $dataFineVita->format('Y-m-d'),
            'data_sostituzione'  => $minima?->format('Y-m-d'),
        ];
    }
}
