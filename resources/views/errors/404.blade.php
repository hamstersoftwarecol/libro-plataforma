@extends('errors.layout', ['code' => 404])

@section('title', 'Página no encontrada')
@section('eyebrow', 'Camino perdido')
@section('heading')
Esta página <em>no existe</em>.
@endsection
@section('message')
Quizá el enlace está mal escrito o la página se movió. A veces perderse es parte del camino: vuelve al inicio y sigue leyendo.
@endsection
@section('quote', 'Todo camino tiene un regreso.')

