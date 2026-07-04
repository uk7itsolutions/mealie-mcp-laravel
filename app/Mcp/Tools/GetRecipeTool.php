<?php

namespace App\Mcp\Tools;

use App\Services\MealieClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get_recipe')]
#[Description('Get the full details of a single recipe by its id or slug.')]
class GetRecipeTool extends MealieTool
{
    public function __construct(private readonly MealieClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'recipeId' => $schema->string()->description('The id or slug of the recipe. See list_recipes.')->required(),
        ];
    }

    protected function execute(Request $request): Response
    {
        $recipeId = $request->get('recipeId');

        return Response::text(json_encode($this->client->get('recipes/'.rawurlencode($recipeId))));
    }
}
