Hola {{$user->name}}!
<br>
{{$from->name}} desea ser tu amigo.
<br>
Click <a href={{$url}}>aquí</a> si deseas ver su solicitud de amistad.
<br>
Saludos!
<br>
{{$name_app}}
<br>
<br>
<small style="color: red;">Si no deseás recibir más este tipo de correo, <a href="{{ $domain }}/desuscribirme?email={{ $user->email }}">hacé click aquí</a>.</small>
