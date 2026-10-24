<?php

namespace STS\Support;

final class SupportTicketAutoCloseMessage
{
    public const MARKDOWN = <<<'MARKDOWN'
Buenas %s

Te escribimos para avisarte que no hemos tenido novedades en este ticket, así que vamos a cerrarlo para mantener en orden la mesa de ayuda.

No te preocupes: si todavía necesitás ayuda con esto, podés responder este ticket y lo volvemos a abrir sin problema.

¡Abrazo!
Equipo Carpoolear

[Carpoolear es un proyecto de gestión colectiva, sin fines de lucro y [código libre](https://github.com/sts-rosario) de la asociación civil [STS Rosario](https://stsrosario.org.ar/) en marcha desde el 2013. Vos también podés [sumarte](https://carpoolear.com.ar/colabora-como-colaborar) o [APORTAR](https://carpoolear.com.ar/aportar) para que siga adelante :D]
MARKDOWN;

    public static function forUser(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            $name = 'de nuevo';
        }

        return sprintf(self::MARKDOWN, $name);
    }
}
