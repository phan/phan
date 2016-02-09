#!/bin/bash

if [[ -z $WORKSPACE ]]
then
    export WORKSPACE=.
fi
cd $WORKSPACE

for dir in \
    src \
    tests/Phan \
    vendor/phpunit/phpunit/src vendor/symfony/console
do
    if [ -d "$dir" ]; then
        find $dir -name '*.php'
    fi
done
