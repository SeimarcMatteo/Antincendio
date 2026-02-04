<?php
// database/migrations/2025_10_13_140000_add_billing_fields_to_interventi.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
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
        $table->string('fattura_ref', 80)->nullable()->after('fatturato_at');
      }
      if (!Schema::hasColumn('interventi', 'fattura_ref_data')) {
        $table->json('fattura_ref_data')->nullable()->after('fattura_ref'); // {tipork,serie,anno,numero}
      }
    });
  }

  public function down(): void {
    Schema::table('interventi', function (Blueprint $table) {
      foreach (['fattura_ref_data','fattura_ref','fatturato_at','fatturazione_payload','fatturato'] as $c) {
        if (Schema::hasColumn('interventi', $c)) $table->dropColumn($c);
      }
    });
  }
};
