<?php

namespace STS\Support;

final class SupportTicketAutoCloseMessage
{
    public const MARKDOWN = <<<'MARKDOWN'
Buenas %s

Te escribimos para avisarte que no hemos tenido novedades por este ticket
Así que vamos cerrarlo para mantener en orden la mesa de ayuda
No te preocupes, cualquier cosa nos podes abrir un nuevo ticket, sea por este o algún otro tema.
Abrazo!
Equipo Carpoolear
_Carpoolear es un proyecto de gestión colectiva sin fines de lucro y código libre de la asociación civil STS Rosario. Vos también _podés sumar :D
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
