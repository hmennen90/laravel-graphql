<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Console;

use Hmennen90\GraphQL\Directives\DirectiveRegistry;
use Hmennen90\GraphQL\Engine\Error\SyntaxError;
use Hmennen90\GraphQL\Engine\Language\AST\InputObjectTypeDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\InterfaceTypeDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\AST\ObjectTypeDefinitionNode;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Scans the SDL for directives this package does not support — a migration aid and
 * CI guard. Reports each unsupported directive and where it is used.
 */
final class LintSchemaCommand extends Command
{
    /** Directives provided by the GraphQL spec / this engine (always available). */
    private const array BUILT_IN = ['skip', 'include', 'deprecated', 'specifiedBy', 'oneOf'];

    /** Apollo Federation directives — allowed (rendered into the subgraph SDL). */
    private const array FEDERATION = ['key', 'shareable', 'external', 'requires', 'provides', 'link'];

    protected $signature = 'graphql:lint';

    protected $description = 'Check the SDL for unsupported directives (migration aid / CI guard).';

    public function handle(Config $config, DirectiveRegistry $registry): int
    {
        $sdl = $this->readSdl($config->get('graphql.schema.sdl_path'));
        if ($sdl === '') {
            $this->warn('graphql:lint scans SDL schemas only (set graphql.schema.sdl_path). Nothing to check.');

            return self::SUCCESS;
        }

        try {
            $document = Parser::parse($sdl);
        } catch (SyntaxError $e) {
            $this->error('SDL syntax error: '.$e->getMessage());

            return self::FAILURE;
        }

        $known = array_merge(array_keys($registry->all()), self::BUILT_IN, self::FEDERATION);
        $unsupported = [];
        $total = 0;

        foreach ($this->usages($document->definitions) as [$name, $location]) {
            $total++;
            if (! in_array($name, $known, true)) {
                $unsupported[] = ['@'.$name, $location];
            }
        }

        if ($unsupported === []) {
            $this->info(sprintf('Schema OK: all %d directive usages are supported.', $total));

            return self::SUCCESS;
        }

        $this->error(sprintf('%d unsupported directive usage(s) found:', count($unsupported)));
        $this->table(['directive', 'location'], $unsupported);
        $this->line('See docs/migrating-from-lighthouse.md for how to replace these.');

        return self::FAILURE;
    }

    /**
     * @param  array<int, object>  $definitions
     * @return list<array{0: string, 1: string}>  directive name + location
     */
    private function usages(array $definitions): array
    {
        $usages = [];
        foreach ($definitions as $definition) {
            if (
                ! $definition instanceof ObjectTypeDefinitionNode
                && ! $definition instanceof InterfaceTypeDefinitionNode
                && ! $definition instanceof InputObjectTypeDefinitionNode
            ) {
                continue;
            }

            $typeName = $definition->name;
            foreach ($definition->directives as $directive) {
                $usages[] = [$directive->name, $typeName];
            }

            foreach ($definition->fields as $field) {
                foreach ($field->directives as $directive) {
                    $usages[] = [$directive->name, $typeName.'.'.$field->name];
                }

                if ($field instanceof \Hmennen90\GraphQL\Engine\Language\AST\FieldDefinitionNode) {
                    foreach ($field->arguments as $argument) {
                        foreach ($argument->directives as $directive) {
                            $usages[] = [$directive->name, $typeName.'.'.$field->name.'('.$argument->name.':)'];
                        }
                    }
                }
            }
        }

        return $usages;
    }

    private function readSdl(mixed $paths): string
    {
        if (is_string($paths)) {
            $paths = [$paths];
        }
        if (! is_array($paths)) {
            return '';
        }

        $parts = [];
        foreach ($paths as $path) {
            if (is_string($path) && is_file($path)) {
                $contents = file_get_contents($path);
                if ($contents !== false) {
                    $parts[] = $contents;
                }
            }
        }

        return implode("\n", $parts);
    }
}
