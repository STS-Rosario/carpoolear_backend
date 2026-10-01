<?php

namespace STS\Services\ContributionCheck;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Asks an LLM (through OpenRouter's OpenAI-compatible chat completions API)
 * whether a trip description asks for more than the max contribution per seat
 * and whether it contains a phone number.
 */
class OpenRouterContributionChecker
{
    private const DISABLED_REASONING_VALUES = ['', 'off', 'false', '0', 'no', 'none', 'disabled'];

    private const CURRENCIES_BY_LOCALE = [
        'arg' => 'ARS (pesos argentinos)',
        'chl' => 'CLP (pesos chilenos)',
    ];

    public const SYSTEM_PROMPT = <<<'PROMPT'
Sos un asistente de moderación de Carpoolear, una plataforma sin fines de lucro para compartir viajes en auto en Argentina y Chile. Los conductores publican viajes con una contribución máxima por asiento, pensada solo para compartir gastos (combustible, peajes). Está prohibido pedir en la descripción del viaje una contribución o precio por persona mayor a esa máxima, y también publicar números de teléfono en la descripción.
Analizá la descripción que te pasan y respondé únicamente con un objeto JSON válido, sin texto adicional ni bloques de código.
PROMPT;

    public function __construct(private readonly ContributionCheckResponseParser $parser) {}

    public function isConfigured(): bool
    {
        return trim((string) config('services.openrouter.api_key')) !== '';
    }

    /**
     * @throws ContributionCheckFailedException
     */
    public function check(string $description, int $maxSeatPriceCents): ContributionCheckResult
    {
        try {
            $response = Http::withToken((string) config('services.openrouter.api_key'))
                ->withHeaders([
                    'HTTP-Referer' => (string) config('app.url'),
                    'X-Title' => 'Carpoolear',
                ])
                ->acceptJson()
                ->timeout((int) config('services.openrouter.timeout', 30))
                ->post(
                    rtrim((string) config('services.openrouter.base_url'), '/').'/chat/completions',
                    $this->buildPayload($description, $maxSeatPriceCents)
                );
        } catch (ConnectionException $e) {
            throw new ContributionCheckFailedException('OpenRouter request failed: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new ContributionCheckHttpException(
                $response->status(),
                'OpenRouter responded with HTTP '.$response->status().': '.mb_substr($response->body(), 0, 500)
            );
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content)) {
            throw new InvalidContributionCheckResponseException('OpenRouter answer has no message content.');
        }

        return $this->parser->parse($content);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(string $description, int $maxSeatPriceCents): array
    {
        return [
            'model' => (string) config('services.openrouter.contribution_check.model'),
            'messages' => [
                ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                ['role' => 'user', 'content' => $this->buildUserPrompt($description, $maxSeatPriceCents)],
            ],
            'temperature' => 0,
            'response_format' => ['type' => 'json_object'],
            'reasoning' => $this->reasoningConfig(),
        ];
    }

    private function buildUserPrompt(string $description, int $maxSeatPriceCents): string
    {
        $maxLine = $maxSeatPriceCents > 0
            ? 'Contribución máxima permitida por asiento (por persona): '.$this->formatAmount($maxSeatPriceCents).$this->currencySuffix().'.'
            : 'El viaje no tiene una contribución máxima definida (aporte voluntario): en ese caso "exceeds_max" debe ser false.';

        return <<<PROMPT
{$maxLine}

Descripción del viaje (entre las etiquetas <descripcion>):
<descripcion>
{$description}
</descripcion>

Respondé:
a) ¿La descripción pide una contribución o precio por persona mayor a la contribución máxima permitida? Extraé el monto por persona sospechado, en la misma moneda y como número (por ejemplo "$24.000", "24k" o "24 lucas" son 24000). Si no se menciona ningún monto que se le pida a los pasajeros, usá null.
b) ¿La descripción contiene un número de teléfono, aunque esté ofuscado (dígitos separados por espacios, puntos o guiones, números escritos con palabras como "tres cuatro uno", mezcla de letras y dígitos, prefijos como +54, +56, 0341 o 15)?

Respondé solo con este JSON estricto:
{"suspected_contribution": number|null, "exceeds_max": boolean, "phone_in_description": boolean}
PROMPT;
    }

    /**
     * @return array<string, bool|string>
     */
    private function reasoningConfig(): array
    {
        $reasoning = strtolower(trim((string) config('services.openrouter.contribution_check.reasoning', 'off')));

        if (in_array($reasoning, self::DISABLED_REASONING_VALUES, true)) {
            return ['enabled' => false];
        }

        return ['effort' => $reasoning];
    }

    private function formatAmount(int $cents): string
    {
        $formatted = number_format($cents / 100, 2, '.', '');

        return str_ends_with($formatted, '.00') ? substr($formatted, 0, -3) : $formatted;
    }

    private function currencySuffix(): string
    {
        $currency = self::CURRENCIES_BY_LOCALE[(string) config('app.locale')] ?? null;

        return $currency === null ? '' : ' '.$currency;
    }
}
