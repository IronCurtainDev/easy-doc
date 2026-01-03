<?php

namespace EasyDoc\Docs;

use EasyDoc\Exceptions\DocumentationModeEnabledException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * DocBuilder collects and manages API documentation.
 */
class DocBuilder
{
    protected Collection $apiCalls;
    protected array $attributes = [];

    public function __construct()
    {
        $this->apiCalls = new Collection();
    }

    public function reset(): void
    {
        $this->apiCalls = new Collection();
    }

    /**
     * Register an API Call with the Doc Builder.
     */
    public function register(APICall $apiCall): void
    {
        if (!env('DOCUMENTATION_MODE', false)) {
            return;
        }

        // Set defines or uses
        $define = $apiCall->getDefine();
        if (!empty($define)) {
            $group = $apiCall->getGroup();
            if (empty($group)) {
                $apiCall->setGroup(Str::snake($define['title']));
            }
            $this->apiCalls->push($apiCall);
            return;
        } else {
            if ($apiCall->isAddDefaultHeaders()) {
                $apiCall->setUse('default_headers');
            }
        }

        if (empty($apiCall->getRoute())) {
            if (!empty($this->attributes['uri'])) {
                $apiCall->setRoute($this->attributes['uri']);
            } else {
                throw new \Exception("The route must be set for the API call");
            }
        }

        if (empty($apiCall->getMethod()) || $apiCall->getMethod() === 'GET') {
            if (!empty($this->attributes['method'])) {
                $apiCall->setMethod($this->attributes['method']);
            }
        }

        // Set default group
        $group = $apiCall->getGroup();
        if (empty($group)) {
            // Get the full controller name and extract the Prefix from {Prefix}Controller as the default group
            if (isset($this->attributes['action'])) {
                $parts = explode('@', $this->attributes['action']);
                $reflection = new \ReflectionClass($parts[0]);
                if ($reflection) {
                    $group = str_replace('Controller', '', $reflection->getShortName());
                    $apiCall->setGroup($group);
                }
            }
        }

        // Try to set a default name
        $name = $apiCall->getName();
        if (empty($name)) {
            $singularGroup = Str::singular($group ?? 'Item');
            $method = strtolower($apiCall->getMethod());
            $newName = match ($method) {
                'post' => "Create a $singularGroup",
                'delete' => "Delete a $singularGroup",
                'put', 'patch' => "Update a $singularGroup",
                'get' => $this->getDefaultGetName($singularGroup),
                default => "Get a $singularGroup",
            };

            if (empty($newName)) {
                $newName = '<UNKNOWN NAME>';
            }
            $apiCall->setName($newName);
        }

        // If there's still no group, set a default group
        if (empty($group)) {
            $apiCall->setGroup('Misc');
        }

        $this->apiCalls->push($apiCall);
    }

    protected function getDefaultGetName(string $singularGroup): string
    {
        $newName = "Get a $singularGroup";
        if (isset($this->attributes['action'])) {
            $action = strtolower($this->attributes['action']);
            if (str_contains($action, 'search')) {
                $newName = "List " . Str::plural($singularGroup);
            }
            if (str_contains($action, 'index')) {
                $newName = "Search $singularGroup";
            }
        }
        return $newName;
    }

    public function findByDefinition(string $defineName): ?APICall
    {
        $apiCalls = $this->apiCalls->filter(function (APICall $item) use ($defineName) {
            $define = $item->getDefine();
            if (isset($define['title'])) {
                return $define['title'] === $defineName;
            }
            return false;
        });

        if ($apiCalls->isNotEmpty()) {
            return $apiCalls->first();
        }

        return null;
    }

    public function setInterceptor(string $method, string $uri, string $action): void
    {
        $this->attributes['method'] = $method;
        $this->attributes['uri'] = $uri;
        $this->attributes['action'] = $action;
    }

    public function clearInterceptor(): void
    {
        $this->attributes = [];
    }

    public function getApiCalls(): Collection
    {
        return $this->apiCalls;
    }

    public function throwDocumentationModeException(): void
    {
        throw new DocumentationModeEnabledException("Requests cannot be executed while in documentation mode.");
    }

    /**
     * Get configured auth headers from config.
     */
    public function getConfiguredAuthHeaders(): array
    {
        return config('easy-doc.auth_headers', []);
    }

    /**
     * Get default headers definition using configured headers.
     */
    public function getDefaultHeadersDefinition(): APICall
    {
        $apiCall = new APICall();
        $apiCall->setDefine('default_headers');

        $headers = [];

        // Add Accept header
        $headers[] = (new Param('Accept', Param::TYPE_STRING, 'Set to `application/json`'))
            ->setDefaultValue('application/json');

        // Add configured auth headers
        foreach ($this->getConfiguredAuthHeaders() as $headerConfig) {
            $header = new Param(
                $headerConfig['name'],
                Param::TYPE_STRING,
                $headerConfig['description'] ?? ucfirst(str_replace(['-', '_'], ' ', $headerConfig['name']))
            );

            if (isset($headerConfig['example'])) {
                $header->setDefaultValue($headerConfig['example']);
            }

            if (isset($headerConfig['required']) && $headerConfig['required'] === false) {
                $header->optional();
            }

            $headers[] = $header;
        }

        $apiCall->setHeaders($headers);

        return $apiCall;
    }
}
