<?php

declare(strict_types=1);

namespace EasyDoc\Exceptions;

use RuntimeException;

class InvalidDocAttributeException extends RuntimeException
{
    public static function forAttribute(string $attributeName, string $message): self
    {
        return new self("Invalid attribute [{$attributeName}]: {$message}");
    }
}
