#!/usr/bin/env bash
EXPECTED_PATH=expected/all_output.expected
ACTUAL_PATH=all_output.actual
if [ ! -d expected  ]; then
	echo "Error: must run this script from tests/plugin_test folder" 1>&2
	exit 1
fi
echo "Generating test cases"

PHP_VERSION_ID=$(php -r "echo PHP_VERSION_ID;")
for path in $(echo expected/*.php*.expected | LC_ALL=C sort); do
    original_path="$path"
    if [[ "$PHP_VERSION_ID" -ge 80400 ]]; then
        alternate_path=${original_path/.expected/.expected84}
        if [ -f "$alternate_path" ]; then
            path="$alternate_path"
        fi
    fi
    if [[ "$PHP_VERSION_ID" -ge 80500 ]]; then
        alternate_path=${original_path/.expected/.expected85}
        if [ -f "$alternate_path" ]; then
            path="$alternate_path"
        fi
    fi
    cat $path;
done > $EXPECTED_PATH
if [[ $? != 0 ]]; then
	echo "Failed to concatenate test cases" 1>&2
	exit 1
fi
echo "Running phan in '$PWD' ..."
rm -f $ACTUAL_PATH || exit 1

# We use the polyfill parser because it behaves consistently in all php versions.
../../phan --force-polyfill-parser --memory-limit 1G | tee $ACTUAL_PATH

sed -i -e 's,\<closure_[0-9a-f]\{12\}\>,closure_%s,g' \
    -e "s,[^']*plugin_test[/\\\\]*,,g" \
    -e 's,\(PhanTypeErrorInInternalCall.*\)integer given,\1int given,g' \
    $ACTUAL_PATH $EXPECTED_PATH

sed -i 's,missing closing parenthesis,missing ),g' $ACTUAL_PATH

# diff returns a non-zero exit code if files differ or are missing
# This outputs the difference between actual and expected output.
echo
echo "Comparing the output:"

# Normalize PHP_VERSION_ID, paths, and version-dependent messages
sed -i -e 's/^\(src.020_bool.php.*of type\) [0-9]\+ \(evaluated\)/\1 int \2/g' \
    -e 's/alphanumeric, backslash, or NUL byte/alphanumeric or backslash/g' \
    -e 's/alphanumeric, backslash, or NUL/alphanumeric or backslash/g' \
    -e "s/Unknown modifier 'e'/The \\/e modifier is no longer supported, use preg_replace_callback instead/g" \
    -e 's/('\''Not using'\'' . " args\\n")/"Not using args\\n"/g' \
    -e 's/src\\/src\//g' \
    $ACTUAL_PATH

if type colordiff >/dev/null; then
    DIFF=colordiff
else
    DIFF=diff
fi

$DIFF $EXPECTED_PATH $ACTUAL_PATH
EXIT_CODE=$?
if [ "$EXIT_CODE" == 0 ]; then
	echo "Files $EXPECTED_PATH and output $ACTUAL_PATH are identical"
    rm $ACTUAL_PATH
fi
exit $EXIT_CODE
