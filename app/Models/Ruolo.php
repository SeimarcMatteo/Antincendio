<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ruolo extends Model
{
    use HasFactory;
    protected $table = 'ruoli';

    protected $fillable = [
        'nome',
        'descrizione',
    ];

    public function utenti()
    {
        return $this->belongsToMany(User::class, 'utente_ruoli', 'ruolo_id', 'utente_id');
    }
}
