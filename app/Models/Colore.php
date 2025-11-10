<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Colore extends Model
{
    protected $table = 'colori';
    protected $fillable = ['nome', 'hex'];

    // helper rapido per garantire "#RRGGBB"
    public function setHexAttribute($value) {
        $v = strtoupper(trim($value));
        if (!str_starts_with($v, '#')) $v = '#'.$v;
        $this->attributes['hex'] = substr($v, 0, 7);
    }

    // app/Models/Colore.php
    public function getHexAttribute(): ?string
    {
        return $this->codice_hex;
    }

}
