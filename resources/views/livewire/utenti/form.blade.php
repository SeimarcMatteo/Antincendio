<div class="p-4" id="utente-form-root">
    <h1 class="text-2xl font-bold mb-4 text-red-700">
        {{ $utenteId ? 'Modifica Utente' : 'Nuovo Utente' }}
    </h1>

    <form wire:submit.prevent="save" onsubmit="acquisisciFirmaTecnico()" class="space-y-4 max-w-xl">
        <div>
            <label>Nome</label>
            <input type="text" wire:model="name" class="input input-bordered w-full" />
            @error('name') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
        </div>

        <div>
            <label>Email</label>
            <input type="email" wire:model="email" class="input input-bordered w-full" />
            @error('email') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
        </div>

        <div>
            <label>Password {{ $utenteId ? '(lascia vuota per non modificare)' : '' }}</label>
            <input type="password" wire:model="password" class="input input-bordered w-full" />
            @error('password') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
        </div>

        <div>
            <label>Ruoli</label>
            <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2 border rounded p-3 bg-gray-50">
                @foreach ($ruoli as $ruolo)
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox"
                               wire:model.live="ruolo_ids"
                               value="{{ $ruolo->id }}"
                               class="rounded border-gray-300">
                        <span>{{ $ruolo->nome }}</span>
                    </label>
                @endforeach
            </div>
            @error('ruolo_ids') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
            @error('ruolo_ids.*') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
        </div>

        <div>
            <label>Colore identificativo</label>
            <input type="color" wire:model="colore_ruolo" class="input w-20 h-10 p-1" />
        </div>

        <div>
            <label>Immagine profilo</label>
            <input type="file" wire:model="profile_image" class="file-input file-input-bordered w-full" />
            @error('profile_image') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
        </div>

        @if($hasFirmaTecnicoColumn)
            <div>
                <label class="block mb-2 font-medium">Firma tecnico</label>
                <div class="rounded border bg-white p-3 space-y-2">
                    <div class="text-xs text-gray-600">
                        Questa firma verrà stampata sul rapportino quando il tecnico chiude l'intervento.
                    </div>

                    <input type="hidden" id="firma_tecnico_base64_input" wire:model.defer="firma_tecnico_base64">

                    <canvas id="firmaTecnicoCanvas" width="700" height="220" class="w-full border rounded bg-white"></canvas>

                    <div class="flex flex-wrap gap-2">
                        <button type="button"
                                onclick="acquisisciFirmaTecnico()"
                                class="px-3 py-1.5 rounded bg-green-600 text-white text-sm hover:bg-green-700">
                            Acquisisci firma
                        </button>
                        <button type="button"
                                onclick="pulisciFirmaTecnico()"
                                class="px-3 py-1.5 rounded bg-gray-600 text-white text-sm hover:bg-gray-700">
                            Pulisci
                        </button>
                    </div>

                    @if(!empty($firma_tecnico_base64))
                        <div class="pt-2">
                            <div class="text-xs text-gray-600 mb-1">Anteprima firma salvata</div>
                            <img src="{{ $firma_tecnico_base64 }}" alt="Firma tecnico" class="max-h-24 border rounded bg-white p-1">
                        </div>
                    @endif
                </div>
                @error('firma_tecnico_base64') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
            </div>
        @else
            <div class="rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                Colonna firma tecnico non presente su <code>users</code>. Esegui le migrazioni per abilitarla.
            </div>
        @endif

        <div class="mt-4">
            <button class="btn btn-primary" type="submit">Salva</button>
            <a href="{{ route('utenti.index') }}" class="btn btn-outline ml-2">Annulla</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
(() => {
    const canvas = document.getElementById('firmaTecnicoCanvas');
    if (!canvas || canvas.dataset.initialized === '1') return;
    canvas.dataset.initialized = '1';
    canvas.dataset.blank = canvas.toDataURL('image/png');

    const ctx = canvas.getContext('2d');
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#111827';

    let drawing = false;

    function getPos(evt) {
        const rect = canvas.getBoundingClientRect();
        const clientX = evt.clientX ?? (evt.touches && evt.touches[0] ? evt.touches[0].clientX : 0);
        const clientY = evt.clientY ?? (evt.touches && evt.touches[0] ? evt.touches[0].clientY : 0);
        return { x: clientX - rect.left, y: clientY - rect.top };
    }

    function start(evt) {
        drawing = true;
        const p = getPos(evt);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
    }

    function move(evt) {
        if (!drawing) return;
        evt.preventDefault();
        const p = getPos(evt);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
    }

    function stop() {
        drawing = false;
    }

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    canvas.addEventListener('mouseup', stop);
    canvas.addEventListener('mouseleave', stop);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', stop);

    const existing = @js($firma_tecnico_base64 ?? null);
    if (existing) {
        const img = new Image();
        img.onload = () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        };
        img.src = existing;
    }
})();

function acquisisciFirmaTecnico() {
    const canvas = document.getElementById('firmaTecnicoCanvas');
    const input = document.getElementById('firma_tecnico_base64_input');
    if (!canvas || !input) return;

    const current = canvas.toDataURL('image/png');
    const blank = canvas.dataset.blank || '';
    input.value = (blank && current === blank) ? '' : current;
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

function pulisciFirmaTecnico() {
    const canvas = document.getElementById('firmaTecnicoCanvas');
    const input = document.getElementById('firma_tecnico_base64_input');
    if (!canvas || !input) return;

    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    input.value = '';
    input.dispatchEvent(new Event('input', { bubbles: true }));
}
</script>
@endpush
