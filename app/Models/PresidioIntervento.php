<?php

namespace App\Models;

use App\Models\Anomalia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class PresidioIntervento extends Model
{
    protected $table = 'presidi_intervento';
    protected $casts = [
        'anomalie' => 'array',
        'usa_ritiro' => 'boolean',
    ];
    protected $fillable = [
        'intervento_id',
        'presidio_id',
        'esito',
        'anomalie',
        'note',
        'sostituito_con_presidio_id',
        'usa_ritiro',
    ];

    public function intervento(): BelongsTo
    {
        return $this->belongsTo(Intervento::class);
    }

    public function presidio(): BelongsTo
    {
        return $this->belongsTo(Presidio::class);
    }
    public function sostituitoCon()
    {
        return $this->belongsTo(Presidio::class, 'sostituito_con_presidio_id');
    }

    public function anomalieItems(): HasMany
    {
        return $this->hasMany(PresidioInterventoAnomalia::class, 'presidio_intervento_id');
    }

    public function getAnomalieAttribute($value)
    {
        $ids = $this->anomalie_ids;
        if (empty($ids)) {
            return collect();
        }
        return Anomalia::whereIn('id', $ids)->get();
    }

    public function getAnomalieIdsAttribute(): array
    {
        if (Schema::hasTable('presidio_intervento_anomalie')) {
            if ($this->relationLoaded('anomalieItems') && $this->anomalieItems->isNotEmpty()) {
                return $this->anomalieItems
                    ->pluck('anomalia_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();
            }

            if ($this->anomalieItems()->exists()) {
                return $this->anomalieItems()
                    ->pluck('anomalia_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();
            }
        }

        $decoded = is_array($this->attributes['anomalie'] ?? null)
            ? $this->attributes['anomalie']
            : json_decode((string) ($this->attributes['anomalie'] ?? '[]'), true);

        if (!is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
