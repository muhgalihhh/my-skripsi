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
    Schema::create('skripsi', function (Blueprint $table) {
      $table->id();
      $table->string('title');
      $table->text('abstract')->nullable();
      $table->string('type')->nullable();
      $table->string('id_code')->nullable();
      $table->text('keywords')->nullable();
      $table->string('subjects')->nullable();
      $table->string('divisions')->nullable();
      $table->string('author')->nullable();
      $table->string('deposit_date')->nullable();
      $table->string('modified_date')->nullable();
      $table->string('uri')->nullable();
      $table->integer('year')->nullable()->index();
      $table->json('pdf_documents')->nullable();
      $table->string('url')->unique();
      $table->text('conclusion')->nullable();
      $table->string('conclusion_source')->nullable();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('skripsi');
  }
};
