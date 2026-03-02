#!/bin/sh
#
# Build the phan-mcp binary.
# Downloads Go automatically if not installed.
#
set -e

cd "$(dirname "$0")"

GO="$(./ensure-go.sh)"
info() { printf '%s\n' "$1" >&2; }

info "Building phan-mcp..."
"$GO" build -o phan-mcp .
info "Built: $(pwd)/phan-mcp"
