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
{{$name_app}}
<br>
<br>
<small style="color: red;">Si no deseás recibir más este tipo de correo, <a href="{{ $domain }}/desuscribirme?email={{ $user->email }}">hacé click aquí</a>.</small>