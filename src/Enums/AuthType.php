<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Enums;

enum AuthType: string
{
    case NONE = 'none';
    case INHERIT = 'inherit';
    case BEARER = 'bearer';
    case BASIC = 'basic';
    case DIGEST = 'digest';
    case APIKEY = 'apikey';
    case OAUTH1 = 'oauth1';
    case OAUTH2 = 'oauth2';
    case AWS_SIG_V4 = 'awsv4';
    case NTLM = 'ntlm';
    case WSSE = 'wsse';
    case AKAMAI_EDGEGRID = 'akamai-edgegrid';
}
