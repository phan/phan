#!/usr/bin/env bash

function build {
    phpize
    ./configure
    make
}

function cleanBuild {
    make clean
    build
}

function install {
    make install
    echo "extension=ast.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
}

# Ensure the build directory exists
[[ -d "build" ]] || mkdir build

# Ensure that the PHP version hasn't changed under us. If it has, we'll have to
# rebuild the extension.
if [[ -e "build/phpversion.txt" ]]; then
  if ! diff -q build/phpversion.txt <(php -r "echo PHP_VERSION_ID;"); then
    # Something has changed, so nuke the build/ast directory if it exists.
    echo "New version of PHP detected. Removing build/ast so we can do a fresh build."
    rm -rf build/ast
  fi
fi

# Ensure that we have a copy of the ast extension source code.
if [[ ! -e "build/ast/config.m4" ]]; then
    # If build/ast exists, but build/ast/config.m4 doesn't, nuke it and start over.
    [[ ! -d "build/ast" ]] || rm -rf build/ast
    git clone --depth 1 https://github.com/nikic/php-ast.git build/ast
fi

# Install the ast extension
pushd ./build/ast
  # If we don't have ast.so, we have to build it.
  if [[ ! -e "modules/ast.so" ]]; then
      echo "No cached extension found. Building..."
      build
  else
      # If there are new commits, we need to rebuild the extension.
      git fetch origin master
      newCommits=$(git rev-list HEAD...origin/master --count)
      if [[ "$newCommits" != "0" ]]; then
          echo "New commits found upstream. Updating and rebuilding..."
          git pull origin master
          cleanBuild
      else
          echo "Using cached extension."
      fi
  fi

  # No matter what, we still have to move the .so into place and enable it.
  install
popd

# Note the PHP version for later builds.
php -r "echo PHP_VERSION_ID;" > build/phpversion.txt

# Disable xdebug, since we aren't currently gathering code coverage data and
# having xdebug slows down Composer a bit.
phpenv config-rm xdebug.ini
