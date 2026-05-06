<?php

namespace STS\Services\HttpClients;

use Facebook\HttpClients\FacebookCurlHttpClient;

class DevCurlHttpClient extends FacebookCurlHttpClient
{
    /**
     * Override the cURL options to disable SSL verification in development
     */
    public function openConnection($url, $method, $body, array $headers, $timeOut)
    {
        parent::openConnection($url, $method, $body, $headers, $timeOut);

        // Try to find the cURL handle using reflection
        $reflection = new \ReflectionClass($this);

        // Look for common cURL handle property names
        $possibleProperties = ['curlHandle', 'curl', 'handle', 'resource'];
        $curlHandle = null;

        foreach ($possibleProperties as $propName) {
            try {
                $property = $reflection->getProperty($propName);
                $property->setAccessible(true);
                $curlHandle = $property->getValue($this);
                break;
            } catch (\ReflectionException $e) {
                // Property doesn't exist, try next one
                continue;
            }
        }

        DevFacebookCurlSsl::applyInsecureSslOrLogMissing($curlHandle);
    }
}
