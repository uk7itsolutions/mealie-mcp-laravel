<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\BuildsRecipePayload;
use App\Services\MealieClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_recipe')]
#[Description('Create a new recipe with optional description, times, ingredients and instructions. For structured ingredients, look up or create the foods and units first and pass their ids.')]
class CreateRecipeTool extends MealieTool
{
    use BuildsRecipePayload;

    public function __construct(private readonly MealieClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Name of the recipe.')->required(),
            ...$this->recipeFieldSchemas($schema),
        ];
    }

    protected function execute(Request $request): Response
    {
        // Mealie creates recipes in two steps: POST with the name returns the
        // new slug, then the details are applied with a PATCH.
        $slug = $this->client->post('recipes', ['name' => $request->get('name')]);

        if ($payload = $this->buildRecipePayload($request)) {
            $this->client->patch('recipes/'.rawurlencode($slug), $payload);
        }

        return Response::text(json_encode($this->client->get('recipes/'.rawurlencode($slug))));
    }
}
