#!/usr/bin/env bash
# This test suite tests rare config options and configuration modes.
EXPECTED_PATH=expected/all_output.expected
ACTUAL_PATH=all_output.actual
if [ ! -d expected  ]; then
	echo "Error: must run this script from tests/misc/fallback_test folder" 1>&2
	exit 1
fi
for path in $(echo expected/*.php.expected | LC_ALL=C sort); do cat $path; done > $EXPECTED_PATH
if [[ $? != 0 ]]; then
	echo "Failed to concatenate test cases" 1>&2
	exit 1
fi
echo "Running phan in '$PWD' ..."
rm -f $ACTUAL_PATH || exit 1
../../../phan --output-mode pylint | tee $ACTUAL_PATH
# diff returns a non-zero exit code if files differ or are missing
echo
echo "Comparing the output:"
diff $EXPECTED_PATH $ACTUAL_PATH
EXIT_CODE=$?
if [ "$EXIT_CODE" == 0 ]; then
	echo "Files $EXPECTED_PATH and output $ACTUAL_PATH are identical"
    rm $ACTUAL_PATH
fi
exit $EXIT_CODE
