<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Add fastapi_job_id to scraping_logs for async job tracking.
   */
  public function up(): void
  {
    Schema::table('scraping_logs', function (Blueprint $table) {
      $table->string('fastapi_job_id')->nullable()->after('status');
      $table->index('fastapi_job_id');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('scraping_logs', function (Blueprint $table) {
      $table->dropIndex(['fastapi_job_id']);
      $table->dropColumn('fastapi_job_id');
    });
  }
};
