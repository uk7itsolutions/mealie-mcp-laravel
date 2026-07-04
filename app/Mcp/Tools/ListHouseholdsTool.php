<?php

namespace App\Mcp\Tools;

use App\Services\MealieClient;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('list_households')]
#[Description('List the households in the current group. Cookbooks are scoped to a household — use this to discover household ids.')]
class ListHouseholdsTool extends MealieTool
{
    public function __construct(private readonly MealieClient $client) {}

    protected function execute(Request $request): Response
    {
        return Response::text(json_encode($this->client->get('groups/households')));
    }
}
