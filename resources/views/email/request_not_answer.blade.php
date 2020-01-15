Hola {{$user->name}},
<br>
Te avisamos que tu solicitud para subirte al viaje hacia {{$trip->to_town}}, aún no fue contestada por su chofer.
<br>
Click <a href={{$url}}>aquí</a> si quieres ver más detalles del viaje.
<br>
Saludos!
<br>
{{$name_app}}
<br>
<br>
<small style="color: red;">Si no deseás recibir más este tipo de correo, <a href="{{ $domain }}/desuscribirme?email={{ $user->email }}">hacé click aquí</a>.</small>