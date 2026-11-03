@extends('layouts.master')

@section('title', 'Aportar - Carpoolear')
@section('body-class', 'body-difusion')

@section('content')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Dela+Gothic+One&display=swap" rel="stylesheet">
<style>
    .public-donate {
        --ds-text-primary: #22211F;
        --ds-text-secondary: #404040;
        --ds-header-donate-bg: #5cb85c;
        color: var(--ds-text-primary);
        padding-bottom: 4rem;
    }
    .public-donate__hero {
        display: grid;
        gap: 1.5rem;
        margin: 1.5rem 0 2rem;
    }
    .public-donate__hero-copy {
        padding: 0 1rem;
        text-align: center;
    }
    .public-donate__title {
        margin: 0;
        font-family: 'Dela Gothic One', sans-serif;
        font-weight: 400;
        line-height: 0.95;
        letter-spacing: -0.02em;
        text-transform: uppercase;
    }
    .public-donate__title-line {
        display: block;
        font-size: clamp(1.75rem, 8vw, 4.5rem);
        color: var(--ds-text-secondary);
    }
    .public-donate__title-accent {
        color: var(--ds-header-donate-bg);
    }
    .public-donate__mission {
        margin: 1.25rem 0 0;
        font-size: 1.2rem;
        line-height: 1.45;
        text-align: left;
        color: var(--ds-text-secondary);
    }
    .public-donate__hero-media {
        width: 100%;
    }
    .public-donate__image-frame {
        position: relative;
        width: 85vw;
        max-width: 450px;
        margin: 0 auto;
        aspect-ratio: 4 / 3;
    }
    .public-donate__image-backdrop,
    .public-donate__image {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        border-radius: 1.25rem;
    }
    .public-donate__image-backdrop {
        background: var(--ds-header-donate-bg);
    }
    .public-donate__image {
        object-fit: cover;
        transform: rotate(-4deg);
        z-index: 1;
    }
    .public-donate__cta {
        padding: 0 1rem 2rem;
        text-align: center;
    }
    .public-donate__cta-title {
        margin: 0 0 1rem;
        font-family: 'Dela Gothic One', sans-serif;
        font-size: clamp(1.75rem, 8vw, 3rem);
        font-weight: 400;
        line-height: 1.05;
        text-transform: uppercase;
        color: var(--ds-text-secondary);
    }
    .public-donate__cta-title-accent {
        display: block;
        color: var(--ds-header-donate-bg);
    }
    .public-donate__benefits {
        max-width: 42rem;
        margin: 1.25rem auto 0;
        padding-inline-start: 2rem;
        text-align: left;
        list-style: disc;
    }
    .public-donate__benefits li {
        margin: 0 0 0.75rem;
        font-size: 1.05rem;
        line-height: 1.45;
    }
    .public-donate__form {
        max-width: 42rem;
        margin: 0 auto;
        padding: 0 1rem 2rem;
        text-align: center;
    }
    .public-donate__usage {
        margin-bottom: 1rem;
        font-size: 1.2rem;
        line-height: 1.45;
    }
    .public-donate .radio {
        margin-bottom: 1.5em;
    }
    .public-donate .btn-donar,
    .club-login-modal .btn-donar {
        display: block;
        width: fit-content;
        max-width: 100%;
        margin: 1rem auto 0;
        min-height: auto;
        padding: 0.85em 1.5em;
        border: 0;
        border-radius: 10px;
        color: #fff;
        background: var(--ds-header-donate-bg);
    }
    .public-donate .btn-once,
    .public-donate .btn-qr {
        background: transparent;
        border: 2px solid var(--ds-header-donate-bg);
        color: var(--ds-text-primary);
    }
    .public-donate .btn-qr {
        display: none;
        margin-top: 0.75rem;
    }
    .public-donate__once {
        margin-top: 9rem;
    }
    .public-donate__alt {
        margin-top: 3rem;
        text-align: left;
        font-size: 1.05rem;
        line-height: 1.45;
    }
    .qr-payment-panel {
        display: none;
        margin-top: 1.5em;
        padding: 1.5em;
        border: 1px solid #ddd;
        border-radius: 10px;
        background: #fff;
        text-align: center;
        max-width: 360px;
        margin-left: auto;
        margin-right: auto;
    }
    .qr-payment-panel.is-open {
        display: block;
    }
    .qr-image {
        max-width: 256px;
        height: auto;
        margin: 1em 0;
    }
    .club-login-modal {
        display: none;
        position: fixed;
        z-index: 9998;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .club-login-modal.is-open {
        display: flex;
    }
    .club-login-modal__dialog {
        background: #fff;
        border-radius: 10px;
        max-width: 28rem;
        width: 100%;
        padding: 1.5rem;
        text-align: center;
    }
    .club-login-modal__dialog h3 {
        margin-top: 0;
    }
    @media (min-width: 768px) {
        .public-donate__hero {
            grid-template-columns: 1fr 1fr;
            align-items: center;
            gap: 4rem;
            padding: 0 2.5rem;
        }
        .public-donate__hero-copy {
            padding: 0;
            text-align: left;
        }
        .public-donate__image-frame {
            width: 100%;
            max-width: 100%;
        }
    }
</style>
<section class="public-donate">
    <div class="public-donate__hero">
        <div class="public-donate__hero-copy">
            <h1 class="public-donate__title">
                <span class="public-donate__title-line">Tu aporte</span>
                <span class="public-donate__title-line public-donate__title-accent">es fundamental</span>
            </h1>
            <div class="public-donate__mission">
                <p><strong>Queremos seguir siendo la mejor comunidad de carpooling de Argentina.</strong></p>
                <p>Carpoolear es un proyecto colaborativo sin fines de lucro de la ONG STS Rosario. En ruta desde el 2013, hoy día es la comunidad de Carpooling más grande de Argentina, integrada por más de 450.000 personas y más de 1500 viajes mensuales. Se requiere mucho trabajo, ya no es suficiente sólo con voluntariado. Por eso, es importante tu aporte para poder mantener y mejorar Carpoolear tal como lo venimos haciendo desde que salimos de “Zona de derrumbe” en 2025.</p>
            </div>
        </div>
        <div class="public-donate__hero-media">
            <div class="public-donate__image-frame">
                <div class="public-donate__image-backdrop" aria-hidden="true"></div>
                <img class="public-donate__image" src="/img/carpoolear-grupal-1.jpg" alt="">
            </div>
        </div>
    </div>

    <section class="public-donate__cta">
        <h2 class="public-donate__cta-title">
            Sumate al
            <span class="public-donate__cta-title-accent">Club Carpoolear</span>
        </h2>
        <p>Con tu aporte mensual podemos mantener y seguir mejorando Carpoolear, a cambio obtenés los siguientes beneficios por ser parte:</p>
        <ul class="public-donate__benefits">
            <li><strong>Soporte prioritario:</strong> tus tickets de Mesa de Ayuda tendrán prioridad.</li>
            <li><strong>Acceso anticipado:</strong> vas a poder probar funcionalidades nuevas antes que salgan.</li>
            <li><strong>Informe semestral:</strong> cada 6 meses te vamos a mandar un mail contándote lo que logramos gracias a tu aporte.</li>
            <li><strong>Pin:</strong> tu perfil tendrá una insignia y vas a aparecer en una lista de personas que forman parte del Club Carpoolear (si así lo querés).</li>
        </ul>
    </section>

    <div class="public-donate__form">
        <p class="public-donate__usage">Puedo aportar cada mes con el equivalente a...</p>
        <div class="radio" data-donation-tiers>
            <label class="radio-inline"><input type="radio" name="donationValor" value="5000"><span>$ 5000 (café)</span></label>
            <label class="radio-inline"><input type="radio" name="donationValor" value="7500"><span>$ 7500 (pinta de cerveza)</span></label>
            <label class="radio-inline"><input type="radio" name="donationValor" value="12000"><span>$ 12000 (comida)</span></label>
        </div>
        <button class="btn-donar" type="button" data-club>Quiero formar parte del Club Carpoolear<br />(cancelá cuando quieras)</button>

        <section class="public-donate__once">
            <p>¿No podés comprometerte a aportar cada mes? Podés hacerlo por única vez:</p>
            <div class="radio" data-donation-tiers>
                <label class="radio-inline"><input type="radio" name="donationValorOnce" value="5000"><span>$ 5000 (café)</span></label>
                <label class="radio-inline"><input type="radio" name="donationValorOnce" value="7500"><span>$ 7500 (pinta de cerveza)</span></label>
                <label class="radio-inline"><input type="radio" name="donationValorOnce" value="12000"><span>$ 12000 (comida)</span></label>
            </div>
            <button class="btn-donar btn-once btn-unica" type="button">Quiero aportar por única vez</button>
            <button class="btn-donar btn-qr" type="button">Pagar con QR</button>
            <div class="qr-payment-panel" data-qr-panel>
                <p><strong>Escanéa con una billetera virtual</strong></p>
                <img class="qr-image" alt="QR" hidden />
                <p class="qr-expiry">El QR expira en 15 minutos.</p>
                <button type="button" data-qr-close>Cerrar</button>
            </div>
        </section>

        <div class="public-donate__alt">
            <p>¿No estás en condiciones de aportar económicamente? ¿Simplemente te gusta meter las manos en el barro? Podés aportar tu tiempo <strong><a href="https://carpoolear.com.ar/colabora-como-colaborar" target="_blank" rel="noopener noreferrer">sumándote al voluntariado</a></strong>, siempre necesitamos una mano en las distintas áreas del proyecto.</p>
            <p>Siempre recordá que el aporte más sencillo y fundamental es hacer correr la voz. Sí, el boca en boca es fundamental para que crezca la comunidad Carpoolear y se compartan más viajes:</p>
            <ul>
                <li>Si usas las redes sociales virtuales <strong><a href="https://instagram.com/carpoolear" target="_blank" rel="noopener noreferrer">Instagram</a></strong>, <strong><a href="https://facebook.com/carpoolear" target="_blank" rel="noopener noreferrer">Facebook</a></strong> y compartí nuestras publicaciones/historias.</li>
                <li>Mejor aún, la red social del cara a cara… contale a tus amistades y familia sobre Carpoolear, contales de ese viaje que compartiste, de la gente que conociste, contales que compartir no es una locura.</li>
            </ul>
            <p>Buen viaje!</p>
            <p><strong>Equipo Carpoolear</strong> (también conocido como “La gente de Carpu”)</p>
        </div>
    </div>
</section>

<div class="club-login-modal" data-club-modal>
    <div class="club-login-modal__dialog">
        <h3>Club Carpoolear</h3>
        <p>Para ser miembro del Club Carpoolear tenés que estar logueado en la app, te vamos a redirigir</p>
        <button type="button" class="btn-donar" data-club-login-ok>Ok</button>
    </div>
</div>

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
    function donationPayload(amount) {
        var payload = { amount: parseInt(amount, 10), source: 'aportar' };
        var user_id = checkoutUserId();
        if (user_id) {
            payload.user_id = user_id;
        }
        return payload;
    }
    function startAportarCheckout(amount) {
        fetch('/api/donations/checkout/once', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(donationPayload(amount))
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
    var qrPollId = null;
    function selectedAmount(groupName) {
        var rdb = document.querySelector('input[name="' + groupName + '"]:checked');
        return rdb ? rdb.value : null;
    }
    function qrPanel() {
        return document.querySelector('[data-qr-panel]');
    }
    function closeQrPanel() {
        var panel = qrPanel();
        if (panel) {
            panel.classList.remove('is-open');
        }
        if (qrPollId) {
            clearInterval(qrPollId);
            qrPollId = null;
        }
    }
    function startAportarQrCheckout(amount) {
        fetch('/api/donations/checkout/qr-order', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(donationPayload(amount))
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            });
        }).then(function (result) {
            if (!result.ok || !result.data || !result.data.qr_data || !result.data.payment_id) {
                alert('No pudimos iniciar el aporte con QR. Probá de nuevo.');
                return;
            }
            var panel = qrPanel();
            var img = panel ? panel.querySelector('.qr-image') : null;
            if (!panel || !img || typeof QRCode === 'undefined') {
                alert('No pudimos mostrar el QR. Probá de nuevo.');
                return;
            }
            QRCode.toDataURL(result.data.qr_data, { width: 256, margin: 2 }, function (err, url) {
                if (err) {
                    alert('No pudimos mostrar el QR. Probá de nuevo.');
                    return;
                }
                img.src = url;
                img.hidden = false;
                panel.classList.add('is-open');
            });
            if (qrPollId) {
                clearInterval(qrPollId);
            }
            qrPollId = setInterval(function () {
                fetch('/api/donations/payments/' + result.data.payment_id, {
                    headers: { 'Accept': 'application/json' }
                }).then(function (response) {
                    return response.json();
                }).then(function (statusPayload) {
                    if (statusPayload && statusPayload.status === 'approved') {
                        closeQrPanel();
                        alert('¡Gracias por tu aporte!');
                    }
                }).catch(function () {});
            }, 3000);
        }).catch(function () {
            alert('No pudimos iniciar el aporte con QR. Probá de nuevo.');
        });
    }
    function renderDonationTiers(tiers) {
        document.querySelectorAll('[data-donation-tiers]').forEach(function (container, index) {
            var name = index === 0 ? 'donationValor' : 'donationValorOnce';
            container.innerHTML = tiers.map(function (tier) {
                return '<label class="radio-inline"><input type="radio" name="'
                    + name + '" value="' + tier.amount + '"><span>$ '
                    + tier.amount + '</span></label>';
            }).join('');
        });
    }
    function clubModal() {
        return document.querySelector('[data-club-modal]');
    }
    fetch('/api/donation-tiers').then(function (response) {
        return response.json();
    }).then(function (tiers) {
        if (Array.isArray(tiers) && tiers.length) {
            renderDonationTiers(tiers);
        }
    }).catch(function () {});
    fetch('/api/config').then(function (response) {
        return response.json();
    }).then(function (config) {
        if (config && config.platform_donations_qr_enabled) {
            document.querySelectorAll('.btn-qr').forEach(function (btn) {
                btn.style.display = 'block';
            });
        }
    }).catch(function () {});
    document.querySelectorAll('.btn-donar').forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            if (event.currentTarget.hasAttribute('data-qr-close')) {
                closeQrPanel();
                return;
            }
            if (event.currentTarget.hasAttribute('data-club')) {
                clubModal().classList.add('is-open');
                return;
            }
            if (event.currentTarget.hasAttribute('data-club-login-ok')) {
                window.location.href = '/app/donate';
                return;
            }
            var amount = selectedAmount('donationValorOnce') || selectedAmount('donationValor');
            if (!amount) {
                alert('Tienes que seleccionar un valor de aporte');
                return;
            }
            if (event.currentTarget.className.indexOf('btn-qr') >= 0) {
                startAportarQrCheckout(amount);
                return;
            }
            startAportarCheckout(amount);
        });
    });
    var closeQr = document.querySelector('[data-qr-close]');
    if (closeQr) {
        closeQr.addEventListener('click', closeQrPanel);
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
@endsection
