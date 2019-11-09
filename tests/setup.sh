#!/usr/bin/env bash
set -xeu

if [[ "x$TRAVIS" == "x" ]]; then
    echo "This should only be run in travis"
    exit 1
fi

# Ensure the build directory exist
PHP_VERSION_ID=$(php -r "echo PHP_VERSION_ID;")
PHAN_BUILD_DIR="$HOME/.cache/phan-ast-build"
EXPECTED_AST_FILE="$PHAN_BUILD_DIR/build/php-ast-$PHP_VERSION_ID.so"

[[ -d "$PHAN_BUILD_DIR" ]] || mkdir -p "$PHAN_BUILD_DIR"

cd "$PHAN_BUILD_DIR"

if [[ ! -e "$EXPECTED_AST_FILE" ]]; then
  echo "No cached extension found. Building..."
  rm -rf php-ast build
  mkdir build

  git clone --depth 1 https://github.com/nikic/php-ast.git php-ast

  pushd php-ast
  # Install the ast extension
  phpize
  ./configure
  make

  cp modules/ast.so "$EXPECTED_AST_FILE"
  popd
else
  echo "Using cached extension."
fi

php -r 'function_exists("ast\parse_code") || exit("Failed to enable php-ast");'

echo "extension=$EXPECTED_AST_FILE" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

# Disable Xdebug, since we aren't currently gathering code coverage data and
# having Xdebug slows down Composer a bit.
phpenv config-rm xdebug.ini
