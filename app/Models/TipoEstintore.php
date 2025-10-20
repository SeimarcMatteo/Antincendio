<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoEstintore extends Model
{
    protected $table = 'tipi_estintori';

    protected $fillable = ['sigla', 'descrizione', 'kg', 'tipo'];

    public function presidi()
    {
        return $this->hasMany(Presidio::class, 'tipo_estintore_id');
    }

    public function classificazione()
    {
        return $this->belongsTo(ClassificazioneEstintore::class, 'classificazione_id');
    }
}
