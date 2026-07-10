<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The engine must stay framework-agnostic: nothing under src/Engine may depend
 * on Laravel (Illuminate\*).
 */
final class EnginePurityTest extends TestCase
{
    public function test_engine_has_no_illuminate_dependencies(): void
    {
        $engineDir = dirname(__DIR__, 3).'/src/Engine';
        $offenders = [];

        /** @var iterable<SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($engineDir));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            if (str_contains($contents, 'Illuminate\\')) {
                $offenders[] = $file->getPathname();
            }
        }

        $this->assertSame([], $offenders, 'The engine must not depend on Illuminate.');
    }
}
