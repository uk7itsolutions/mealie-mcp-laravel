<?php

namespace App\Mcp\Tools;

use App\Services\MealieClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('delete_cookbook')]
#[Description('Delete a cookbook.')]
class DeleteCookbookTool extends MealieTool
{
    public function __construct(private readonly MealieClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'cookbookId'  => $schema->string()->description('The id of the cookbook to delete. See list_cookbooks.')->required(),
        ];
    }

    protected function execute(Request $request): Response
    {
        $deleted = $this->client->delete(
            'households/cookbooks/'.rawurlencode($request->get('cookbookId')),
        );

        return Response::text($deleted === null
            ? 'Cookbook deleted.'
            : json_encode($deleted));
    }
}
