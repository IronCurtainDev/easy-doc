<?php

declare(strict_types=1);

namespace EasyDoc\Docs;

enum ParamType: string
{
    case STRING = 'string';
    case INT = 'integer';
    case NUMBER = 'number';
    case BOOLEAN = 'boolean';
    case ARRAY = 'array';
    case FILE = 'file';
    case OBJECT = 'object';

    public function toSwagger(): string
    {
        return match ($this) {
            self::INT => 'integer',
            self::NUMBER => 'number',
            self::BOOLEAN => 'boolean',
            self::ARRAY => 'array',
            self::OBJECT => 'object',
            self::FILE => 'file',
            default => 'string',
        };
    }
}
