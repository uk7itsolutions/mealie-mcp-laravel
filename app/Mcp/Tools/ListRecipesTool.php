<?php

namespace App\Mcp\Tools;

use App\Services\MealieClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('list_recipes')]
#[Description('List recipes, with pagination and an optional free-text search on name or content.')]
class ListRecipesTool extends MealieTool
{
    public function __construct(private readonly MealieClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'search'  => $schema->string()->description('Partial string to search recipes by name or content.'),
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

        if ($request->has('search')) {
            $params['search'] = $request->get('search');
        }

        return Response::text(json_encode($this->client->get('recipes', $params)));
    }
}
