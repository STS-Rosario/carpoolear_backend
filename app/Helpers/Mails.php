<?php
function ssmtp_send_mail ($subject, $to, $body) {
    
    \Log::info('ssmtp_send_mail: START');
    $destinatario = $to;
    $mensaje = 'From: Carpoolear<contacto@carpoolear.com.ar> 
Subject: ' . $subject . '
MIME-Version: 1.0
Content-type: text/html; charset=UTF-8
    '. $body .'
    ';

    // Crear el archivo de mensaje
    $archivoMensaje = storage_path() . '/mensaje.txt';
    file_put_contents($archivoMensaje, $mensaje);

    // Construir el comando ssmtp
    $comando = "ssmtp -v $destinatario < $archivoMensaje";
    \Log::info('ssmtp_send_mail: ' . $comando);

    // Ejecutar el comando
    $ouput = [];
    $result_code = 0;
    $resultado = exec($comando, $ouput, $result_code);
    \Log::info($resultado);
    \Log::info($result_code);
    \Log::info(json_encode($ouput));
    \Log::info('ssmtp_send_mail: END');

    // Verificar el resultado

    // Opcional: Eliminar el archivo de mensaje despu  s de enviar el correo
    // unlink($archivoMensaje);

}