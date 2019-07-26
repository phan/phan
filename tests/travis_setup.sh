#!/usr/bin/env bash
# NOTE: This is installing the master branch of php-ast to test Phan's compatibility with it.
# Many ci jobs can instead use `pecl install ast` to install php-ast 1.0.1+
if [[ "x${TRAVIS:-}" == "x" ]]; then
    echo "This should only be run in travis"
    exit 1
fi

set -xeu

# Ensure the build directory exists.
PHP_VERSION_ID=$(php -r "echo PHP_VERSION_ID . '_' . PHP_DEBUG . '_' . PHP_ZTS . '_new';")
PHAN_BUILD_DIR="$HOME/.cache/phan-ast"
EXPECTED_AST_FILE="$PHAN_BUILD_DIR/build/php-ast-$PHP_VERSION_ID.so"

[[ -d "$PHAN_BUILD_DIR" ]] || mkdir -p "$PHAN_BUILD_DIR"

cd "$PHAN_BUILD_DIR"

if [[ ! -e "$EXPECTED_AST_FILE" ]]; then
  echo "No cached extension found. Building..."
  rm -rf php-ast build
  mkdir build

  git clone --depth 1 https://github.com/nikic/php-ast.git php-ast

  export CFLAGS="-O3"
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

echo "extension=$EXPECTED_AST_FILE" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

php -r 'function_exists("ast\parse_code") || (print("Failed to enable php-ast\n") && exit(1));'

# Disable xdebug if it's enabled, since we aren't currently gathering code coverage data and
# having xdebug slows down Composer a bit.
# I'm keeping the `|| true` here for future PHP versions (7.4, 8.0, etc)
phpenv config-rm xdebug.ini || true
