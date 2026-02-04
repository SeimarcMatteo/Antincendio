<?php

namespace App\Livewire\Presidi;

use App\Jobs\ImportPresidiDocxJob;
use App\Models\Cliente;
use App\Models\Sede;
use Livewire\Component;
use Livewire\WithFileUploads;

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
            preg_match('/\b(\d{4})\b/', $name, $m);
            $code4 = $m[1] ?? null;
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

                $this->clientiInput[$cliente->id] = $this->clientiInput[$cliente->id] ?? [
                    'nome' => $cliente->nome,
                    'mesi_visita' => $cliente->mesi_visita ?? [],
                    'minuti_intervento' => $cliente->minuti_intervento,
                    'minuti_intervento_mese1' => $cliente->minuti_intervento_mese1,
                    'minuti_intervento_mese2' => $cliente->minuti_intervento_mese2,
                    'zona' => $cliente->zona,
                ];
            }

            $this->fileRows[] = $row;
        }
    }

    public function saveClientiMissing(): void
    {
        foreach ($this->clientiInput as $id => $data) {
            $cliente = Cliente::find($id);
            if (!$cliente) continue;
            $cliente->update([
                'mesi_visita' => array_values(array_filter($data['mesi_visita'] ?? [])),
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
            $path = $file->store('import_massivo', 'local');
            $sedeId = $row['sede_id'] === 'principal' ? null : (int)$row['sede_id'];
            ImportPresidiDocxJob::dispatch(storage_path('app/'.$path), (int)$row['cliente_id'], $sedeId);
        }

        $this->dispatch('toast', type: 'success', message: 'Import massivo avviato in coda.');
        $this->reset(['files','fileRows','clientiInput','fileErrors']);
    }

    public function render()
    {
        return view('livewire.presidi.importa-presidi-massivo')
            ->layout('layouts.app', ['title' => 'Import massivo presidi']);
    }
}
