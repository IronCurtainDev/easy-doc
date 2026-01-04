<?php

namespace EasyDoc\Domain\CodeExamples;

/**
 * Generate JavaScript fetch code examples for API endpoints.
 */
class FetchGenerator
{
    /**
     * Generate a JavaScript fetch code example for an API endpoint.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url Full URL or path
     * @param array $headers Request headers
     * @param array|null $body Request body
     * @param bool $useAsync Use async/await syntax
     * @return string JavaScript fetch code
     */
    public static function generate(
        string $method,
        string $url,
        array $headers = [],
        ?array $body = null,
        bool $useAsync = true
    ): string {
        $method = strtoupper($method);

        if ($useAsync) {
            return self::generateAsync($method, $url, $headers, $body);
        }

        return self::generatePromise($method, $url, $headers, $body);
    }

    /**
     * Generate async/await syntax.
     */
    protected static function generateAsync(
        string $method,
        string $url,
        array $headers,
        ?array $body
    ): string {
        $code = "const response = await fetch('{$url}', {\n";
        $code .= "  method: '{$method}',\n";

        // Headers
        $defaultHeaders = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        $allHeaders = array_merge($defaultHeaders, $headers);

        $code .= "  headers: {\n";
        $headerLines = [];
        foreach ($allHeaders as $name => $value) {
            $headerLines[] = "    '{$name}': '{$value}'";
        }
        $code .= implode(",\n", $headerLines) . "\n";
        $code .= "  }";

        // Body
        if ($body !== null && !in_array($method, ['GET', 'HEAD', 'DELETE'])) {
            $jsonBody = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            // Indent the JSON
            $jsonBody = preg_replace('/^/m', '  ', $jsonBody);
            $code .= ",\n  body: JSON.stringify(" . trim($jsonBody) . ")";
        }

        $code .= "\n});\n\n";
        $code .= "const data = await response.json();\n";
        $code .= "console.log(data);";

        return $code;
    }

    /**
     * Generate Promise .then() syntax.
     */
    protected static function generatePromise(
        string $method,
        string $url,
        array $headers,
        ?array $body
    ): string {
        $code = "fetch('{$url}', {\n";
        $code .= "  method: '{$method}',\n";

        // Headers
        $defaultHeaders = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        $allHeaders = array_merge($defaultHeaders, $headers);

        $code .= "  headers: {\n";
        $headerLines = [];
        foreach ($allHeaders as $name => $value) {
            $headerLines[] = "    '{$name}': '{$value}'";
        }
        $code .= implode(",\n", $headerLines) . "\n";
        $code .= "  }";

        // Body
        if ($body !== null && !in_array($method, ['GET', 'HEAD', 'DELETE'])) {
            $jsonBody = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $jsonBody = preg_replace('/^/m', '  ', $jsonBody);
            $code .= ",\n  body: JSON.stringify(" . trim($jsonBody) . ")";
        }

        $code .= "\n})\n";
        $code .= "  .then(response => response.json())\n";
        $code .= "  .then(data => console.log(data))\n";
        $code .= "  .catch(error => console.error('Error:', error));";

        return $code;
    }

    /**
     * Generate axios code as alternative.
     */
    public static function generateAxios(
        string $method,
        string $url,
        array $headers = [],
        ?array $body = null
    ): string {
        $method = strtolower($method);

        $code = "const response = await axios.{$method}('{$url}'";

        // For POST/PUT/PATCH, body is second argument
        if ($body !== null && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            $jsonBody = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $code .= ", " . $jsonBody;
        }

        // Config with headers
        if (!empty($headers)) {
            $config = "{\n  headers: {\n";
            $headerLines = [];
            foreach ($headers as $name => $value) {
                $headerLines[] = "    '{$name}': '{$value}'";
            }
            $config .= implode(",\n", $headerLines) . "\n  }\n}";
            $code .= ", {$config}";
        }

        $code .= ");\n\nconsole.log(response.data);";

        return $code;
    }
}
