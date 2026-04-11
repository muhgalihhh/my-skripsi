<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Middleware\EnsureUserIsJurusan;
use App\Http\Middleware\EnsureUserIsMahasiswa;
use App\Livewire\Auth\LoginForm;
use App\Livewire\Jurusan\AccountManager;
use App\Livewire\Jurusan\Dashboard;
use App\Livewire\Mahasiswa\ProfileEditor as MahasiswaProfileEditor;
use App\Livewire\Mahasiswa\TitleRecommendationDetail;
use App\Livewire\Mahasiswa\TitleRecommendationIndex;
use App\Livewire\Mahasiswa\TopicExplorer;
use App\Livewire\Jurusan\ProfileEditor;
use App\Livewire\Jurusan\ScrapingManager;
use App\Livewire\Jurusan\SkripsiManager;
use App\Livewire\Jurusan\TopicCurationManager;
use App\Livewire\Jurusan\TopicModelingManager;
use App\Livewire\Jurusan\VisualizationManager;
use App\Http\Controllers\Jurusan\TopicModelingModelController;
use Illuminate\Support\Facades\Route;

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

    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->name('auth.google.callback');
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

        // Topic Curation (Livewire)
        Route::get('/topic-curation', TopicCurationManager::class)->name('topic-curation');

        // Visualisasi Topik (Livewire)
        Route::get('/visualisasi', VisualizationManager::class)->name('visualisasi');

        // Topic Modeling - model artifacts
        Route::get('/topic-modeling/model/{jobId}/download', [TopicModelingModelController::class, 'download'])
            ->name('topic-modeling.model.download');
    });

// ============================================
// Mahasiswa Routes
// ============================================

Route::middleware(['auth', EnsureUserIsMahasiswa::class])
    ->prefix('mahasiswa')
    ->name('mahasiswa.')
    ->group(function () {
        Route::get('/dashboard', TopicExplorer::class)->name('dashboard');
        Route::get('/rekomendasi-judul', TitleRecommendationIndex::class)->name('rekomendasi-judul.index');
        Route::get('/rekomendasi-judul/{topicRowId}', TitleRecommendationDetail::class)
            ->whereNumber('topicRowId')
            ->name('rekomendasi-judul.detail');
        Route::get('/profil', MahasiswaProfileEditor::class)->name('profil');
    });
