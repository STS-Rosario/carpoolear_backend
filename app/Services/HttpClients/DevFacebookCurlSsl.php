<?php

namespace STS\Services\HttpClients;

/**
 * Disables SSL verification on a libcurl handle for local/dev Facebook Graph usage.
 */
final class DevFacebookCurlSsl
{
    public static function isUsableCurlHandle(mixed $handle): bool
    {
        return $handle instanceof \CurlHandle;
    }

    /**
     * @param  \CurlHandle|null  $curlHandle
     */
    public static function applyInsecureSslOrLogMissing(mixed $curlHandle): void
    {
        if (self::isUsableCurlHandle($curlHandle)) {
            curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curlHandle, CURLOPT_CAINFO, false);
            curl_setopt($curlHandle, CURLOPT_CAPATH, false);
        } else {
            \Log::warning('Could not find cURL handle in DevCurlHttpClient');
        }
    }
}
