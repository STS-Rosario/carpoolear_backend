@extends('layouts.master')

@section('title', 'Aportar - Carpoolear')
@section('body-class', 'body-difusion public-donate-page')

@section('content')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&family=Dela+Gothic+One&display=swap" rel="stylesheet">
<style>
    body.public-donate-page {
        background: #F0F0F0;
        color: #22211F;
    }
    body.public-donate-page .main-container {
        background: #F0F0F0;
    }
    .public-donate {
        --ds-text-primary: #22211F;
        --ds-text-secondary: #404040;
        --ds-header-donate-bg: #5cb85c;
        --ds-header-donate-border: #4cae4c;
        --ds-font-family: 'Barlow', sans-serif;
        box-sizing: border-box;
        width: 100%;
        max-width: 100%;
        margin: 0 auto;
        padding-bottom: 4em;
        color: var(--ds-text-primary);
        font-family: 'Barlow', sans-serif;
        font-size: 16px;
        font-weight: 400;
        line-height: 1.45;
    }
    .public-donate *,
    .public-donate *::before,
    .public-donate *::after {
        box-sizing: border-box;
    }
    .public-donate h1,
    .public-donate h2,
    .public-donate h3,
    .public-donate p,
    .public-donate ul,
    .public-donate ol,
    .public-donate li {
        margin: 0;
        padding: 0;
    }
    .public-donate h1,
    .public-donate h2,
    .public-donate h3 {
        font-size: inherit;
        font-weight: inherit;
        line-height: inherit;
        letter-spacing: normal;
        color: inherit;
    }
    .public-donate p,
    .public-donate li,
    .public-donate span,
    .public-donate strong,
    .public-donate label {
        font-family: inherit;
    }
    .public-donate a {
        color: inherit;
        font-family: inherit;
        font-weight: 700;
        text-decoration: underline;
    }
    .public-donate__hero {
        display: grid;
        gap: 1.5em;
        width: 100%;
        max-width: 100vw;
        margin: 1.5em 0 2em;
        box-sizing: border-box;
    }
    .public-donate__hero-copy {
        width: 100%;
        padding: 0 1em;
        text-align: center;
        container-type: inline-size;
    }
    .public-donate h1.public-donate__title {
        width: 100%;
        font-family: 'Dela Gothic One', var(--ds-font-family);
        font-size: 16px;
        font-weight: 400;
        line-height: 0.95;
        letter-spacing: -0.02em;
    }
    .public-donate span.public-donate__title-line {
        display: block;
        width: 100%;
        max-width: 100%;
        font-family: 'Dela Gothic One', var(--ds-font-family);
        font-size: clamp(1.75em, 9.5cqw, 4.5em);
        font-weight: 400;
        line-height: 0.95;
        letter-spacing: inherit;
        text-transform: uppercase;
        color: var(--ds-text-secondary);
    }
    .public-donate span.public-donate__title-line.public-donate__title-accent {
        color: var(--ds-header-donate-bg);
    }
    .public-donate__mission {
        display: none;
        margin: 1.25em 0 0;
        color: var(--ds-text-secondary);
        font-family: var(--ds-font-family);
        font-size: 1.4em;
        font-weight: 400;
        line-height: 1.45;
        text-align: left;
    }
    .public-donate__mission-lead,
    .public-donate__mission-body {
        font-size: inherit;
        font-weight: 400;
        line-height: inherit;
    }
    .public-donate__mission-body {
        margin-top: 0.75em;
    }
    .public-donate__hero-media {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow: hidden;
    }
    .public-donate__image-frame {
        position: relative;
        width: 85vw;
        max-width: 85vw;
        min-width: 0;
        margin: 0 auto;
        aspect-ratio: 4 / 3;
    }
    .public-donate__image-backdrop,
    .public-donate__image {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        border-radius: 1.25em;
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
        width: 100%;
        max-width: 100vw;
        padding: 0 1em 2em;
        text-align: center;
    }
    .public-donate h2.public-donate__cta-title,
    .public-donate span.public-donate__cta-title-accent {
        font-family: 'Dela Gothic One', var(--ds-font-family);
        font-weight: 400;
        letter-spacing: -0.02em;
        text-transform: uppercase;
    }
    .public-donate h2.public-donate__cta-title {
        margin: 0 0 1em;
        font-size: clamp(1.75em, 8vw, 3em);
        line-height: 1.05;
        color: var(--ds-text-secondary);
    }
    .public-donate span.public-donate__cta-title-accent {
        display: block;
        color: var(--ds-header-donate-bg);
    }
    .public-donate__cta-intro {
        margin: 0 auto;
        color: var(--ds-text-primary);
        font-size: 1.2em;
        line-height: 1.45;
    }
    .public-donate__benefits {
        margin: 1.25em auto 0;
        padding: 0 0 0 2em;
        max-width: 42em;
        list-style: disc;
        list-style-position: outside;
        text-align: left;
    }
    .public-donate__benefits li {
        margin: 0 0 0.75em;
        padding-left: 0.5em;
        color: var(--ds-text-primary);
        font-size: 1.05em;
        line-height: 1.45;
    }
    .public-donate__benefits li:last-child {
        margin-bottom: 0;
    }
    .public-donate__form {
        max-width: 42em;
        margin: 0 auto;
        padding: 0 1em 2em;
        text-align: center;
    }
    .public-donate__usage {
        margin-bottom: 1em;
        color: var(--ds-text-primary);
        font-size: 1.2em;
        line-height: 1.45;
    }
    .public-donate .radio {
        margin: 0 0 1.5em;
    }
    .public-donate .radio-inline {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin: 0 12px 8px 0;
        padding: 0;
        font-weight: 400;
        color: var(--ds-text-secondary);
        white-space: nowrap;
    }
    .public-donate .radio-inline input {
        position: static;
        margin: 0;
    }
    .public-donate .btn-donar,
    .club-login-modal .btn-donar {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.15em;
        width: fit-content;
        max-width: 100%;
        margin: 1em auto 0;
        min-height: auto;
        padding: 0.75em 1.25em;
        border: 1.5px solid var(--ds-header-donate-border);
        border-radius: 8px;
        color: #fff;
        background: var(--ds-header-donate-bg, #5cb85c);
        font-family: 'Barlow', sans-serif;
        font-size: 16px;
        font-weight: 600;
        line-height: 1.2;
        cursor: pointer;
    }
    .public-donate__btn-hint {
        display: block;
        font-size: 0.85em;
        font-weight: 400;
        line-height: 1.2;
    }
    .public-donate .btn-once,
    .public-donate .btn-qr {
        background: transparent;
        border: 2px solid var(--ds-header-donate-bg);
        color: var(--ds-text-primary);
        flex-direction: row;
    }
    .public-donate .btn-qr {
        display: none;
        margin-top: 0.75em;
    }
    .public-donate__once {
        margin-top: 9em;
        text-align: center;
    }
    .public-donate__once-intro {
        margin: 0 0 1em;
        color: var(--ds-text-primary);
        font-size: 1.05em;
        line-height: 1.45;
    }
    .public-donate__alt {
        display: flex;
        flex-direction: column;
        gap: 2.5em;
        margin-top: 9em;
        text-align: left;
        font-size: 1.05em;
        line-height: 1.45;
        color: var(--ds-text-primary);
    }
    .public-donate__alt ul {
        margin: 0.75em 0 0;
        padding-left: 1.5em;
        list-style: disc;
    }
    .public-donate__alt li + li {
        margin-top: 0.5em;
    }
    .qr-payment-panel {
        display: none;
        margin: 1.25em auto 0;
        padding: 1.5em;
        border: 1px solid #ddd;
        border-radius: 10px;
        background: #fff;
        text-align: center;
        max-width: 360px;
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
        --ds-text-primary: #22211F;
        --ds-header-donate-bg: #5cb85c;
        --ds-header-donate-border: #4cae4c;
        display: none;
        position: fixed;
        z-index: 9998;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        align-items: center;
        justify-content: center;
        padding: 1em;
        font-family: 'Barlow', sans-serif;
        font-size: 16px;
    }
    .club-login-modal.is-open {
        display: flex;
    }
    .club-login-modal__dialog {
        background: #fff;
        border-radius: 20px;
        max-width: 28em;
        width: 100%;
        padding: 1.5em;
        text-align: center;
        color: var(--ds-text-primary, #22211F);
        box-shadow: 3px 3px 7.3px 0 rgba(0, 0, 0, 0.05);
    }
    .club-login-modal__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75em;
        margin: 0 0 0.75em;
    }
    .club-login-modal__dialog h3 {
        margin: 0;
        flex: 1;
        min-width: 0;
        font-family: 'Dela Gothic One', 'Barlow', sans-serif;
        font-size: 1.5em;
        font-weight: 400;
        text-align: left;
        color: var(--ds-text-primary, #22211F);
    }
    .club-login-modal__close {
        flex-shrink: 0;
        margin: 0;
        padding: 0 0.25em;
        border: 0;
        background: transparent;
        color: #333;
        font-size: 1.5em;
        line-height: 1;
        cursor: pointer;
        appearance: none;
    }
    .club-login-modal__dialog p {
        margin: 0 0 1em;
        font-family: 'Barlow', sans-serif;
        font-size: 1em;
        line-height: 1.45;
        text-align: left;
        color: #333;
    }
    .club-login-modal .btn-donar {
        margin-top: 0.5em;
        margin-left: auto;
        margin-right: auto;
    }
    .club-login-modal__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75em;
        justify-content: center;
        margin-top: 0.5em;
    }
    .club-login-modal__actions .btn-donar {
        margin: 0;
    }
    .club-login-modal .btn-once {
        background: transparent;
        border: 2px solid var(--ds-header-donate-bg, #5cb85c);
        color: var(--ds-text-primary, #22211F);
        flex-direction: row;
    }
    @media (max-width: 767px) {
        .public-donate__benefits {
            padding-left: 1.75em;
            list-style-position: inside;
        }
        .public-donate__benefits li {
            padding-left: 0;
        }
    }
    @media (min-width: 768px) {
        .public-donate__hero {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            align-items: center;
            gap: 4em;
            padding: 0 2.5em;
            margin-top: 2em;
        }
        .public-donate__hero-copy {
            padding: 0;
            text-align: left;
        }
        .public-donate span.public-donate__title-line {
            font-size: clamp(2.75em, 4.2vw, 5.5em);
        }
        .public-donate__hero-media {
            padding: 1.5em 0 1.5em 1.5em;
        }
        .public-donate__image-frame {
            width: 100%;
            max-width: 100%;
        }
        .public-donate__cta {
            padding-left: 2.5em;
            padding-right: 2.5em;
        }
        .public-donate__form {
            padding-left: 0;
            padding-right: 0;
        }
    }
    @media (min-width: 992px) {
        .public-donate {
            width: 80vw;
            max-width: 80vw;
        }
        .public-donate__mission {
            display: block;
        }
        .public-donate__image-frame {
            width: 30vw;
            max-width: 30vw;
            margin-left: auto;
            margin-right: 0;
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
                <p class="public-donate__mission-lead">Queremos seguir siendo la mejor comunidad de carpooling de Argentina.</p>
                <p class="public-donate__mission-body">Carpoolear es un proyecto colaborativo sin fines de lucro de la ONG STS Rosario. En ruta desde el 2013, hoy día es la comunidad de Carpooling más grande de Argentina, integrada por más de 450.000 personas y más de 1500 viajes mensuales. Se requiere mucho trabajo, ya no es suficiente sólo con voluntariado. Por eso, es importante tu aporte para poder mantener y mejorar Carpoolear tal como lo venimos haciendo desde que salimos de “Zona de derrumbe” en 2025.</p>
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
        <p class="public-donate__cta-intro">Con tu aporte mensual podemos mantener y seguir mejorando Carpoolear, a cambio obtenés los siguientes beneficios por ser parte:</p>
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
            <label class="radio-inline"><input type="radio" name="donationValor" value="5000"><span>$ 5.000 (café)</span></label>
            <label class="radio-inline"><input type="radio" name="donationValor" value="7500"><span>$ 7.500 (pinta de cerveza)</span></label>
            <label class="radio-inline"><input type="radio" name="donationValor" value="12000"><span>$ 12.000 (comida)</span></label>
        </div>
        <button class="btn-donar" type="button" data-club>
            <span class="public-donate__btn-label">Quiero formar parte del Club Carpoolear</span>
            <span class="public-donate__btn-hint">(cancelá cuando quieras)</span>
        </button>

        <section class="public-donate__once">
            <p class="public-donate__once-intro">¿No podés comprometerte a aportar cada mes? Podés hacerlo por única vez:</p>
            <div class="radio" data-donation-tiers>
                <label class="radio-inline"><input type="radio" name="donationValorOnce" value="5000"><span>$ 5.000 (café)</span></label>
                <label class="radio-inline"><input type="radio" name="donationValorOnce" value="7500"><span>$ 7.500 (pinta de cerveza)</span></label>
                <label class="radio-inline"><input type="radio" name="donationValorOnce" value="12000"><span>$ 12.000 (comida)</span></label>
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
        <div class="club-login-modal__header">
            <h3>Club Carpoolear</h3>
            <button type="button" class="club-login-modal__close" data-club-login-close aria-label="Cerrar">&times;</button>
        </div>
        <p>Para ser miembro del Club Carpoolear tenés que estar logueado en la app, te vamos a redirigir</p>
        <button type="button" class="btn-donar" data-club-login-ok>Ok</button>
    </div>
</div>

<div class="club-login-modal" data-guest-donate-modal>
    <div class="club-login-modal__dialog">
        <div class="club-login-modal__header">
            <h3>Aportar</h3>
            <button type="button" class="club-login-modal__close" data-guest-donate-close aria-label="Cerrar">&times;</button>
        </div>
        <p>No estás logueado, por lo que tu donación no quedará asociada a tu usuario. Te recomendamos loguearte así podemos identificarte como donante :)</p>
        <div class="club-login-modal__actions">
            <button type="button" class="btn-donar btn-once" data-guest-donate-continue>Donar sin ingresar</button>
            <button type="button" class="btn-donar" data-guest-donate-login>Ingresar a Carpoolear</button>
        </div>
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
    function formatAmount(amount) {
        return Number(amount).toLocaleString('es-AR');
    }
    function tierLabel(tier) {
        var labels = { 5000: 'café', 7500: 'pinta de cerveza', 12000: 'comida' };
        var extra = tier.label || labels[parseInt(tier.amount, 10)];
        return extra ? '$ ' + formatAmount(tier.amount) + ' (' + extra + ')' : '$ ' + formatAmount(tier.amount);
    }
    function renderDonationTiers(tiers) {
        document.querySelectorAll('[data-donation-tiers]').forEach(function (container, index) {
            var name = index === 0 ? 'donationValor' : 'donationValorOnce';
            container.innerHTML = tiers.map(function (tier) {
                return '<label class="radio-inline"><input type="radio" name="'
                    + name + '" value="' + tier.amount + '"><span>'
                    + tierLabel(tier) + '</span></label>';
            }).join('');
        });
    }
    function clubModal() {
        return document.querySelector('[data-club-modal]');
    }
    function closeClubModal() {
        var modal = clubModal();
        if (modal) {
            modal.classList.remove('is-open');
        }
    }
    var pendingDonateKind = null;
    function guestDonateModal() {
        return document.querySelector('[data-guest-donate-modal]');
    }
    function closeGuestDonateModal() {
        var modal = guestDonateModal();
        if (modal) {
            modal.classList.remove('is-open');
        }
    }
    function dismissGuestDonateModal() {
        pendingDonateKind = null;
        closeGuestDonateModal();
    }
    function openGuestDonateModal(kind) {
        pendingDonateKind = kind;
        guestDonateModal().classList.add('is-open');
    }
    function startPendingDonation() {
        var kind = pendingDonateKind;
        var amount = selectedAmount('donationValorOnce') || selectedAmount('donationValor');
        pendingDonateKind = null;
        closeGuestDonateModal();
        if (!amount) {
            return;
        }
        if (kind === 'qr') {
            startAportarQrCheckout(amount);
            return;
        }
        startAportarCheckout(amount);
    }
    function redirectToAppDonate() {
        window.location.href = '/app/donate';
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
                redirectToAppDonate();
                return;
            }
            if (event.currentTarget.hasAttribute('data-guest-donate-login')) {
                redirectToAppDonate();
                return;
            }
            if (event.currentTarget.hasAttribute('data-guest-donate-continue')) {
                startPendingDonation();
                return;
            }
            var amount = selectedAmount('donationValorOnce') || selectedAmount('donationValor');
            if (!amount) {
                alert('Tienes que seleccionar un valor de aporte');
                return;
            }
            var donateKind = event.currentTarget.className.indexOf('btn-qr') >= 0 ? 'qr' : 'once';
            if (!checkoutUserId()) {
                openGuestDonateModal(donateKind);
                return;
            }
            if (donateKind === 'qr') {
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
    var clubModalEl = clubModal();
    if (clubModalEl) {
        clubModalEl.addEventListener('click', function (event) {
            if (event.target === event.currentTarget) {
                closeClubModal();
            }
        });
    }
    document.querySelectorAll('[data-club-login-close]').forEach(function (btn) {
        btn.addEventListener('click', closeClubModal);
    });
    var guestDonateModalEl = guestDonateModal();
    if (guestDonateModalEl) {
        guestDonateModalEl.addEventListener('click', function (event) {
            if (event.target === event.currentTarget) {
                dismissGuestDonateModal();
            }
        });
    }
    document.querySelectorAll('[data-guest-donate-close]').forEach(function (btn) {
        btn.addEventListener('click', dismissGuestDonateModal);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeClubModal();
            dismissGuestDonateModal();
        }
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
@endsection
