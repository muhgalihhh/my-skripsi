@extends('layouts.error')

@section('title', 'Akses Ditolak')
@section('headline', 'Akses ditolak')
@section('code', '403')
@section('message', 'Kamu tidak memiliki izin untuk mengakses halaman ini.')

@section('detail')
    <p>Jika kamu merasa ini seharusnya bisa diakses, silakan login dengan akun yang benar atau hubungi admin.</p>
@endsection
