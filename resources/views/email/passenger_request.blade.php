Hola {{$user->name}}!
<br>
Te informamos que {{$from->name}} desea subirse a tu viaje hacia {{$trip->to_town}}.
<br>
Click <a href={{$url}}>aquí</a> para ver tus solicitudes.
<br>
Saludos!
<br>
Carpoolear
<br>
<br>
<small>Si no deseás recibir más este tipo de correo, <a href="https://carpoolear.com.ar/desuscribirme?email={{ $user->email }}">hacé click aquí</a>.</small>