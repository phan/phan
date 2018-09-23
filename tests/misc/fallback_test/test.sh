#!/usr/bin/env bash
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
rm $ACTUAL_PATH -f || exit 1
../../../phan --use-fallback-parser 2>&1 | tee $ACTUAL_PATH
# normalize output for https://github.com/phan/phan/issues/1130
sed -i "s/ syntax error, unexpected return (T_RETURN)/ syntax error, unexpected 'return' (T_RETURN)/" $ACTUAL_PATH
sed -i "s/ syntax error, unexpected new (T_NEW)/ syntax error, unexpected 'new' (T_NEW)/" $ACTUAL_PATH
# This warns in php 7.0 only, but the important thing to test is that the fallback doesn't crash.
sed -i "/src\/018_list_expression_18\.php:2 PhanSyntaxError syntax error, unexpected '0'/d" $ACTUAL_PATH
# This has a varying order for src/020_issue.php
sed -i "s/anonymous_class_\w\+/anonymous_class_%s/g" $ACTUAL_PATH $EXPECTED_PATH
# This isn't emitted in newer PHP versions
sed -i "/PhanSyntaxError syntax error, unexpected ',', expecting ']'/d" $ACTUAL_PATH
sed -i "/030_crash_extract_type.php:3 PhanSyntaxError syntax error, unexpected ',', expecting ')'/d" $ACTUAL_PATH

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
