#!/usr/bin/env bash
EXPECTED_PATH=json_stubs.expected
ACTUAL_PATH=all_output.actual
if [ ! -e $EXPECTED_PATH  ]; then
	echo "Error: must run this script from tests/tool_test folder" 1>&2
	exit 1
fi
echo "Running make_stubs in '$PWD' ..."
rm $ACTUAL_PATH -f || exit 1
../../tool/make_stubs -e json | tee $ACTUAL_PATH
sed -i 's,@phan-stub-for-extension json@.*$,@phan-stub-for-extension json@%s,' $ACTUAL_PATH
# Normalize output by deleting constants added in php 7.1.0+
sed -i '/JSON_INVALID_UTF8_\|JSON_UNESCAPED_LINE_TERMINATOR\|JSON_UNESCAPED_SLASHES\|JSON_THROW_ON_ERROR/d' $ACTUAL_PATH

# PHP 7.3 introduced class JsonException, so delete the lines with that class definition
sed -i '/^class JsonException extends/,+8 d' $ACTUAL_PATH
# diff returns a non-zero exit code if files differ or are missing
# This outputs the difference between actual and expected output.
echo
echo "Comparing the output:"
diff $EXPECTED_PATH $ACTUAL_PATH
EXIT_CODE=$?
if [ "$EXIT_CODE" == 0 ]; then
	echo "Files $EXPECTED_PATH and output $ACTUAL_PATH are identical"
    rm $ACTUAL_PATH
fi
exit $EXIT_CODE
