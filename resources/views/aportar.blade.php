@extends('layouts.master')

@section('title', 'Aportar - Carpoolear')
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
                <img src="/img/economia-colaborativa.jpg" style="float: right; width: 100%; max-width: 450px;" class="hidden-xs" />
                <div class="donation donation-top">
                    <h3>Aportar</h3>
                    <div class="radio" data-donation-tiers>
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" value="5000"><span>$ 5000</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" value="7500"><span>$ 7500</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" value="12000"><span>$ 12000</span>
                        </label>
                    </div>
                    <div>
                        <button class="btn-unica-vez btn-donar btn-unica" id="btn-unica">ÚNICA VEZ</button>
                        <button class="btn-mensualmente btn-donar" id="btn-mensual">MENSUALMENTE <br />(cancelá cuando quieras)</button>
                    </div>
                </div>
                <p>¡Hola Carpooler@s!</p>

                <p>En el 2013 pusimos en marcha a Carpoolear y desde ese momento estamos en ruta llevando adelante la plataforma bajo USO GRATUITO tanto en su versión web como app. Somos un proyecto SIN FINES DE LUCRO, COLABORATIVO y de CÓDIGO LIBRE. Queremos seguir siéndolo y para eso NECESITAMOS tu APOYO.</p>

                <p>Quienes nos acompañan desde el comienzo saben que avanzamos un montón con muy pocos recursos esporádicos, pero esto es cada vez más difícil. Hoy en día la plataforma tiene más de 100 mil personas registradas, requiere mucho trabajo y coordinación, que son llevados adelante mediante esfuerzo de un EQUIPO VOLUNTARIO pero también tenemos gastos de servidores, legales y administrativos como cualquier proyecto.</p>

                <p>Por eso necesitamos tu APORTE para poder avanzar con el desarrollo de la plataforma más rápido manteniendo nuestra filosofía de trabajo. Si Carpoolear es útil para vos, te gusta lo que hacemos, querés cuidar el medio ambiente, tomate 1 MINUTO y COLABORÁ :D
                Podés aportar $1000, $2000 y más allá también. O sea, que nos podés invitar un café con leche, una pinta o por qué no, salir a comer.</p>

                <p>Carpoolear es un proyecto de STS Rosario, una ONG sin fines de lucro, constituida como asociación civil desde el 2014. A través de proyectos concretos, divulga las problemáticas socioambientales actuales y genera herramientas, para provocar un cambio cultural hacia una sociedad sustentable, resiliente y equitativa. Del total del aporte realizado a nosotros, un 10% será destinada al sostenimiento de nuestra organización, para que pueda haber más proyectos como Carpoolear. Podés enterarte más acerca de <a href="https://www.stsrosario.org.ar" target="_blank">STS en www.stsrosario.org.ar</a></p>

                <div class="donation hidden-sm hidden-md hidden-lg">
                    <div class="radio" data-donation-tiers>
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" value="5000"><span>$ 5000</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" value="7500"><span>$ 7500</span>
                        </label>
                        <label class="radio-inline">
                            <input type="radio" name="donationValor" value="12000"><span>$ 12000</span>
                        </label>
                    </div>
                    <div>
                        <button class="btn-unica-vez btn-donar btn-unica" id="btn-unica">ÚNICA VEZ</button>
                        <button class="btn-mensualmente btn-donar" id="btn-mensual">MENSUALMENTE <br />(cancelá cuando quieras)</button>
                    </div>
                </div>
                <p>Cualquier duda, escribinos a nuestras redes sociales o a <a href="mailto:carpoolear@stsrosario.org.ar">carpoolear@stsrosario.org.ar</a></p>
                <img src="/img/economia-colaborativa.jpg" style="float: right; width: 100%; max-width: 450px;" class="hidden-sm hidden-md hidden-lg" />
            </div>
        </div>
    </div>
</section>
<script>
    function getParameterByName(name, url) {
        if (!url) url = window.location.href;
        name = name.replace(/[\[\]]/g, '\\$&');
        var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
            results = regex.exec(url);
        if (!results) return null;
        if (!results[2]) return '';
        return decodeURIComponent(results[2].replace(/\+/g, ' '));
    }
    function checkoutUserId() {
        var user_id = getParameterByName('u') || getParameterByName('user');
        return user_id ? parseInt(user_id, 10) : null;
    }
    function startAportarCheckout(type, amount) {
        var payload = { amount: parseInt(amount, 10), source: 'aportar' };
        var user_id = checkoutUserId();
        if (user_id) {
            payload.user_id = user_id;
        }
        var path = type === 'monthly'
            ? '/api/donations/checkout/monthly'
            : '/api/donations/checkout/once';
        fetch(path, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            });
        }).then(function (result) {
            if (result.ok && result.data && result.data.init_point) {
                window.open(result.data.init_point, '_blank');
                return;
            }
            alert('No pudimos iniciar el aporte. Probá de nuevo.');
        }).catch(function () {
            alert('No pudimos iniciar el aporte. Probá de nuevo.');
        });
    }
    function renderDonationTiers(tiers) {
        document.querySelectorAll('[data-donation-tiers]').forEach(function (container) {
            container.innerHTML = tiers.map(function (tier) {
                return '<label class="radio-inline"><input type="radio" name="donationValor" value="'
                    + tier.amount + '"><span>$ ' + tier.amount + '</span></label>';
            }).join('');
        });
    }
    fetch('/api/donation-tiers').then(function (response) {
        return response.json();
    }).then(function (tiers) {
        if (Array.isArray(tiers) && tiers.length) {
            renderDonationTiers(tiers);
        }
    }).catch(function () {});
    document.querySelectorAll('.btn-donar').forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            var rdb = document.querySelector('input[name="donationValor"]:checked');
            if (!rdb) {
                alert('Debes seleccionar un monto de donación. Gracias!');
                return;
            }
            var type = event.currentTarget.className.indexOf('btn-unica') >= 0 ? 'once' : 'monthly';
            startAportarCheckout(type, rdb.value);
        });
    });
</script>
@endsection
