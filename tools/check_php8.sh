#!/usr/bin/env bash
# Production may run PHP 7.4: fail on PHP 8-only functions.
# php -l on 7.4 already catches PHP 8 syntax (match, ?->, named args, union types).
set -euo pipefail
pattern='\b(str_contains|str_starts_with|str_ends_with|get_debug_type|get_resource_id|fdiv|array_is_list|enum_exists)\s*\('
if git grep -nE "$pattern" -- '*.php' ':!tools/*'; then
  echo "::error::PHP 8-only functions found (production may be PHP 7.4). Use strpos()/substr() instead."
  exit 1
fi
echo "OK: no PHP 8-only functions"
