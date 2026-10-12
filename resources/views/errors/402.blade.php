@extends('errors.layout', ['code' => 402])

@section('title', 'Pago requerido')
@section('eyebrow', 'Pago pendiente')
@section('heading')
Falta <em>un último paso</em>.
@endsection
@section('message')
Para continuar necesitas completar tu pago. El acceso se activa en cuanto se confirme.
@endsection

@section('actions')
    <a href="{{ url('/pagar') }}" class="err-btn err-btn-primary">Completar pago</a>
    <a href="{{ url('/') }}" class="err-btn err-btn-ghost">Volver al inicio</a>
@endsection
