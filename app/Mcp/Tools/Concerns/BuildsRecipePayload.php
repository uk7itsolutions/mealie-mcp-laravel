<?php

namespace App\Mcp\Tools\Concerns;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

/**
 * Shared schema fragments and payload building for create_recipe and
 * update_recipe. Mealie stores recipe details via PATCH /api/recipes/{slug};
 * this trait maps the tool's flat input to that Recipe payload.
 */
trait BuildsRecipePayload
{
    /**
     * Mealie requires the full food/unit object (id + name at minimum) inside
     * a recipe ingredient, so each referenced id is resolved once per request.
     */
    private array $resolvedFoods = [];

    private array $resolvedUnits = [];

    protected function recipeFieldSchemas(JsonSchema $schema): array
    {
        return [
            'description'  => $schema->string()->description('Recipe description.'),
            'servings'     => $schema->number()->description('Number of servings, e.g. 4.'),
            'yield'        => $schema->string()->description('Yield text, e.g. "1 loaf".'),
            'prepTime'     => $schema->string()->description('Preparation time as text, e.g. "15 minutes".'),
            'cookTime'     => $schema->string()->description('Cook time as text, e.g. "45 minutes".'),
            'totalTime'    => $schema->string()->description('Total time as text, e.g. "1 hour".'),
            'ingredients'  => $schema->array()->items($schema->object([
                'quantity' => $schema->number()->description('Amount, e.g. 2 or 0.5.'),
                'unitId'   => $schema->string()->description('Id of a unit. See list_units / create_unit.'),
                'foodId'   => $schema->string()->description('Id of a food. See list_foods / create_food.'),
                'note'     => $schema->string()->description('Free text, e.g. "finely diced" — or the entire ingredient line when no foodId/unitId is given.'),
                'title'    => $schema->string()->description('Optional section header shown above this ingredient, e.g. "For the sauce".'),
            ]))->description('Ingredient list, replacing any existing ingredients. Prefer structured entries (quantity + unitId + foodId + note); create missing foods/units first. A plain-text entry with only a note also works.'),
            'instructions' => $schema->array()->items($schema->object([
                'text'  => $schema->string()->description('The step text.')->required(),
                'title' => $schema->string()->description('Optional section header shown above this step, e.g. "Bake".'),
            ]))->description('Ordered list of instruction steps, replacing any existing steps.'),
            'tags' => $schema->array()->items($schema->string())->description('List of tag names to apply to the recipe.'),
            'categories' => $schema->array()->items($schema->string())->description('List of category names to apply to the recipe.'),
        ];
    }

    protected function buildRecipePayload(Request $request): array
    {
        $payload = [];

        foreach ([
            'description' => 'description',
            'yield'       => 'recipeYield',
            'prepTime'    => 'prepTime',
            // Mealie's UI displays performTime as the recipe's cook time.
            'cookTime'    => 'performTime',
            'totalTime'   => 'totalTime',
        ] as $param => $field) {
            if ($request->has($param)) {
                $payload[$field] = $request->get($param);
            }
        }

        if ($request->has('servings')) {
            $payload['recipeServings'] = $request->get('servings');
        }

        if ($request->has('ingredients')) {
            $payload['recipeIngredient'] = array_map(
                fn (array $ingredient) => $this->buildIngredient($ingredient),
                $request->get('ingredients'),
            );
        }

        if ($request->has('instructions')) {
            $payload['recipeInstructions'] = array_map(
                fn (array $step) => array_filter([
                    'text'  => $step['text'] ?? '',
                    'title' => $step['title'] ?? null,
                ], fn ($value) => $value !== null),
                $request->get('instructions'),
            );
        }

        if ($request->has('tags')) {
            $payload['tags'] = array_map(
                fn (string $tag) => ['name' => $tag],
                $request->get('tags')
            );
        }

        if ($request->has('categories')) {
            $payload['recipeCategory'] = array_map(
                fn (string $category) => ['name' => $category],
                $request->get('categories')
            );
        }

        return $payload;
    }

    private function buildIngredient(array $ingredient): array
    {
        $entry = [];

        foreach (['quantity', 'note', 'title'] as $field) {
            if (isset($ingredient[$field])) {
                $entry[$field] = $ingredient[$field];
            }
        }

        if (! empty($ingredient['foodId'])) {
            $entry['food'] = $this->resolvedFoods[$ingredient['foodId']]
                ??= $this->client->get('foods/'.rawurlencode($ingredient['foodId']));
        }

        if (! empty($ingredient['unitId'])) {
            $entry['unit'] = $this->resolvedUnits[$ingredient['unitId']]
                ??= $this->client->get('units/'.rawurlencode($ingredient['unitId']));
        }

        return $entry;
    }
}
