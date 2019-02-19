@extends('layouts.master')

@section('title', 'Datos - Carpoolear')
@section('body-class', 'body-difusion')

@section('content')
<style>
    #grafico-solicitudes,
    #grafico-viajes {
        height: 400px;
    }
</style>
<section>
    <div class="container">
        <div class="row">
            <div class="col-sm-12 pt48 body-donar">

                <h2>Estadísticas</h2>

                <p>Te mostramos las principales estadísticas de carpoolear en tiempo real</p>

                <h3>Viajes</h3>
                <canvas id="grafico-viajes"></canvas>
                <h3>Asientos disponibles</h3>
                <canvas id="grafico-solicitudes"></canvas>
                <h3>Usuarios registrados</h3>
                <canvas id="grafico-usuarios"></canvas>
                <canvas id="grafico-usuarios-totales"></canvas>
                <!--<h3>Conductores con más viajes cargados</h3>
                <div id="ranking-conductores"></div>
                <h3>Pasajeros que más han carpooleado</h3>
                <div id="ranking-pasajeros"></div>
                <h3>Usuarios más calificados</h3>
                <div id="ranking-calificaciones"></div>-->
                <h3>Viajes más frecuentes (acumulados desde Agosto 2017)</h3>
                <div id="ranking-origen-destino"></div>
                <h3>Origen más frecuentes (acumulados desde Agosto 2017)</h3>
                <div id="ranking-origen"></div>
                <h3>Destino más frecuentes (acumulados desde Agosto 2017)</h3>
                <div id="ranking-destino"></div>
            </div>
        </div>
    </div>
</section>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.2/Chart.min.js"></script>
<script>
    function crearGraficoUsuarios (labels, data, data2) {
        console.log(labels, data);
        var ctx = document.getElementById('grafico-usuarios');
        var chart = new Chart(ctx, {
            // The type of chart we want to create
            type: 'line',

            // The data for our dataset
            data: {
                labels: labels,
                datasets: [{
                        label: 'Usuarios',
                        backgroundColor: "#F00",
                        borderColor: "#F00",
                        data: data,
                        fill: false,
                }]
            },

            // Configuration options go here
            options: {
                responsive: true,
                title: {
                    display: true,
                    text: 'Usuarios registrados por mes'
                },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                },
                hover: {
                    mode: 'nearest',
                    intersect: true
                },
                scales: {
                    xAxes: [{
                        display: true,
                        scaleLabel: {
                            display: true,
                            labelString: 'Mes'
                        }
                    }],
                    yAxes: [{
                        display: true,
                        scaleLabel: {
                            display: true,
                            labelString: 'Cantidad'
                        }
                    }]
                }
            }
        });
        ctx = document.getElementById('grafico-usuarios-totales');
        chart = new Chart(ctx, {
            // The type of chart we want to create
            type: 'line',

            // The data for our dataset
            data: {
                labels: labels,
                datasets: [
                {
                        label: 'Usuarios',
                        backgroundColor: "#0F0",
                        borderColor: "#0F0",
                        data: data2,
                        fill: false,
                },
                ]
            },

            // Configuration options go here
            options: {
                responsive: true,
                title: {
                    display: true,
                    text: 'Usuarios totales registrados'
                },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                },
                hover: {
                    mode: 'nearest',
                    intersect: true
                },
                scales: {
                    xAxes: [{
                        display: true,
                        scaleLabel: {
                            display: true,
                            labelString: 'Mes'
                        }
                    }],
                    yAxes: [{
                        display: true,
                        scaleLabel: {
                            display: true,
                            labelString: 'Cantidad'
                        }
                    }]
                }
            }
        });
    }
    function crearGraficoViajes (labels, data) {
        var ctx = document.getElementById('grafico-viajes');
        var chart = new Chart(ctx, {
            // The type of chart we want to create
            type: 'line',

            // The data for our dataset
            data: {
                labels: labels,
                datasets: [{
                        label: 'Cantidad de viajes',
                        backgroundColor: "#F00",
                        borderColor: "#F00",
                        data: data,
                        fill: false,
                }]
            },

            // Configuration options go here
            options: {
                responsive: true,
                title: {
                    display: true,
                    text: 'Viajes de conductores en la plataforma'
                },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                },
                hover: {
                    mode: 'nearest',
                    intersect: true
                },
                scales: {
                    xAxes: [{
                        display: true,
                        scaleLabel: {
                            display: true,
                            labelString: 'Mes'
                        }
                    }],
                    yAxes: [{
                        display: true,
                        scaleLabel: {
                            display: true,
                            labelString: 'Cantidad'
                        }
                    }]
                }
            }
        });
    }
    function crearGraficoSolicitudes (labels, ocupados, noOcupados) {
        var ctx = document.getElementById('grafico-solicitudes');
        var chart = new Chart(ctx, {
            // The type of chart we want to create
            type: 'bar',

            // The data for our dataset
            data: {
                labels: labels,
                datasets: [{
                        label: 'Ocupados',
                        backgroundColor: "blue",
                        borderColor: "blue",
                        data: ocupados,
                        fill: false,
                    },{
                        label: 'No ocupados',
                        backgroundColor: "#F00",
                        borderColor: "#F00",
                        data: noOcupados,
                        fill: false,
                }]
            },

            // Configuration options go here
            options: {
                responsive: true,
                title: {
                    display: true,
                    text: 'Asientos'
                },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                },
                hover: {
                    mode: 'nearest',
                    intersect: true
                },
                scales: {
                    xAxes: [{
                        display: true,
                        scaleLabel: {
                            display: true,
                            labelString: 'Mes'
                        },
                        stacked: true
                    }],
                    yAxes: [{
                        display: true,
                        scaleLabel: {
                            display: true,
                            labelString: 'Cantidad'
                        },
                        stacked: true
                    }]
                }
            }
        });
    }

    function tableCreate(domId, columns, data, stop) {
        var body = document.getElementById(domId);
        var tbl = document.createElement('table');
        tbl.style.width = '100%';
        // tbl.setAttribute('border', '1');
        tbl.setAttribute('class', 'table table-bordered table-striped table-hover');
        // ---------------------------------
        var thead = document.createElement('thead');
        var tr = document.createElement('tr');
        for (var index = 0; index < columns.length; index++) {
            var col = columns[index];
            var th = document.createElement('th');
            th.appendChild(document.createTextNode(col.label));
            tr.appendChild(th);
        }
        thead.appendChild(tr);
        tbl.appendChild(thead);
        // ---------------------------------
        var tbdy = document.createElement('tbody');
        for (var i = 0; i < data.length; i++) {
            tr = document.createElement('tr');
            for (var index = 0; index < columns.length; index++) {
                var col = columns[index];
                var td = document.createElement('td');
                td.appendChild(document.createTextNode(data[i][col.key]));
                tr.appendChild(td);
            }
            tbdy.appendChild(tr);
            if (i > stop) break;
        }
        tbl.appendChild(tbdy);
        body.appendChild(tbl)
    }

    window.onload = function () {
        fetch('/data')
        .then((resp) => resp.json()) // Transform the data into json
        .then(function(data) {
            var etiquetas = [];
            var datos = [];
            var ocupados = [];
            var desocupados = [];
            if (data && data.viajes) {
                var arr = data.viajes.sort(function (a, b) {
                    if (a.key < b.key) {
                        return -1;
                    }
                    if (a.key > b.key) {
                        return 1;
                    }
                    return 0;
                });
                var date = new Date();
                var month = (date.getMonth() + 1) > 9 ? (date.getMonth() + 1) : ('0' + (date.getMonth() + 1));
                var stop = date.getFullYear() + '-' + month;
                for (var index = 0; index < arr.length; index++) {
                    var element = data.viajes[index];
                    if (element.key <= stop) {
                        for (var i = 0; i < data.solicitudes.length; i++) {
                            var solicitud = data.solicitudes[i];
                            if (solicitud.key === element.key && solicitud.state === 1) {
                                ocupados.push(solicitud.cantidad);
                                desocupados.push(parseFloat(element.asientos_ofrecidos_total) - solicitud.cantidad);
                                break;
                            }
                        }
                        etiquetas.push(element.key);
                        datos.push(element.cantidad);
                    } else {
                        break;
                    }
                    
                }
                crearGraficoViajes(etiquetas, datos);
                crearGraficoSolicitudes(etiquetas, ocupados, desocupados);
                var columns = [{
                    key: 'origen',
                    label: 'Origen'
                },{
                    key: 'destino',
                    label: 'Destino'
                },{
                    key: 'cantidad',
                    label: 'Cantidad'
                }];
                tableCreate('ranking-origen-destino', columns, data.frecuencia_origenes_destinos_posterior_ago_2017, 19);
                columns = [{
                    key: 'origen',
                    label: 'Origen'
                },{
                    key: 'cantidad',
                    label: 'Cantidad'
                }];
                tableCreate('ranking-origen', columns, data.frecuencia_origenes_posterior_ago_2017, 19);
                columns = [{
                    key: 'destino',
                    label: 'Destino'
                },{
                    key: 'cantidad',
                    label: 'Cantidad'
                }];
                tableCreate('ranking-destino', columns, data.frecuencia_destinos_posterior_ago_2017, 19);
            }
            if (data && data.usuarios) {
                console.log(data.usuarios);
                var labels = [];
                var dataset = [];
                var datasetTotales = [];
                var total = 0;
                data.usuarios.forEach(function (el) {
                    labels.push(el.key);
                    dataset.push(el.cantidad);
                    total += el.cantidad;
                    datasetTotales.push(total);
                });
                crearGraficoUsuarios(labels, dataset, datasetTotales);
            }

        });
    };

    /* setTimeout(function () {
        fetch('/more-data')
        .then((resp) => resp.json()) // Transform the data into json
        .then(function(data) {
            var columns = [{
                key: 'name',
                label: 'Nombre'
            },{
                key: 'drives',
                label: 'Cantidad'
            }];
            tableCreate('ranking-conductores', columns, data.ranking_conductores, 50);
            columns = [{
                key: 'name',
                label: 'Nombre'
            },{
                key: 'drives',
                label: 'Cantidad'
            }];
            tableCreate('ranking-pasajeros', columns, data.ranking_pasajeros, 50);
            columns = [{
                key: 'name',
                label: 'Nombre'
            },{
                key: 'rating',
                label: 'Cantidad'
            }];
            tableCreate('ranking-calificaciones', columns, data.ranking_calificaciones, 50);
        }); */
    }, 4000);
</script>
@endsection
