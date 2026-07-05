<?php

namespace App\Mcp\Tools;

use App\Services\MealieClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('import_recipe_from_url')]
#[Description('Import a recipe by scraping a webpage URL. Mealie parses the page and creates the recipe automatically.')]
class ImportRecipeFromUrlTool extends MealieTool
{
    public function __construct(private readonly MealieClient $client) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'url'         => $schema->string()->description('URL of the recipe webpage to import.')->required(),
            'includeTags' => $schema->boolean()->description('Also import the tags found on the page.')->default(false),
        ];
    }

    protected function execute(Request $request): Response
    {
        $slug = $this->client->post('recipes/create/url', [
            'url'         => $request->get('url'),
            'includeTags' => (bool) $request->get('includeTags', false),
        ]);

        return Response::text(json_encode($this->client->get('recipes/'.rawurlencode($slug))));
    }
}
