<?php

namespace STS\Support;

class TripExcessContributionStatus
{
    public const PENDIENTE = 'pendiente';

    public const RESUELTO = 'resuelto';

    public const DESCARTADO = 'descartado';

    public const EN_PROCESO = 'en_proceso';

    /** @var list<string> */
    public const ALL = [
        self::PENDIENTE,
        self::RESUELTO,
        self::DESCARTADO,
        self::EN_PROCESO,
    ];

    public static function validationRule(): string
    {
        return 'required|in:'.implode(',', self::ALL);
    }
}
