#!/usr/bin/env bash
# Sync skills from canonical source to all AI app directories
# For Windows or environments where symlinks are not supported
# Source: skills/ -> .claude/skills, .codex/skills, .opencode/skills, .agents/skills
set -e
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/skills/bantingan-php-app"
TARGETS=(
  "$ROOT/.claude/skills/bantingan-php-app"
  "$ROOT/.codex/skills/bantingan-php-app"
  "$ROOT/.opencode/skills/bantingan-php-app"
  "$ROOT/.agents/skills/bantingan-php-app"
)

if [ ! -d "$SRC" ]; then
  echo "Source not found: $SRC" >&2
  exit 1
fi

for dst in "${TARGETS[@]}"; do
  # Skip if already a valid symlink to source
  if [ -L "$dst" ]; then
    target=$(readlink "$dst")
    resolved="$(cd "$(dirname "$dst")" && cd "$target" 2>/dev/null && pwd || echo "")"
    if [ "$resolved" = "$SRC" ]; then
      echo "OK symlink: $dst -> $target"
      continue
    fi
    echo "Removing stale symlink: $dst"
    rm -f "$dst"
  fi

  if [ -d "$dst" ] && [ ! -L "$dst" ]; then
    echo "Removing directory copy: $dst"
    rm -rf "$dst"
  fi

  # Try symlink first, fallback to copy on Windows/failure
  mkdir -p "$(dirname "$dst")"
  if ln -s "../../skills/bantingan-php-app" "$dst" 2>/dev/null; then
    echo "Linked: $dst -> ../../skills/bantingan-php-app"
  else
    echo "Symlink failed, copying: $dst"
    cp -R "$SRC" "$dst"
    echo "Copied: $dst"
  fi
done

echo "Done."
