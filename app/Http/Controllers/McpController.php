<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Services\MealieService;

class McpController extends Controller
{
    protected $mealieService;

    public function __construct(MealieService $mealieService)
    {
        $this->mealieService = $mealieService;
    }

    public function sse(Request $request)
    {
        // Disable execution time limit for long running SSE process
        set_time_limit(0);

        $sessionId = uniqid('mcp_', true);

        return response()->stream(function () use ($sessionId) {
            // Send initial endpoint event
            $endpoint = url("/mcp/messages?sessionId={$sessionId}");
            echo "event: endpoint\n";
            echo "data: {$endpoint}\n\n";
            ob_flush();
            flush();

            // Loop and check cache for new messages directed to this session
            while (true) {
                if (connection_aborted()) {
                    break;
                }

                $messages = Cache::pull("mcp_messages_{$sessionId}");
                if ($messages) {
                    foreach ($messages as $msg) {
                        echo "event: message\n";
                        echo "data: " . json_encode($msg) . "\n\n";
                    }
                    ob_flush();
                    flush();
                }

                // Sleep briefly to prevent high CPU usage
                usleep(500000); // 500ms
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no' // Prevent Nginx/Plesk from buffering SSE
        ]);
    }

    public function messages(Request $request)
    {
        $sessionId = $request->query('sessionId');
        if (!$sessionId) {
            return response()->json(['error' => 'Missing sessionId'], 400);
        }

        $payload = $request->json()->all();

        // Process the JSON-RPC payload
        $response = $this->handleJsonRpc($payload);

        if ($response) {
            // Push response to cache for the SSE process to pick up
            $messages = Cache::get("mcp_messages_{$sessionId}", []);
            $messages[] = $response;
            Cache::put("mcp_messages_{$sessionId}", $messages, 60);
        }

        return response('Accepted', 202);
    }

    protected function handleJsonRpc($payload)
    {
        // Basic JSON-RPC validation
        if (!isset($payload['jsonrpc']) || $payload['jsonrpc'] !== '2.0') {
            return null;
        }

        $id = $payload['id'] ?? null;
        $method = $payload['method'] ?? '';
        $params = $payload['params'] ?? [];

        try {
            $result = null;

            switch ($method) {
                case 'initialize':
                    $result = [
                        'protocolVersion' => '2024-11-05',
                        'capabilities' => [
                            'tools' => []
                        ],
                        'serverInfo' => [
                            'name' => 'mealie-mcp-laravel',
                            'version' => '1.0.0'
                        ]
                    ];
                    break;

                case 'notifications/initialized':
                    // Just acknowledge, no response needed for notifications
                    return null;

                case 'tools/list':
                    $result = [
                        'tools' => [
                            [
                                'name' => 'mealie_list_recipes',
                                'description' => 'List all recipes from Mealie',
                                'inputSchema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'page' => ['type' => 'number', 'description' => 'Page number'],
                                        'perPage' => ['type' => 'number', 'description' => 'Results per page'],
                                        'search' => ['type' => 'string', 'description' => 'Partial string to search recipes by name or content']
                                    ]
                                ]
                            ],
                            [
                                'name' => 'mealie_get_recipe',
                                'description' => 'Get a specific recipe by ID or slug',
                                'inputSchema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'recipeId' => ['type' => 'string', 'description' => 'The ID or slug of the recipe']
                                    ],
                                    'required' => ['recipeId']
                                ]
                            ],
                            [
                                'name' => 'mealie_get_cookbooks',
                                'description' => 'Get a list of cookbooks for a household',
                                'inputSchema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'householdId' => ['type' => 'string', 'description' => 'The ID of the household']
                                    ],
                                    'required' => ['householdId']
                                ]
                            ],
                            [
                                'name' => 'mealie_create_cookbook',
                                'description' => 'Create a new cookbook',
                                'inputSchema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'householdId' => ['type' => 'string', 'description' => 'The ID of the household'],
                                        'name' => ['type' => 'string', 'description' => 'Name of the cookbook']
                                    ],
                                    'required' => ['householdId', 'name']
                                ]
                            ],
                            [
                                'name' => 'mealie_update_cookbook',
                                'description' => 'Update an existing cookbook',
                                'inputSchema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'householdId' => ['type' => 'string', 'description' => 'The ID of the household'],
                                        'cookbookId' => ['type' => 'string', 'description' => 'The ID of the cookbook to update'],
                                        'name' => ['type' => 'string', 'description' => 'New name for the cookbook']
                                    ],
                                    'required' => ['householdId', 'cookbookId', 'name']
                                ]
                            ],
                            [
                                'name' => 'mealie_delete_cookbook',
                                'description' => 'Delete a cookbook',
                                'inputSchema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'householdId' => ['type' => 'string', 'description' => 'The ID of the household'],
                                        'cookbookId' => ['type' => 'string', 'description' => 'The ID of the cookbook to delete']
                                    ],
                                    'required' => ['householdId', 'cookbookId']
                                ]
                            ]
                        ]
                    ];
                    break;

                case 'tools/call':
                    $toolName = $params['name'] ?? '';
                    $toolArgs = $params['arguments'] ?? [];
                    
                    if ($toolName === 'mealie_list_recipes') {
                        $page = $toolArgs['page'] ?? 1;
                        $perPage = $toolArgs['perPage'] ?? 50;
                        $search = $toolArgs['search'] ?? null;
                        $recipes = $this->mealieService->getRecipes($page, $perPage, $search);
                        $result = [
                            'content' => [
                                ['type' => 'text', 'text' => json_encode($recipes, JSON_PRETTY_PRINT)]
                            ]
                        ];
                    } elseif ($toolName === 'mealie_get_recipe') {
                        $recipeId = $toolArgs['recipeId'] ?? '';
                        if (!$recipeId) {
                            throw new \Exception("Missing recipeId argument");
                        }
                        $recipe = $this->mealieService->getRecipe($recipeId);
                        $result = [
                            'content' => [
                                ['type' => 'text', 'text' => json_encode($recipe, JSON_PRETTY_PRINT)]
                            ]
                        ];
                    } elseif ($toolName === 'mealie_get_cookbooks') {
                        $householdId = $toolArgs['householdId'] ?? '';
                        if (!$householdId) {
                            throw new \Exception("Missing householdId argument");
                        }
                        $cookbooks = $this->mealieService->getCookbooks($householdId);
                        $result = [
                            'content' => [['type' => 'text', 'text' => json_encode($cookbooks, JSON_PRETTY_PRINT)]]
                        ];
                    } elseif ($toolName === 'mealie_create_cookbook') {
                        $householdId = $toolArgs['householdId'] ?? '';
                        $name = $toolArgs['name'] ?? '';
                        if (!$householdId || !$name) throw new \Exception("Missing householdId or name argument");
                        
                        $cookbook = $this->mealieService->createCookbook($householdId, ['name' => $name]);
                        $result = [
                            'content' => [['type' => 'text', 'text' => json_encode($cookbook, JSON_PRETTY_PRINT)]]
                        ];
                    } elseif ($toolName === 'mealie_update_cookbook') {
                        $householdId = $toolArgs['householdId'] ?? '';
                        $cookbookId = $toolArgs['cookbookId'] ?? '';
                        $name = $toolArgs['name'] ?? '';
                        if (!$householdId || !$cookbookId || !$name) throw new \Exception("Missing arguments");
                        
                        $cookbook = $this->mealieService->updateCookbook($householdId, $cookbookId, ['name' => $name]);
                        $result = [
                            'content' => [['type' => 'text', 'text' => json_encode($cookbook, JSON_PRETTY_PRINT)]]
                        ];
                    } elseif ($toolName === 'mealie_delete_cookbook') {
                        $householdId = $toolArgs['householdId'] ?? '';
                        $cookbookId = $toolArgs['cookbookId'] ?? '';
                        if (!$householdId || !$cookbookId) throw new \Exception("Missing arguments");
                        
                        $response = $this->mealieService->deleteCookbook($householdId, $cookbookId);
                        $result = [
                            'content' => [['type' => 'text', 'text' => json_encode($response, JSON_PRETTY_PRINT)]]
                        ];
                    } else {
                        throw new \Exception("Tool not found: {$toolName}");
                    }
                    break;

                default:
                    // Method not found
                    if ($id !== null) {
                        return [
                            'jsonrpc' => '2.0',
                            'id' => $id,
                            'error' => [
                                'code' => -32601,
                                'message' => 'Method not found'
                            ]
                        ];
                    }
                    return null;
            }

            if ($id !== null) {
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => $result
                ];
            }
        } catch (\Exception $e) {
            if ($id !== null) {
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'error' => [
                        'code' => -32000,
                        'message' => $e->getMessage()
                    ]
                ];
            }
        }

        return null;
    }
}
