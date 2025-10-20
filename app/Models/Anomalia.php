<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Anomalia extends Model
{
    protected $fillable = [
        'categoria',
        'etichetta',
        'attiva',
    ];
    protected $table = 'anomalie';
}
