<?php

declare(strict_types=1);

namespace EasyDoc\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class DocRequest
{
    public function __construct(
        public string $requestClass
    ) {}
}
