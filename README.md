# Mealie MCP Server

A Laravel-based [Model Context Protocol](https://modelcontextprotocol.io) server for [Mealie](https://mealie.io/). It exposes your Mealie instance as MCP tools so AI clients (like Cursor, Claude Desktop, or others) can manage your recipes and cookbooks on your behalf.

> **Note:** This server communicates with Mealie via its REST API (`/api`). It features a custom Server-Sent Events (SSE) implementation built natively in Laravel, making it perfect for deploying on standard web hosts like Plesk without needing terminal access to run background Node.js daemons.

## How It Works

```
MCP Client (e.g., Cursor, Claude)
        │
        ▼ (SSE Connection)
mealie-mcp.yourdomain.com/mcp/sse
        │
        ├── McpController (Holds connection open)
        │   Returns an endpoint URL for messages.
        │
        ▼ (POST JSON-RPC Messages)
mealie-mcp.yourdomain.com/mcp/messages
        │
        └── MCP Tools → Mealie REST API
```

**Authentication** is handled using a Mealie API token. You must generate a long-lived API token from your Mealie instance's User Profile page and provide it in your `.env` file.

---

## Requirements

- PHP 8.2+
- Composer (if installing manually, or let Plesk handle it)
- A Mealie instance
- An API token from your Mealie User Profile

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

Open `.env` and configure your Mealie URL and Token. **Crucially, make sure your cache store is set to `file`** so the SSE queue works without needing database migrations.

```env
CACHE_STORE=file

MEALIE_BASE_URL=http://your-mealie-instance.com:9000
MEALIE_API_TOKEN=your_mealie_api_token_here
```

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

Visit `https://mealie-mcp.yourdomain.com/mcp/sse` in your browser. You should see a blank page that doesn't stop loading, and if you check the network tab, you will see the `event: endpoint` stream. This confirms the SSE loop is running!

---

## Connecting an MCP Client

When configuring your AI client, you will set the transport method to **SSE** and point it to the SSE route.

*(Note: Depending on your specific AI client, configuration structures vary. Below is a generic example for clients supporting remote SSE URLs).*

```json
{
  "mcpServers": {
    "mealie": {
      "type": "sse",
      "url": "https://mealie-mcp.yourdomain.com/mcp/sse"
    }
  }
}
```

---

## Available Tools

| Tool | Description |
|---|---|
| `mealie_list_recipes` | List recipes from Mealie. Supports pagination (`page`, `perPage`) and partial string searches (`search`). |
| `mealie_get_recipe` | Get full details for a single recipe by its ID or slug. |
| `mealie_get_cookbooks` | Get a list of cookbooks for a specific household (`householdId`). |
| `mealie_create_cookbook` | Create a new cookbook (requires `householdId` and `name`). |
| `mealie_update_cookbook` | Update the name of an existing cookbook. |
| `mealie_delete_cookbook` | Delete a cookbook. |

---

## Project Structure

```
app/
├── Http/
│   └── Controllers/
│       └── McpController.php      # Handles the SSE loop and JSON-RPC tool logic
└── Services/
    └── MealieService.php          # HTTP client wrapper for the Mealie REST API

routes/
└── web.php   # Registers /mcp/sse and /mcp/messages

bootstrap/app.php       # Excludes /mcp/* routes from CSRF token validation
config/mealie.php       # Reads MEALIE_BASE_URL and MEALIE_API_TOKEN from .env
```
