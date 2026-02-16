<?php

namespace App\Livewire\Anomalie;

use App\Models\Anomalia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class ImpostaPrezzi extends Component
{
    public bool $hasPrezzoColumn = false;
    public array $prezzi = [];
    public array $attive = [];
    public array $invalidPrezzi = [];

    public function mount(): void
    {
        $this->hasPrezzoColumn = Schema::hasColumn('anomalie', 'prezzo');
        $this->caricaStato();
    }

    public function updatedAttive($value, $key): void
    {
        $id = (int) $key;
        if ($id <= 0) {
            return;
        }

        if (!$this->persistAnomalia($id)) {
            $this->dispatch('toast', type: 'error', message: 'Valore prezzo non valido.');
            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Anomalia aggiornata.');
    }

    public function updatedPrezzi($value, $key): void
    {
        $id = (int) $key;
        if ($id <= 0) {
            return;
        }

        // normalizza in tempo reale input più comuni (virgola italiana)
        $raw = trim((string) $value);
        $normalized = str_replace(',', '.', $raw);
        $this->prezzi[(string) $id] = $normalized;
        unset($this->invalidPrezzi[(string) $id]);
    }

    public function salvaRiga(int $anomaliaId): void
    {
        if (!$this->hasPrezzoColumn) {
            $this->dispatch('toast', type: 'error', message: 'Colonna prezzo non trovata. Esegui le migration.');
            return;
        }

        if (!$this->persistAnomalia($anomaliaId)) {
            $this->dispatch('toast', type: 'error', message: 'Valore prezzo non valido.');
            return;
        }

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

        $this->dispatch('toast', type: 'success', message: 'Prezzi anomalie salvati con successo.');
    }

    public function getAnomalieByCategoriaProperty(): Collection
    {
        $query = Anomalia::query()->select(['id', 'categoria', 'etichetta', 'attiva']);
        if ($this->hasPrezzoColumn) {
            $query->addSelect('prezzo');
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
        ]);
    }

    private function caricaStato(): void
    {
        $this->invalidPrezzi = [];

        $query = Anomalia::query()->select(['id', 'attiva']);
        if ($this->hasPrezzoColumn) {
            $query->addSelect('prezzo');
        }

        foreach ($query->get() as $anomalia) {
            $key = (string) $anomalia->id;
            $this->attive[$key] = (bool) $anomalia->attiva;
            $this->prezzi[$key] = number_format((float) ($anomalia->prezzo ?? 0), 2, '.', '');
        }
    }

    private function persistAnomalia(int $anomaliaId): bool
    {
        $anomalia = Anomalia::find($anomaliaId);
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

        $anomalia->update($payload);

        $this->attive[(string) $anomaliaId] = (bool) $anomalia->attiva;
        if ($this->hasPrezzoColumn) {
            $this->prezzi[(string) $anomaliaId] = number_format((float) ($anomalia->prezzo ?? 0), 2, '.', '');
        }

        return true;
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
}
