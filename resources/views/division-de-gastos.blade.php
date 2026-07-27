@extends('layouts.master')

@section('title', 'División de gastos - Carpoolear')
@section('body-class', 'body-plataforma division-de-gastos')

@section('content')
<section>
    <div class="container">
        <div class="row">
            <div class="col-sm-12 pt48">
              @include('static-pages.division-de-gastos')
            </div>
        </div>
    </div>
</section>
@endsection
