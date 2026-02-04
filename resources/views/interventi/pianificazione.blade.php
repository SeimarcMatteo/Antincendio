@extends('layouts.app')

@section('content')
    <div class="max-w-7xl mx-auto p-4 space-y-6">
        {{-- Form sopra --}}
        <livewire:interventi.form-pianificazione-intervento />

        {{-- Planning settimanale sotto --}}
        <livewire:interventi.planning-settimanale />
    </div>
@endsection
