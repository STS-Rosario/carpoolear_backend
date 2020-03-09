Hola {{$user->name}}!
<br>
Te recordamos que tenés solicitudes pendientes por contestar en el viaje hacia {{$trip->to_town}}.
<br>
Haz click <a href="{{$url}}">aquí</a> para ver tus soliciutdes.
<br>
Saludos!
<br>
{{$name_app}}
<br>
<br>
<small style="color: red;">Si no deseás recibir más este tipo de correo, <a href="{{ $domain }}/desuscribirme?email={{ $user->email }}">hacé click aquí</a>.</small>
