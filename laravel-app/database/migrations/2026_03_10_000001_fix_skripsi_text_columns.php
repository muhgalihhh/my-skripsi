<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Fix columns that can have very long values from the repository.
   * Some entries have divisions listing ALL university faculties.
   */
  public function up(): void
  {
    Schema::table('skripsi', function (Blueprint $table) {
      $table->text('divisions')->nullable()->change();
      $table->text('subjects')->nullable()->change();
      $table->text('title')->change();
      $table->text('author')->nullable()->change();
    });
  }

  public function down(): void
  {
    Schema::table('skripsi', function (Blueprint $table) {
      $table->string('divisions')->nullable()->change();
      $table->string('subjects')->nullable()->change();
      $table->string('title')->change();
      $table->string('author')->nullable()->change();
    });
  }
};
