<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterventoTecnico extends Model
{
    protected $table = 'intervento_tecnico';

    protected $fillable = [
        'intervento_id',
        'user_id',
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
