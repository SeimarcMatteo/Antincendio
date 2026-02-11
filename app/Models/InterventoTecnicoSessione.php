<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterventoTecnicoSessione extends Model
{
    protected $table = 'intervento_tecnico_sessioni';

    protected $fillable = [
        'intervento_tecnico_id',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function interventoTecnico(): BelongsTo
    {
        return $this->belongsTo(InterventoTecnico::class, 'intervento_tecnico_id');
    }
}
