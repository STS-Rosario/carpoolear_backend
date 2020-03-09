Hola, {{$user->name}}!
<br>
Hemos encontrado un viaje que coincide con tu búsqueda.
<br>
<a href="{{$url}}">Puedes ver el detalle de las coincidencias entrando en este link.</a>
<br>
Saludos!
<br>
{{$name_app}}
<br>
<br>
<small style="color: red;">Si no deseás recibir más este tipo de correo, <a href="{{ $domain }}/desuscribirme?email={{ $user->email }}">hacé click aquí</a>.</small>
