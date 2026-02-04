<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportMassivoFile extends Model
{
    protected $table = 'import_massivo_files';

    protected $fillable = [
        'batch_id',
        'cliente_id',
        'sede_id',
        'original_name',
        'stored_path',
        'azione',
        'status',
        'importati',
        'saltati',
        'error',
    ];
}
