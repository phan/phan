#!/bin/sh

find src \
    -type f \
    -path '*.php' \
    -exec vendor/bin/phpcbf \
        --standard=PSR2 {} \;
