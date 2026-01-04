<?php

namespace EasyDoc\Domain\CodeExamples;

/**
 * Generate curl command examples for API endpoints.
 */
class CurlGenerator
{
    /**
     * Generate a curl command for an API endpoint.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url Full URL or path
     * @param array $headers Request headers ['Header-Name' => 'value']
     * @param array|null $body Request body (for POST, PUT, PATCH)
     * @param array $queryParams Query parameters
     * @return string Formatted curl command
     */
    public static function generate(
        string $method,
        string $url,
        array $headers = [],
        ?array $body = null,
        array $queryParams = []
    ): string {
        $curl = "curl -X {$method}";

        // Add query params to URL
        if (!empty($queryParams)) {
            $queryString = http_build_query($queryParams);
            $url .= (str_contains($url, '?') ? '&' : '?') . $queryString;
        }

        // Add URL (quoted for special chars)
        $curl .= " \"{$url}\"";

        // Add headers
        foreach ($headers as $name => $value) {
            $curl .= " \\\n  -H \"{$name}: {$value}\"";
        }

        // Add body for non-GET requests
        if ($body !== null && !in_array(strtoupper($method), ['GET', 'HEAD', 'DELETE'])) {
            $jsonBody = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            // Escape for shell
            $jsonBody = str_replace("'", "'\\''", $jsonBody);
            $curl .= " \\\n  -d '{$jsonBody}'";
        }

        return $curl;
    }

    /**
     * Generate curl with common defaults.
     */
    public static function generateWithDefaults(
        string $method,
        string $baseUrl,
        string $path,
        array $headers = [],
        ?array $body = null
    ): string {
        $defaultHeaders = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $allHeaders = array_merge($defaultHeaders, $headers);
        $fullUrl = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');

        return self::generate($method, $fullUrl, $allHeaders, $body);
    }
}
