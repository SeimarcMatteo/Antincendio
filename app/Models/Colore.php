<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Colore extends Model
{
    protected $table = 'colori';

    // in tabella i campi sono: id, nome, hex, slug, ...
    protected $fillable = ['nome', 'hex', 'slug'];

    protected static function booted(): void
    {
        static::saving(function (Colore $c) {
            // slug leggibile
            $c->slug = $c->slug ?: Str::slug($c->nome);

            // normalizza HEX sempre con # e maiuscolo
            if ($c->hex) {
                $hex = strtoupper(ltrim($c->hex, '#'));
                $c->hex = '#'.$hex;
            }
        });
    }

    public function tipiEstintore()
    {
        return $this->hasMany(TipoEstintore::class, 'colore_id');
    }
}
