<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Enums;

enum BodyType: string
{
    case JSON = 'json';
    case XML = 'xml';
    case FORM_URLENCODED = 'form-urlencoded';
    case MULTIPART_FORM = 'multipart-form';
    case TEXT = 'text';
    case NONE = 'none';
}
