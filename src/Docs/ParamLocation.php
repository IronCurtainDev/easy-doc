<?php

declare(strict_types=1);

namespace EasyDoc\Docs;

enum ParamLocation: string
{
    case PATH = 'path';
    case QUERY = 'query';
    case HEADER = 'header';
    case COOKIE = 'cookie';
    case BODY = 'body';
    case FORM = 'formData';
}
