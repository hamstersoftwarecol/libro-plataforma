@extends('errors.layout', ['code' => 405])

@section('title', 'Acción no permitida')
@section('eyebrow', 'Desvío')
@section('heading')
Por aquí <em>no se llega</em>.
@endsection
@section('message')
Esta acción no está permitida desde esta página. Vuelve atrás e inténtalo de nuevo desde el botón correspondiente.
@endsection


