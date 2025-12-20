<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Enums;

enum AuthType: string
{
    case NONE = 'none';
    case BEARER = 'bearer';
    case BASIC = 'basic';
    case OAUTH2 = 'oauth2';
    case DIGEST = 'digest';
    case AWS_SIG_V4 = 'aws-sig-v4';
}
