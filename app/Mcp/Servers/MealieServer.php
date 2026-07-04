<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateCookbookTool;
use App\Mcp\Tools\CreateFoodTool;
use App\Mcp\Tools\CreateRecipeTool;
use App\Mcp\Tools\CreateUnitTool;
use App\Mcp\Tools\DeleteCookbookTool;
use App\Mcp\Tools\GetRecipeTool;
use App\Mcp\Tools\ImportRecipeFromUrlTool;
use App\Mcp\Tools\ListCookbooksTool;
use App\Mcp\Tools\ListFoodsTool;
use App\Mcp\Tools\ListHouseholdsTool;
use App\Mcp\Tools\ListRecipesTool;
use App\Mcp\Tools\ListUnitsTool;
use App\Mcp\Tools\UpdateCookbookTool;
use App\Mcp\Tools\UpdateRecipeTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Mealie')]
#[Version('2.1.0')]
#[Instructions('Manage recipes, foods, units and cookbooks in a Mealie instance. To create a recipe with structured ingredients: find or create each ingredient\'s food (list_foods / create_food) and unit (list_units / create_unit) first, then call create_recipe with the ids — or pass ingredients as plain text in the note field. import_recipe_from_url scrapes a recipe webpage instead. Cookbooks are scoped to a household — call list_households first to discover household ids.')]
class MealieServer extends Server
{
    protected array $tools = [
        ListRecipesTool::class,
        GetRecipeTool::class,
        CreateRecipeTool::class,
        UpdateRecipeTool::class,
        ImportRecipeFromUrlTool::class,
        ListFoodsTool::class,
        CreateFoodTool::class,
        ListUnitsTool::class,
        CreateUnitTool::class,
        ListHouseholdsTool::class,
        ListCookbooksTool::class,
        CreateCookbookTool::class,
        UpdateCookbookTool::class,
        DeleteCookbookTool::class,
    ];
}
