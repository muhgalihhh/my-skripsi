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
    Schema::table('skripsi', function (Blueprint $table) {
      $table->longText('cleaned_text')->nullable()->after('abstract');
      $table->longText('processed_text')->nullable()->after('cleaned_text');
    });

    Schema::table('topic_model_runs', function (Blueprint $table) {
        $table->string('fastapi_preprocessing_job_id')->nullable()->after('status');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('skripsi', function (Blueprint $table) {
      $table->dropColumn(['cleaned_text', 'processed_text']);
    });

    Schema::table('topic_model_runs', function (Blueprint $table) {
        $table->dropColumn('fastapi_preprocessing_job_id');
    });
  }
};
