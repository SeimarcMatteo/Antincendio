<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TipoEstintore extends Model
{
    protected $table = 'tipi_estintori';

    protected $fillable = ['sigla', 'codice_articolo_fatturazione', 'descrizione', 'kg', 'tipo', 'colore_id'];

    public function presidi()
    {
        return $this->hasMany(Presidio::class, 'tipo_estintore_id');
    }

    public function classificazione()
    {
        return $this->belongsTo(ClassificazioneEstintore::class, 'classificazione_id');
    }

    public function colore(): BelongsTo
    {
        return $this->belongsTo(Colore::class, 'colore_id');
    }
}
