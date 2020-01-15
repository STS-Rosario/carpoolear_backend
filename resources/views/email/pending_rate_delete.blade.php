Hola {{$user->name}}! </br>

{{$from->name}} ha eliminado el viaje hacia {{$trip->to_town}}. Puedes calificar su desición.</br>

<a href="{{$url}}">Calificar</a> </br>

Saludos! </br>

{{$name_app}} </br>
<br>
<br>
<small style="color: red;">Si no deseás recibir más este tipo de correo, <a href="{{ $domain }}/desuscribirme?email={{ $user->email }}">hacé click aquí</a>.</small>