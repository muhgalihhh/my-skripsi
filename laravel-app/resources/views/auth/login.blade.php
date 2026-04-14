@extends('layouts.app')

@section('title', 'Login')

@section('content')
    <div class="flex items-center justify-center min-h-screen bg-gradient-to-br from-indigo-100 via-white to-cyan-100">
        <div class="w-full max-w-md px-8 py-10 bg-white rounded-2xl shadow-xl">
            {{-- Header --}}
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Portal Skripsi Informatika</h1>
                <p class="mt-2 text-sm text-gray-500">
                    Platform layanan data, insight, dan rekomendasi skripsi
                </p>
                <p class="mt-1 text-xs text-gray-400">
                    S1 Teknik Informatika - UNSOED
                </p>
            </div>

            {{-- Error Message --}}
            @if ($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 text-red-600 text-sm px-4 py-3 rounded-lg">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- Login Form --}}
            <form method="POST" action="{{ route('login') }}">
                @csrf

                {{-- Email --}}
                <div class="mb-5">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                        Email
                    </label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm transition"
                        placeholder="admin@unsoed.ac.id">
                </div>

                {{-- Password --}}
                <div class="mb-5">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                        Password
                    </label>
                    <input type="password" name="password" id="password" required
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm transition"
                        placeholder="••••••••">
                </div>

                {{-- Remember Me --}}
                <div class="flex items-center justify-between mb-6">
                    <label class="flex items-center text-sm text-gray-600">
                        <input type="checkbox" name="remember"
                            class="mr-2 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Ingat saya
                    </label>
                </div>

                {{-- Submit Button --}}
                <button type="submit"
                    class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Masuk
                </button>
            </form>

            <p class="mt-6 text-center text-xs text-gray-400">
                &copy; {{ date('Y') }} Teknik Informatika UNSOED
            </p>
        </div>
    </div>
@endsection
