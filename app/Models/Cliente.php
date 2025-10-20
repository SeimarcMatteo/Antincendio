<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clienti';

    protected $fillable = [
        'nome',
        'p_iva',
        'email',
        'telefono',
        'indirizzo',
        'citta',
        'cap',
        'provincia',
        'codice_esterno',
        'mesi_visita',
        'minuti_intervento',
    ];
    protected $casts = [
        'mesi_visita' => 'array',
    ];
    

    public function sedi()
    {
        return $this->hasMany(Sede::class, 'cliente_id');
    }
    public function presidi()
    {
        return $this->hasMany(Presidio::class,'cliente_id');
    }
    public function interventi()
    {
        return $this->hasMany(\App\Models\Intervento::class);
    }
}
