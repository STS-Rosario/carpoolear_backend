@extends('layouts.master')

@section('title', 'Preguntas Frecuentes - Plataforma - Carpoolear')
@section('body-class', 'body-plataforma plataforma-preguntas-frecuentes')

@section('content')
<section>
    <div class="container">
        <div class="row">
            <div class="col-sm-12 pt48">
              @include('static-pages.plataforma-preguntas-frecuentes')
            </div>
        </div>
    </div>
</section>
@endsection
