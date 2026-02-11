<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoPresidio extends Model
{
    protected $table = 'tipi_presidio';

    protected $fillable = [
        'categoria',
        'nome',
        'codice_articolo_fatturazione',
    ];
}
