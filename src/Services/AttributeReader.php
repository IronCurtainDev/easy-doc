<?php

declare(strict_types=1);

namespace EasyDoc\Services;

use EasyDoc\Attributes\DocAPI;
use EasyDoc\Attributes\DocError;
use EasyDoc\Attributes\DocGroup;
use EasyDoc\Attributes\DocHeader;
use EasyDoc\Attributes\DocParam;
use EasyDoc\Attributes\DocResponse;
use EasyDoc\Attributes\DocRequest;
use EasyDoc\Docs\APICall;
use EasyDoc\Docs\Param;
use EasyDoc\Exceptions\InvalidDocAttributeException;
use ReflectionClass;
use ReflectionMethod;

/**
 * Service to read PHP 8 Attributes from controller methods
 * and convert them to APICall objects for documentation generation.
 */
class AttributeReader
{
    /**
     * Read documentation attributes from a controller method.
     *
     * @param string $controller Fully qualified controller class name
     * @param string $method Method name
     * @return APICall|null Returns APICall if DocAPI attribute found, null otherwise
     */
    public function readFromMethod(string $controller, string $method): ?APICall
    {
        try {
            $reflection = new ReflectionMethod($controller, $method);
        } catch (\ReflectionException $e) {
            return null;
        }

        // Check for main DocAPI attribute
        $docApiAttributes = $reflection->getAttributes(DocAPI::class);

        if (empty($docApiAttributes)) {
            return null; // No DocAPI attribute, fall back to document() function
        }

        /** @var DocAPI $docApi */
        $docApi = $docApiAttributes[0]->newInstance();

        // Read DocGroup defaults from controller class
        $groupDefaults = $this->readDocGroup($controller);

        // Build the APICall
        $apiCall = new APICall();

        // Apply DocGroup defaults first, then DocAPI overrides
        $this->applyGroupDefaults($apiCall, $groupDefaults);

        // Basic properties
        if ($docApi->name) {
            $apiCall->setName($docApi->name);
        }

        if ($docApi->group) {
            $apiCall->setGroup($docApi->group);
        }

        if ($docApi->description) {
            $apiCall->setDescription($docApi->description);
        }

        if ($docApi->operationId) {
            $apiCall->setOperationId($docApi->operationId);
        }

        $apiCall->setVersion($docApi->version);

        if (!empty($docApi->tags)) {
            $apiCall->setTags($docApi->tags);
        }

        if ($docApi->deprecated !== null) {
            $apiCall->deprecated($docApi->deprecated);
        }

        if ($docApi->rateLimit !== null) {
            $apiCall->rateLimit($docApi->rateLimit['limit'] ?? 60, $docApi->rateLimit['period'] ?? 'minute');
        }

        if (!empty($docApi->consumes)) {
            $apiCall->setConsumes($docApi->consumes);
        }

        // Default headers handling
        if (!$docApi->addDefaultHeaders) {
            $apiCall->noDefaultHeaders();
        }

        // Success object
        if ($docApi->successObject) {
            $apiCall->setSuccessObject($docApi->successObject);
        }

        if ($docApi->successPaginatedObject) {
            $apiCall->setSuccessPaginatedObject($docApi->successPaginatedObject);
        }

        if ($docApi->successMessageOnly) {
            $apiCall->setSuccessMessageOnly();
        }

        // Request example
        if (!empty($docApi->requestExample)) {
            $apiCall->setRequestExample($docApi->requestExample);
        }

        // Success params
        if (!empty($docApi->successParams)) {
            $this->applySuccessParams($apiCall, $docApi->successParams);
        }

        // Define reusable block
        if ($docApi->define !== null && isset($docApi->define['title'])) {
            $apiCall->setDefine(
                $docApi->define['title'],
                $docApi->define['description'] ?? ''
            );
        }

        // Use defined blocks
        if (!empty($docApi->use)) {
            $uses = is_array($docApi->use) ? $docApi->use : [$docApi->use];
            foreach ($uses as $useName) {
                $apiCall->setUse($useName);
            }
        }

        // Possible errors
        if (!empty($docApi->possibleErrors)) {
            $apiCall->possibleErrors($docApi->possibleErrors);
        }

        // Custom schema references
        if ($docApi->successSchema) {
            $apiCall->setSuccessSchema($docApi->successSchema);
        }

        if ($docApi->errorSchema) {
            $apiCall->setErrorSchema($docApi->errorSchema);
        }

        // Headers from DocAPI attribute (config header names)
        // These are string header names that need to be resolved from config
        if (!empty($docApi->headers)) {
            $this->applyConfigHeaders($apiCall, $docApi->headers);
        }

        // Params from DocAPI attribute
        if (!empty($docApi->params)) {
            $apiCall->setParams($docApi->params);
        }

        // Read repeatable DocParam attributes
        $this->applyParamAttributes($reflection, $apiCall);

        // Read DocRequest attribute
        $this->applyDocRequest($reflection, $apiCall);

        // Read repeatable DocHeader attributes
        $this->applyHeaderAttributes($reflection, $apiCall);

        // Read repeatable DocResponse attributes
        $this->applyResponseAttributes($reflection, $apiCall);

        // Read repeatable DocError attributes
        $this->applyDocErrors($reflection, $apiCall);

        return $apiCall;
    }

    /**
     * Apply DocParam attributes to the APICall.
     */
    protected function applyParamAttributes(ReflectionMethod $reflection, APICall $apiCall): void
    {
        $paramAttributes = $reflection->getAttributes(DocParam::class);

        if (empty($paramAttributes)) {
            return;
        }

        $params = [];
        $queryParams = [];
        $pathParams = [];

        foreach ($paramAttributes as $attribute) {
            /** @var DocParam $docParam */
            $docParam = $attribute->newInstance();

            // Resolve template if specified
            $resolved = $this->resolveParamTemplate($docParam);

            $param = new Param(
                $resolved['name'],
                $resolved['type'],
                $resolved['description']
            );

            if ($resolved['example'] !== null) {
                $param->setExample($resolved['example']);
            }

            if (!$resolved['required']) {
                $param->optional();
            }

            if ($resolved['default'] !== null) {
                $param->setDefaultValue($resolved['default']);
            }

            if ($resolved['enum'] !== null) {
                $param->enum($resolved['enum']);
            }

            if ($resolved['min'] !== null) {
                $param->min($resolved['min']);
            }

            if ($resolved['max'] !== null) {
                $param->max($resolved['max']);
            }

            if ($resolved['pattern'] !== null) {
                $param->pattern($resolved['pattern']);
            }

            // Route to correct collection based on location
            switch ($resolved['location']) {
                case 'query':
                    $param->setLocation(Param::LOCATION_QUERY);
                    $queryParams[] = $param;
                    break;
                case 'path':
                    $param->setLocation(Param::LOCATION_PATH);
                    $pathParams[] = $param;
                    break;
                default:
                    $param->setLocation(Param::LOCATION_BODY);
                    $params[] = $param;
            }
        }

        if (!empty($params)) {
            $existingParams = $apiCall->getParams();
            $apiCall->setParams(array_merge($existingParams, $params));
        }

        foreach ($queryParams as $qp) {
            $apiCall->addQueryParam($qp);
        }

        foreach ($pathParams as $pp) {
            $apiCall->addPathParam($pp);
        }
    }

    /**
     * Apply DocHeader attributes to the APICall.
     */
    protected function applyHeaderAttributes(ReflectionMethod $reflection, APICall $apiCall): void
    {
        $headerAttributes = $reflection->getAttributes(DocHeader::class);

        if (empty($headerAttributes)) {
            return;
        }

        $headers = $apiCall->getHeaders();

        foreach ($headerAttributes as $attribute) {
            /** @var DocHeader $docHeader */
            $docHeader = $attribute->newInstance();

            $header = new Param(
                $docHeader->name,
                Param::TYPE_STRING,
                $docHeader->description,
                Param::LOCATION_HEADER
            );

            if ($docHeader->example !== null) {
                $header->setDefaultValue($docHeader->example);
            }

            if (!$docHeader->required) {
                $header->optional();
            }

            if ($docHeader->default !== null) {
                $header->setDefaultValue($docHeader->default);
            }

            $headers[] = $header;
        }

        $apiCall->setHeaders($headers);
    }

    /**
     * Apply DocResponse attributes to the APICall.
     */
    protected function applyResponseAttributes(ReflectionMethod $reflection, APICall $apiCall): void
    {
        $responseAttributes = $reflection->getAttributes(DocResponse::class);

        foreach ($responseAttributes as $attribute) {
            /** @var DocResponse $docResponse */
            $docResponse = $attribute->newInstance();

            if (!is_int($docResponse->status) || $docResponse->status < 100 || $docResponse->status > 599) {
                throw InvalidDocAttributeException::forAttribute(DocResponse::class, "Status code must be a valid integer between 100 and 599, got: " . var_export($docResponse->status, true));
            }

            if ($docResponse->isError) {
                $apiCall->setErrorExample(
                    $docResponse->example,
                    $docResponse->status,
                    $docResponse->description
                );
            } else {
                $apiCall->setSuccessExample(
                    $docResponse->example,
                    $docResponse->status,
                    $docResponse->description
                );
            }
        }
    }

    /**
     * Apply headers from DocAPI.headers property (config header names).
     * Converts string header names to Param objects using config lookup.
     */
    protected function applyConfigHeaders(APICall $apiCall, array $headerNames): void
    {
        $authHeaders = config('easy-doc.auth_headers', []);
        $headers = [];

        foreach ($headerNames as $name) {
            // First check if it matches a config header
            $found = false;
            foreach ($authHeaders as $headerConfig) {
                if ($headerConfig['name'] === $name) {
                    $header = new Param(
                        $headerConfig['name'],
                        Param::TYPE_STRING,
                        $headerConfig['description'] ?? ucfirst(str_replace(['-', '_'], ' ', $headerConfig['name'])),
                        Param::LOCATION_HEADER
                    );
                    if (isset($headerConfig['example'])) {
                        $header->setDefaultValue($headerConfig['example']);
                    }
                    if (isset($headerConfig['required']) && $headerConfig['required'] === false) {
                        $header->optional();
                    }
                    $headers[] = $header;
                    $found = true;
                    break;
                }
            }

            // If not in config, create a basic header param
            if (!$found) {
                $header = new Param(
                    $name,
                    Param::TYPE_STRING,
                    ucfirst(str_replace(['-', '_'], ' ', $name)),
                    Param::LOCATION_HEADER
                );
                $headers[] = $header;
            }
        }

        if (!empty($headers)) {
            $apiCall->setHeaders($headers);
        }
    }

    /**
     * Apply success params from DocAPI.successParams property.
     * Converts array definitions to Param objects.
     */
    protected function applySuccessParams(APICall $apiCall, array $successParams): void
    {
        $params = [];

        foreach ($successParams as $paramDef) {
            if (!isset($paramDef['name'])) {
                continue;
            }

            $param = new Param(
                $paramDef['name'],
                $paramDef['type'] ?? Param::TYPE_STRING,
                $paramDef['description'] ?? null
            );

            if (isset($paramDef['example'])) {
                $param->setExample($paramDef['example']);
            }

            if (isset($paramDef['required']) && $paramDef['required'] === false) {
                $param->optional();
            }

            $params[] = $param;
        }

        if (!empty($params)) {
            $apiCall->setSuccessParams($params);
        }
    }

    /**
     * Check if a controller method has DocAPI attribute.
     */
    public function hasDocAPIAttribute(string $controller, string $method): bool
    {
        try {
            $reflection = new ReflectionMethod($controller, $method);
            return !empty($reflection->getAttributes(DocAPI::class));
        } catch (\ReflectionException $e) {
            return false;
        }
    }

    /**
     * Read DocGroup attribute from a controller class.
     */
    protected function readDocGroup(string $controller): ?DocGroup
    {
        try {
            $classReflection = new ReflectionClass($controller);
            $groupAttributes = $classReflection->getAttributes(DocGroup::class);

            if (empty($groupAttributes)) {
                return null;
            }

            return $groupAttributes[0]->newInstance();
        } catch (\ReflectionException $e) {
            return null;
        }
    }

    /**
     * Apply DocGroup defaults to an APICall.
     */
    protected function applyGroupDefaults(APICall $apiCall, ?DocGroup $group): void
    {
        if ($group === null) {
            return;
        }

        if ($group->group) {
            $apiCall->setGroup($group->group);
        }

        $apiCall->setVersion($group->version);

        if (!empty($group->tags)) {
            $apiCall->setTags($group->tags);
        }

        if (!empty($group->consumes)) {
            $apiCall->setConsumes($group->consumes);
        }

        if (!$group->addDefaultHeaders) {
            $apiCall->noDefaultHeaders();
        }

        if (!empty($group->headers)) {
            $this->applyConfigHeaders($apiCall, $group->headers);
        }

        if ($group->rateLimit !== null) {
            $apiCall->rateLimit($group->rateLimit['limit'] ?? 60, $group->rateLimit['period'] ?? 'minute');
        }

        if (!empty($group->possibleErrors)) {
            $apiCall->possibleErrors($group->possibleErrors);
        }
    }

    /**
     * Resolve DocParam template from config.
     * Returns an array of resolved param values.
     */
    protected function resolveParamTemplate(DocParam $docParam): array
    {
        $templates = config('easy-doc.param_templates', []);
        $template = null;

        // If template is specified, load from config
        if ($docParam->template !== null && isset($templates[$docParam->template])) {
            $template = $templates[$docParam->template];
        }

        // Merge template with explicit values (explicit values override template)
        $paramName = $docParam->name ?? ($template['name'] ?? $docParam->template);

        if (empty($paramName)) {
            throw InvalidDocAttributeException::forAttribute(DocParam::class, "Parameter name is required and cannot be empty.");
        }

        return [
            'name' => $paramName,
            'type' => $docParam->type !== 'string' ? $docParam->type : ($template['type'] ?? 'string'),
            'description' => $docParam->description ?? ($template['description'] ?? null),
            'example' => $docParam->example ?? ($template['example'] ?? null),
            'required' => $docParam->required && ($template['required'] ?? true),
            'default' => $docParam->default ?? ($template['default'] ?? null),
            'enum' => $docParam->enum ?? ($template['enum'] ?? null),
            'min' => $docParam->min ?? ($template['min'] ?? null),
            'max' => $docParam->max ?? ($template['max'] ?? null),
            'pattern' => $docParam->pattern ?? ($template['pattern'] ?? null),
            'location' => $docParam->location !== 'body' ? $docParam->location : ($template['location'] ?? 'body'),
        ];
    }

    /**
     * Apply DocError attributes to the APICall.
     */
    protected function applyDocErrors(ReflectionMethod $reflection, APICall $apiCall): void
    {
        $errorAttributes = $reflection->getAttributes(DocError::class);

        if (empty($errorAttributes)) {
            return;
        }

        $presets = config('easy-doc.error_presets', []);

        foreach ($errorAttributes as $attribute) {
            /** @var DocError $docError */
            $docError = $attribute->newInstance();

            if (!isset($presets[$docError->preset])) {
                continue; // Skip unknown presets
            }

            $preset = $presets[$docError->preset];

            $apiCall->setErrorExample(
                $docError->example ?? $preset['example'] ?? [],
                $preset['status'] ?? 500,
                $docError->description ?? $preset['description'] ?? 'Error'
            );
        }
    }
    /**
     * Apply DocRequest attribute to the APICall.
     * Reads validation rules from a FormRequest and converts them to params.
     */
    protected function applyDocRequest(ReflectionMethod $reflection, APICall $apiCall): void
    {
        $attributes = $reflection->getAttributes(DocRequest::class);

        if (empty($attributes)) {
            return;
        }

        /** @var DocRequest $docRequest */
        $docRequest = $attributes[0]->newInstance();
        $requestClass = $docRequest->requestClass;

        if (!class_exists($requestClass)) {
            return;
        }

        try {
            // Resolve form request using container
            $formRequest = app($requestClass);

            if (!method_exists($formRequest, 'rules')) {
                return;
            }

            $rules = $formRequest->rules();
            $params = [];

            foreach ($rules as $field => $rule) {
                $param = $this->convertRuleToParam($field, $rule);
                if ($param) {
                    $params[] = $param;
                }
            }

            if (!empty($params)) {
                $existingParams = $apiCall->getParams();
                $apiCall->setParams(array_merge($existingParams, $params));
            }
        } catch (\Throwable $e) {
            // Ignore errors during resolution to avoid breaking documentation generation
        }
    }

    /**
     * Convert a validation rule to a Param object.
     */
    protected function convertRuleToParam(string $field, mixed $rule): ?Param
    {
        if (is_string($rule)) {
            $rule = explode('|', $rule);
        }

        if (!is_array($rule)) {
            return null;
        }

        // Determine type
        $type = Param::TYPE_STRING;
        if (in_array('integer', $rule) || in_array('int', $rule)) {
            $type = Param::TYPE_INTEGER;
        } elseif (in_array('numeric', $rule)) {
            $type = Param::TYPE_NUMBER;
        } elseif (in_array('boolean', $rule) || in_array('bool', $rule)) {
            $type = Param::TYPE_BOOLEAN;
        } elseif (in_array('array', $rule)) {
            $type = Param::TYPE_ARRAY;
        }

        $param = new Param($field, $type, 'Auto-generated from FormRequest');

        // Required
        if (!in_array('required', $rule)) {
            $param->optional();
        }

        return $param;
    }
}
