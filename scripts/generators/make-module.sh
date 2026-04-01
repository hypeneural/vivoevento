#!/bin/bash
# ──────────────────────────────────────────────────────────────
# Evento Vivo — Backend Module Generator
# Usage: bash scripts/generators/make-module.sh ModuleName
# ──────────────────────────────────────────────────────────────

set -e

MODULE_NAME=$1

if [ -z "$MODULE_NAME" ]; then
    echo "❌ Usage: bash scripts/generators/make-module.sh ModuleName"
    exit 1
fi

BASE_DIR="apps/api/app/Modules/$MODULE_NAME"

if [ -d "$BASE_DIR" ]; then
    echo "⚠️  Module '$MODULE_NAME' already exists at $BASE_DIR"
    exit 1
fi

echo "📦 Creating module: $MODULE_NAME"

# Create directories
DIRS=(
    "Actions"
    "Data"
    "DTOs"
    "Enums"
    "Events"
    "Exceptions"
    "Http/Controllers"
    "Http/Requests"
    "Http/Resources"
    "Jobs"
    "Listeners"
    "Models"
    "Policies"
    "Queries"
    "Services"
    "Support"
    "routes"
    "Providers"
)

for dir in "${DIRS[@]}"; do
    mkdir -p "$BASE_DIR/$dir"
    # Add .gitkeep to empty dirs
    touch "$BASE_DIR/$dir/.gitkeep"
done

# Create ServiceProvider
cat > "$BASE_DIR/Providers/${MODULE_NAME}ServiceProvider.php" << 'PROVIDER'
<?php

namespace App\Modules\MODULE_NAME\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MODULE_NAMEServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutes();
    }

    protected function loadRoutes(): void
    {
        $routeFile = __DIR__ . '/../routes/api.php';

        if (file_exists($routeFile)) {
            Route::prefix(config('modules.api_prefix') . '/' . config('modules.api_version'))
                ->middleware(['api'])
                ->group($routeFile);
        }
    }
}
PROVIDER

# Replace placeholder
sed -i "s/MODULE_NAME/$MODULE_NAME/g" "$BASE_DIR/Providers/${MODULE_NAME}ServiceProvider.php"

# Create routes file
cat > "$BASE_DIR/routes/api.php" << 'ROUTES'
<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MODULE_NAME Module Routes
|--------------------------------------------------------------------------
*/

// Route::middleware('auth:sanctum')->group(function () {
//     // Add routes here
// });
ROUTES

sed -i "s/MODULE_NAME/$MODULE_NAME/g" "$BASE_DIR/routes/api.php"

# Create README
cat > "$BASE_DIR/README.md" << README
# $MODULE_NAME Module

## Responsabilidade
<!-- Descrever a responsabilidade deste módulo -->

## Entidades
<!-- Listar as models/entidades deste módulo -->

## Rotas
<!-- Listar endpoints -->

## Dependências
<!-- Listar módulos dos quais este depende -->
README

# Remove .gitkeep from dirs that got files
rm -f "$BASE_DIR/Providers/.gitkeep"
rm -f "$BASE_DIR/routes/.gitkeep"

echo "✅ Module '$MODULE_NAME' created at $BASE_DIR"
echo ""
echo "Next steps:"
echo "  1. Register in config/modules.php"
echo "  2. Add routes in routes/api.php"
echo "  3. Create Models, Controllers, and Actions"
