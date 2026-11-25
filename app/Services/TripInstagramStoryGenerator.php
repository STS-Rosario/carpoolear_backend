<?php

namespace STS\Services;

use Carbon\Carbon;
use RuntimeException;
use STS\Models\Trip;

class TripInstagramStoryGenerator
{
    private const WIDTH = 1080;

    private const HEIGHT = 1920;

    private const FONT = __DIR__.'/../../public/fonts/Roboto-Variable.ttf';

    public function generate(Trip $trip): string
    {
        if (! function_exists('imagettftext')) {
            throw new RuntimeException('GD with FreeType support is required to generate trip stories.');
        }
        if (! is_file(self::FONT)) {
            throw new RuntimeException('The Roboto font file required to generate trip stories is missing.');
        }

        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if (! $image) {
            throw new RuntimeException('Could not create the trip story image.');
        }

        imagealphablending($image, true);
        $colors = $this->colors($image);

        imagefill($image, 0, 0, $colors['blue']);
        $this->drawRoundedRectangle($image, 72, 242, 1008, 356, 34, $colors['blue_light']);
        $this->drawMegaphone($image, 336, 286, $colors['white']);
        $this->drawText($image, $trip->is_passenger ? 'Busco viaje' : 'Busco pasajeros', 390, 324, 42, $colors['white'], true);

        $this->drawRoundedRectangle($image, 72, 390, 1008, 1395, 36, $colors['white']);
        $this->drawRoute($image, $trip, $colors);
        $this->drawTripDate($image, $trip, $colors);
        $this->drawSeats($image, $trip, $colors);
        $this->drawDriver($image, $trip, $colors);
        $this->drawFooter($image, $colors);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        if (! is_string($png) || $png === '') {
            throw new RuntimeException('Could not encode the trip story image.');
        }

        return $png;
    }

    private function colors($image): array
    {
        return [
            'blue' => imagecolorallocate($image, 35, 101, 159),
            'blue_light' => imagecolorallocate($image, 79, 143, 198),
            'blue_dark' => imagecolorallocate($image, 39, 93, 141),
            'blue_pale' => imagecolorallocate($image, 235, 244, 251),
            'white' => imagecolorallocate($image, 255, 255, 255),
            'black' => imagecolorallocate($image, 43, 48, 54),
            'gray' => imagecolorallocate($image, 115, 123, 130),
            'line' => imagecolorallocate($image, 222, 228, 233),
            'green' => imagecolorallocate($image, 35, 147, 91),
            'green_pale' => imagecolorallocate($image, 226, 244, 234),
            'yellow' => imagecolorallocate($image, 191, 131, 30),
            'yellow_pale' => imagecolorallocate($image, 252, 242, 218),
            'red' => imagecolorallocate($image, 196, 69, 69),
            'red_pale' => imagecolorallocate($image, 251, 231, 231),
            'gray_pale' => imagecolorallocate($image, 237, 239, 241),
        ];
    }

    private function drawRoute($image, Trip $trip, array $colors): void
    {
        $points = $trip->points->sortBy('id')->values();
        $origin = $points->first();
        $destination = $points->last();
        $originCity = $this->pointCity($origin, $trip->from_town ?: 'Origen');
        $destinationCity = $this->pointCity($destination, $trip->to_town ?: 'Destino');
        $originProvince = $this->pointProvince($origin);
        $destinationProvince = $this->pointProvince($destination);

        imagefilledellipse($image, 158, 534, 30, 30, $colors['green']);
        $this->drawLine($image, 158, 549, 158, 716, $colors['line'], 6);
        imagefilledellipse($image, 158, 731, 30, 30, $colors['blue']);

        $this->drawFittedText($image, $originCity, 216, 548, 66, 740, $colors['black'], true);
        $this->drawFittedText($image, $originProvince, 216, 596, 34, 740, $colors['gray']);
        $this->drawFittedText($image, $destinationCity, 216, 748, 66, 740, $colors['black'], true);
        $this->drawFittedText($image, $destinationProvince, 216, 796, 34, 740, $colors['gray']);
        $this->drawLine($image, 126, 843, 954, 843, $colors['line'], 2);
    }

    private function drawTripDate($image, Trip $trip, array $colors): void
    {
        $date = $trip->trip_date ? Carbon::parse($trip->trip_date) : null;
        $weeklyTime = $trip->weekly_schedule_time ? Carbon::parse($trip->weekly_schedule_time) : null;
        $dateLabel = $date
            ? mb_convert_case(trim(str_replace('.', '', $date->locale('es')->translatedFormat('D j M'))), MB_CASE_TITLE, 'UTF-8')
            : 'Viaje recurrente';
        $timeLabel = $date
            ? $date->format('H:i').' hs'
            : ($weeklyTime ? $weeklyTime->format('H:i').' hs' : '');

        $this->drawRoundedRectangle($image, 126, 880, 542, 978, 18, $colors['blue_pale']);
        $this->drawCalendarIcon($image, 151, 907, $colors['blue_dark']);
        $this->drawFittedText($image, $dateLabel, 202, 943, 29, 325, $colors['blue_dark'], true);

        if ($timeLabel !== '') {
            $this->drawRoundedRectangle($image, 568, 880, 954, 978, 18, $colors['blue_pale']);
            $this->drawClockIcon($image, 591, 903, $colors['blue_dark']);
            $this->drawText($image, $timeLabel, 636, 943, 30, $colors['blue_dark'], true);
        }

        $this->drawLine($image, 126, 1010, 954, 1010, $colors['line'], 2);
    }

    private function drawSeats($image, Trip $trip, array $colors): void
    {
        $availableSeats = max(0, (int) $trip->seats_available);
        [$tone, $paleTone] = match (true) {
            $availableSeats === 0 => ['gray', 'gray_pale'],
            $availableSeats === 1 => ['red', 'red_pale'],
            $availableSeats === 2 => ['yellow', 'yellow_pale'],
            default => ['green', 'green_pale'],
        };

        imagefilledellipse($image, 178, 1094, 88, 88, $colors[$paleTone]);
        $this->drawPeopleIcon($image, 178, 1094, $colors[$tone]);

        if ($trip->is_passenger) {
            $this->drawText($image, 'Busco un viaje', 246, 1085, 40, $colors['black'], true);
        } else {
            $count = (string) $availableSeats;
            $this->drawText($image, $count, 246, 1081, 60, $colors[$tone], true);
            $this->drawText($image, $availableSeats === 1 ? 'LUGAR LIBRE' : 'LUGARES LIBRES', 302, 1080, 27, $colors['black'], true);
        }

        $this->drawLine($image, 126, 1150, 954, 1150, $colors['line'], 2);
    }

    private function drawDriver($image, Trip $trip, array $colors): void
    {
        $driver = $trip->user;
        $name = $driver ? $this->shortName((string) $driver->name) : 'Conductor/a';
        $initials = $this->initials($name);

        imagefilledellipse($image, 188, 1236, 112, 112, $colors['blue_pale']);
        $this->drawCenteredText($image, $initials, 188, 1248, 36, $colors['blue_dark'], true);
        $this->drawText($image, 'CONDUCE', 270, 1207, 21, $colors['gray'], true);
        $this->drawFittedText($image, $name, 270, 1253, 37, 445, $colors['black'], true);

        if ($driver && ($driver->driver_is_verified || $driver->identity_validated_at)) {
            $this->drawRoundedRectangle($image, 740, 1184, 954, 1232, 18, $colors['green_pale']);
            imagefilledellipse($image, 762, 1208, 22, 22, $colors['green']);
            $this->drawLine($image, 756, 1208, 760, 1212, $colors['white'], 2);
            $this->drawLine($image, 760, 1212, 768, 1203, $colors['white'], 2);
            $this->drawText($image, 'VERIFICADO', 779, 1216, 17, $colors['green'], true);
        }

        $ratings = $driver
            ? sprintf('%d  +     %d  =     %d  -', (int) $driver->positive_ratings, (int) $driver->neutral_ratings, (int) $driver->negative_ratings)
            : '0  +     0  =     0  -';
        $tripsCount = $driver ? (int) ($driver->trips_count ?? 0) : 0;
        $this->drawFittedText($image, $ratings.'     ·     '.$tripsCount.' viajes', 270, 1308, 23, 680, $colors['gray']);
    }

    private function drawFooter($image, array $colors): void
    {
        imagefilledellipse($image, 539, 1508, 112, 112, $colors['white']);
        $logoPath = public_path('img/carpoolear_logo_square.png');
        if (! is_file($logoPath)) {
            throw new RuntimeException('The Carpoolear logo required to generate trip stories is missing.');
        }

        $logo = imagecreatefrompng($logoPath);
        if (! $logo) {
            throw new RuntimeException('The Carpoolear logo could not be loaded for the trip story.');
        }
        imagecopyresampled($image, $logo, 501, 1470, 0, 0, 76, 76, imagesx($logo), imagesy($logo));
        imagedestroy($logo);

        $this->drawCenteredText($image, 'Sumate a este viaje', 540, 1640, 39, $colors['white'], true);
        $this->drawCenteredText($image, 'carpoolear.com.ar', 540, 1701, 28, $colors['white']);
        $this->drawCenteredText($image, 'Disponible en Android e iOS', 540, 1745, 23, $colors['white']);
    }

    private function drawCalendarIcon($image, int $x, int $y, int $color): void
    {
        imagerectangle($image, $x, $y, $x + 25, $y + 23, $color);
        $this->drawLine($image, $x, $y + 7, $x + 25, $y + 7, $color, 2);
        $this->drawLine($image, $x + 6, $y - 4, $x + 6, $y + 4, $color, 3);
        $this->drawLine($image, $x + 19, $y - 4, $x + 19, $y + 4, $color, 3);
    }

    private function drawClockIcon($image, int $x, int $y, int $color): void
    {
        imageellipse($image, $x + 14, $y + 14, 28, 28, $color);
        $this->drawLine($image, $x + 14, $y + 14, $x + 14, $y + 5, $color, 2);
        $this->drawLine($image, $x + 14, $y + 14, $x + 21, $y + 18, $color, 2);
    }

    private function drawPeopleIcon($image, int $centerX, int $centerY, int $color): void
    {
        imagefilledellipse($image, $centerX - 10, $centerY - 12, 16, 16, $color);
        imagefilledellipse($image, $centerX + 10, $centerY - 12, 16, 16, $color);
        imagefilledarc($image, $centerX - 10, $centerY + 10, 30, 25, 180, 360, $color, IMG_ARC_PIE);
        imagefilledarc($image, $centerX + 10, $centerY + 10, 30, 25, 180, 360, $color, IMG_ARC_PIE);
    }

    private function drawMegaphone($image, int $x, int $y, int $color): void
    {
        imagefilledpolygon($image, [$x, $y + 10, $x + 40, $y, $x + 40, $y + 34, $x, $y + 24], $color);
        imagefilledrectangle($image, $x - 8, $y + 11, $x + 2, $y + 23, $color);
        $this->drawLine($image, $x + 15, $y + 24, $x + 20, $y + 42, $color, 5);
    }

    private function drawLine($image, int $x1, int $y1, int $x2, int $y2, int $color, int $thickness = 1): void
    {
        imagesetthickness($image, $thickness);
        imageline($image, $x1, $y1, $x2, $y2, $color);
        imagesetthickness($image, 1);
    }

    private function drawRoundedRectangle($image, int $left, int $top, int $right, int $bottom, int $radius, int $color): void
    {
        imagefilledrectangle($image, $left + $radius, $top, $right - $radius, $bottom, $color);
        imagefilledrectangle($image, $left, $top + $radius, $right, $bottom - $radius, $color);
        imagefilledellipse($image, $left + $radius, $top + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $right - $radius, $top + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $left + $radius, $bottom - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $right - $radius, $bottom - $radius, $radius * 2, $radius * 2, $color);
    }

    private function drawText($image, string $text, int $x, int $y, int $size, int $color, bool $bold = false): void
    {
        if ($text === '') {
            return;
        }

        imagettftext($image, $size, 0, $x, $y, $color, self::FONT, $text);
        if ($bold) {
            imagettftext($image, $size, 0, $x + 1, $y, $color, self::FONT, $text);
        }
    }

    private function drawFittedText($image, string $text, int $x, int $y, int $size, int $maxWidth, int $color, bool $bold = false): void
    {
        $text = $this->safeText($text);
        while ($size > 18 && $this->textWidth($text, $size) > $maxWidth) {
            $size -= 2;
        }
        $this->drawText($image, $text, $x, $y, $size, $color, $bold);
    }

    private function drawCenteredText($image, string $text, int $centerX, int $y, int $size, int $color, bool $bold = false): void
    {
        $text = $this->safeText($text);
        $this->drawText($image, $text, (int) ($centerX - $this->textWidth($text, $size) / 2), $y, $size, $color, $bold);
    }

    private function textWidth(string $text, int $size): float
    {
        $bounds = imagettfbbox($size, 0, self::FONT, $text);

        return (float) ($bounds[2] - $bounds[0]);
    }

    private function pointCity($point, string $fallback): string
    {
        $address = $point?->json_address ?? [];

        return $this->safeText((string) ($address['ciudad'] ?? $address['name'] ?? $fallback));
    }

    private function pointProvince($point): string
    {
        $address = $point?->json_address ?? [];

        return $this->safeText((string) ($address['provincia'] ?? $address['state'] ?? ''));
    }

    private function shortName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if (! $parts) {
            return 'Conductor/a';
        }

        return count($parts) > 1
            ? $parts[0].' '.mb_strtoupper(mb_substr($parts[1], 0, 1)).'.'
            : $parts[0];
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        $initials = array_map(fn ($part) => mb_substr($part, 0, 1), $parts ?: []);

        return mb_strtoupper(implode('', array_slice($initials, 0, 2))) ?: 'C';
    }

    private function safeText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
    }
}
