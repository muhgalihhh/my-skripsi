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
    Schema::create('scraping_logs', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained()->onDelete('cascade');
      $table->enum('trigger_type', ['manual', 'scheduled'])->default('manual');
      $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
      $table->integer('total_scraped')->default(0);
      $table->integer('new_added')->default(0);
      $table->integer('duplicates_skipped')->default(0);
      $table->text('error_message')->nullable();
      $table->timestamp('started_at')->nullable();
      $table->timestamp('completed_at')->nullable();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('scraping_logs');
  }
};
