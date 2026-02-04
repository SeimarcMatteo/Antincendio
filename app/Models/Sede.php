<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    use HasFactory;

    protected $table = 'sedi';

    protected $fillable = [
        'cliente_id',
        'nome',
        'indirizzo',
        'citta',
        'cap',
        'provincia',
        'codice_esterno',
        'minuti_intervento',
        'mesi_visita',
    ];
    protected $casts = [
        'mesi_visita' => 'array',
    ];
    

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
    public function interventi()
    {
        return $this->hasMany(\App\Models\Intervento::class);
    }
    public function presidi()
    {
        return $this->hasMany(Presidio::class,'sede_id');
    }
}
