<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MealieService
{
    protected $baseUrl;
    protected $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('mealie.base_url'), '/');
        $this->token = config('mealie.api_token');
    }

    protected function client()
    {
        return Http::withToken($this->token)
            ->baseUrl($this->baseUrl)
            ->acceptJson();
    }

    public function getRecipes($page = 1, $perPage = 50)
    {
        $response = $this->client()->get('/api/recipes', [
            'page' => $page,
            'perPage' => $perPage,
        ]);
        
        if ($response->failed()) {
            throw new \Exception("Mealie API error: " . $response->body());
        }

        return $response->json();
    }

    public function getRecipe($recipeId)
    {
        $response = $this->client()->get("/api/recipes/{$recipeId}");
        
        if ($response->failed()) {
            throw new \Exception("Mealie API error: " . $response->body());
        }

        return $response->json();
    }
}
