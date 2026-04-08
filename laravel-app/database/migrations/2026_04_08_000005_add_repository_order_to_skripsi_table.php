<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    if (!Schema::hasColumn('skripsi', 'repository_order')) {
      Schema::table('skripsi', function (Blueprint $table) {
        $table->unsignedInteger('repository_order')->nullable()->after('year')->index();
      });
    }

    // Backfill legacy rows to keep deterministic ordering before next scraping refresh.
    DB::statement('UPDATE skripsi SET repository_order = id WHERE repository_order IS NULL');
  }

  public function down(): void
  {
    if (Schema::hasColumn('skripsi', 'repository_order')) {
      Schema::table('skripsi', function (Blueprint $table) {
        $table->dropIndex(['repository_order']);
        $table->dropColumn('repository_order');
      });
    }
  }
};
