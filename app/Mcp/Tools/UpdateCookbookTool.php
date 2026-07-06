<?php

namespace App\Mcp\Tools;

use App\Services\MealieClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update_cookbook')]
#[Description('Update an existing cookbook.')]
class UpdateCookbookTool extends MealieTool
{
    public function __construct(private readonly MealieClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'cookbookId'  => $schema->string()->description('The id of the cookbook to update. See list_cookbooks.')->required(),
            'name'        => $schema->string()->description('New name for the cookbook.')->required(),
            'description' => $schema->string()->description('Optional new description of the cookbook.'),
        ];
    }

    protected function execute(Request $request): Response
    {
        $data = ['name' => $request->get('name')];

        if ($request->has('description')) {
            $data['description'] = $request->get('description');
        }

        return Response::text(json_encode($this->client->put(
            'households/cookbooks/'.rawurlencode($request->get('cookbookId')),
            $data,
        )));
    }
}
