<?php

namespace App\Mcp\Tools;

use App\Services\MealieClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_cookbook')]
#[Description('Create a new cookbook in a household.')]
class CreateCookbookTool extends MealieTool
{
    public function __construct(private readonly MealieClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'householdId' => $schema->string()->description('The id of the household. See list_households.')->required(),
            'name'        => $schema->string()->description('Name of the cookbook.')->required(),
            'description' => $schema->string()->description('Optional description of the cookbook.'),
        ];
    }

    protected function execute(Request $request): Response
    {
        $data = ['name' => $request->get('name')];

        if ($request->has('description')) {
            $data['description'] = $request->get('description');
        }

        return Response::text(json_encode($this->client->post(
            'households/'.rawurlencode($request->get('householdId')).'/cookbooks',
            $data,
        )));
    }
}
