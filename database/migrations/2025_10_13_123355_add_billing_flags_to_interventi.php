<?php
// database/migrations/2025_10_13_120000_add_billing_flags_to_interventi.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('interventi', function (Blueprint $table) {
            if (!Schema::hasColumn('interventi', 'fatturato')) {
                $table->boolean('fatturato')->default(false)->after('stato');
            }
            if (!Schema::hasColumn('interventi', 'fatturazione_payload')) {
                $table->json('fatturazione_payload')->nullable()->after('fatturato');
            }
            if (!Schema::hasColumn('interventi', 'fatturato_at')) {
                $table->timestamp('fatturato_at')->nullable()->after('fatturazione_payload');
            }
            if (!Schema::hasColumn('interventi', 'fattura_ref')) {
                $table->string('fattura_ref', 50)->nullable()->after('fatturato_at'); // id/numero documento Business
            }
        });
    }

    public function down(): void
    {
        Schema::table('interventi', function (Blueprint $table) {
            if (Schema::hasColumn('interventi', 'fattura_ref')) {
                $table->dropColumn('fattura_ref');
            }
            if (Schema::hasColumn('interventi', 'fatturato_at')) {
                $table->dropColumn('fatturato_at');
            }
            if (Schema::hasColumn('interventi', 'fatturazione_payload')) {
                $table->dropColumn('fatturazione_payload');
            }
            if (Schema::hasColumn('interventi', 'fatturato')) {
                $table->dropColumn('fatturato');
            }
        });
    }
};
