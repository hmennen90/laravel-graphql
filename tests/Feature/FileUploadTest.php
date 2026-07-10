<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Tests\TestCase;
use Illuminate\Http\UploadedFile;

final class FileUploadTest extends TestCase
{
    public function test_multipart_file_upload_reaches_the_resolver(): void
    {
        $file = UploadedFile::fake()->create('report.pdf', 12);

        $response = $this->post('/graphql', [
            'operations' => json_encode([
                'query' => 'mutation ($f: Upload!) { upload(file: $f) }',
                'variables' => ['f' => null],
            ]),
            'map' => json_encode(['0' => ['variables.f']]),
            '0' => $file,
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertExactJson(['data' => ['upload' => 'report.pdf']]);
    }
}
