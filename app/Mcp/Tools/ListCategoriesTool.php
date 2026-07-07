<?php

namespace App\Mcp\Tools;

use App\Services\MealieClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('list_categories')]
#[Description('List the categories known to Mealie. Useful for discovering existing category names before applying them to a recipe.')]
class ListCategoriesTool extends MealieTool
{
    public function __construct(private readonly MealieClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'page'    => $schema->integer()->description('Page number.')->default(1),
            'perPage' => $schema->integer()->description('Results per page.')->default(50),
        ];
    }

    protected function execute(Request $request): Response
    {
        $params = [
            'page'    => $request->get('page', 1),
            'perPage' => $request->get('perPage', 50),
        ];

        return Response::text(json_encode($this->client->get('categories', $params)));
    }
}
