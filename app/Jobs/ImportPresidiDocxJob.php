<?php

namespace App\Jobs;

use App\Services\Presidi\DocxPresidiImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportPresidiDocxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $relativePath;
    public int $clienteId;
    public ?int $sedeId;
    public string $azione;

    public function __construct(string $relativePath, int $clienteId, ?int $sedeId = null, string $azione = 'skip_if_exists')
    {
        $this->relativePath = $relativePath;
        $this->clienteId = $clienteId;
        $this->sedeId = $sedeId;
        $this->azione = $azione;
    }

    public function handle(): void
    {
        $fullPath = Storage::disk('local')->path($this->relativePath);
        if (!is_file($fullPath) || filesize($fullPath) === 0) {
            Log::error('[IMPORT MASSIVO] File non trovato o vuoto', [
                'cliente_id' => $this->clienteId,
                'sede_id' => $this->sedeId,
                'path' => $this->relativePath,
                'full' => $fullPath,
            ]);
            return;
        }

        if ($this->azione === 'overwrite') {
            \App\Models\Presidio::where('cliente_id', $this->clienteId)
                ->when($this->sedeId === null, fn($q) => $q->whereNull('sede_id'))
                ->when($this->sedeId !== null, fn($q) => $q->where('sede_id', $this->sedeId))
                ->delete();
        }

        if ($this->azione === 'skip_if_exists') {
            $exists = \App\Models\Presidio::where('cliente_id', $this->clienteId)
                ->when($this->sedeId === null, fn($q) => $q->whereNull('sede_id'))
                ->when($this->sedeId !== null, fn($q) => $q->where('sede_id', $this->sedeId))
                ->exists();
            if ($exists) {
                Log::info('[IMPORT MASSIVO] Saltato (presidi già presenti)', [
                    'cliente_id' => $this->clienteId,
                    'sede_id' => $this->sedeId,
                    'path' => $this->path,
                ]);
                return;
            }
        }

        $importer = new DocxPresidiImporter($this->clienteId, $this->sedeId);
        $res = $importer->importFromPath($fullPath);
        Log::info('[IMPORT MASSIVO] Completato', [
            'cliente_id' => $this->clienteId,
            'path' => $this->relativePath,
            'importati' => $res['importati'] ?? 0,
            'saltati' => $res['saltati'] ?? 0,
        ]);
    }
}
