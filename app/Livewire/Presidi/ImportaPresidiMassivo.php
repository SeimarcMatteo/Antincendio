<?php

namespace App\Livewire\Presidi;

use App\Jobs\ImportPresidiDocxJob;
use App\Models\Cliente;
use App\Models\Sede;
use App\Models\Presidio;
use App\Models\ImportMassivoFile;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportaPresidiMassivo extends Component
{
    use WithFileUploads;

    public array $files = [];
    public array $fileRows = [];
    public array $targetInputs = [];
    public array $fileErrors = [];
    public ?string $batchId = null;
    public array $jobs = [];
    public array $zoneSuggestions = [];

    public function mount(): void
    {
        $this->refreshZoneSuggestions();
    }

    public function updatedFiles(): void
    {
        $this->prepareFiles();
    }

    public function prepareFiles(): void
    {
        $this->fileErrors = [];
        $prevRows = collect($this->fileRows ?? [])
            ->keyBy('name')
            ->map(fn($r) => [
                'sede_id' => $r['sede_id'] ?? null,
                'azione' => $r['azione'] ?? null,
            ])
            ->all();
        $this->fileRows = [];
        $this->targetInputs = [];

        if (empty($this->files)) return;

        $clienti = Cliente::all([
            'id','nome','codice_esterno','mesi_visita',
            'minuti_intervento','minuti_intervento_mese1','minuti_intervento_mese2','zona'
        ]);
        $map = [];
        $clientiById = [];
        foreach ($clienti as $c) {
            $clientiById[$c->id] = $c;
            $code = (string)($c->codice_esterno ?? '');
            $last4 = strlen($code) >= 4 ? substr($code, -4) : null;
            if (!$last4) continue;
            $map[$last4][] = $c;
        }
        $sediByCliente = Sede::query()
            ->select('id','cliente_id','nome','indirizzo','citta','cap','provincia','mesi_visita','minuti_intervento','minuti_intervento_mese1','minuti_intervento_mese2','zona')
            ->get()
            ->groupBy('cliente_id');
        $sediById = Sede::query()
            ->select('id','cliente_id','nome','indirizzo','citta','cap','provincia','mesi_visita','minuti_intervento','minuti_intervento_mese1','minuti_intervento_mese2','zona')
            ->get()
            ->keyBy('id');

        foreach ($this->files as $i => $file) {
            $name = $file->getClientOriginalName();
            $code4 = $this->extractCode4FromFilename($name);
            $prev = $prevRows[$name] ?? null;
            $row = [
                'index' => $i,
                'name' => $name,
                'code4' => $code4,
                'cliente_id' => null,
                'cliente_nome' => null,
                'status' => 'ok',
            ];

            if (!$code4) {
                $row['status'] = 'no_code';
                $this->fileErrors[] = "File {$name}: codice 4 cifre non trovato.";
            } elseif (!isset($map[$code4])) {
                $row['status'] = 'no_match';
                $this->fileErrors[] = "File {$name}: nessun cliente con codice esterno che termina per {$code4}.";
            } elseif (count($map[$code4]) > 1) {
                $row['status'] = 'ambiguous';
                $this->fileErrors[] = "File {$name}: più clienti con codice esterno che termina per {$code4}.";
            } else {
                $cliente = $map[$code4][0];
                $row['cliente_id'] = $cliente->id;
                $row['cliente_nome'] = $cliente->nome;
                $row['principal_label'] = $this->formatClienteLabel($cliente);
                $sedi = $sediByCliente->get($cliente->id, collect())
                    ->map(fn($s) => [
                        'id' => $s->id,
                        'nome' => $s->nome,
                        'label' => $this->formatSedeLabel($s),
                    ])
                    ->values()
                    ->all();
                $row['sedi'] = $sedi;
                $defaultSede = count($sedi) ? $sedi[0]['id'] : 'principal';
                $selectedSede = $prev['sede_id'] ?? $defaultSede;
                if ($selectedSede !== 'principal') {
                    $exists = collect($sedi)->contains(fn($s) => (string)$s['id'] === (string)$selectedSede);
                    if (!$exists) $selectedSede = $defaultSede;
                }
                $row['sede_id'] = $selectedSede;
                $row['azione'] = $prev['azione'] ?? 'skip_duplicates';
                $row['target_key'] = $this->targetKeyFor($row['cliente_id'], $row['sede_id']);
                $this->ensureTargetInput($row['target_key'], $cliente, $sediById->get($row['sede_id']));
            }

            $this->fileRows[] = $row;
        }

        // dopo aver impostato le sedi, calcola presidi esistenti per cliente+sede
        foreach ($this->fileRows as $idx => $row) {
            if (($row['status'] ?? '') !== 'ok') continue;
            $clienteId = $row['cliente_id'] ?? null;
            if (!$clienteId) continue;
            $sedeId = $row['sede_id'] ?? null;
            $count = $this->countPresidi($clienteId, $sedeId);
            $this->fileRows[$idx]['presidi_esistenti'] = $count;
            $this->fileRows[$idx]['target_key'] = $this->targetKeyFor($clienteId, $sedeId);
            $this->ensureTargetInput(
                $this->fileRows[$idx]['target_key'],
                $clientiById[$clienteId] ?? null,
                $sedeId && $sedeId !== 'principal' ? $sediById->get((int)$sedeId) : null
            );
        }
    }

    public function saveTargetInputs(): void
    {
        foreach ($this->targetInputs as $key => $data) {
            $type = $data['target_type'] ?? null;
            if (!$type) continue;
            $mesi = collect($data['mesi_visita'] ?? [])
                ->filter(fn($v) => $v)
                ->keys()
                ->map(fn($m) => (int)$m)
                ->sort()
                ->values()
                ->all();
            $payload = [
                'mesi_visita' => $mesi,
                'minuti_intervento' => $data['minuti_intervento'] ?? null,
                'minuti_intervento_mese1' => $data['minuti_intervento_mese1'] ?? null,
                'minuti_intervento_mese2' => $data['minuti_intervento_mese2'] ?? null,
                'zona' => $data['zona'] ?? null,
            ];
            if ($type === 'cliente') {
                $cliente = Cliente::find($data['cliente_id'] ?? null);
                if ($cliente) $cliente->update($payload);
            } elseif ($type === 'sede') {
                $sede = Sede::find($data['sede_id'] ?? null);
                if ($sede) $sede->update($payload);
            }
        }
        $this->dispatch('toast', type: 'success', message: 'Dati aggiornati.');
        $this->refreshZoneSuggestions();
        $this->prepareFiles();
    }

    public function canImport(): bool
    {
        $usedKeys = [];
        foreach ($this->fileRows as $row) {
            if ($row['status'] !== 'ok') return false;
            if (empty($row['sede_id'])) return false;
            if (($row['presidi_esistenti'] ?? 0) > 0 && empty($row['azione'])) return false;
            if (!empty($row['target_key'])) $usedKeys[$row['target_key']] = true;
        }
        foreach (array_keys($usedKeys) as $key) {
            $data = $this->targetInputs[$key] ?? [];
            $mesi = $data['mesi_visita'] ?? [];
            if (empty($mesi)) return false;
            if (empty($data['minuti_intervento_mese1']) || empty($data['minuti_intervento_mese2'])) return false;
            if (empty($data['zona'])) return false;
        }
        return true;
    }

    public function confermaImportMassivo(): void
    {
        if (!$this->canImport()) {
            $this->dispatch('toast', type: 'error', message: 'Completa i dati mancanti prima di importare.');
            return;
        }

        $this->batchId = (string) Str::uuid();
        foreach ($this->fileRows as $row) {
            if ($row['status'] !== 'ok') continue;
            $file = $this->files[$row['index']] ?? null;
            if (!$file) continue;
            $ext = $file->getClientOriginalExtension() ?: 'docx';
            $name = Str::uuid()->toString().'.'.$ext;
            $path = $file->storeAs('import_massivo', $name, 'local');
            if (!Storage::disk('local')->exists($path)) {
                $this->fileErrors[] = "File {$row['name']}: salvataggio non riuscito.";
                continue;
            }
            $sedeId = $row['sede_id'] === 'principal' ? null : (int)$row['sede_id'];
            $import = ImportMassivoFile::create([
                'batch_id' => $this->batchId,
                'cliente_id' => (int) $row['cliente_id'],
                'sede_id' => $sedeId,
                'original_name' => $row['name'],
                'stored_path' => $path,
                'azione' => $row['azione'] ?? 'skip_duplicates',
                'status' => 'queued',
            ]);
            ImportPresidiDocxJob::dispatch(
                $import->id,
                $path,
                (int)$row['cliente_id'],
                $sedeId,
                $row['azione'] ?? 'skip_duplicates'
            );
        }

        $this->dispatch('toast', type: 'success', message: 'Import massivo avviato in coda.');
        $this->reset(['files','fileRows','targetInputs','fileErrors']);
        $this->refreshJobStatuses();
    }

    public function sedeChanged(int $index): void
    {
        $row = $this->fileRows[$index] ?? null;
        if (!$row || ($row['status'] ?? '') !== 'ok') return;

        $clienteId = $row['cliente_id'] ?? null;
        $sedeId = $row['sede_id'] ?? null;
        if (!$clienteId) return;

        $this->fileRows[$index]['target_key'] = $this->targetKeyFor($clienteId, $sedeId);
        $cliente = Cliente::find($clienteId);
        $sede = ($sedeId && $sedeId !== 'principal') ? Sede::find((int)$sedeId) : null;
        $this->ensureTargetInput($this->fileRows[$index]['target_key'], $cliente, $sede);
        $this->fileRows[$index]['presidi_esistenti'] = $this->countPresidi($clienteId, $sedeId);
    }

    public function render()
    {
        return view('livewire.presidi.importa-presidi-massivo')
            ->layout('layouts.app', ['title' => 'Import massivo presidi']);
    }

    public function refreshJobStatuses(): void
    {
        if (!$this->batchId) return;
        $this->jobs = ImportMassivoFile::where('batch_id', $this->batchId)
            ->orderBy('id')
            ->get()
            ->toArray();
    }

    private function extractCode4FromFilename(string $name): ?string
    {
        preg_match_all('/\d+/', $name, $m);
        if (empty($m[0])) return null;
        $raw = end($m[0]);
        if ($raw === '') return null;
        if (strlen($raw) > 4) {
            $raw = substr($raw, -4);
        }
        return str_pad($raw, 4, '0', STR_PAD_LEFT);
    }

    private function normalizeMesiForCheckboxes($raw): array
    {
        $map = ['gen'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'mag'=>5,'giu'=>6,'lug'=>7,'ago'=>8,'set'=>9,'ott'=>10,'nov'=>11,'dic'=>12];
        $out = [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = array_map('trim', explode(',', $raw));
            }
        }
        if (is_array($raw)) {
            if (array_values($raw) === $raw) {
                foreach ($raw as $v) {
                    $m = is_numeric($v) ? (int)$v : ($map[mb_strtolower((string)$v)] ?? null);
                    if ($m && $m >= 1 && $m <= 12) $out[$m] = true;
                }
            } else {
                foreach ($raw as $k => $v) {
                    if (!$v) continue;
                    $m = is_numeric($k) ? (int)$k : ($map[mb_strtolower((string)$k)] ?? null);
                    if ($m && $m >= 1 && $m <= 12) $out[$m] = true;
                }
            }
        }
        return $out;
    }

    private function targetKeyFor(?int $clienteId, $sedeId): ?string
    {
        if (!$clienteId) return null;
        if ($sedeId === 'principal' || $sedeId === null) return 'c:'.$clienteId;
        return 's:'.$sedeId;
    }

    private function ensureTargetInput(?string $key, ?Cliente $cliente, ?Sede $sede): void
    {
        if (!$key || isset($this->targetInputs[$key])) return;

        if (str_starts_with($key, 'c:') && $cliente) {
            $this->targetInputs[$key] = [
                'label' => $cliente->nome.' — Sede principale',
                'target_type' => 'cliente',
                'cliente_id' => $cliente->id,
                'sede_id' => null,
                'mesi_visita' => $this->normalizeMesiForCheckboxes($cliente->mesi_visita ?? []),
                'minuti_intervento' => $cliente->minuti_intervento,
                'minuti_intervento_mese1' => $cliente->minuti_intervento_mese1,
                'minuti_intervento_mese2' => $cliente->minuti_intervento_mese2,
                'zona' => $cliente->zona,
            ];
            return;
        }

        if (str_starts_with($key, 's:') && $sede) {
            $this->targetInputs[$key] = [
                'label' => ($cliente?->nome ? $cliente->nome.' — ' : '').($this->formatSedeLabel($sede)),
                'target_type' => 'sede',
                'cliente_id' => $sede->cliente_id,
                'sede_id' => $sede->id,
                'mesi_visita' => $this->normalizeMesiForCheckboxes($sede->mesi_visita ?? []),
                'minuti_intervento' => $sede->minuti_intervento ?? null,
                'minuti_intervento_mese1' => $sede->minuti_intervento_mese1 ?? null,
                'minuti_intervento_mese2' => $sede->minuti_intervento_mese2 ?? null,
                'zona' => $sede->zona ?? null,
            ];
            return;
        }
    }

    private function countPresidi(int $clienteId, $sedeId): int
    {
        return Presidio::where('cliente_id', $clienteId)
            ->when($sedeId === 'principal', fn($q) => $q->whereNull('sede_id'))
            ->when($sedeId !== 'principal', fn($q) => $q->where('sede_id', $sedeId))
            ->count();
    }

    private function refreshZoneSuggestions(): void
    {
        $clienteZones = Cliente::query()
            ->whereNotNull('zona')
            ->where('zona', '!=', '')
            ->pluck('zona');

        $sedeZones = Sede::query()
            ->whereNotNull('zona')
            ->where('zona', '!=', '')
            ->pluck('zona');

        $this->zoneSuggestions = $clienteZones
            ->merge($sedeZones)
            ->map(fn($z) => trim(preg_replace('/\s+/', ' ', (string)$z)))
            ->filter()
            ->unique(fn($z) => mb_strtolower($z))
            ->sort()
            ->values()
            ->toArray();
    }

    private function formatSedeLabel(Sede $sede): string
    {
        $nome = trim((string) $sede->nome);
        $indirizzo = trim((string) $sede->indirizzo);
        $cap = trim((string) $sede->cap);
        $citta = trim((string) $sede->citta);
        $provincia = trim((string) $sede->provincia);

        $localita = trim(($cap !== '' ? $cap.' ' : '').$citta);
        if ($provincia !== '') {
            $localita = trim($localita.' ('.$provincia.')');
        }

        $dettagli = trim($indirizzo.($indirizzo && $localita ? ', ' : '').$localita);
        if ($dettagli !== '') {
            return $nome.' — '.$dettagli;
        }
        return $nome !== '' ? $nome : 'Sede';
    }

    private function formatClienteLabel(Cliente $cliente): string
    {
        $nome = trim((string) $cliente->nome);
        $indirizzo = trim((string) $cliente->indirizzo);
        $cap = trim((string) $cliente->cap);
        $citta = trim((string) $cliente->citta);
        $provincia = trim((string) $cliente->provincia);

        $localita = trim(($cap !== '' ? $cap.' ' : '').$citta);
        if ($provincia !== '') {
            $localita = trim($localita.' ('.$provincia.')');
        }

        $dettagli = trim($indirizzo.($indirizzo && $localita ? ', ' : '').$localita);
        $label = $nome !== '' ? $nome : 'Cliente';
        if ($dettagli !== '') {
            return $label.' — '.$dettagli;
        }
        return $label;
    }
}
