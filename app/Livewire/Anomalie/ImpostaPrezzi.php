<?php

namespace App\Livewire\Anomalie;

use App\Models\Anomalia;
use App\Models\AnomaliaPrezzoTipoEstintore;
use App\Models\AnomaliaPrezzoTipoPresidio;
use App\Models\TipoEstintore;
use App\Models\TipoPresidio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class ImpostaPrezzi extends Component
{
    public bool $hasPrezzoColumn = false;
    public bool $hasFlagTipoEstintoreColumn = false;
    public bool $hasFlagTipoPresidioColumn = false;
    public bool $hasPrezziTipoEstintoreTable = false;
    public bool $hasPrezziTipoPresidioTable = false;

    public array $prezzi = [];
    public array $attive = [];
    public array $usaPrezziTipo = [];
    public array $prezziTipoEstintore = [];
    public array $prezziTipoPresidio = [];
    public array $prezziTipoAttiviEstintore = [];
    public array $prezziTipoAttiviPresidio = [];

    public array $invalidPrezzi = [];
    public array $invalidPrezziTipoEstintore = [];
    public array $invalidPrezziTipoPresidio = [];

    public array $tipiEstintori = [];
    public array $tipiIdranti = [];
    public array $tipiPorte = [];

    public function mount(): void
    {
        $this->hasPrezzoColumn = Schema::hasColumn('anomalie', 'prezzo');
        $this->hasFlagTipoEstintoreColumn = Schema::hasColumn('anomalie', 'usa_prezzi_tipo_estintore');
        $this->hasFlagTipoPresidioColumn = Schema::hasColumn('anomalie', 'usa_prezzi_tipo_presidio');
        $this->hasPrezziTipoEstintoreTable = Schema::hasTable('anomalia_prezzi_tipo_estintore');
        $this->hasPrezziTipoPresidioTable = Schema::hasTable('anomalia_prezzi_tipo_presidio');

        $this->tipiEstintori = TipoEstintore::query()
            ->select(['id', 'sigla', 'descrizione'])
            ->orderBy('descrizione')
            ->orderBy('sigla')
            ->get()
            ->map(fn (TipoEstintore $tipo) => [
                'id' => (int) $tipo->id,
                'label' => trim((string) ($tipo->sigla . ' ' . $tipo->descrizione)),
            ])
            ->values()
            ->all();

        $tipiPresidio = TipoPresidio::query()
            ->select(['id', 'categoria', 'nome'])
            ->orderBy('categoria')
            ->orderBy('nome')
            ->get()
            ->map(fn (TipoPresidio $tipo) => (object) [
                'id' => (int) $tipo->id,
                'categoria' => (string) $tipo->categoria,
                'label' => trim((string) $tipo->nome),
            ]);

        $this->tipiIdranti = $tipiPresidio
            ->filter(fn ($tipo) => $this->isCategoriaIdrante($tipo->categoria))
            ->values()
            ->all();
        $this->tipiPorte = $tipiPresidio
            ->filter(fn ($tipo) => $this->isCategoriaPorta($tipo->categoria))
            ->values()
            ->all();

        $this->caricaStato();
    }

    public function updatedAttive($value, $key): void
    {
        $id = (int) $key;
        if ($id <= 0) {
            return;
        }

        if (!$this->persistAnomalia($id)) {
            $this->dispatch('toast', type: 'error', message: 'Valori prezzo non validi.');
            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Anomalia aggiornata.');
    }

    public function updatedUsaPrezziTipo($value, $key): void
    {
        $id = (int) $key;
        if ($id <= 0) {
            return;
        }

        $this->usaPrezziTipo[(string) $id] = filter_var($value, FILTER_VALIDATE_BOOL);
        $this->salvaRiga($id);
    }

    public function updatedPrezzi($value, $key): void
    {
        $id = (int) $key;
        if ($id <= 0) {
            return;
        }

        $raw = trim((string) $value);
        $normalized = str_replace(',', '.', $raw);
        $this->prezzi[(string) $id] = $normalized;
        unset($this->invalidPrezzi[(string) $id]);
    }

    public function updatedPrezziTipoEstintore($value, $key): void
    {
        [$anomaliaId, $tipoId] = $this->splitNestedKey($key);
        if ($anomaliaId <= 0 || $tipoId <= 0) {
            return;
        }

        $this->prezziTipoEstintore[(string) $anomaliaId][(string) $tipoId] = str_replace(',', '.', trim((string) $value));
        unset($this->invalidPrezziTipoEstintore[$this->priceKey($anomaliaId, $tipoId)]);
    }

    public function updatedPrezziTipoPresidio($value, $key): void
    {
        [$anomaliaId, $tipoId] = $this->splitNestedKey($key);
        if ($anomaliaId <= 0 || $tipoId <= 0) {
            return;
        }

        $this->prezziTipoPresidio[(string) $anomaliaId][(string) $tipoId] = str_replace(',', '.', trim((string) $value));
        unset($this->invalidPrezziTipoPresidio[$this->priceKey($anomaliaId, $tipoId)]);
    }

    public function updatedPrezziTipoAttiviEstintore($value, $key): void
    {
        [$anomaliaId, $tipoId] = $this->splitNestedKey($key);
        if ($anomaliaId <= 0 || $tipoId <= 0) {
            return;
        }

        $enabled = filter_var($value, FILTER_VALIDATE_BOOL);
        $this->prezziTipoAttiviEstintore[(string) $anomaliaId][(string) $tipoId] = $enabled;
        if (!$enabled) {
            unset($this->invalidPrezziTipoEstintore[$this->priceKey($anomaliaId, $tipoId)]);
            $this->prezziTipoEstintore[(string) $anomaliaId][(string) $tipoId] = '';
        }

        $this->salvaRiga($anomaliaId);
    }

    public function updatedPrezziTipoAttiviPresidio($value, $key): void
    {
        [$anomaliaId, $tipoId] = $this->splitNestedKey($key);
        if ($anomaliaId <= 0 || $tipoId <= 0) {
            return;
        }

        $enabled = filter_var($value, FILTER_VALIDATE_BOOL);
        $this->prezziTipoAttiviPresidio[(string) $anomaliaId][(string) $tipoId] = $enabled;
        if (!$enabled) {
            unset($this->invalidPrezziTipoPresidio[$this->priceKey($anomaliaId, $tipoId)]);
            $this->prezziTipoPresidio[(string) $anomaliaId][(string) $tipoId] = '';
        }

        $this->salvaRiga($anomaliaId);
    }

    public function salvaRiga(int $anomaliaId): void
    {
        if (!$this->hasPrezzoColumn) {
            $this->dispatch('toast', type: 'error', message: 'Colonna prezzo non trovata. Esegui le migration.');
            return;
        }

        if (!$this->persistAnomalia($anomaliaId)) {
            $this->dispatch('toast', type: 'error', message: 'Valori prezzo non validi.');
            return;
        }

        $this->caricaStato();
        $this->dispatch('toast', type: 'success', message: 'Anomalia aggiornata.');
    }

    public function salvaTutti(): void
    {
        if (!$this->hasPrezzoColumn) {
            $this->dispatch('toast', type: 'error', message: 'Colonna prezzo non trovata. Esegui le migration.');
            return;
        }

        $invalidi = [];
        $ids = Anomalia::query()->pluck('id')->all();

        foreach ($ids as $id) {
            $id = (int) $id;
            if (!$this->persistAnomalia($id)) {
                $invalidi[] = $id;
            }
        }

        if (!empty($invalidi)) {
            $this->dispatch('toast', type: 'error', message: 'Alcuni prezzi non sono validi. Controlla i campi evidenziati.');
            return;
        }

        $this->caricaStato();
        $this->dispatch('toast', type: 'success', message: 'Prezzi anomalie salvati con successo.');
    }

    public function getAnomalieByCategoriaProperty(): Collection
    {
        $query = Anomalia::query()->select(['id', 'categoria', 'etichetta', 'attiva']);

        if ($this->hasPrezzoColumn) {
            $query->addSelect('prezzo');
        }
        if ($this->hasFlagTipoEstintoreColumn) {
            $query->addSelect('usa_prezzi_tipo_estintore');
        }
        if ($this->hasFlagTipoPresidioColumn) {
            $query->addSelect('usa_prezzi_tipo_presidio');
        }

        return $query
            ->orderBy('categoria')
            ->orderBy('etichetta')
            ->get()
            ->groupBy(fn (Anomalia $anomalia) => (string) $anomalia->categoria);
    }

    public function render()
    {
        return view('livewire.anomalie.imposta-prezzi', [
            'anomalieByCategoria' => $this->anomalieByCategoria,
            'hasPrezzoColumn' => $this->hasPrezzoColumn,
            'tipiEstintori' => $this->tipiEstintori,
            'tipiIdranti' => $this->tipiIdranti,
            'tipiPorte' => $this->tipiPorte,
        ]);
    }

    private function caricaStato(): void
    {
        $this->invalidPrezzi = [];
        $this->invalidPrezziTipoEstintore = [];
        $this->invalidPrezziTipoPresidio = [];

        $query = Anomalia::query()->select(['id', 'categoria', 'attiva']);
        if ($this->hasPrezzoColumn) {
            $query->addSelect('prezzo');
        }
        if ($this->hasFlagTipoEstintoreColumn) {
            $query->addSelect('usa_prezzi_tipo_estintore');
        }
        if ($this->hasFlagTipoPresidioColumn) {
            $query->addSelect('usa_prezzi_tipo_presidio');
        }
        if ($this->hasPrezziTipoEstintoreTable) {
            $query->with('prezziTipoEstintore');
        }
        if ($this->hasPrezziTipoPresidioTable) {
            $query->with('prezziTipoPresidio');
        }

        foreach ($query->get() as $anomalia) {
            $id = (int) $anomalia->id;
            $key = (string) $id;
            $mode = $this->modeForCategoria((string) ($anomalia->categoria ?? ''));
            $flagEst = (bool) ($anomalia->usa_prezzi_tipo_estintore ?? false);
            $flagPres = (bool) ($anomalia->usa_prezzi_tipo_presidio ?? false);

            $this->attive[$key] = (bool) $anomalia->attiva;
            $this->prezzi[$key] = number_format((float) ($anomalia->prezzo ?? 0), 2, '.', '');
            $this->usaPrezziTipo[$key] = $mode === 'estintore'
                ? $flagEst
                : ($mode === 'presidio' ? $flagPres : ($flagEst || $flagPres));

            $this->prezziTipoEstintore[$key] = collect($anomalia->prezziTipoEstintore ?? [])
                ->mapWithKeys(fn ($row) => [
                    (string) ((int) $row->tipo_estintore_id) => number_format((float) ($row->prezzo ?? 0), 2, '.', ''),
                ])
                ->toArray();
            $this->prezziTipoAttiviEstintore[$key] = collect(array_keys($this->prezziTipoEstintore[$key]))
                ->mapWithKeys(fn ($tipoId) => [(string) $tipoId => true])
                ->toArray();

            $this->prezziTipoPresidio[$key] = collect($anomalia->prezziTipoPresidio ?? [])
                ->mapWithKeys(fn ($row) => [
                    (string) ((int) $row->tipo_presidio_id) => number_format((float) ($row->prezzo ?? 0), 2, '.', ''),
                ])
                ->toArray();
            $this->prezziTipoAttiviPresidio[$key] = collect(array_keys($this->prezziTipoPresidio[$key]))
                ->mapWithKeys(fn ($tipoId) => [(string) $tipoId => true])
                ->toArray();
        }
    }

    private function persistAnomalia(int $anomaliaId): bool
    {
        $anomalia = Anomalia::query()->find($anomaliaId);
        if (!$anomalia) {
            return true;
        }

        $payload = [
            'attiva' => (bool) $this->valueById($this->attive, $anomaliaId, false),
        ];

        if ($this->hasPrezzoColumn) {
            $parsedPrezzo = $this->parsePrezzo($this->valueById($this->prezzi, $anomaliaId));
            if ($parsedPrezzo === null) {
                $this->invalidPrezzi[(string) $anomaliaId] = true;
                return false;
            }
            unset($this->invalidPrezzi[(string) $anomaliaId]);
            $payload['prezzo'] = $parsedPrezzo;
        }

        $mode = $this->modeForCategoria((string) ($anomalia->categoria ?? ''));
        $useTipo = (bool) $this->valueById($this->usaPrezziTipo, $anomaliaId, false);

        if ($this->hasFlagTipoEstintoreColumn) {
            $payload['usa_prezzi_tipo_estintore'] = $mode === 'estintore' ? $useTipo : false;
        }
        if ($this->hasFlagTipoPresidioColumn) {
            $payload['usa_prezzi_tipo_presidio'] = $mode === 'presidio' ? $useTipo : false;
        }

        try {
            $anomalia->update($payload);
        } catch (\Throwable $e) {
            Log::error('Errore salvataggio anomalia', [
                'anomalia_id' => $anomaliaId,
                'payload' => $payload,
                'message' => $e->getMessage(),
            ]);
            return false;
        }

        if ($mode === 'estintore') {
            if ($this->hasPrezziTipoEstintoreTable && !$this->persistPrezziTipoEstintore($anomaliaId, $useTipo)) {
                return false;
            }
            if ($this->hasPrezziTipoPresidioTable) {
                AnomaliaPrezzoTipoPresidio::query()
                    ->where('anomalia_id', $anomaliaId)
                    ->delete();
            }
        } elseif ($mode === 'presidio') {
            if ($this->hasPrezziTipoPresidioTable && !$this->persistPrezziTipoPresidio($anomaliaId, $useTipo)) {
                return false;
            }
            if ($this->hasPrezziTipoEstintoreTable) {
                AnomaliaPrezzoTipoEstintore::query()
                    ->where('anomalia_id', $anomaliaId)
                    ->delete();
            }
        } else {
            if ($this->hasPrezziTipoEstintoreTable) {
                AnomaliaPrezzoTipoEstintore::query()
                    ->where('anomalia_id', $anomaliaId)
                    ->delete();
            }
            if ($this->hasPrezziTipoPresidioTable) {
                AnomaliaPrezzoTipoPresidio::query()
                    ->where('anomalia_id', $anomaliaId)
                    ->delete();
            }
        }

        return true;
    }

    private function persistPrezziTipoEstintore(int $anomaliaId, bool $enabled): bool
    {
        if (!$enabled) {
            AnomaliaPrezzoTipoEstintore::query()
                ->where('anomalia_id', $anomaliaId)
                ->delete();
            return true;
        }

        $activeRows = $this->valueById($this->prezziTipoAttiviEstintore, $anomaliaId, []);
        $activeRows = is_array($activeRows) ? $activeRows : [];

        $rows = $this->valueById($this->prezziTipoEstintore, $anomaliaId, []);
        $rows = is_array($rows) ? $rows : [];

        $keep = [];
        foreach ($activeRows as $tipoIdRaw => $enabledRaw) {
            $enabled = filter_var($enabledRaw, FILTER_VALIDATE_BOOL);
            if (!$enabled) {
                continue;
            }

            $tipoId = (int) $tipoIdRaw;
            if ($tipoId <= 0) {
                continue;
            }

            $key = $this->priceKey($anomaliaId, $tipoId);
            $parsed = $this->parsePrezzoTipologiaChecked($this->valueById($rows, $tipoId));

            if ($parsed === null) {
                $this->invalidPrezziTipoEstintore[$key] = true;
                return false;
            }

            unset($this->invalidPrezziTipoEstintore[$key]);

            AnomaliaPrezzoTipoEstintore::query()->updateOrCreate(
                [
                    'anomalia_id' => $anomaliaId,
                    'tipo_estintore_id' => $tipoId,
                ],
                [
                    'prezzo' => $parsed,
                ]
            );
            $keep[] = $tipoId;
        }

        AnomaliaPrezzoTipoEstintore::query()
            ->where('anomalia_id', $anomaliaId)
            ->when(!empty($keep), fn ($q) => $q->whereNotIn('tipo_estintore_id', $keep))
            ->delete();

        return true;
    }

    private function persistPrezziTipoPresidio(int $anomaliaId, bool $enabled): bool
    {
        if (!$enabled) {
            AnomaliaPrezzoTipoPresidio::query()
                ->where('anomalia_id', $anomaliaId)
                ->delete();
            return true;
        }

        $activeRows = $this->valueById($this->prezziTipoAttiviPresidio, $anomaliaId, []);
        $activeRows = is_array($activeRows) ? $activeRows : [];

        $rows = $this->valueById($this->prezziTipoPresidio, $anomaliaId, []);
        $rows = is_array($rows) ? $rows : [];

        $keep = [];
        foreach ($activeRows as $tipoIdRaw => $enabledRaw) {
            $enabled = filter_var($enabledRaw, FILTER_VALIDATE_BOOL);
            if (!$enabled) {
                continue;
            }

            $tipoId = (int) $tipoIdRaw;
            if ($tipoId <= 0) {
                continue;
            }

            $key = $this->priceKey($anomaliaId, $tipoId);
            $parsed = $this->parsePrezzoTipologiaChecked($this->valueById($rows, $tipoId));

            if ($parsed === null) {
                $this->invalidPrezziTipoPresidio[$key] = true;
                return false;
            }

            unset($this->invalidPrezziTipoPresidio[$key]);

            AnomaliaPrezzoTipoPresidio::query()->updateOrCreate(
                [
                    'anomalia_id' => $anomaliaId,
                    'tipo_presidio_id' => $tipoId,
                ],
                [
                    'prezzo' => $parsed,
                ]
            );
            $keep[] = $tipoId;
        }

        AnomaliaPrezzoTipoPresidio::query()
            ->where('anomalia_id', $anomaliaId)
            ->when(!empty($keep), fn ($q) => $q->whereNotIn('tipo_presidio_id', $keep))
            ->delete();

        return true;
    }

    private function splitNestedKey($key): array
    {
        $parts = explode('.', (string) $key);
        if (count($parts) < 2) {
            return [0, 0];
        }

        return [(int) $parts[0], (int) $parts[1]];
    }

    private function priceKey(int $anomaliaId, int $tipoId): string
    {
        return $anomaliaId . ':' . $tipoId;
    }

    private function valueById(array $source, int $id, $default = null)
    {
        if (array_key_exists($id, $source)) {
            return $source[$id];
        }

        $key = (string) $id;
        if (array_key_exists($key, $source)) {
            return $source[$key];
        }

        return $default;
    }

    private function parsePrezzo($raw): ?float
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return 0.0;
        }

        $value = preg_replace('/[^0-9,.\-]/', '', $value);
        $value = str_replace(',', '.', (string) $value);

        if (substr_count((string) $value, '.') > 1) {
            $parts = explode('.', (string) $value);
            $decimal = array_pop($parts);
            $value = implode('', $parts) . '.' . $decimal;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return max(0, round((float) $value, 2));
    }

    private function parsePrezzoTipologiaChecked($raw): ?float
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        $parsed = $this->parsePrezzo($value);
        if ($parsed === null) {
            return null;
        }

        return $parsed;
    }

    private function modeForCategoria(?string $categoria): string
    {
        if ($this->isCategoriaEstintore($categoria)) {
            return 'estintore';
        }

        if ($this->isCategoriaIdrante($categoria) || $this->isCategoriaPorta($categoria)) {
            return 'presidio';
        }

        return '';
    }

    private function isCategoriaEstintore(?string $categoria): bool
    {
        $cat = mb_strtolower(trim((string) $categoria));
        return str_contains($cat, 'estint');
    }

    private function isCategoriaIdrante(?string $categoria): bool
    {
        $cat = mb_strtolower(trim((string) $categoria));
        return str_contains($cat, 'idrant');
    }

    private function isCategoriaPorta(?string $categoria): bool
    {
        $cat = mb_strtolower(trim((string) $categoria));
        return str_contains($cat, 'port');
    }
}
