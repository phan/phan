#!/usr/bin/env bash
# See https://github.com/composer/composer/issues/6366

SCRIPT_PATH="${BASH_SOURCE[0]}"
cd "$( dirname "$SCRIPT_PATH" )"
if type composer.phar 2>/dev/null >/dev/null; then
    composer.phar install --ignore-platform-reqs
else
    composer install --ignore-platform-reqs
fi
