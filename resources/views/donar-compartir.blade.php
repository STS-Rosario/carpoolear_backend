<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8" />
        <title>Aportar a Carpoolear</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css" integrity="sha384-MCw98/SFnGE8fJT3GXwEOngsV7Zt27NXFoaoApmYm81iuXoPkFOJwJ8ERdknLPMO" crossorigin="anonymous">
        <link href='http://fonts.googleapis.com/css?family=Open+Sans:400,500,600' rel='stylesheet' type='text/css'>
        <style>
            html, body {
                background: #FAFAFA;
            }
            h3 {
                font-weight: normal;
            }
            .container {
                margin-top: 3rem;
            }
            .donation {
                margin-top: 4em;
                margin-bottom: 1em;
                color: #666;
                font-size: 16px;
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
                border-radius: 10px;
                margin-right: 10px;

                width: 43%;
                margin: 2%;
                padding: 1em 0;
                white-space: normal;
                max-width: 250px;
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
            .radio-inline > * {
                vertical-align: middle;
            }
            .radio-inline input {
                margin-right: .5rem;
            }
            .radio-inline span {
                margin-right: 1.5rem;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="card-header bg-transparent text-center">
                    <h3>
                        Aportá a Carpoolear 
                        <br class="d-md-none d-lg-none d-xl-none">
                        un proyecto de <img class="flush-right" src="/img/logo_sts_nuevo_color.png" width="170" height="50" alt="STS Rosario">
                    </h3>
                </div>
                <div class="card-body text-center">
                    <div class="donation donation-top">
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
                            <button class="btn-mensualmente btn-donar" id="btn-mensual">MENSUAL <br />(cancelá cuando quieras)</button>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="/aportar" class="card-link">Por qué aportar a Carpoolear</a>
                </div>
            </div>
        </div>
    </body>
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
                    alert('Debes seleccionar un monto de aporte. Gracias!');
                    return;
                }
                var type = event.currentTarget.className.indexOf('btn-unica') >= 0 ? 'once' : 'monthly';
                startAportarCheckout(type, rdb.value);
            });
        });
    </script>
</html>