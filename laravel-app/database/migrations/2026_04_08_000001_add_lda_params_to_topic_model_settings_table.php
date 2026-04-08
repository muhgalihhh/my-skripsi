<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    if (!Schema::hasColumn('topic_model_settings', 'lda_params')) {
      Schema::table('topic_model_settings', function (Blueprint $table) {
        $table->json('lda_params')->nullable()->after('bertopic_params');
      });
    }
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    if (Schema::hasColumn('topic_model_settings', 'lda_params')) {
      Schema::table('topic_model_settings', function (Blueprint $table) {
        $table->dropColumn('lda_params');
      });
    }
  }
};
