<?php

use Carbon\Carbon;

function parse_date($srt, $format = 'Y-m-d')
{
    return Carbon::createFromFormat($format, $srt);
}

function date_to_string($date, $format = 'Y-m-d')
{
    return $date->format($format);
}
