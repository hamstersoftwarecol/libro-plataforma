@extends('errors.layout', ['code' => 429])

@section('title', 'Demasiadas solicitudes')
@section('eyebrow', 'Respira')
@section('heading')
Vamos <em>más despacio</em>.
@endsection
@section('message')
Hiciste demasiados intentos en poco tiempo. Espera un minuto y vuelve a intentarlo.
@endsection
@section('quote', 'No hay prisa para renacer.')

