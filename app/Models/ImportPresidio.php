<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\Presidi\ProgressivoParser;

class ImportPresidio extends Model
{
    protected $table = 'import_presidi';

    protected $guarded = [];   // nessun campo in guarded → li accetta tutti
    protected $fillable = [
        'cliente_id', 'sede_id', 'categoria', 'progressivo', 'progressivo_num', 'progressivo_suffix',
        'ubicazione', 'tipo_contratto', 'tipo_estintore', 'tipo_estintore_id',
        'idrante_tipo','idrante_lunghezza','idrante_sopra_suolo','idrante_sotto_suolo','porta_tipo',
        'flag_anomalia1', 'flag_anomalia2', 'flag_anomalia3',
        'note', 'data_serbatoio', 'marca_serbatoio', 'data_revisione', 'data_collaudo',
        'data_fine_vita', 'data_sostituzione', 'data_acquisto', 'scadenza_presidio','data_ultima_revisione'
    ];

   
    protected $casts = [
        'data_serbatoio'    => 'date:Y-m-d',
        'data_revisione'    => 'date:Y-m-d',
        'data_collaudo'     => 'date:Y-m-d',
        'data_fine_vita'    => 'date:Y-m-d',
        'data_sostituzione' => 'date:Y-m-d',
        'data_acquisto' => 'date:Y-m-d',
        'scadenza_presidio' => 'date:Y-m-d',
        'idrante_sopra_suolo' => 'boolean',
        'idrante_sotto_suolo' => 'boolean',
        'progressivo_num' => 'integer',
    ];

    protected static function booted()
    {
        static::saving(function (ImportPresidio $presidio) {
            if ($parsed = ProgressivoParser::parse($presidio->progressivo)) {
                $presidio->progressivo = $parsed['label'];
                $presidio->progressivo_num = $parsed['num'];
                $presidio->progressivo_suffix = $parsed['suffix'];
            }
        });
    }

}
