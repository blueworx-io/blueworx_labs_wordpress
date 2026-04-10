#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
BUILD_DIR="$ROOT_DIR/.build/blueworx-enhancements"
DIST_DIR="$ROOT_DIR/dist"

rm -rf "$ROOT_DIR/.build"
mkdir -p "$BUILD_DIR" "$DIST_DIR"

cp "$ROOT_DIR/blueworx-enhancements.php" "$BUILD_DIR/"

(
  cd "$ROOT_DIR/.build"
  zip -r "$DIST_DIR/blueworx-enhancements.zip" blueworx-enhancements >/dev/null
)

rm -rf "$ROOT_DIR/.build"

echo "Created: $DIST_DIR/blueworx-enhancements.zip"
