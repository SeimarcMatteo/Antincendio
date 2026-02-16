<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'firma_tecnico_base64')) {
                    $table->longText('firma_tecnico_base64')->nullable()->after('profile_image');
                }
            });
        }

        if (Schema::hasTable('interventi')) {
            Schema::table('interventi', function (Blueprint $table) {
                if (!Schema::hasColumn('interventi', 'closed_by_user_id')) {
                    $table->unsignedBigInteger('closed_by_user_id')->nullable()->after('stato');
                    $table->index('closed_by_user_id', 'idx_interventi_closed_by_user');
                    $table->foreign('closed_by_user_id', 'fk_interventi_closed_by_user')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('interventi')) {
            Schema::table('interventi', function (Blueprint $table) {
                if (Schema::hasColumn('interventi', 'closed_by_user_id')) {
                    $table->dropForeign('fk_interventi_closed_by_user');
                    $table->dropIndex('idx_interventi_closed_by_user');
                    $table->dropColumn('closed_by_user_id');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'firma_tecnico_base64')) {
                    $table->dropColumn('firma_tecnico_base64');
                }
            });
        }
    }
};

