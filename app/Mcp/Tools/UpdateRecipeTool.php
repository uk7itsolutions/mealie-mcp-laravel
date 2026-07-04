<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\BuildsRecipePayload;
use App\Services\MealieClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update_recipe')]
#[Description('Update an existing recipe. Only the provided fields change; ingredients and instructions replace the existing lists when given.')]
class UpdateRecipeTool extends MealieTool
{
    use BuildsRecipePayload;

    public function __construct(private readonly MealieClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'recipeId' => $schema->string()->description('The id or slug of the recipe to update. See list_recipes.')->required(),
            'name'     => $schema->string()->description('New name for the recipe.'),
            ...$this->recipeFieldSchemas($schema),
        ];
    }

    protected function execute(Request $request): Response
    {
        $slug = $request->get('recipeId');

        $payload = $this->buildRecipePayload($request);

        if ($request->has('name')) {
            $payload['name'] = $request->get('name');
        }

        if ($payload) {
            // Renaming changes the slug; follow the recipe under its new one.
            $updated = $this->client->patch('recipes/'.rawurlencode($slug), $payload);
            $slug = $updated['slug'] ?? $slug;
        }

        return Response::text(json_encode($this->client->get('recipes/'.rawurlencode($slug))));
    }
}
