#!/bin/sh
#
# ensure-go.sh - Ensure a minimum Go version is available.
#
# Checks system Go, then a local cache (.goroot/), and downloads the
# latest Go from go.dev if neither satisfies the minimum version.
#
# Usage:  ./ensure-go.sh [MIN_VERSION]
#   MIN_VERSION defaults to 1.22.0
#
# Output: prints the path to a suitable `go` binary on stdout.
#         All status/progress messages go to stderr.

set -e

MIN_VERSION="${1:-1.22.0}"
GOROOT_CACHE="$(cd "$(dirname "$0")" && pwd)/.goroot"
CACHED_GO="$GOROOT_CACHE/go/bin/go"

# --- helpers ---------------------------------------------------------------

die() {
    printf 'error: %s\n' "$1" >&2
    exit 1
}

info() {
    printf '%s\n' "$1" >&2
}

# Compare two dotted version strings (e.g. 1.25.7).
# Returns 0 (true) when $1 >= $2.
version_ge() {
    _v1="$1"; _v2="$2"

    _major1="${_v1%%.*}"; _rest1="${_v1#*.}"
    _minor1="${_rest1%%.*}"; _patch1="${_rest1#*.}"
    # handle versions with no patch component (e.g. "1.25")
    case "$_patch1" in "$_rest1") _patch1=0 ;; esac

    _major2="${_v2%%.*}"; _rest2="${_v2#*.}"
    _minor2="${_rest2%%.*}"; _patch2="${_rest2#*.}"
    case "$_patch2" in "$_rest2") _patch2=0 ;; esac

    if [ "$_major1" -gt "$_major2" ] 2>/dev/null; then return 0; fi
    if [ "$_major1" -lt "$_major2" ] 2>/dev/null; then return 1; fi
    if [ "$_minor1" -gt "$_minor2" ] 2>/dev/null; then return 0; fi
    if [ "$_minor1" -lt "$_minor2" ] 2>/dev/null; then return 1; fi
    if [ "$_patch1" -ge "$_patch2" ] 2>/dev/null; then return 0; fi
    return 1
}

# Extract the version number from `go version` output.
# "go version go1.25.7 linux/amd64" → "1.25.7"
parse_go_version() {
    "$1" version 2>/dev/null | sed -n 's/.*go\([0-9][0-9.]*\).*/\1/p'
}

# Check a go binary; print its path and exit 0 if it satisfies MIN_VERSION.
try_go() {
    _go_bin="$1"
    _label="$2"
    if [ ! -x "$_go_bin" ]; then return 1; fi
    _ver="$(parse_go_version "$_go_bin")"
    if [ -z "$_ver" ]; then return 1; fi
    if version_ge "$_ver" "$MIN_VERSION"; then
        info "$_label Go $_ver >= $MIN_VERSION — using $_go_bin"
        printf '%s\n' "$_go_bin"
        exit 0
    fi
    info "$_label Go $_ver < $MIN_VERSION"
    return 1
}

# Portable download: prefers curl, falls back to wget.
# Uses connect timeout and overall timeout to avoid hanging on slow networks.
download() {
    _url="$1"; _out="$2"
    if command -v curl >/dev/null 2>&1; then
        curl -fsSL --connect-timeout 30 --max-time 300 --retry 2 -o "$_out" "$_url"
    elif command -v wget >/dev/null 2>&1; then
        wget -q --connect-timeout=30 --timeout=300 --tries=3 -O "$_out" "$_url"
    else
        die "neither curl nor wget found"
    fi
}

# Portable download to stdout.
download_stdout() {
    _url="$1"
    if command -v curl >/dev/null 2>&1; then
        curl -fsSL --connect-timeout 30 --max-time 60 --retry 2 "$_url"
    elif command -v wget >/dev/null 2>&1; then
        wget -q --connect-timeout=30 --timeout=60 --tries=3 -O- "$_url"
    else
        die "neither curl nor wget found"
    fi
}

# --- 1. Try system Go -----------------------------------------------------

SYSTEM_GO="$(command -v go 2>/dev/null || true)"
if [ -n "$SYSTEM_GO" ]; then
    try_go "$SYSTEM_GO" "System" || true
fi

# --- 2. Try cached Go -----------------------------------------------------

try_go "$CACHED_GO" "Cached" || true

# --- 3. Download latest Go ------------------------------------------------

info "Downloading Go toolchain (need >= $MIN_VERSION)..."

# Detect OS
KERNEL="$(uname -s)"
case "$KERNEL" in
    Linux)  OS=linux ;;
    Darwin) OS=darwin ;;
    *)      die "unsupported OS: $KERNEL (need Linux or macOS)" ;;
esac

# Detect architecture
ARCH="$(uname -m)"
case "$ARCH" in
    x86_64|amd64)   GOARCH=amd64 ;;
    aarch64|arm64)   GOARCH=arm64 ;;
    *)               die "unsupported architecture: $ARCH" ;;
esac

# Fetch version list from go.dev
DL_JSON="$(download_stdout "https://go.dev/dl/?mode=json")"

# Parse latest version and checksum.
# Prefer jq if available; fall back to grep/sed.
if command -v jq >/dev/null 2>&1; then
    GO_VERSION="$(printf '%s' "$DL_JSON" | jq -r '.[0].version')"
    GO_SHA256="$(printf '%s' "$DL_JSON" \
        | jq -r ".[0].files[] | select(.os==\"$OS\" and .arch==\"$GOARCH\" and .kind==\"archive\") | .sha256")"
    # jq outputs literal "null" for missing fields
    case "$GO_VERSION" in "null"|"") GO_VERSION="" ;; esac
    case "$GO_SHA256" in "null"|"") GO_SHA256="" ;; esac
else
    # Fallback: extract from JSON with grep/sed.  The first "version" key
    # in the array is the latest stable release.
    GO_VERSION="$(printf '%s' "$DL_JSON" \
        | grep -o '"version" *: *"go[0-9.]*"' \
        | head -1 \
        | sed 's/.*"go\([0-9.]*\)".*/go\1/')"

    # Extract the sha256 for our os/arch archive.
    # The JSON block for each file looks like:
    #   "filename":"go1.X.Y.linux-amd64.tar.gz", ... "sha256":"abc..."
    FILENAME="${GO_VERSION}.${OS}-${GOARCH}.tar.gz"
    # Escape dots for use in grep regex (dots are the only metachar in Go filenames)
    FILENAME_RE="$(printf '%s' "$FILENAME" | sed 's/\./\\./g')"
    GO_SHA256="$(printf '%s' "$DL_JSON" \
        | tr '\n' ' ' \
        | grep -o "\"filename\" *: *\"${FILENAME_RE}\"[^}]*" \
        | grep -o '"sha256" *: *"[0-9a-f]*"' \
        | sed 's/.*"\([0-9a-f]*\)".*/\1/')"
fi

if [ -z "$GO_VERSION" ]; then
    die "could not determine latest Go version"
fi

TARBALL="${GO_VERSION}.${OS}-${GOARCH}.tar.gz"
DL_URL="https://go.dev/dl/${TARBALL}"

info "Downloading $DL_URL ..."

mkdir -p "$GOROOT_CACHE"
TARBALL_PATH="$GOROOT_CACHE/$TARBALL"

# Serialize concurrent download/install operations with flock when available.
LOCKFILE="$GOROOT_CACHE/.lock"
if command -v flock >/dev/null 2>&1; then
    lock_fd=9
    eval "exec $lock_fd>\"$LOCKFILE\""
    if ! flock -w 300 $lock_fd; then
        die "timed out waiting for lock on $LOCKFILE"
    fi
else
    info "flock not found; continuing without install lock (concurrent runs may race)."
fi
# Re-check cached Go under lock — another process may have installed it.
if [ -x "$CACHED_GO" ]; then
    _ver="$(parse_go_version "$CACHED_GO")"
    if [ -n "$_ver" ] && version_ge "$_ver" "$MIN_VERSION"; then
        info "Cached Go $_ver >= $MIN_VERSION (installed by another process) — using $CACHED_GO"
        printf '%s\n' "$CACHED_GO"
        exit 0
    fi
fi

download "$DL_URL" "$TARBALL_PATH"

# Verify checksum
if [ -n "$GO_SHA256" ]; then
    if command -v sha256sum >/dev/null 2>&1; then
        ACTUAL_SHA256="$(sha256sum "$TARBALL_PATH" | cut -d' ' -f1)"
    elif command -v shasum >/dev/null 2>&1; then
        ACTUAL_SHA256="$(shasum -a 256 "$TARBALL_PATH" | cut -d' ' -f1)"
    else
        rm -f "$TARBALL_PATH"
        die "neither sha256sum nor shasum found"
    fi
    if [ "$ACTUAL_SHA256" != "$GO_SHA256" ]; then
        rm -f "$TARBALL_PATH"
        die "SHA256 mismatch: expected $GO_SHA256, got $ACTUAL_SHA256"
    fi
    info "SHA256 checksum verified."
else
    rm -f "$TARBALL_PATH"
    die "could not extract checksum from API; refusing to install unverified tarball"
fi

# Extract to a temp directory first, then atomically swap into place.
EXTRACT_TMP="$GOROOT_CACHE/go.tmp.$$"
rm -rf "$EXTRACT_TMP"
mkdir -p "$EXTRACT_TMP"
if ! tar -C "$EXTRACT_TMP" -xzf "$TARBALL_PATH"; then
    rm -rf "$EXTRACT_TMP"
    die "tar extraction failed; tarball kept at $TARBALL_PATH"
fi
rm -f "$TARBALL_PATH"

# Verify the new binary works before swapping
NEW_GO="$EXTRACT_TMP/go/bin/go"
NEW_VER="$(parse_go_version "$NEW_GO")"
if [ -z "$NEW_VER" ]; then
    rm -rf "$EXTRACT_TMP"
    die "downloaded Go binary does not work"
fi
if ! version_ge "$NEW_VER" "$MIN_VERSION"; then
    rm -rf "$EXTRACT_TMP"
    die "downloaded Go $NEW_VER still < $MIN_VERSION"
fi

# Atomic swap: move old aside, install new, then clean up.
# If mv fails, restore the previous version.
OLD_GO="$GOROOT_CACHE/go.old.$$"
if [ -d "$GOROOT_CACHE/go" ]; then
    mv "$GOROOT_CACHE/go" "$OLD_GO" || {
        rm -rf "$EXTRACT_TMP"
        die "failed to move existing cached Go aside"
    }
fi
if mv "$EXTRACT_TMP/go" "$GOROOT_CACHE/go"; then
    rm -rf "$EXTRACT_TMP" "$OLD_GO"
else
    # Restore previous version on failure
    [ -d "$OLD_GO" ] && mv "$OLD_GO" "$GOROOT_CACHE/go" 2>/dev/null || true
    rm -rf "$EXTRACT_TMP"
    die "failed to install new Go into $GOROOT_CACHE/go"
fi

info "Installed Go $NEW_VER to $CACHED_GO"
printf '%s\n' "$CACHED_GO"
