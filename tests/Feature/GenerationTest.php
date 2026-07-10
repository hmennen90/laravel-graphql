<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Feature;

use Hmennen90\GraphQL\Generation\ModelTypeGenerator;
use Hmennen90\GraphQL\Generation\ResponseTypeGenerator;
use Hmennen90\GraphQL\Generation\ValidationInputGenerator;
use Hmennen90\GraphQL\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @property int $id
 */
final class GeneratedUser extends Model
{
    /** @var list<string> */
    protected $fillable = ['name', 'email'];

    /** @var array<string, string> */
    protected $casts = ['active' => 'boolean', 'score' => 'float', 'meta' => 'array'];
}

final class StoreUserRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'age' => 'integer',
            'active' => 'boolean',
            'tags' => 'array',
            'email' => 'required|email',
        ];
    }
}

final class GenerationTest extends TestCase
{
    public function test_object_type_generated_from_model(): void
    {
        $type = new ModelTypeGenerator()->fromModel(GeneratedUser::class);

        $this->assertSame('GeneratedUser', $type->name());
        $this->assertSame('ID!', (string) $type->getField('id')->getType());
        $this->assertSame('String', (string) $type->getField('name')->getType());
        $this->assertSame('Boolean', (string) $type->getField('active')->getType());
        $this->assertSame('Float', (string) $type->getField('score')->getType());
        $this->assertSame('JSON', (string) $type->getField('meta')->getType());
        $this->assertTrue($type->hasField('created_at'));
    }

    public function test_input_type_generated_from_form_request(): void
    {
        $type = new ValidationInputGenerator()->fromRequest(StoreUserRequest::class, 'StoreUserInput');

        $this->assertSame('String!', (string) $type->getField('name')->getType());
        $this->assertSame('Int', (string) $type->getField('age')->getType());
        $this->assertSame('Boolean', (string) $type->getField('active')->getType());
        $this->assertSame('JSON', (string) $type->getField('tags')->getType());
        $this->assertSame('String!', (string) $type->getField('email')->getType());
    }

    public function test_object_type_inferred_from_json_response(): void
    {
        $sample = [
            'id' => 1,
            'name' => 'Ada',
            'active' => true,
            'profile' => ['bio' => 'Engineer', 'followers' => 10],
            'tags' => ['a', 'b'],
        ];

        $type = new ResponseTypeGenerator()->fromArray($sample, 'UserResource');

        $this->assertSame('Int', (string) $type->getField('id')->getType());
        $this->assertSame('String', (string) $type->getField('name')->getType());
        $this->assertSame('Boolean', (string) $type->getField('active')->getType());
        $this->assertSame('[String]', (string) $type->getField('tags')->getType());

        $profile = $type->getField('profile')->getType();
        $this->assertInstanceOf(\Hmennen90\GraphQL\Engine\Type\Definition\ObjectType::class, $profile);
        $this->assertSame('String', (string) $profile->getField('bio')->getType());
        $this->assertSame('Int', (string) $profile->getField('followers')->getType());
    }
}
