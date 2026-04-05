<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Middleware\EnsureUserIsJurusan;
use App\Livewire\Auth\LoginForm;
use App\Livewire\Jurusan\AccountManager;
use App\Livewire\Jurusan\Dashboard;
use App\Livewire\Jurusan\ProfileEditor;
use App\Livewire\Jurusan\ScrapingManager;
use App\Livewire\Jurusan\SkripsiManager;
use Illuminate\Support\Facades\Route;
use App\Livewire\Jurusan\TopicModelingManager;
use App\Http\Controllers\Jurusan\TopicModelingModelController;

// ============================================
// Public Routes
// ============================================

Route::get('/', function () {
    return view('landing');
})->name('landing');

// ============================================
// Auth Routes (Guest only)
// ============================================

Route::middleware('guest')->group(function () {
    Route::get('/login', LoginForm::class)->name('login');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// ============================================
// Jurusan (Admin) Routes
// ============================================

Route::middleware(['auth', EnsureUserIsJurusan::class])
    ->prefix('jurusan')
    ->name('jurusan.')
    ->group(function () {
        // Dashboard (Livewire)
        Route::get('/dashboard', Dashboard::class)->name('dashboard');

        // Scraping (Livewire)
        Route::get('/scraping', ScrapingManager::class)->name('scraping.index');

        // Manajemen Skripsi (Livewire)
        Route::get('/skripsi', SkripsiManager::class)->name('skripsi.index');

        // Manajemen Akun (Livewire)
        Route::get('/akun', AccountManager::class)->name('akun.index');

        // Edit Profil (Livewire)
        Route::get('/profil', ProfileEditor::class)->name('profil');

        // Topic Modeling (Livewire)
        Route::get('/topic-modeling', TopicModelingManager::class)->name('topic-modeling');

        // Topic Modeling - model artifacts
        Route::get('/topic-modeling/model/{jobId}/download', [TopicModelingModelController::class, 'download'])
            ->name('topic-modeling.model.download');
    });
