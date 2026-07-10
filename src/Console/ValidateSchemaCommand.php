<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Console;

use Hmennen90\GraphQL\GraphQL;
use Illuminate\Console\Command;
use Throwable;

/** Validates the configured schema — handy as a CI guard. */
final class ValidateSchemaCommand extends Command
{
    protected $signature = 'graphql:validate';

    protected $description = 'Validate the configured GraphQL schema.';

    public function handle(GraphQL $graphql): int
    {
        try {
            $errors = $graphql->schema()->validate();
        } catch (Throwable $e) {
            $this->error('Schema could not be built: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($errors === []) {
            $this->info('The GraphQL schema is valid.');

            return self::SUCCESS;
        }

        foreach ($errors as $error) {
            $this->error('• '.$error->getMessage());
        }
        $this->error(sprintf('Schema is invalid: %d error(s).', count($errors)));

        return self::FAILURE;
    }
}
