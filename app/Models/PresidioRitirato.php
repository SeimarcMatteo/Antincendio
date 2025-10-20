<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresidioRitirato extends Model
{
    protected $table = 'presidi_ritirati';

    protected $fillable = [
        'presidio_id',
        'cliente_id',
        'sede_id',
        'data_ritiro',
        'note',
        'stato',
    ];

    protected $dates = ['data_ritiro'];

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
    public function presidioIntervento()
    {
        return $this->belongsTo(\App\Models\PresidioIntervento::class);
    }

}
