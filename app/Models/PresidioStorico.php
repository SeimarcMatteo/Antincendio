<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PresidioStorico extends Model
{
    use HasFactory;
    protected $table='presidi_storico';
    protected $fillable = [
        'presidio_id',
        'data_evento',
        'evento',
        'note',
    ];

    public function presidio()
    {
        return $this->belongsTo(Presidio::class);
    }

    public function tipoEstintore()
{
    return $this->belongsTo(TipoEstintore::class, 'tipo_estintore_id');
}

}
