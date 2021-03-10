@extends('layouts.master')

@section('title', 'Contribución Máxima')
@section('body-class', 'body-difusion')

@section('content')
<style>
    .body-donar {
        min-height: 80vh;
    }
    .donation {
        margin-top: 4em;
        margin-bottom: 1em;
    }
    .donation-top {
        margin-top: 0;
    }
    .radio {
        margin-bottom: 1.5em;
    }
    .btn-donar {
        min-height: 5em;
        vertical-align: middle;
        border: 0;
        padding: 1em 2em;
        min-width: 250px;
        border-radius: 10px;
        margin-right: 10px;
    }
    .btn-donar:hover,
    .btn-donar:active,
    .btn-donar:focus {
        opacity: 0.90;
    }
    .btn-unica-vez {    
        color: #fff;
        background-color: #5cb85c;
        border-color: #4cae4c;
    }
    .btn-mensualmente {    
        color: #fff;
        background-color: #5bc0de;
        border-color: #46b8da;
    }
</style>
<section>
    <div class="container">
        <div class="row">
            <div class="col-sm-12 pt48 body-donar">
                <div class="donation donation-top">
                    <h3>En los #viajes es FUNDAMENTAL cumplir con la regla de CONTRIBUCIÓN MÁXIMA</h3>
                    <img src="/img/lucro.png" style="width: 100%; max-width: 450px;" class="hidden-xs" />
                    <p> #Carpoolear NO ES UN SERVICIO de transporte de pasajeros, es una propuesta de #EconomíaColaborativa, donde quienes conducen comparten su viaje y pueden pedir una #colaboración que puede ser en chipá, mates bien cebados, charla, dinero, etc. </p>

                    <p>En caso de elegir dinero, para cuentas claras (que ya dice el refrán, conservan la amistad) RECOMENDAMOS llenar el tanque antes del viaje y nuevamente al llegar. El primer ticket validará que el tanque estaba lleno, el segundo ticket mostrará el gasto que sumado a los peajes, da un total a dividirse entre TODAS las personas que van en el auto, ¡quien conduce TAMBIÉN! Esa es la máxima contribución que se puede pedir (claro que puede ser menos o lo que se pueda colaborar!).</p>

                    <p>
                        Al crear un viaje en nuestra plataforma, la CALCULADORA da un aproximado del TOTAL del dinero para combustible, dejando sumar un margen del 15% para peajes. No pueden pasar de ese monto total todas las contribuciones pedidas.
                    </p>
                    <p>
                        Mandá tu denuncia por privado nuestras redes (twitter, facebook, instagram) o a nuestro mail carpoolear@stsrosario.org.ar
                    </p>
                    <p>
                        Hacemos la  #ComunidadCarpoolear en conjunto!
                    </p>
            </div>
        </div>
    </div>
</section>
@endsection
