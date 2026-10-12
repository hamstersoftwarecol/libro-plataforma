@extends('errors.layout', ['code' => 503])

@section('title', 'En mantenimiento')
@section('eyebrow', 'En mantenimiento')
@section('heading')
Estamos <em>preparando algo</em>.
@endsection
@section('message')
La plataforma está en mantenimiento por unos minutos. Vuelve pronto: tu progreso de lectura está guardado.
@endsection
@section('quote', 'Toda pausa precede a un nuevo comienzo.')
@section('actions')
    <button type="button" class="err-btn err-btn-primary" onclick="location.reload()">Volver a intentar</button>
@endsection
