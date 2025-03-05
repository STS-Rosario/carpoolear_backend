<?php

use Illuminate\Support\Facades\Mail;

function ssmtp_send_mail ($subject, $to, $body) {
    
    \Log::info('ssmtp_send_mail: START');

    Mail::send([], [], function ($message) use ($cuerpo) {
        $message->to($to)
                 ->subject($subject);
                 ->setBody($body, 'text/html');

 

    // Verificar el resultado

    // Opcional: Eliminar el archivo de mensaje despu  s de enviar el correo
    // unlink($archivoMensaje);

}