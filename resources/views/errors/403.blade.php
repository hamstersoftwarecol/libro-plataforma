@extends('errors.layout', ['code' => 403])

@section('title', 'Acceso restringido')
@section('eyebrow', 'Acceso restringido')
@section('heading')
Esta puerta <em>no es para ti</em>… todavía.
@endsection
@section('message')
No tienes permiso para ver este contenido. Si es un libro, necesitas una suscripción activa para leerlo.
@endsection
@section('quote', 'Empezar a veces duele.')
@section('actions')
    <a href="{{ url('/empezar') }}" class="err-btn err-btn-primary">Ir a mi libro</a>
    <a href="{{ url('/') }}" class="err-btn err-btn-ghost">Volver al inicio</a>
@endsection
