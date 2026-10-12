@extends('errors.layout', ['code' => 419])

@section('title', 'Sesión expirada')
@section('eyebrow', 'Sesión expirada')
@section('heading')
El tiempo <em>se nos fue</em>.
@endsection
@section('message')
Por seguridad, tu sesión expiró mientras la página estaba abierta. Recarga e inténtalo de nuevo; no se perdió nada.
@endsection
@section('quote', 'Siempre se puede volver a empezar.')
@section('actions')
    <button type="button" class="err-btn err-btn-primary" onclick="location.reload()">Recargar la página</button>
    <a href="{{ url('/') }}" class="err-btn err-btn-ghost">Volver al inicio</a>
@endsection
