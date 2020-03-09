Hola {{$user->name}}! </br>
</br>
Te informamos que {{$from->name}} ha cambiado las condiciones de su viaje hacia {{$trip->to_town}}.</br>
</br>
Click <a href={{$url}}>aquí</a> para ver los nuevos cambios.</br>
</br>
Saludos!</br>
</br>
{{$name_app}}
<br>
<br>
<small style="color: red;">Si no deseás recibir más este tipo de correo, <a href="{{ $domain }}/desuscribirme?email={{ $user->email }}">hacé click aquí</a>.</small>
