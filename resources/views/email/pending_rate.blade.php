Hola {{$user->name}}!
<br>
Acaba de finalizar el viaje hacia {{$trip->to_town}}. Dejanos una breve reseña sobre la experiencia con tus 
compañeros de viaje.
<br>
<a href="{{$url}}">Calificar</a>
<br>
Saludos!
<br>
Carpoolear
<br>
<br>
<small style="color: red;">Si no deseás recibir más este tipo de correo, <a href="https://carpoolear.com.ar/desuscribirme?email={{ $user->email }}">hacé click aquí</a>.</small>