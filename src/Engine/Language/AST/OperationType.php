<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Engine\Language\AST;

enum OperationType: string
{
    case QUERY = 'query';
    case MUTATION = 'mutation';
    case SUBSCRIPTION = 'subscription';
}
