#!/usr/bin/env bash
set -u

if ! php -r 'if (version_compare(PHP_VERSION, "7.4") < 0) exit(1);' ; then
    # If we got here, then the PHP version is 7.3 or lower.
    # For some reason, PHP 7.3 does not have access to the SQLite3 class in the CI environment.
    # The PHP 7.3 CI build had errors in the log:
    #
    #   Error: Class 'SQLite3' not found in /home/vsts/work/1/s/src/Phan/Plugin/Internal/PhoundPlugin.php:66
    #
    # Thus, we skip running the test if we're using PHP version 7.3 or lower.
    echo "The installed PHP version is 7.3 or lower, thus we are skipping the phound tests because we had issues using the SQLite3 extension in the CI environment on this version."
    exit 0
fi

if ! type sqlite3 >/dev/null; then
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

ACTUAL=$(sqlite3 ~/phound.db 'select * from callsites order by callsite, id')
EXPECTED=$(cat <<-EOF
\A::foo|const|src/001_phound_callsites.php:10|11
\A::foo|const|src/001_phound_callsites.php:10|12
\A::foo|prop|src/001_phound_callsites.php:11|21
\A::foo|prop|src/001_phound_callsites.php:11|22
\A::fooz|prop|src/001_phound_callsites.php:12|23
\A::fooz|prop|src/001_phound_callsites.php:12|24
\A::foo|method|src/001_phound_callsites.php:13|13
\A::foo|method|src/001_phound_callsites.php:13|14
\A::bar|method|src/001_phound_callsites.php:14|1
\A::bar|method|src/001_phound_callsites.php:14|2
\A::foo|method|src/001_phound_callsites.php:21|15
\A::foo|method|src/001_phound_callsites.php:21|16
\A::bar|method|src/001_phound_callsites.php:22|3
\A::bar|method|src/001_phound_callsites.php:22|4
\A::foo|method|src/001_phound_callsites.php:24|17
\A::foo|method|src/001_phound_callsites.php:24|18
\A::bar|method|src/001_phound_callsites.php:25|5
\A::bar|method|src/001_phound_callsites.php:25|6
\A::baz|method|src/001_phound_callsites.php:26|9
\A::baz|method|src/001_phound_callsites.php:26|10
\A::bar|method|src/001_phound_callsites.php:31|7
\A::bar|method|src/001_phound_callsites.php:31|8
\A::foo|method|src/001_phound_callsites.php:34|19
\A::foo|method|src/001_phound_callsites.php:34|20
\Closure::fromCallable|method|src/001_phound_callsites.php:34|25
\Closure::fromCallable|method|src/001_phound_callsites.php:34|26
EOF
)

# diff returns a non-zero exit code if files differ or are missing
# This outputs the difference between actual and expected output.
echo
echo "Comparing the output:"

if type colordiff >/dev/null; then
    DIFF="colordiff"
else
    DIFF="diff"
fi

$DIFF <(echo "$EXPECTED") <(echo "$ACTUAL")
EXIT_CODE=$?
if [ "$EXIT_CODE" == 0 ]; then
    echo "The sqlite3 DB content matches what was expected"
else
    echo "The sqlite3 DB content does not match what was expected"
    exit $EXIT_CODE
fi
