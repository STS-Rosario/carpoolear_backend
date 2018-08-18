@extends('layouts.master')

@section('title', 'Donar - Carpoolear')
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

                <h2>Donar</h2>

                <p>¿Donar o no donar? ¡Esa es la cuestión! STS Rosario, la organización que creó y desarrolla Carpoolear, se financia a través del aporte de sus socios, concursos, prestación de servicios, merchandising y donaciones. Es importante destacar que ninguna de estas formas de financiamiento limita nuestra autonomía institucional ni condiciona el cumplimiento de nuestros objetivos. Tu donación nos ayuda a sostenernos de manera independiente como asociación civil, permitiendo también solventar los gastos de nuestro espacio físico y virtual. ¡Sumá tu aporte!</p>

                <div class="donation">
                    <div class="radio">
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" id="donation50" value="50" v-model="donateValue"><span>50</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" id="donation100" value="100" v-model="donateValue"><span>100</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" id="donation200" value="200" v-model="donateValue"><span>200</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" id="donation500" value="500" v-model="donateValue"><span>500</span>
                        </label>
                    </div>
                    <div>
                        <button class="btn-unica-vez btn-donar" id="btn-unica">ÚNICA VEZ</button>
                        <button class="btn-mensualmente btn-donar" id="btn-mensual">MENSUALMENTE <br />(cancelá cuando quieras)</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
    var linksUnicaVez = {
        50: "http://mpago.la/jgap",
        100: "http://mpago.la/CaSZ",
        200: "http://mpago.la/xntw",
        500: "http://mpago.la/QEiN"
    };
    var linksMensual = {
        50: "http://mpago.la/1w3aci",
        100: "http://mpago.la/BfZ",
        200: "http://mpago.la/P02H",
        500: "http://mpago.la/k8Xp"
    };
    var btns = document.querySelectorAll(".btn-donar");
    btns.forEach(function (btn) {
        btn.addEventListener("click", function (event) {
            console.log(event.target.id);
            var value = document.querySelector('input[name="donationValor"]:checked').value;
            if (event.target.id === "btn-unica") {
                window.open(linksUnicaVez[value], '_blank');
            } else {
                window.open(linksMensual[value], '_blank');
            }
        });
    });
</script>
@endsection
