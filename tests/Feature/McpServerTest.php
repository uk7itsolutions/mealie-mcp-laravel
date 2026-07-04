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
            'list_households',
            'list_cookbooks',
            'create_cookbook',
            'update_cookbook',
            'delete_cookbook',
        ] as $tool) {
            $this->assertContains($tool, $names);
        }
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
