<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Enums;

enum GroupStrategy: string
{
    case PREFIX = 'prefix';
    case CONTROLLER = 'controller';
    case TAG = 'tag';
    case NONE = 'none';
}
