# Mealie MCP Server

A Laravel-based [Model Context Protocol](https://modelcontextprotocol.io) server for [Mealie](https://mealie.io/). It exposes your Mealie instance as MCP tools so AI clients (like Claude Desktop, Claude Code, or Cursor) can manage your recipes and cookbooks on your behalf.

Built on the official [`laravel/mcp`](https://github.com/laravel/mcp) package using the Streamable HTTP transport, so it runs on standard web hosts like Plesk without background daemons.

## How It Works

```
MCP Client (e.g. Claude Desktop)
        │  Authorization: Bearer <Mealie API token>
        ▼
mealie-mcp.yourdomain.com/mcp
        │
        ├── ValidateMealieToken middleware
        │   Verifies the token against your Mealie instance
        │   (GET /api/users/self). If it fails, returns 401. If it passes,
        │   the same token is used for all subsequent API calls.
        │
        └── MCP Tools → Mealie REST API
```

**Authentication** is handled entirely by Mealie. Each connection authenticates with a long-lived Mealie API token, generated on the Mealie **User Profile → Manage API Tokens** page. The token is sent by the MCP client on every request and forwarded to Mealie — no credentials are stored on this server.

---

## Requirements

- PHP 8.3+
- Composer
- A Mealie instance and an API token from your Mealie User Profile

---

## Installation (Plesk)

### 1. Create a Subdomain

In Plesk → **Websites & Domains** → **Add Subdomain**.
Name it `mealie-mcp` (or whatever you prefer).

### 2. Clone the Repository

Open **SSH Terminal** for the subdomain (or use Plesk's Git extension) and run:

```bash
git clone https://github.com/uk7itsolutions/mealie-mcp-laravel.git .
```

### 3. Install Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

This creates `.env` from `.env.example` and generates the application key automatically. If `storage/` and `bootstrap/cache/` need to be writable by the web server:

```bash
chmod -R 775 storage bootstrap/cache
```

### 4. Configure `.env`

Open `.env` and set the Mealie URL (no trailing slash):

```env
MEALIE_BASE_URL=https://mealie.yourdomain.com
```

The app reads this via `config/mealie.php` and uses it for every API call. The API token is **not** configured here — each MCP client supplies its own token (see below).

### 5. Set the Document Root

In Plesk → **Websites & Domains** → your subdomain → **Hosting Settings**,
set the document root to:

```
mealie-mcp.yourdomain.com/public
```

### 6. Enable nginx Rewrites (Plesk)

Plesk runs nginx in front of Apache. By default, nginx tries to serve every URL as a static file and returns 404 for routes. To fix this:

1. Plesk → **Websites & Domains** → your subdomain → **Apache & nginx Settings**
2. In **Additional nginx directives**, paste:

   ```nginx
   location / {
       try_files $uri $uri/ /index.php?$query_string;
   }
   ```
3. Click **OK**.

### 7. Enable SSL

In Plesk → **SSL/TLS Certificates** → **Let's Encrypt** → check **Redirect HTTP to HTTPS** → **Get it free**.

### 8. Verify

Visit `https://mealie-mcp.yourdomain.com/` in a browser — it returns a JSON blob naming the server and its MCP endpoint. Then confirm the endpoint itself with your token:

```bash
curl https://mealie-mcp.yourdomain.com/mcp -X POST \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -H "Authorization: Bearer <your-mealie-api-token>" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"curl","version":"1.0"}}}'
```

A JSON response containing `"name":"Mealie"` confirms the server is up and your token is valid. Without the `Authorization` header you should get a 401.

---

## Connecting an MCP Client

Point your client at the Streamable HTTP endpoint and send your Mealie API token as a Bearer header:

```json
{
  "mcpServers": {
    "mealie": {
      "type": "http",
      "url": "https://mealie-mcp.yourdomain.com/mcp",
      "headers": {
        "Authorization": "Bearer <your-mealie-api-token>"
      }
    }
  }
}
```

Or with Claude Code:

```bash
claude mcp add --transport http mealie https://mealie-mcp.yourdomain.com/mcp \
  --header "Authorization: Bearer <your-mealie-api-token>"
```

---

## Available Tools

| Tool | Description |
|---|---|
| `list_recipes` | List recipes. Supports pagination (`page`, `perPage`) and partial string searches (`search`). |
| `get_recipe` | Get full details for a single recipe by its id or slug. |
| `create_recipe` | Create a recipe with optional description, servings, times, ingredients and instructions. |
| `update_recipe` | Update a recipe; ingredients/instructions replace the existing lists when given. |
| `import_recipe_from_url` | Import a recipe by scraping a webpage URL. |
| `list_foods` | List foods (ingredient items), with search — ids are used as `foodId` in recipe ingredients. |
| `create_food` | Create a food, e.g. "onion". |
| `list_units` | List measurement units, with search — ids are used as `unitId` in recipe ingredients. |
| `create_unit` | Create a measurement unit, e.g. "cup". |
| `list_households` | List the households in the current group (cookbooks are scoped to a household). |
| `list_cookbooks` | List the cookbooks of a household (`householdId`). |
| `create_cookbook` | Create a new cookbook (requires `householdId` and `name`). |
| `update_cookbook` | Update the name/description of an existing cookbook. |
| `delete_cookbook` | Delete a cookbook. |

Tool failures are returned as structured MCP errors that tell the calling AI whether the problem was reported by Mealie (invalid data, missing record, permissions) or was a connection failure — and whether retrying can help.

### Creating recipes

Mealie stores ingredients as structured data: an amount, a *unit* (cup, tablespoon, …) and a *food* (flour, onion, …), plus a free-text note. The server handles the two-step creation Mealie requires (create by name, then patch the details), and resolves the `foodId`/`unitId` references for you. A typical AI workflow:

1. `list_foods` / `list_units` to find existing ids (`create_food` / `create_unit` for anything missing)
2. `create_recipe` with structured `ingredients` (`quantity`, `unitId`, `foodId`, `note`) and `instructions`

Ingredients can also be plain text — an entry with only a `note` (e.g. `"a pinch of salt"`) works without any food/unit setup. Or skip all of it and point `import_recipe_from_url` at a recipe webpage.

---

## Troubleshooting

Set `MEALIE_DEBUG=true` (and `LOG_LEVEL=debug`) in `.env` to log every Mealie API request and response to `storage/logs/laravel.log`. Failed requests are always logged with their full response body, regardless of this flag. Run `php artisan config:clear` after changing `.env`.

---

## Project Structure

```
app/
├── Exceptions/
│   ├── MealieApiException.php       # Mealie returned 4xx/5xx (AI-readable hints)
│   └── MealieConnectionException.php # Mealie was unreachable
├── Http/
│   └── Middleware/
│       └── ValidateMealieToken.php  # Verifies the Bearer token against Mealie
├── Mcp/
│   ├── Servers/
│   │   └── MealieServer.php         # Registers the tools + server instructions
│   └── Tools/
│       ├── MealieTool.php           # Base class: converts failures to MCP errors
│       ├── Concerns/
│       │   └── BuildsRecipePayload.php  # Shared recipe fields + food/unit id resolution
│       ├── ListRecipesTool.php
│       ├── GetRecipeTool.php
│       ├── CreateRecipeTool.php
│       ├── UpdateRecipeTool.php
│       ├── ImportRecipeFromUrlTool.php
│       ├── ListFoodsTool.php
│       ├── CreateFoodTool.php
│       ├── ListUnitsTool.php
│       ├── CreateUnitTool.php
│       ├── ListHouseholdsTool.php
│       ├── ListCookbooksTool.php
│       ├── CreateCookbookTool.php
│       ├── UpdateCookbookTool.php
│       └── DeleteCookbookTool.php
└── Services/
    └── MealieClient.php             # HTTP client wrapper for the Mealie REST API

routes/ai.php           # Registers the MCP server at /mcp (Streamable HTTP)
bootstrap/app.php       # Middleware alias + CSRF exemption for /mcp
config/mealie.php       # Reads MEALIE_BASE_URL and MEALIE_DEBUG from .env
```
