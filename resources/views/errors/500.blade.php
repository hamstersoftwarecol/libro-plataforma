@extends('errors.layout', ['code' => 500])

@section('title', 'Error del servidor')
@section('eyebrow', 'Algo se rompió')
@section('heading')
Caímos, pero <em>nos levantamos</em>.
@endsection
@section('message')
Ocurrió un error inesperado de nuestro lado. Ya quedó registrado; inténtalo de nuevo en unos minutos.
@endsection

@section('actions')
    <button type="button" class="err-btn err-btn-primary" onclick="location.reload()">Intentar de nuevo</button>
    <a href="{{ url('/') }}" class="err-btn err-btn-ghost">Volver al inicio</a>
@endsection
