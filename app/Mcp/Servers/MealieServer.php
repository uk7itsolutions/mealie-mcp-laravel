<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateCookbookTool;
use App\Mcp\Tools\DeleteCookbookTool;
use App\Mcp\Tools\GetRecipeTool;
use App\Mcp\Tools\ListCookbooksTool;
use App\Mcp\Tools\ListHouseholdsTool;
use App\Mcp\Tools\ListRecipesTool;
use App\Mcp\Tools\UpdateCookbookTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Mealie')]
#[Version('2.0.0')]
#[Instructions('Manage recipes and cookbooks in a Mealie instance. Recipes can be listed with pagination and free-text search, and fetched in full by id or slug. Cookbooks are scoped to a household — call list_households first to discover household ids, then use the cookbook tools with that id.')]
class MealieServer extends Server
{
    protected array $tools = [
        ListRecipesTool::class,
        GetRecipeTool::class,
        ListHouseholdsTool::class,
        ListCookbooksTool::class,
        CreateCookbookTool::class,
        UpdateCookbookTool::class,
        DeleteCookbookTool::class,
    ];
}
