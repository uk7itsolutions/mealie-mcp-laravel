<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['mealie.base_url' => 'https://mealie.test']);
    }

    public function test_mcp_endpoint_rejects_missing_authorization_header(): void
    {
        $response = $this->postJson('/mcp', $this->initializePayload());

        $response->assertStatus(401);
    }

    public function test_mcp_endpoint_rejects_token_that_mealie_rejects(): void
    {
        Http::fake([
            'https://mealie.test/api/users/self' => Http::response(['detail' => 'Unauthorized'], 401),
        ]);

        $response = $this->postJson('/mcp', $this->initializePayload(), [
            'Authorization' => 'Bearer bad-token',
        ]);

        $response->assertStatus(401);
    }

    public function test_mcp_endpoint_reports_misconfigured_base_url(): void
    {
        config(['mealie.base_url' => '']);

        $response = $this->postJson('/mcp', $this->initializePayload(), [
            'Authorization' => 'Bearer some-token',
        ]);

        $response->assertStatus(500);
    }

    public function test_mcp_initialize_handshake_succeeds_with_valid_token(): void
    {
        Http::fake([
            'https://mealie.test/api/users/self' => Http::response(['id' => 'user-1'], 200),
        ]);

        $response = $this->postJson('/mcp', $this->initializePayload(), [
            'Authorization' => 'Bearer good-token',
            'Accept' => 'application/json, text/event-stream',
        ]);

        $response->assertOk();
        $this->assertSame('Mealie', $response->json('result.serverInfo.name'));
    }

    public function test_tools_list_contains_all_mealie_tools(): void
    {
        Http::fake([
            'https://mealie.test/api/users/self' => Http::response(['id' => 'user-1'], 200),
        ]);

        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ], [
            'Authorization' => 'Bearer good-token',
            'Accept' => 'application/json, text/event-stream',
        ]);

        $response->assertOk();

        $names = collect($response->json('result.tools'))->pluck('name')->all();

        foreach ([
            'list_recipes',
            'get_recipe',
            'create_recipe',
            'update_recipe',
            'import_recipe_from_url',
            'list_foods',
            'create_food',
            'list_units',
            'create_unit',
            'list_households',
            'list_cookbooks',
            'create_cookbook',
            'update_cookbook',
            'delete_cookbook',
        ] as $tool) {
            $this->assertContains($tool, $names);
        }
    }

    public function test_create_recipe_creates_stub_then_patches_details(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            $method = $request->method();

            return match (true) {
                str_contains($url, '/api/users/self') => Http::response(['id' => 'user-1'], 200),
                $method === 'POST' && str_ends_with($url, '/api/recipes') => Http::response('"pancakes"', 200),
                $method === 'GET' && str_contains($url, '/api/foods/food-1') => Http::response(['id' => 'food-1', 'name' => 'flour'], 200),
                $method === 'GET' && str_contains($url, '/api/units/unit-1') => Http::response(['id' => 'unit-1', 'name' => 'cup'], 200),
                $method === 'PATCH' && str_contains($url, '/api/recipes/pancakes') => Http::response(['slug' => 'pancakes'], 200),
                $method === 'GET' && str_contains($url, '/api/recipes/pancakes') => Http::response(['slug' => 'pancakes', 'name' => 'Pancakes'], 200),
                default => Http::response(['detail' => 'unexpected request: '.$method.' '.$url], 500),
            };
        });

        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => [
                'name' => 'create_recipe',
                'arguments' => [
                    'name' => 'Pancakes',
                    'description' => 'Fluffy pancakes',
                    'ingredients' => [
                        ['quantity' => 2, 'unitId' => 'unit-1', 'foodId' => 'food-1', 'note' => 'sifted'],
                        ['note' => 'a pinch of salt'],
                    ],
                    'instructions' => [
                        ['text' => 'Mix everything.'],
                        ['text' => 'Fry until golden.'],
                    ],
                ],
            ],
        ], [
            'Authorization' => 'Bearer good-token',
            'Accept' => 'application/json, text/event-stream',
        ]);

        $response->assertOk();
        $this->assertFalse((bool) $response->json('result.isError'), $response->json('result.content.0.text') ?? '');
        $this->assertStringContainsString('pancakes', $response->json('result.content.0.text'));

        Http::assertSent(function ($request) {
            if ($request->method() !== 'PATCH') {
                return false;
            }

            $data = $request->data();

            return $data['description'] === 'Fluffy pancakes'
                && $data['recipeIngredient'][0]['food']['name'] === 'flour'
                && $data['recipeIngredient'][0]['unit']['name'] === 'cup'
                && $data['recipeIngredient'][1] === ['note' => 'a pinch of salt']
                && $data['recipeInstructions'][1] === ['text' => 'Fry until golden.'];
        });
    }

    public function test_list_recipes_tool_calls_mealie_and_returns_payload(): void
    {
        Http::fake([
            'https://mealie.test/api/users/self' => Http::response(['id' => 'user-1'], 200),
            'https://mealie.test/api/recipes*' => Http::response([
                'items' => [['name' => 'Spaghetti', 'slug' => 'spaghetti']],
            ], 200),
        ]);

        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'list_recipes',
                'arguments' => ['search' => 'spaghetti'],
            ],
        ], [
            'Authorization' => 'Bearer good-token',
            'Accept' => 'application/json, text/event-stream',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Spaghetti', $response->json('result.content.0.text'));
        $this->assertFalse((bool) $response->json('result.isError'));
    }

    public function test_tool_reports_mealie_api_error_as_structured_error(): void
    {
        Http::fake([
            'https://mealie.test/api/users/self' => Http::response(['id' => 'user-1'], 200),
            'https://mealie.test/api/recipes/*' => Http::response(['detail' => 'Not found'], 404),
        ]);

        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_recipe',
                'arguments' => ['recipeId' => 'does-not-exist'],
            ],
        ], [
            'Authorization' => 'Bearer good-token',
            'Accept' => 'application/json, text/event-stream',
        ]);

        $response->assertOk();
        $this->assertTrue((bool) $response->json('result.isError'));
        $this->assertStringContainsString('404', $response->json('result.content.0.text'));
    }

    private function initializePayload(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => (object) [],
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
            ],
        ];
    }
}
