#!/usr/bin/env bash
set -u
EXPECTED_PATH=expected/all_output.expected
ACTUAL_PATH=all_output.actual
if [ ! -d expected -o ! -d expected_src ]; then
	echo "Error: must run this script from tests/fixer_test folder" 1>&2
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

# Create a copy so we can verify the copy is changed in place
rm -rf src_copy
cp -r src src_copy

# We use the polyfill parser because it behaves consistently in all php versions.
../../phan --automatic-fix --dead-code-detection --force-polyfill-parser --memory-limit 1G | tee $ACTUAL_PATH

# diff returns a non-zero exit code if files differ or are missing
# This outputs the difference between actual and expected output.
echo
echo "Comparing the output:"

diff $EXPECTED_PATH $ACTUAL_PATH
EXIT_CODE=$?
if [ "$EXIT_CODE" == 0 ]; then
	echo "Files $EXPECTED_PATH and output $ACTUAL_PATH are identical"
	rm $ACTUAL_PATH
else
	echo "Files $EXPECTED_PATH and output $ACTUAL_PATH are different"
	exit $EXIT_CODE
fi
FOUND_EXPECTED=0
UNEXPECTED_FIX=0
for expected_src_file in expected_src/*.php; do
	FOUND_EXPECTED=1
	actual_src_file=${expected_src_file/expected_src/src_copy}
	# diff returns a non-zero exit code if files differ or are missing
	if ! diff -C 3 "$actual_src_file" "$expected_src_file"; then
		echo "phan --automatic-fix did not generate the expected fix for $actual_src_file and $expected_src_file"
		UNEXPECTED_FIX=1
	fi
done
if [ "$FOUND_EXPECTED" = 0 ]; then
	# should not happen
	echo "No php files found in expected_src"
	exit 1
fi
if [ "$UNEXPECTED_FIX" = 0 ]; then
	echo "Files in src_copy are identical to those in expected_src"
else
	echo "Files in src_copy are different from those in expected_src"
	EXIT_CODE=1
fi
exit $EXIT_CODE
