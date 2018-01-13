Hola {{$user->name}}! </br>

{{$from->name}} ha eliminado el viaje hacia {{$trip->to_town}}. Puedes calificar su desición.</br>

<a href="{{$url}}">Calificar</a> </br>

Saludos! </br>

Carpoolear </br>
<br>
<br>
<small>Si no deseás recibir más este tipo de correo, <a href="https://carpoolear.com.ar/desuscribirme?email={{ $user->email }}">hacé click aquí</a>.</small>