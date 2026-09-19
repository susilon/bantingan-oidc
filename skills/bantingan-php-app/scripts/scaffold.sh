#!/usr/bin/env bash
# Bantingan PHP App Scaffold Script
# Usage: ./scripts/scaffold.sh [target_dir]
# If target_dir not provided, uses current directory.
set -e

TARGET_DIR="${1:-.}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
TEMPLATES_DIR="$SCRIPT_DIR/../templates"

TARGET_DIR="$(cd "$TARGET_DIR" && pwd)"

echo "Scaffolding Bantingan app in: $TARGET_DIR"

# 1. composer.json
if [ -f "$TARGET_DIR/composer.json" ]; then
  echo "Warning: composer.json already exists, merging..."
  if command -v jq >/dev/null 2>&1; then
    echo "Please manually ensure repositories and require.susilon/bantingan exists. Skipping auto-merge."
  else
    echo "Please manually merge composer.json template."
  fi
else
  cp "$TEMPLATES_DIR/composer.json" "$TARGET_DIR/composer.json"
  echo "Created composer.json"
fi

# 2. Directories
mkdir -p "$TARGET_DIR/app/controllers"
mkdir -p "$TARGET_DIR/app/models"
mkdir -p "$TARGET_DIR/app/views/Shared"
mkdir -p "$TARGET_DIR/app/views/Home"
mkdir -p "$TARGET_DIR/config"
mkdir -p "$TARGET_DIR/modules"
mkdir -p "$TARGET_DIR/public"
echo "Created directory structure: app/controllers, app/models, app/views/Shared, app/views/Home, config, modules, public"

# 3. index.php
if [ -f "$TARGET_DIR/index.php" ]; then
  echo "Warning: index.php already exists, skipping"
else
  cp "$TEMPLATES_DIR/index.php" "$TARGET_DIR/index.php"
  echo "Created index.php"
fi

# 4. config/web.config.yml
if [ -f "$TARGET_DIR/config/web.config.yml" ]; then
  echo "Warning: config/web.config.yml already exists, skipping"
else
  cp "$TEMPLATES_DIR/web.config.yml" "$TARGET_DIR/config/web.config.yml"
  echo "Created config/web.config.yml"
fi

# 5. app/controllers/HomeController.php
if [ -f "$TARGET_DIR/app/controllers/HomeController.php" ]; then
  echo "Warning: app/controllers/HomeController.php already exists, skipping"
else
  mkdir -p "$TARGET_DIR/app/controllers"
  cp "$TEMPLATES_DIR/app/controllers/HomeController.php" "$TARGET_DIR/app/controllers/HomeController.php"
  echo "Created app/controllers/HomeController.php"
fi

# 6. app/views/Shared/layout.html
if [ -f "$TARGET_DIR/app/views/Shared/layout.html" ]; then
  echo "Warning: app/views/Shared/layout.html already exists, skipping"
else
  mkdir -p "$TARGET_DIR/app/views/Shared"
  cp "$TEMPLATES_DIR/app/views/Shared/layout.html" "$TARGET_DIR/app/views/Shared/layout.html"
  echo "Created app/views/Shared/layout.html"
fi

# 7. app/views/Home/index.html
if [ -f "$TARGET_DIR/app/views/Home/index.html" ]; then
  echo "Warning: app/views/Home/index.html already exists, skipping"
else
  mkdir -p "$TARGET_DIR/app/views/Home"
  cp "$TEMPLATES_DIR/app/views/Home/index.html" "$TARGET_DIR/app/views/Home/index.html"
  echo "Created app/views/Home/index.html"
fi

# 8. Dockerfile
if [ -f "$TARGET_DIR/Dockerfile" ]; then
  echo "Warning: Dockerfile already exists, skipping"
else
  cp "$TEMPLATES_DIR/Dockerfile" "$TARGET_DIR/Dockerfile"
  echo "Created Dockerfile"
fi

# 9. docker/app/entrypoint.sh
if [ -f "$TARGET_DIR/docker/app/entrypoint.sh" ]; then
  echo "Warning: docker/app/entrypoint.sh already exists, skipping"
else
  mkdir -p "$TARGET_DIR/docker/app"
  cp "$TEMPLATES_DIR/docker/app/entrypoint.sh" "$TARGET_DIR/docker/app/entrypoint.sh"
  chmod +x "$TARGET_DIR/docker/app/entrypoint.sh"
  echo "Created docker/app/entrypoint.sh"
fi

# 10. Caddyfile
if [ -f "$TARGET_DIR/Caddyfile" ]; then
  echo "Warning: Caddyfile already exists, skipping"
else
  cp "$TEMPLATES_DIR/Caddyfile" "$TARGET_DIR/Caddyfile"
  echo "Created Caddyfile"
fi

# 11. .dockerignore
if [ -f "$TARGET_DIR/.dockerignore" ]; then
  echo "Warning: .dockerignore already exists, skipping"
else
  cp "$TEMPLATES_DIR/.dockerignore" "$TARGET_DIR/.dockerignore"
  echo "Created .dockerignore"
fi

echo ""
echo "Done. Next steps:"
echo "  cd $TARGET_DIR && composer install"
echo "  php -S localhost:8000"
echo "  # or docker: docker build -t bantingan-app . && docker run -p 80:80 bantingan-app"
# then visit http://localhost:8000/ -> HomeController::index()
