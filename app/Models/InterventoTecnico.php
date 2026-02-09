<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterventoTecnico extends Model
{
    protected $table = 'intervento_tecnico';

    protected $fillable = [
        'intervento_id',
        'user_id',
        'started_at',
        'ended_at',
        'scheduled_start_at',
        'scheduled_end_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'scheduled_start_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
    ];

    public function intervento()
    {
        return $this->belongsTo(Intervento::class);
    }

    public function tecnico()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
