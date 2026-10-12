@extends('errors.layout', ['code' => 401])

@section('title', 'Necesitas ingresar')
@section('eyebrow', 'Identifícate')
@section('heading')
Primero, <em>entra a tu cuenta</em>.
@endsection
@section('message')
Para ver esta página necesitas iniciar sesión. Solo te pediremos tu correo: te enviamos un código y listo.
@endsection

@section('actions')
    <a href="{{ url('/acceso') }}" class="err-btn err-btn-primary">Ingresar</a>
    <a href="{{ url('/') }}" class="err-btn err-btn-ghost">Volver al inicio</a>
@endsection
