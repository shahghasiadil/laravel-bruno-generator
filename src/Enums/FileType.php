<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Enums;

enum FileType: string
{
    case BRUNO_REQUEST = 'request';
    case BRUNO_COLLECTION = 'collection';
    case BRUNO_ENVIRONMENT = 'environment';
}
