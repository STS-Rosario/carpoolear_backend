@extends('layouts.master')

@section('title', 'Verificación de cuenta - Carpoolear')
@section('body-class', 'body-plataforma verificacion-cuenta')

@section('content')
<section>
    <div class="container">
        <div class="row">
            <div class="col-sm-12 pt48">
              @include('static-pages.verificacion-cuenta')
            </div>
        </div>
    </div>
</section>
@endsection
