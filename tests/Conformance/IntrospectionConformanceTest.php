<?php

declare(strict_types=1);

namespace Hmennen90\GraphQL\Tests\Conformance;

use Hmennen90\GraphQL\Engine\Building\SchemaFirst\SchemaBuilder;
use Hmennen90\GraphQL\Engine\Executor\Executor;
use Hmennen90\GraphQL\Engine\Language\Parser;
use Hmennen90\GraphQL\Engine\Schema\Schema;
use PHPUnit\Framework\TestCase;

/**
 * GraphQL spec conformance — the introspection system ("Introspection" section):
 * `__schema`, `__type`, `__typename`, kinds, field/arg metadata and deprecation.
 */
final class IntrospectionConformanceTest extends TestCase
{
    private function schema(): Schema
    {
        $sdl = <<<'GRAPHQL'
        type Query {
          "A user by id"
          user(id: ID!, active: Boolean = true): User
          legacy: String @deprecated(reason: "use user")
        }
        type User implements Node {
          id: ID!
          name: String
          role: Role
        }
        interface Node { id: ID! }
        enum Role { ADMIN MEMBER GUEST @deprecated(reason: "no guests") }
        GRAPHQL;

        return SchemaBuilder::fromSdl($sdl, ['Query' => ['user' => static fn () => null]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function introspect(string $query): array
    {
        return Executor::execute($this->schema(), Parser::parse($query))->toArray();
    }

    public function test_schema_root_types(): void
    {
        $data = $this->introspect('{ __schema { queryType { name } types { name kind } } }')['data'];
        $this->assertSame('Query', $data['__schema']['queryType']['name']);

        $byName = [];
        foreach ($data['__schema']['types'] as $t) {
            $byName[$t['name']] = $t['kind'];
        }
        $this->assertSame('OBJECT', $byName['User']);
        $this->assertSame('INTERFACE', $byName['Node']);
        $this->assertSame('ENUM', $byName['Role']);
        $this->assertSame('SCALAR', $byName['Int']);
    }

    public function test_type_fields_args_and_kinds(): void
    {
        $q = '{ __type(name: "Query") { fields { name args { name type { kind name ofType { name } } } type { name } } } }';
        $fields = $this->introspect($q)['data']['__type']['fields'];
        $user = array_values(array_filter($fields, static fn ($f): bool => $f['name'] === 'user'))[0];

        $this->assertCount(2, $user['args']);
        $id = array_values(array_filter($user['args'], static fn ($a): bool => $a['name'] === 'id'))[0];
        $this->assertSame('NON_NULL', $id['type']['kind']);
        $this->assertSame('ID', $id['type']['ofType']['name']);
    }

    public function test_field_deprecation(): void
    {
        $q = '{ __type(name: "Query") { fields(includeDeprecated: true) { name isDeprecated deprecationReason } } }';
        $fields = $this->introspect($q)['data']['__type']['fields'];
        $legacy = array_values(array_filter($fields, static fn ($f): bool => $f['name'] === 'legacy'))[0];

        $this->assertTrue($legacy['isDeprecated']);
        $this->assertSame('use user', $legacy['deprecationReason']);
    }

    public function test_deprecated_fields_hidden_by_default(): void
    {
        $q = '{ __type(name: "Query") { fields { name } } }';
        $names = array_map(static fn ($f): string => $f['name'], $this->introspect($q)['data']['__type']['fields']);

        $this->assertContains('user', $names);
        $this->assertNotContains('legacy', $names);
    }

    public function test_enum_values_and_deprecation(): void
    {
        $q = '{ __type(name: "Role") { enumValues(includeDeprecated: true) { name isDeprecated } } }';
        $values = $this->introspect($q)['data']['__type']['enumValues'];
        $names = array_map(static fn ($v): string => $v['name'], $values);

        $this->assertSame(['ADMIN', 'MEMBER', 'GUEST'], $names);
        $guest = array_values(array_filter($values, static fn ($v): bool => $v['name'] === 'GUEST'))[0];
        $this->assertTrue($guest['isDeprecated']);
    }

    public function test_interfaces_and_possible_types(): void
    {
        $q = '{ __type(name: "Node") { kind possibleTypes { name } } user: __type(name: "User") { interfaces { name } } }';
        $data = $this->introspect($q)['data'];
        $this->assertSame('INTERFACE', $data['__type']['kind']);
        $this->assertContains('User', array_map(static fn ($t): string => $t['name'], $data['__type']['possibleTypes']));
        $this->assertContains('Node', array_map(static fn ($t): string => $t['name'], $data['user']['interfaces']));
    }

    public function test_directives_are_introspectable(): void
    {
        $q = '{ __schema { directives { name locations args { name } } } }';
        $directives = $this->introspect($q)['data']['__schema']['directives'];
        $names = array_map(static fn ($d): string => $d['name'], $directives);

        $this->assertContains('skip', $names);
        $this->assertContains('include', $names);
        $this->assertContains('deprecated', $names);
    }
}
