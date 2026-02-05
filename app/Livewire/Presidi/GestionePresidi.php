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
    public ?string $marcaSerbatoio = null;
    public ?string $dataUltimaRevisione = null;
    public $flagPreventivo = false;
    public $descrizione;

    public Cliente $cliente;
    public $sede;
    public $presidioInModifica = null;
    public $presidiData = [];
    protected $listeners = ['presidi-updated' => '$refresh'];

    // === Nuovi campi per l'inserimento "in acquisto"
    public bool $isAcquisto = false;          // toggle "Estintore acquistato"
    public ?string $dataAcquisto = null;      // Y-m-d
    public ?string $scadenzaPresidio = null;  // Y-m-d
    public int $anniScadenzaPresidioDefault = 3; // fallback (vedi nota)

    
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
        'marcaSerbatoio' => 'nullable|string|max:20',
        'dataUltimaRevisione' => 'nullable|date',
        'flagPreventivo' => 'nullable|boolean',
        'descrizione' => 'nullable|string|max:255',
        'isAcquisto'        => 'boolean',
        'dataAcquisto'      => 'nullable|date|required_if:isAcquisto,true',
        'scadenzaPresidio'  => 'nullable|date', // calcolata: la teniamo "nullable" per sicurezza
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
    
    

    private function anniScadenzaPresidio(): int
    {
        // Seleziona da classificazione (se vuoi), altrimenti default 3
        if ($this->categoria === 'Estintore' && $this->tipoEstintore) {
            $tipo = \App\Models\TipoEstintore::with('classificazione')->find($this->tipoEstintore);
            if ($tipo && $tipo->classificazione && !empty($tipo->classificazione->anni_scadenza_presidio)) {
                return (int) $tipo->classificazione->anni_scadenza_presidio;
            }
        }
        return $this->anniScadenzaPresidioDefault;
    }
    public function aggiornaScadenzaPresidio(): void
    {
        if (!$this->isAcquisto || empty($this->dataAcquisto)) {
            $this->scadenzaPresidio = null;
            return;
        }
        $anni = $this->anniScadenzaPresidio();
        $this->scadenzaPresidio = \Carbon\Carbon::parse($this->dataAcquisto)
            ->addYears($anni)->startOfMonth()->format('Y-m-d');
    }
public function abilitaModifica($id)
{
    $this->presidioInModifica = $id;
    $p = Presidio::find($id);
    $row = $p->toArray();

    foreach ([
        'data_acquisto','scadenza_presidio','data_serbatoio','data_revisione',
        'data_collaudo','data_fine_vita','data_sostituzione','data_ultima_revisione' // <— aggiunto
      ] as $k) {
          if (!empty($row[$k])) {
              $row[$k] = \Carbon\Carbon::parse($row[$k])->format('Y-m-d');
          }
      }
      
    $this->presidiData[$id] = $row;
}
public function disattiva($id)
{
    
    $presidio = Presidio::find($id);
    $presidio->attivo = 0;
    $presidio->save();

    $this->dispatch('toast', type: 'success', message: 'Presidio disattivato.');
    $this->dispatch('$refresh');
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
    $this->dispatch('toast', type: 'success', message: 'Presidio aggiornato.');
    $this->dispatch('$refresh');
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
public function ricalcolaDate(int $id): void
{
    // lavora sulla mappa modifiche (presidiData) se presente, altrimenti su valori correnti del model
    $row = $this->presidiData[$id] ?? [
        'tipo_estintore_id'     => $this->presidi->firstWhere('id', $id)?->tipo_estintore_id,
        'data_serbatoio'        => $this->presidi->firstWhere('id', $id)?->data_serbatoio,
        'data_acquisto'         => $this->presidi->firstWhere('id', $id)?->data_acquisto,
        'scadenza_presidio'     => $this->presidi->firstWhere('id', $id)?->scadenza_presidio,
        'data_ultima_revisione' => $this->presidi->firstWhere('id', $id)?->data_ultima_revisione, // <— aggiunto
        'marca_serbatoio' => $this->presidi->firstWhere('id', $id)?->marca_serbatoio,
    ];

    
    $last = $row['data_ultima_revisione'] ?? null;
    $tipoId   = $row['tipo_estintore_id'] ?? null;
    $serb     = $row['data_serbatoio']    ?? null;
    $acq      = $row['data_acquisto']     ?? null;

    // se c’è acquisto e non c’è scadenza_presidio, calcolala qui (regola tua di business)
    if ($acq && empty($row['scadenza_presidio'])) {
        // es.: 10 anni dall’acquisto
        $row['scadenza_presidio'] = \Carbon\Carbon::parse($acq)->addYears(10)->startOfMonth()->format('Y-m-d');
    }

    // se mancano tipo o serbatoio, azzero derivate
    if (!$tipoId || !$serb) {
        $row['data_revisione']    = null;
        $row['data_collaudo']     = null;
        $row['data_fine_vita']    = null;
        $row['data_sostituzione'] = $row['scadenza_presidio'] ?? null;
        $this->presidiData[$id]   = $row;
        return;
    }

    $tipo   = \App\Models\TipoEstintore::with('classificazione')->find($tipoId);
    $classi = $tipo?->classificazione;

    $periodoRev    = \App\Livewire\Presidi\ImportaPresidi::pickPeriodoRevisione($serb, $classi, $last, $row['marca_serbatoio'] ?? null);
    $baseRevisione = $last ?: $serb;
    $scadRevisione = \App\Livewire\Presidi\ImportaPresidi::nextDueAfter($baseRevisione, $periodoRev);$scadCollaudo  = !empty($classi?->anni_collaudo)
        ? \App\Livewire\Presidi\ImportaPresidi::nextDueAfter($serb, (int)$classi->anni_collaudo)
        : null;
    $fineVita      = \App\Livewire\Presidi\ImportaPresidi::addYears($serb, $classi?->anni_fine_vita);

    // se hai i "mesi preferiti", allinea come già fatto nell’import (riusa lo stesso helper se l’hai messo qui)
    if (method_exists($this, 'alignToPreferred')) {
        $scadRevisione = $this->alignToPreferred($scadRevisione) ?: $scadRevisione;
        $scadCollaudo  = $this->alignToPreferred($scadCollaudo)  ?: $scadCollaudo;
    }

    $row['data_revisione'] = $scadRevisione;
    $row['data_collaudo']  = $scadCollaudo;
    $row['data_fine_vita'] = $fineVita;

    // sostituzione operativa = min tra scadenze assolute
    $row['data_sostituzione'] = \App\Livewire\Presidi\ImportaPresidi::minDate(
        $row['scadenza_presidio'] ?? null,
        $row['data_fine_vita']    ?? null,
        $row['data_collaudo']     ?? null,
        $row['data_revisione']    ?? null,
    );

    $this->presidiData[$id] = $row;
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
            ->orderBy('progressivo_num')
            ->orderBy('progressivo_suffix')
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
            'marca_serbatoio' => $this->marcaSerbatoio,
            'data_ultima_revisione' => $this->dataUltimaRevisione,
            'flag_preventivo' => $this->flagPreventivo,
            'descrizione' => $this->descrizione,
               // NUOVI CAMPI (valorizzali solo se isAcquisto)
        'data_acquisto'     => $this->isAcquisto ? $this->dataAcquisto : null,
        'scadenza_presidio' => $this->isAcquisto ? $this->scadenzaPresidio : null,
       
        ]);
        Log::info('PRE SALVA');
        $presidio->save();                  // trigger boot/calcolo scadenze del model
        $presidio->load('tipoEstintore.classificazione');
        $presidio->calcolaScadenze();       // revisione/collaudo/fine vita da data serbatoio
        $presidio->save();
    
        session()->flash('message', 'Presidio creato con successo.');
        $this->dispatch('toast', type: 'success', message: 'Presidio creato con successo.');
    
        // reset ONLY dei campi di form
        $this->reset([
            'ubicazione','tipoContratto','tipoEstintore','dataSerbatoio','marcaSerbatoio','flagPreventivo',
            'anomalia1','anomalia2','anomalia3','note','descrizione',
            'isAcquisto','dataAcquisto','scadenzaPresidio','dataUltimaRevisione',
        ]);
        Log::info('Fine del metodo salvaPresidio');
    }
    public function elimina(int $id): void
    {
        // Se stavi editando proprio questa riga, esci dall’edit
        if ($this->presidioInModifica === $id) {
            $this->presidioInModifica = null;
        }
        unset($this->presidiData[$id]);

        $presidio = \App\Models\Presidio::find($id);
        if (!$presidio) {
            session()->flash('message', 'Presidio già rimosso o inesistente.');
            return;
        }

        // Se hai relazioni con FK "restrict", valuta try/catch
        $presidio->delete();

        session()->flash('message', 'Presidio eliminato definitivamente.');
        $this->dispatch('toast', type: 'success', message: 'Presidio eliminato definitivamente.');
        $this->dispatch('$refresh');
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
        $this->dispatch('toast', type: 'success', message: 'Presidi aggiornati con successo.');
        $this->dispatch('$refresh');
    }



    public function render()
{
    $presidi = Presidio::where('cliente_id', $this->clienteId)->where('attivo','1')->where('categoria',$this->categoriaAttiva)
        ->when($this->sedeId && $this->sedeId !== 'principale', fn($q) => $q->where('sede_id', $this->sedeId))
        ->orderBy('categoria')
        ->orderBy('progressivo_num')
        ->orderBy('progressivo_suffix')
        ->orderBy('progressivo')
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
