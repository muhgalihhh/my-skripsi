@extends('layouts.error')

@section('title', 'Kesalahan Server')
@section('headline', 'Terjadi kesalahan pada server')
@section('code', '500')
@section('message', 'Sistem sedang mengalami gangguan. Silakan coba beberapa saat lagi.')

@section('detail')
    <p>Tim kami sudah mencatat kejadian ini melalui log sistem.</p>
@endsection
