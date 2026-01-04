<?php

namespace EasyDoc\Services;

use EasyDoc\Docs\APICall;
use EasyDoc\Docs\Param;

class OpenApiConverter
{
    /**
     * Build parameters array for OpenAPI/Swagger.
     */
    public function buildParameters(APICall $item, string $method): array
    {
        $parameters = [];

        // Add path parameters
        foreach ($item->getPathParams() as $param) {
            $paramData = $this->buildParamData($param, Param::LOCATION_PATH);
            $parameters[] = $paramData;
        }

        // Add query parameters
        foreach ($item->getQueryParams() as $param) {
            $paramData = $this->buildParamData($param, Param::LOCATION_QUERY);
            $parameters[] = $paramData;
        }

        // Add headers and body params
        $allParams = array_merge($item->getHeaders(), $item->getParams());

        foreach ($allParams as $param) {
            $location = $param->getLocation();
            if ($location === null) {
                $location = $method === 'get' ? Param::LOCATION_QUERY : Param::LOCATION_FORM;
            }

            $paramData = $this->buildParamData($param, $location);
            $parameters[] = $paramData;
        }

        return $parameters;
    }

    /**
     * Build parameter data array with enum, min, max, pattern support.
     */
    protected function buildParamData(Param $param, string $location): array
    {
        $paramData = [
            'name' => $param->getName(),
            'in' => $location,
            'required' => $param->getRequired(),
            'description' => $param->getDescription(),
            'type' => strtolower($param->getDataType()),
        ];

        // Add enum values if set
        if ($param->getEnum() !== null) {
            $paramData['enum'] = $param->getEnum();
        }

        // Add validation constraints
        if ($param->getMin() !== null) {
            $paramData['minimum'] = $param->getMin();
        }

        if ($param->getMax() !== null) {
            $paramData['maximum'] = $param->getMax();
        }

        if ($param->getPattern() !== null) {
            $paramData['pattern'] = $param->getPattern();
        }

        if ($param->getDefaultValue() !== null) {
            $paramData['default'] = $param->getDefaultValue();
        }

        if ($param->getExample() !== null) {
            $paramData['example'] = $param->getExample();
        }

        return $paramData;
    }

    /**
     * Build responses array for OpenAPI/Swagger.
     */
    public function buildResponses(APICall $item): array
    {
        $responses = [];

        $successExamples = $item->getSuccessExamples();
        if (!empty($successExamples)) {
            foreach ($successExamples as $code => $data) {
                $responses[(string)$code] = [
                    'description' => $data['description'],
                    'examples' => [
                        'application/json' => $data['example'],
                    ],
                ];
            }
        } else {
            $responses['200'] = ['description' => 'Successful response'];
        }

        $errorExamples = $item->getErrorExamples();
        if (!empty($errorExamples)) {
            foreach ($errorExamples as $code => $data) {
                $responses[(string)$code] = [
                    'description' => $data['description'],
                    'examples' => [
                        'application/json' => $data['example'],
                    ],
                ];
            }
        } else {
            $responses['401'] = ['description' => 'Unauthorized'];
            $responses['422'] = ['description' => 'Validation error'];
        }

        return $responses;
    }
}
