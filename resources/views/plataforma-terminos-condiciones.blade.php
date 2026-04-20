@extends('layouts.master')

@section('title', 'Términos y Condiciones - Plataforma - Carpoolear')
@section('body-class', 'body-plataforma body-terminos-condiciones')

@section('content')
<section>
    <div class="container">
        <div class="row">
            <div class="col-sm-12 pt48"><div data-v-6ccf5284="" class="terms-page container">
                <h2>T&eacute;rminos y condiciones de Carpoolear&reg;</h2>

                <p><span style="font-size:11pt"><span style="font-family:Arial"><span style="color:#222222"><strong>&Uacute;ltima revisi&oacute;n: </strong></span></span></span><span style="font-size:11pt"><span style="font-family:Arial"><span style="color:#ff0000"><strong>21 de abril de 2026</strong></span></span></span></p>

                @include('partials.terminos-y-condiciones-texto')
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
