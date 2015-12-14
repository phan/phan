#!/usr/bin/env bash

# Ensure the build directory exists
[[ -d "build" ]] || mkdir build

# Ensure that we have a copy of the ast extension
[[ -d "build/ast" ]] || git clone --depth 1 https://github.com/nikic/php-ast.git build/ast

# Install the ast extension
pushd ./build/ast
  phpize
  ./configure
  make
  make install
  echo "extension=ast.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
popd

# Update composer.
travis_retry composer self-update
