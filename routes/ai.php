<?php

use App\Mcp\Servers\MealieServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', MealieServer::class)
    ->middleware(['validate.mealie.token']);
