<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PdfController;
use Illuminate\Support\Facades\Route;
use App\Livewire\Dashboard\Admin;
use App\Livewire\Dashboard\Tecnico;
use App\Livewire\Utenti\Form as UtentiForm;
use App\Livewire\Utenti\Index as UtentiIndex;
use App\Livewire\Clienti\Index as ClientiIndex;
use App\Livewire\Clienti\Mostra;
use App\Livewire\Presidi\GestionePresidi;

use App\Livewire\StatisticheAvanzate;
use App\Livewire\Interventi\PlanningSettimanale;
use App\Livewire\Interventi\EvadiInterventi;
use App\Livewire\Interventi\EvadiInterventoSingolo;

use App\Livewire\Fatturazione\GeneraFattura;
use App\Livewire\Fatturazione\ElencoDaFatturare;


// Redirect utente in base al ruolo
Route::get('/reindirizzamento', function () {
    $ruolo = auth()->user()->ruoli()->first()?->nome;

    return match ($ruolo) {
        'Admin' => redirect()->route('admin.dashboard'),
        'Tecnico' => redirect()->route('tecnico.dashboard'),
        default => abort(403),
    };
})->middleware(['auth']);

// Rotte Livewire dashboard
Route::middleware(['auth'])->group(function () {
    Route::get('/admin', Admin::class)->name('admin.dashboard');
    Route::get('/tecnico', Tecnico::class)->name('tecnico.dashboard');
});


// Rotte standard
Route::get('/', fn () => view('welcome'));

Route::get('/dashboard/statistiche', StatisticheAvanzate::class)->name('statistiche');

Route::get('/dashboard', Admin::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');
// Gestione profilo
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


Route::middleware(['auth'])->group(function () {
    Route::get('/utenti', UtentiIndex::class)->name('utenti.index');
    Route::get('/utenti/crea', UtentiForm::class)->name('utenti.form');
    Route::get('/utenti/modifica/{id}', UtentiForm::class)->name('utenti.edit');
});


Route::middleware(['auth'])->group(function () {
    Route::get('/clienti', ClientiIndex::class)->name('clienti.index');
});


Route::get('/fatturazione/genera', GeneraFattura::class)
    ->name('fatturazione.genera')
    ->middleware(['auth','ruoli:Admin,Amministrazione']);

Route::get('/fatturazione/da-fatturare', ElencoDaFatturare::class)
    ->name('fatturazione.da_fatturare')
    ->middleware(['auth','ruoli:Admin,Amministrazione']);


Route::middleware('auth')->group(function () {
    Route::get('/clienti/{cliente}', Mostra::class)->name('clienti.mostra');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/presidi/gestione/{clienteId}/{sedeId?}', GestionePresidi::class)
        ->name('presidi.gestione');
});


Route::middleware(['auth'])->group(function () {
    Route::get('/interventi/planning', PlanningSettimanale::class)
        ->name('interventi.planning');
});


Route::middleware(['auth'])->group(function () {
    Route::view('/interventi/pianificazione', 'interventi.pianificazione')
        ->name('interventi.pianificazione');
});


Route::view('/admin/impostazioni', 'admin.impostazioni')
    ->name('admin.impostazioni')
    ->middleware(['auth','ruoli:Admin']);


Route::middleware(['auth'])->group(function () {
    Route::get('/tecnico/interventi', EvadiInterventi::class)->name('interventi.evadi');
    Route::get('/tecnico/intervento/{intervento}', EvadiInterventoSingolo::class)->name('interventi.evadi.dettaglio');
});
Route::get('/rapportino/{id}', [PdfController::class, 'rapportino'])->name('rapportino.pdf');

require __DIR__.'/auth.php';
