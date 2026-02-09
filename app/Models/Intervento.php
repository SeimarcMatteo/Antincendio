<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Intervento extends Model
{
    protected $table = 'interventi';
    protected $fillable = [
        'cliente_id',
        'sede_id',
        'data_intervento',
        'durata_minuti',
        'durata_effettiva',
        'stato',
        'zona',
        'firma_cliente_base64',
        'fatturato' => 'boolean',
        'fatturazione_payload' => 'array',
        'fatturato_at' => 'datetime',
        'fattura_ref_data' => 'array',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }

    public function tecnici()
    {
        return $this->belongsToMany(User::class, 'intervento_tecnico')
            ->withPivot('started_at', 'ended_at');
    }
    public function presidiIntervento()
    {
        return $this->hasMany(PresidioIntervento::class);
    }
    public function presidi()
    {
        return $this->belongsToMany(Presidio::class, 'presidi_intervento')
                    ->withPivot('esito', 'note')
                    ->withTimestamps();
    }


}
