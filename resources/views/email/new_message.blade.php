Hola {{$user->name}}!
<br>
<br>
{{$from->name}} te ha enviado un nuevo mensaje.
<br>
Haz click <a href="{{$url}}">aquí</a> para leerlo.
<br>
<br>
Saludos!
<br>
Carpoolear
<br>
<br>
<small style="color: red;">Si no deseás recibir más este tipo de correo, <a href="https://carpoolear.com.ar/desuscribirme?email={{ $user->email }}">hacé click aquí</a>.</small>