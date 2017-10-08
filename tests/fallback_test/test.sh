#!/usr/bin/env bash
EXPECTED_PATH=expected/all_output.expected
ACTUAL_PATH=all_output.actual
if [ ! -e $EXPECTED_PATH ]; then
	echo "Error: must run this script from tests/fallback_test folder" 1>&2
	exit 1
fi
echo "Running phan in '$PWD' ..."
rm $ACTUAL_PATH -f || exit 1
../../phan --use-fallback-parser | tee $ACTUAL_PATH
# normalize output for https://github.com/phan/phan/issues/1130
sed -i "s/ syntax error, unexpected return (T_RETURN)/ syntax error, unexpected 'return' (T_RETURN)/" $ACTUAL_PATH
# diff returns a non-zero exit code if files differ or are missing
# This outputs the 
echo
echo "Comparing the output:"
diff $EXPECTED_PATH $ACTUAL_PATH
EXIT_CODE=$?
if [ "$EXIT_CODE" == 0 ]; then
	echo "Files $EXPECTED_PATH and output $ACTUAL_PATH are identical"
fi
exit $EXIT_CODE
