<?php

namespace STS\Services;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StaticPageContentService
{
    private const PAGES = [
        'faq' => 'static-pages.plataforma-preguntas-frecuentes',
        'division-de-gastos' => 'static-pages.division-de-gastos',
        'verificacion-cuenta' => 'static-pages.verificacion-cuenta',
    ];

    public function get(string $slug): object
    {
        $view = self::PAGES[$slug] ?? null;
        if ($view === null) {
            throw new NotFoundHttpException;
        }

        return (object) [
            'content' => view($view)->render(),
        ];
    }
}
