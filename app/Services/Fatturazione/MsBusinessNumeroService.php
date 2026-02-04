<?php
// app/Services/Fatturazione/MsBusinessNumeroService.php
namespace App\Services\Fatturazione;

use Illuminate\Support\Facades\DB;

class MsBusinessNumeroService
{
    private const CONN = 'sqlsrv';
    private const T_TABNUMA = 'tabnuma';

    public function nextNumero(string $tipork, string $serie, int $anno): array
    {
        return DB::connection(self::CONN)->transaction(function () use ($tipork,$serie,$anno) {
            $row = DB::connection(self::CONN)->table(self::T_TABNUMA)
                ->where('tb_numtipo',$tipork)
                ->where('tb_numserie',$serie)
                ->where('tb_numcodl',$anno)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                DB::connection(self::CONN)->table(self::T_TABNUMA)->insert([
                    'tb_numtipo'=>$tipork,'tb_numserie'=>$serie,'tb_numcodl'=>$anno,'tb_numprog'=>0,
                ]);
                $row = (object)['tb_numprog'=>0];
            }

            $next = (int)$row->tb_numprog + 1;

            DB::connection(self::CONN)->table(self::T_TABNUMA)
              ->where('tb_numtipo',$tipork)->where('tb_numserie',$serie)->where('tb_numcodl',$anno)
              ->update(['tb_numprog'=>$next]);

            return ['tipork'=>$tipork,'serie'=>$serie,'anno'=>$anno,'numero'=>$next];
        });
    }
}
