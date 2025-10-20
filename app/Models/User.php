<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_image',
        'colore_ruolo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // Ruoli associati
    public function ruoli()
    {
        return $this->belongsToMany(Ruolo::class, 'utente_ruoli', 'utente_id', 'ruolo_id');
    }
    
    // Primo ruolo (utile per dashboard o controlli)
    public function ruoloPrincipale()
    {
        return $this->ruoli()->first();
    }

    // Url immagine profilo
    public function immagineProfiloUrl()
    {
        return $this->profile_image
            ? Storage::url($this->profile_image)
            : asset('images/default-profile.png');
    }

// app/Models/User.php
    public function hasRuolo(string $ruolo): bool
    {
        return $this->ruoli()->where('nome', $ruolo)->exists();
    }

    public function hasAnyRuolo(array $ruoli): bool
    {
        return $this->ruoli()->whereIn('nome', $ruoli)->exists();
    }



    public function interventi()
    {
        return $this->belongsToMany(Intervento::class, 'intervento_tecnico');
    }


}
