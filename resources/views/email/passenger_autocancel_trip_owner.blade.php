Hola {{$user->name}}!
<br>
Te informamos que se ha retirado automáticamente una solicitud que realizó {{$from->name}} a tu viaje con destino a {{$trip->to_town}} debido a que el pasajero se subió a otro viaje con igual destino.
<br>
Saludos!
<br>
{{$name_app}}
<br>
<br>
<small style="color: red;">Si no deseás recibir más este tipo de correo, <a href="{{ $domain }}/desuscribirme?email={{ $user->email }}">hacé click aquí</a>.</small>