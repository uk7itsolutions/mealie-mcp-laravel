<?php

namespace App\Mcp\Tools;

use App\Services\MealieClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('list_cookbooks')]
#[Description('List the cookbooks of a household.')]
class ListCookbooksTool extends MealieTool
{
    public function __construct(private readonly MealieClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'householdId' => $schema->string()->description('The id of the household. See list_households.')->required(),
        ];
    }

    protected function execute(Request $request): Response
    {
        $householdId = $request->get('householdId');

        return Response::text(json_encode(
            $this->client->get('households/'.rawurlencode($householdId).'/cookbooks')
        ));
    }
}
