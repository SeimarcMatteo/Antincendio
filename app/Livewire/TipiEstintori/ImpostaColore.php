namespace App\Livewire\TipoEstintori;

use Livewire\Component;
use App\Models\TipoEstintore;
use App\Models\Colore;

class ColoriEstintori extends Component
{
    public $tipi;
    public $colori;

    public $selectedTipoId = null;
    public $selectedColoreId = null;

    public function mount()
    {
        $this->tipi   = TipoEstintore::with('colore')->orderBy('descrizione')->get();
        $this->colori = Colore::orderBy('nome')->get();

        // seleziona il primo tipo come default
        if ($this->tipi->isNotEmpty()) {
            $this->selectedTipoId  = $this->tipi->first()->id;
            $this->selectedColoreId = $this->tipi->first()->colore_id;
        }
    }

    public function selectTipo($id)
    {
        $this->selectedTipoId = $id;

        $tipo = $this->tipi->firstWhere('id', $id);

        $this->selectedColoreId = $tipo?->colore_id;
    }

    public function updatedSelectedColoreId()
    {
        $this->salvaColore();
    }

    public function salvaColore()
    {
        if (!$this->selectedTipoId) {
            return;
        }

        $tipo = TipoEstintore::find($this->selectedTipoId);
        if (!$tipo) {
            return;
        }

        $tipo->colore_id = $this->selectedColoreId ?: null;
        $tipo->save();

        // aggiorna la collection in memoria
        $this->tipi = TipoEstintore::with('colore')->orderBy('descrizione')->get();

        session()->flash('message', 'Colore aggiornato correttamente.');
    }

    public function render()
    {
        return view('livewire.impostazioni.colori-estintori');
    }
}
