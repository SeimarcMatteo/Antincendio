<?php

namespace App\Models;

use App\Models\Anomalia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresidioIntervento extends Model
{
    protected $table = 'presidi_intervento';
    protected $casts = [
        'anomalie' => 'array',
    ];
    protected $fillable = [
        'intervento_id',
        'presidio_id',
        'esito',
        'note',
    ];

    public function intervento(): BelongsTo
    {
        return $this->belongsTo(Intervento::class);
    }

    public function presidio(): BelongsTo
    {
        return $this->belongsTo(Presidio::class);
    }
    public function sostituitoCon()
    {
        return $this->belongsTo(Presidio::class, 'sostituito_con_presidio_id');
    }

   
    public function getAnomalieAttribute()
    {
        // Se anomalies è null o vuoto, ritorna una collection vuota
        if (empty($this->attributes['anomalie'])) {
            return collect();
        }

        $ids = json_decode($this->attributes['anomalie'], true);
        return Anomalia::whereIn('id', $ids)->get();
    }

}
