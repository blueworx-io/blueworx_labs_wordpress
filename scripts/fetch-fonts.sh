#!/usr/bin/env bash
#
# Fetches the latin-subset woff2 for Sora + Inter from Google Fonts and stores
# them as stable, self-hosted assets under assets/fonts/. Re-runnable.
#
set -euo pipefail

UA="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36"
OUT="assets/fonts"
mkdir -p "$OUT"

fetch() { # family weight outname
  local css url
  css="$(curl -fsSL -A "$UA" "https://fonts.googleapis.com/css2?family=$1:wght@$2&display=swap")"
  # Take the woff2 URL from the "/* latin */" block (skip latin-ext/cyrillic/greek/vietnamese).
  url="$(printf '%s\n' "$css" | awk '/\/\* latin \*\//{f=1} f&&/woff2/{match($0,/https:[^)]+woff2/); print substr($0,RSTART,RLENGTH); exit}')"
  if [ -z "$url" ]; then
    echo "ERROR: no latin woff2 found for $1 $2" >&2
    exit 1
  fi
  curl -fsSL -A "$UA" "$url" -o "$OUT/$3"
  echo "saved $OUT/$3"
}

fetch Sora 400 sora-400.woff2
fetch Sora 600 sora-600.woff2
fetch Sora 700 sora-700.woff2
fetch Inter 400 inter-400.woff2
fetch Inter 500 inter-500.woff2
fetch Inter 600 inter-600.woff2
