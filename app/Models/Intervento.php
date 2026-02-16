<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\InterventoTecnico;

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
        'closed_by_user_id',
        'zona',
        'note',
        'pagamento_metodo',
        'pagamento_importo',
        'firma_cliente_base64',
        'fatturato',
        'fatturazione_payload',
        'fatturato_at',
        'fattura_ref_data',
    ];
    protected $casts = [
        'fatturato' => 'boolean',
        'fatturazione_payload' => 'array',
        'fatturato_at' => 'datetime',
        'fattura_ref_data' => 'array',
        'pagamento_importo' => 'decimal:2',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function sede()
    {
        return $this->belongsTo(Sede::class);
    }

    public function tecnicoChiusura()
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function tecnici()
    {
        return $this->belongsToMany(User::class, 'intervento_tecnico')
            ->using(InterventoTecnico::class)
            ->withPivot('started_at', 'ended_at', 'scheduled_start_at', 'scheduled_end_at');
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
