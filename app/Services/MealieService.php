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

    public function getRecipes($page = 1, $perPage = 50, $search = null)
    {
        $params = [
            'page' => $page,
            'perPage' => $perPage,
        ];
        
        if ($search) {
            $params['search'] = $search; // Mealie uses 'search' for partial matching
        }

        $response = $this->client()->get('/api/recipes', $params);
        
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

    // --- Cookbooks CRUD ---
    // Note: In newer versions of Mealie, cookbooks are scoped to a household.

    public function getCookbooks($householdId)
    {
        $response = $this->client()->get("/api/households/{$householdId}/cookbooks");
        
        if ($response->failed()) {
            throw new \Exception("Mealie API error: " . $response->body());
        }

        return $response->json();
    }

    public function createCookbook($householdId, $data)
    {
        $response = $this->client()->post("/api/households/{$householdId}/cookbooks", $data);
        
        if ($response->failed()) {
            throw new \Exception("Mealie API error: " . $response->body());
        }

        return $response->json();
    }

    public function updateCookbook($householdId, $cookbookId, $data)
    {
        $response = $this->client()->put("/api/households/{$householdId}/cookbooks/{$cookbookId}", $data);
        
        if ($response->failed()) {
            throw new \Exception("Mealie API error: " . $response->body());
        }

        return $response->json();
    }

    public function deleteCookbook($householdId, $cookbookId)
    {
        $response = $this->client()->delete("/api/households/{$householdId}/cookbooks/{$cookbookId}");
        
        if ($response->failed()) {
            throw new \Exception("Mealie API error: " . $response->body());
        }

        return $response->json();
    }
}
