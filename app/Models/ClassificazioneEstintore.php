<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ClassificazioneEstintore extends Model
{
    use HasFactory;

    protected $table = 'classificazioni_estintori';

    protected $fillable = [
        'nome',
        'anni_revisione_dopo',
        'anni_revisione_prima',
        'anni_collaudo',
        'anni_fine_vita',
    ];
    public function tipiEstintori()
    {
        return $this->hasMany(TipoEstintore::class, 'classificazione_id');
    }
    
}