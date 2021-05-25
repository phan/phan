#!/usr/bin/env bash
EXPECTED_PATH=expected/all_output.expected
ACTUAL_PATH=all_output.actual
if [ ! -d expected  ]; then
	echo "Error: must run this script from tests/real_types_test folder" 1>&2
	exit 1
fi
echo "Generating test cases"
for path in $(echo expected/*.php.expected | LC_ALL=C sort); do cat $path; done > $EXPECTED_PATH
if [[ $? != 0 ]]; then
	echo "Failed to concatenate test cases" 1>&2
	exit 1
fi
echo "Running phan in '$PWD' ..."
rm $ACTUAL_PATH -f || exit 1

# We use the polyfill parser because it behaves consistently in all php versions.
../../phan --strict-type-checking --force-polyfill-parser --memory-limit 1G --redundant-condition-detection | tee $ACTUAL_PATH

# diff returns a non-zero exit code if files differ or are missing
# This outputs the difference between actual and expected output.
echo
echo "Comparing the output:"

# Normalize PHP_VERSION_ID
# and remove/replace php 8.0 warnings
sed -i \
    -e 's/\(to cast array_key_exists.* of type \)bool /\1?bool /' \
    -e 's/PhanTypeMismatchArgumentInternalReal/PhanTypeMismatchArgumentInternalProbablyReal/g' \
    -e 's/strlen(): Argument #1 (\$string) must be of type string/strlen() expects parameter 1 to be string/g' \
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
