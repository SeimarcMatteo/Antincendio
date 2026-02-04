<?php
// database/migrations/2025_10_13_130000_add_fattura_ref_data_to_interventi.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('interventi', function (Blueprint $table) {
      if (!Schema::hasColumn('interventi', 'fattura_ref_data')) {
        $table->json('fattura_ref_data')->nullable()->after('fattura_ref');
      }
    });
  }
  public function down(): void {
    Schema::table('interventi', function (Blueprint $table) {
      if (Schema::hasColumn('interventi', 'fattura_ref_data')) {
        $table->dropColumn('fattura_ref_data');
      }
    });
  }
};

