#!/usr/bin/env bash
set -u

if ! which sqlite3 ; then
    echo "sqlite3, which is necessary for this test, is not installed!"
    exit 1
fi

echo "Running phan in '$PWD' ..."

rm -rf ~/phound.db

# We use the polyfill parser because it behaves consistently in all php versions.
if ! ../../phan --force-polyfill-parser --memory-limit 1G --analyze-twice ; then
    echo "Phan found some errors - this is unexpected"
    exit 1
fi

sqlite3 ~/phound.db 'select * from callsites order by callsite, id'

exit 1
