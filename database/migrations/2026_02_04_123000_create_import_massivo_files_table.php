<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_massivo_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id')->index();
            $table->foreignId('cliente_id')->constrained('clienti');
            $table->foreignId('sede_id')->nullable()->constrained('sedi');
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('azione')->default('skip_if_exists');
            $table->string('status')->default('queued'); // queued|running|done|failed|skipped
            $table->unsignedInteger('importati')->default(0);
            $table->unsignedInteger('saltati')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_massivo_files');
    }
};
