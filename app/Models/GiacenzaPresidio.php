<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GiacenzaPresidio extends Model
{
    use HasFactory;

    protected $table = 'giacenze_presidi';

    protected $fillable = [
        'categoria',
        'tipo_estintore_id',
        'quantita',
    ];

    public function tipoEstintore()
    {
        return $this->belongsTo(TipoEstintore::class);
    }
}
