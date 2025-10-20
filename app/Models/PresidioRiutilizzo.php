<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresidioRiutilizzo extends Model
{
    protected $table = 'presidi_riutilizzo';

    protected $fillable = [
        'presidio_id',
        'data_ritiro',
        'stato',
        'note',
        'riutilizzato_il',
        'cliente_id',
        'sede_id',
    ];

    protected $casts = [
        'data_ritiro' => 'date',
        'riutilizzato_il' => 'date',
    ];

    public function presidio(): BelongsTo
    {
        return $this->belongsTo(Presidio::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }
}
