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
        'minuti_intervento_mese1',
        'minuti_intervento_mese2',
        'fatturazione_tipo',
        'mese_fatturazione',
        'note',
        'zona',
    ];  
    protected $casts = [
        'mesi_visita' => 'array',
    ];
    

    /**
     * Normalizza mesi_visita in array di mesi [1..12], ordinati e max 2.
     * Supporta sia array ["gen","lug"] o [1,7] sia oggetto {"gen":true,"lug":true} o {"1":true,"7":true}.
     */
    private function normalizzaMesiVisita(): array
    {
        $raw = $this->mesi_visita ?? [];
        $map = ['gen'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'mag'=>5,'giu'=>6,'lug'=>7,'ago'=>8,'set'=>9,'ott'=>10,'nov'=>11,'dic'=>12];

        $out = [];
        if (array_values($raw) === $raw) {
            // lista
            foreach ($raw as $v) {
                $out[] = is_numeric($v) ? (int)$v : ($map[mb_strtolower((string)$v)] ?? null);
            }
        } else {
            // oggetto { mese => bool }
            foreach ($raw as $k => $v) {
                if (!$v) continue;
                $out[] = is_numeric($k) ? (int)$k : ($map[mb_strtolower((string)$k)] ?? null);
            }
        }

        $out = array_values(array_filter(array_unique($out), fn($m) => $m >= 1 && $m <= 12));
        sort($out);
        return array_slice($out, 0, 2); // business: max 2 visite
    }

    /** Ritorna 1 o 2 se il mese è tra i mesi_visita normalizzati, altrimenti null. */
    public function indiceVisitaPerMese(int $mese): ?int
    {
        $attivi = $this->normalizzaMesiVisita();
        if (!$attivi) return null;

        $pos = array_search($mese, $attivi, true);
        return ($pos === false) ? null : ($pos + 1);
    }

    /** Minuti previsti per il mese dato, usando i due campi “mese1/mese2” con fallback sensati. */
    public function minutiPerMese(int $mese): ?int
    {
        $indice = $this->indiceVisitaPerMese($mese) ?? 1; // se non è tra i mesi previsti, tratto come 1ª
        return $indice === 2
            ? ($this->minuti_intervento_mese2 ?? $this->minuti_intervento_mese1 ?? $this->minuti_intervento)
            : ($this->minuti_intervento_mese1 ?? $this->minuti_intervento);
    }
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
