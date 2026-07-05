<?php

namespace App\Mcp\Tools;

use App\Services\MealieClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_food')]
#[Description('Create a food (ingredient item), e.g. "onion". Check list_foods first to avoid duplicates; use the returned id as foodId in recipe ingredients.')]
class CreateFoodTool extends MealieTool
{
    public function __construct(private readonly MealieClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'name'        => $schema->string()->description('Singular name of the food, e.g. "onion".')->required(),
            'pluralName'  => $schema->string()->description('Plural name, e.g. "onions".'),
            'description' => $schema->string()->description('Optional description.'),
        ];
    }

    protected function execute(Request $request): Response
    {
        $data = ['name' => $request->get('name')];

        foreach (['pluralName', 'description'] as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->get($field);
            }
        }

        return Response::text(json_encode($this->client->post('foods', $data)));
    }
}
