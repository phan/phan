#!/bin/sh

find src \
    -type f \
    -path '*.php' \
    -exec phpcbf \
        --standard=PSR2 {} \;
