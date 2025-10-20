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
        'data_serbatoio', 'data_revisione', 'data_collaudo',
        'data_fine_vita', 'data_sostituzione',
        'flag_anomalia1', 'flag_anomalia2', 'flag_anomalia3',
        'note', 'flag_preventivo'
    ];
    public static function booted()
    {
        static::saving(function ($presidio) {
            $presidio->loadMissing('tipoEstintore');
            $presidio->calcolaScadenze();
        });
    }
    public static function calcolaDateEstintore($dataProduzione, $classificazioneId)
    {
        $classificazione = ClassificazioneEstintore::findOrFail($classificazioneId);
        Log::info("Id Classificazione: " . $classificazioneId);
        $dataProduzione = \Carbon\Carbon::parse($dataProduzione);
        $cutoff = \Carbon\Carbon::create(2024, 8, 31);
    
        $dopoCutoff = $dataProduzione->greaterThan($cutoff);
        Log::info("Classificazione: " . $classificazione);
        $anniRevisione = $dopoCutoff ? $classificazione->anni_revisione_dopo : $classificazione->anni_revisione_prima;
        $dataRevisione = $dataProduzione->copy()->addYears($anniRevisione);
        $dataCollaudo = $classificazione->anni_collaudo ? $dataProduzione->copy()->addYears($classificazione->anni_collaudo) : null;
        $dataFineVita = $dataProduzione->copy()->addYears($classificazione->anni_fine_vita);
    
        $minima = collect([$dataRevisione, $dataCollaudo, $dataFineVita])
            ->filter()
            ->sort()
            ->first();
    
        return [
            'data_revisione' => $dataRevisione,
            'data_collaudo' => $dataCollaudo,
            'data_fine_vita' => $dataFineVita,
            'data_sostituzione' => $minima,
        ];
    }
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
    
    public function calcolaScadenze()
    {
        \Log::info('[CALCOLO SCADENZE] Avvio per presidio ID ' . $this->id);

        if (!$this->data_serbatoio || !$this->tipoEstintore) {
            \Log::warning('[CALCOLO SCADENZE] Dati insufficienti: data_serbatoio=' . $this->data_serbatoio . ', tipoEstintore=' . ($this->tipo_estintore ?? 'null'));
            return;
        }

        $data = Carbon::parse($this->data_serbatoio);
        $cutoff = Carbon::create(2024, 8, 31);
        $classificazione = $this->tipoEstintore->classificazione;
        $dopo = $data->gt($cutoff);

        // Calcoli grezzi
        $revisioneGrezza = $data->copy()->addYears($dopo ? $classificazione->anni_revisione_dopo : $classificazione->anni_revisione_prima);
        $collaudoGrezzo = $classificazione->anni_collaudo ? $data->copy()->addYears($classificazione->anni_collaudo) : null;
        $fineVitaGrezzo = $data->copy()->addYears($classificazione->anni_fine_vita);

        $mesiVisita = $this->sede?->mesi_visita ?? $this->cliente?->mesi_visita ?? [];

        if (is_string($mesiVisita)) {
            $mesiVisita = json_decode($mesiVisita, true);
        }

        if (!is_array($mesiVisita) || empty($mesiVisita)) {
            // Nessun mese visita
            $this->data_revisione = $this->ricalcolaNelFuturo($revisioneGrezza);
            $this->data_collaudo = $collaudoGrezzo;
            $this->data_fine_vita = $fineVitaGrezzo;
            $this->data_sostituzione = collect([$this->data_revisione, $collaudoGrezzo, $fineVitaGrezzo])->filter()->min();
            return;
        }

        sort($mesiVisita);

        $adattaScadenza = function ($scadenzaGrezza) use ($mesiVisita) {
            $oggi = Carbon::today();
            $dataFinale = null;

            foreach (range($oggi->year, $oggi->year + 10) as $anno) {
                foreach ($mesiVisita as $mese) {
                    $dataVisita = Carbon::create($anno, $mese, 1);
                    if ($dataVisita->gte($oggi) && $dataVisita->lte($scadenzaGrezza)) {
                        $dataFinale = $dataVisita; // la più vicina sotto la scadenza
                    }
                }
            }

            // Se è scaduta o non ne troviamo una valida, prendiamo la prima utile dopo oggi
            if (!$dataFinale || $dataFinale->lt($oggi)) {
                foreach (range($oggi->year, $oggi->year + 10) as $anno) {
                    foreach ($mesiVisita as $mese) {
                        $dataProposta = Carbon::create($anno, $mese, 1);
                        if ($dataProposta->gt($oggi)) {
                            return $dataProposta;
                        }
                    }
                }
            }

            return $dataFinale ?? $scadenzaGrezza;
        };

        $this->data_revisione = $adattaScadenza($revisioneGrezza);
        $this->data_collaudo = $collaudoGrezzo ? $adattaScadenza($collaudoGrezzo) : null;
        $this->data_fine_vita = $adattaScadenza($fineVitaGrezzo);

        $this->data_sostituzione = collect([
            $this->data_revisione,
            $this->data_collaudo,
            $this->data_fine_vita,
        ])->filter()->sort()->first();
    }

    protected function ricalcolaNelFuturo($dataBase)
    {
        while ($dataBase->isPast()) {
            $dataBase->addYears(1);
        }
        return $dataBase;
    }
    }