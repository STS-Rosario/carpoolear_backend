<?php

namespace STS\Services\HttpClients;

/**
 * Disables SSL verification on a libcurl handle for local/dev Facebook Graph usage.
 */
final class DevFacebookCurlSsl
{
    /**
     * PHP 8+ returns {@see \CurlHandle} objects from curl_init(); older PHP used resources.
     */
    public static function isUsableCurlHandle(mixed $handle): bool
    {
        if ($handle === null) {
            return false;
        }

        if (is_resource($handle) && get_resource_type($handle) === 'curl') {
            return true;
        }

        return $handle instanceof \CurlHandle;
    }

    /**
     * @param  \CurlHandle|resource|null  $curlHandle
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
