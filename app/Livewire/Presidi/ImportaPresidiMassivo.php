<?php

namespace App\Livewire\Presidi;

use App\Jobs\ImportPresidiDocxJob;
use App\Models\Cliente;
use App\Models\Sede;
use App\Models\Presidio;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportaPresidiMassivo extends Component
{
    use WithFileUploads;

    public array $files = [];
    public array $fileRows = [];
    public array $clientiInput = [];
    public array $fileErrors = [];

    public function updatedFiles(): void
    {
        $this->prepareFiles();
    }

    public function prepareFiles(): void
    {
        $this->fileErrors = [];
        $this->fileRows = [];

        if (empty($this->files)) return;

        $clienti = Cliente::all(['id','nome','codice_esterno','mesi_visita','minuti_intervento','minuti_intervento_mese1','minuti_intervento_mese2','zona']);
        $map = [];
        foreach ($clienti as $c) {
            $code = (string)($c->codice_esterno ?? '');
            $last4 = strlen($code) >= 4 ? substr($code, -4) : null;
            if (!$last4) continue;
            $map[$last4][] = $c;
        }
        $sediByCliente = Sede::query()
            ->select('id','cliente_id','nome')
            ->get()
            ->groupBy('cliente_id');

        foreach ($this->files as $i => $file) {
            $name = $file->getClientOriginalName();
            $code4 = $this->extractCode4FromFilename($name);
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
                $sedi = $sediByCliente->get($cliente->id, collect())
                    ->map(fn($s) => ['id' => $s->id, 'nome' => $s->nome])
                    ->values()
                    ->all();
                $row['sedi'] = $sedi;
                $row['sede_id'] = count($sedi) ? $sedi[0]['id'] : 'principal';
                $row['azione'] = 'skip_if_exists';

                $this->clientiInput[$cliente->id] = $this->clientiInput[$cliente->id] ?? [
                    'nome' => $cliente->nome,
                    'mesi_visita' => $this->normalizeMesiForCheckboxes($cliente->mesi_visita ?? []),
                    'minuti_intervento' => $cliente->minuti_intervento,
                    'minuti_intervento_mese1' => $cliente->minuti_intervento_mese1,
                    'minuti_intervento_mese2' => $cliente->minuti_intervento_mese2,
                    'zona' => $cliente->zona,
                ];
            }

            $this->fileRows[] = $row;
        }

        // dopo aver impostato le sedi, calcola presidi esistenti per cliente+sede
        foreach ($this->fileRows as $idx => $row) {
            if (($row['status'] ?? '') !== 'ok') continue;
            $clienteId = $row['cliente_id'] ?? null;
            if (!$clienteId) continue;
            $sedeId = $row['sede_id'] ?? null;
            $count = Presidio::where('cliente_id', $clienteId)
                ->when($sedeId === 'principal', fn($q) => $q->whereNull('sede_id'))
                ->when($sedeId !== 'principal', fn($q) => $q->where('sede_id', $sedeId))
                ->count();
            $this->fileRows[$idx]['presidi_esistenti'] = $count;
        }
    }

    public function saveClientiMissing(): void
    {
        foreach ($this->clientiInput as $id => $data) {
            $cliente = Cliente::find($id);
            if (!$cliente) continue;
            $mesi = collect($data['mesi_visita'] ?? [])
                ->filter(fn($v) => $v)
                ->keys()
                ->map(fn($m) => (int)$m)
                ->sort()
                ->values()
                ->all();
            $cliente->update([
                'mesi_visita' => $mesi,
                'minuti_intervento' => $data['minuti_intervento'] ?? null,
                'minuti_intervento_mese1' => $data['minuti_intervento_mese1'] ?? null,
                'minuti_intervento_mese2' => $data['minuti_intervento_mese2'] ?? null,
                'zona' => $data['zona'] ?? null,
            ]);
        }
        $this->dispatch('toast', type: 'success', message: 'Dati clienti aggiornati.');
        $this->prepareFiles();
    }

    public function canImport(): bool
    {
        foreach ($this->fileRows as $row) {
            if ($row['status'] !== 'ok') return false;
            if (empty($row['sede_id'])) return false;
            if (($row['presidi_esistenti'] ?? 0) > 0 && empty($row['azione'])) return false;
        }
        foreach ($this->clientiInput as $data) {
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
            ImportPresidiDocxJob::dispatch(
                $path,
                (int)$row['cliente_id'],
                $sedeId,
                $row['azione'] ?? 'skip_if_exists'
            );
        }

        $this->dispatch('toast', type: 'success', message: 'Import massivo avviato in coda.');
        $this->reset(['files','fileRows','clientiInput','fileErrors']);
    }

    public function render()
    {
        return view('livewire.presidi.importa-presidi-massivo')
            ->layout('layouts.app', ['title' => 'Import massivo presidi']);
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
}
