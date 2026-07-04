<?php

namespace App\Mcp\Tools;

use App\Services\MealieClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_unit')]
#[Description('Create a measurement unit, e.g. "cup". Check list_units first to avoid duplicates; use the returned id as unitId in recipe ingredients.')]
class CreateUnitTool extends MealieTool
{
    public function __construct(private readonly MealieClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'name'         => $schema->string()->description('Singular name of the unit, e.g. "cup".')->required(),
            'pluralName'   => $schema->string()->description('Plural name, e.g. "cups".'),
            'abbreviation' => $schema->string()->description('Abbreviation, e.g. "tbsp".'),
            'description'  => $schema->string()->description('Optional description.'),
        ];
    }

    protected function execute(Request $request): Response
    {
        $data = ['name' => $request->get('name')];

        foreach (['pluralName', 'abbreviation', 'description'] as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->get($field);
            }
        }

        return Response::text(json_encode($this->client->post('units', $data)));
    }
}
