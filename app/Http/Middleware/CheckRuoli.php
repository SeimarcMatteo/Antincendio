<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRuoli
{
    public function handle(Request $request, Closure $next, string $commaSeparated = '')
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        // ruoli richiesti in lista, es: "Admin,Amministrazione"
        $richiesti = array_values(array_filter(array_map('trim', explode(',', $commaSeparated))));
        if (empty($richiesti)) {
            // se non sono stati passati ruoli, neghiamo per sicurezza
            abort(403);
        }

        // controlla via relazione, senza helper custom
        $ha = $user->ruoli()->whereIn('nome', $richiesti)->exists();
        if (!$ha) {
            abort(403);
        }

        return $next($request);
    }
}
