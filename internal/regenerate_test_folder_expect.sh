#!/usr/bin/env bash
if [ ! -e all_output.actual ]; then
    echo "This script must be run after running test.sh in a folder such as tests/plugin_test"
    exit 1
fi
# Regenerates the files in a folder with all_output.actual
for file in expected/*.php.expected; do
    basename=$(basename "$file")
    srcname=${basename/\.expected/}
    echo "Regenerating $srcname"
    grep -E "^src/$srcname" all_output.actual > $file;
done
