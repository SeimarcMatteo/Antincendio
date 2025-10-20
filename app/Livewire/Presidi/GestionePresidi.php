<?php
namespace App\Livewire\Presidi;

use App\Models\Presidio;
use App\Models\Cliente;
use App\Models\Sede;
use App\Models\TipoEstintore;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

class GestionePresidi extends Component
{
    public $clienteId;
    public $sedeId;
    public $categoria = 'Estintore';
    public $ubicazione;
    public $tipoContratto;
    public $tipoEstintore;
    public $dataManutenzioneAnnuale;
    public $dataRevisione;
    public $dataCollaudo;
    public $categoriaAttiva = 'Estintore';
    public $anomalia1 = false;
    public $anomalia2 = false;
    public $anomalia3 = false;
    public $note;
    public $dataSerbatoio;
    public $flagPreventivo = false;
    public $descrizione;

    public Cliente $cliente;
    public $sede;
    public $presidioInModifica = null;
    public $presidiData = [];
    
    protected $rules = [
        'ubicazione' => 'required|string|max:255',
        'tipoContratto' => 'required|string|max:255',
        'categoria' => 'required|in:Estintore,Idrante,Porta',
        'tipoEstintore' => 'required_if:categoria,Estintore|exists:tipi_estintori,id',
        'anomalia1' => 'nullable|boolean',
        'anomalia2' => 'nullable|boolean',
        'anomalia3' => 'nullable|boolean',
        'note' => 'nullable|string|max:1000',
        'dataSerbatoio' => 'required_if:categoria,Estintore|date',
        'flagPreventivo' => 'nullable|boolean',
        'descrizione' => 'nullable|string|max:255',
    ];

    public function mount($clienteId, $sedeId = null)
    {
        $this->clienteId = $clienteId;
        $this->sedeId = $sedeId;

        $this->cliente = Cliente::findOrFail($clienteId);

        if ($sedeId === 'principale' || $sedeId === null) {
            $this->sede = (object) session('sede_custom', [
                'nome' => 'Sede principale',
                'indirizzo' => $this->cliente->indirizzo,
                'cap' => $this->cliente->cap,
                'citta' => $this->cliente->citta,
                'provincia' => $this->cliente->provincia,
            ]);
        } else {
            $this->sede = Sede::findOrFail($sedeId);
        }
    }
    
    

public function abilitaModifica($id)
{
    $this->presidioInModifica = $id;
    $presidio = Presidio::find($id);
    $this->presidiData[$id] = $presidio->toArray();
}
public function disattiva($id)
{
    
    $presidio = Presidio::find($id);
    $presidio->attivo = 0;
    $presidio->save();

    $this->render();
}
public function salvaRiga($id)
{
    $presidio = Presidio::find($id);
    $presidio->fill($this->presidiData[$id]);

    if (isset($this->presidiData[$id]['data_serbatoio'])) {
        $presidio->calcolaScadenze();
    }

    $presidio->save();
    $this->presidioInModifica = null;
    session()->flash('message', 'Presidio aggiornato.');
}
public function getRiepilogoTipiEstintoriProperty()
{
    return collect($this->presidi)
        ->where('categoria', 'Estintore')
        ->groupBy(fn ($p) => $p['tipo_estintore_id'])
        ->mapWithKeys(function ($gruppo, $id) {
            $tipo = \App\Models\TipoEstintore::find($id);
            $etichetta = $tipo
                ? "Estintori {$tipo->tipo} {$tipo->kg}KG"
                : "Estintore tipo ID {$id}";
            return [$etichetta => $gruppo->count()];
        });
}
public function ricalcolaDate($id)
{
    if (!isset($this->presidiData[$id]['data_serbatoio'])) return;

    $presidio = Presidio::find($id);
    $presidio->data_serbatoio = $this->presidiData[$id]['data_serbatoio'];
    $presidio->tipo_estintore_id = $presidio->tipo_estintore_id ?? null;
    $presidio->calcolaScadenze();

    $this->presidiData[$id]['data_revisione'] = $presidio->data_revisione;
    $this->presidiData[$id]['data_collaudo'] = $presidio->data_collaudo;
    $this->presidiData[$id]['data_fine_vita'] = $presidio->data_fine_vita;
    $this->presidiData[$id]['data_sostituzione'] = $presidio->data_sostituzione;
}
    
    public function selezionaCategoria($categoria)
    {
        $this->categoriaAttiva = $categoria;
        $this->categoria = $categoria; // <-- necessario per il form
        $this->render();
    }
    public function getPresidiProperty()
    {
        return Presidio::with('tipoEstintore')
            ->where('cliente_id', $this->cliente->id)
            ->where('sede_id', $this->sedeId)
            ->where('categoria', $this->categoriaAttiva)
            ->orderBy('progressivo')
            ->get();
    }
    public function salvaPresidio()
    {
        Log::info('Inizio del metodo salvaPresidio');
        $this->validate();
        Log::info('Validazione Completata' );
        $progressivo = Presidio::prossimoProgressivo($this->clienteId, $this->sedeId, $this->categoria);

        $data = [
            'cliente_id' => $this->clienteId,
            'sede_id' => $this->sedeId,
            'categoria' => $this->categoria,
            'progressivo' => $progressivo,
            'ubicazione' => $this->ubicazione,
            'tipo_contratto' => $this->tipoContratto,
            'flag_anomalia1' => $this->anomalia1,
            'flag_anomalia2' => $this->anomalia2,
            'flag_anomalia3' => $this->anomalia3,
            'note' => $this->note,
        ];
        
        // Dati specifici per ESTINTORE
        if ($this->categoria === 'Estintore') {
            Log::info('Categoria Estintore.');
            $tipo = TipoEstintore::find($this->tipoEstintore);
        
            if (!$tipo) {
                Log::error('TipoEstintore non trovato con ID: ' . $this->tipoEstintore);
                throw new \Exception('Tipo estintore non valido.');
            }
        
            if (!$tipo->classificazione_id ){
                Log::error('Classificazione estintore non definita per tipo ID: ' . $tipo->id);
                throw new \Exception('Classificazione non definita.');
            }
        
            $classificazioneId = $tipo->classificazione_id;
        
            Log::info('Classificazione ID trovata: ' . $classificazioneId);
        
            $date = Presidio::calcolaDateEstintore($this->dataSerbatoio, $classificazioneId);
            Log::info('Date calcolate', $date);
        }
       
        
        // Dati specifici per IDRANTE
        if ($this->categoria === 'Idrante') {
            $data['descrizione'] = $this->descrizione;
        }

        // Dati specifici per PORTA
        if ($this->categoria === 'Porta') {
            $data['descrizione'] = $this->descrizione;
        }

        $presidio = new Presidio([
            'cliente_id' => $this->clienteId,
            'sede_id' => $this->sedeId,
            'categoria' => $this->categoria,
            'progressivo' => $progressivo,
            'ubicazione' => $this->ubicazione,
            'tipo_contratto' => $this->tipoContratto,
            'tipo_estintore_id' => $tipo->id,
            'flag_anomalia1' => $this->anomalia1,
            'flag_anomalia2' => $this->anomalia2,
            'flag_anomalia3' => $this->anomalia3,
            'note' => $this->note,
            'data_serbatoio' => $this->dataSerbatoio,
            'flag_preventivo' => $this->flagPreventivo,
            'descrizione' => $this->descrizione,
        ]);
        Log::info('PRE SALVA');
        $presidio->save(); // <--- qui parte il booted() e il calcolo scadenze
        Log::info('DOPO SALVA');
        // Dopo il salvataggio, ricarico le relazioni e ricalcolo le date
        $presidio->load('tipoEstintore.classificazione');
        $presidio->calcolaScadenze();
        $presidio->save();

        session()->flash('message', 'Presidio creato con successo.');
        Log::info('Pre - RESET');
        // Reset dei soli campi di input
        $this->reset([
            'ubicazione', 'tipoContratto', 'tipoEstintore', 'dataSerbatoio',
            'flagPreventivo', 'anomalia1', 'anomalia2', 'anomalia3',
            'note', 'descrizione'
        ]);
        Log::info('Fine del metodo salvaPresidio');
    }
    public function salvaModifichePresidi()
    {
        foreach ($this->presidiData as $id => $dati) {
            $presidio = Presidio::find($id);

            if (!$presidio) continue;

            // Aggiorna con i dati passati
            $presidio->fill($dati);

            // Se la categoria è Estintore e la data serbatoio è cambiata, ricalcola le scadenze
            if ($presidio->categoria === 'Estintore') {
                $nuovoTipoId = $dati['tipo_estintore_id'] ?? null;
            
                if ($nuovoTipoId && $presidio->tipo_estintore_id !== $nuovoTipoId) {
                    $presidio->tipo_estintore_id = $nuovoTipoId;
                }
            
                if (isset($dati['data_serbatoio'])) {
                    $vecchiaData = optional($presidio->getOriginal('data_serbatoio'))->format('Y-m-d');
                    $nuovaData = \Carbon\Carbon::parse($dati['data_serbatoio'])->format('Y-m-d');
            
                    if ($vecchiaData !== $nuovaData || $presidio->isDirty('tipo_estintore_id')) {
                        $presidio->calcolaScadenze();
                    }
                }
            }
            

            $presidio->save();
        }

        $this->presidioInModifica = null;
        session()->flash('message', 'Presidi aggiornati con successo.');
    }



    public function render()
{
    $presidi = Presidio::where('cliente_id', $this->clienteId)->where('attivo','1')->where('categoria',$this->categoriaAttiva)
        ->when($this->sedeId && $this->sedeId !== 'principale', fn($q) => $q->where('sede_id', $this->sedeId))
        ->orderBy('categoria')->orderBy('progressivo')
        ->get();

    // SOLO SE nel blade usi le variabili $clienti e $sedi
    $clienti = Cliente::all();
    $sedi = Sede::where('cliente_id',$this->clienteId)->get();
    $tipiEstintori = TipoEstintore::orderBy('sigla')->get();
  
    

    return view('livewire.presidi.gestione-presidi', [
        'presidi' => $presidi,
        'clienti' => $clienti,
        'sedi' => $sedi,
        'tipiEstintori' => $tipiEstintori,
    ])->layout('layouts.app');
}
}