#!/usr/bin/env bash
if php -r 'exit(PHP_MAJOR_VERSION >= 8 ? 0 : 1);'; then
    EXPECTED_PATH=json_stubs.expected80
else
    EXPECTED_PATH=json_stubs.expected
fi
ACTUAL_PATH=all_output.actual
if [ ! -e $EXPECTED_PATH  ]; then
	echo "Error: must run this script from tests/tool_test folder" 1>&2
	exit 1
fi
echo "Running make_stubs in '$PWD' ..."
rm -f $ACTUAL_PATH || exit 1
../../tool/make_stubs -e json | tee $ACTUAL_PATH
if php -r 'exit(PHP_MAJOR_VERSION < 8 ? 0 : 1);'; then
    # Normalize output by deleting constants added in php 7.1.0+
    # PHP 7.3 introduced class JsonException, so delete the lines with that class definition
    sed -i -e '/JSON_INVALID_UTF8_\|JSON_UNESCAPED_LINE_TERMINATOR\|JSON_UNESCAPED_SLASHES\|JSON_THROW_ON_ERROR/d' \
        -e '/^class JsonException extends/,+8 d' \
        $ACTUAL_PATH
fi
# php 8.1 adds tentative return types that can only be declared by internal class methods
sed -i -e 's/jsonSerialize() : mixed/jsonSerialize()/' \
    $ACTUAL_PATH

sed -i -e 's,@phan-stub-for-extension json@.*$,@phan-stub-for-extension json@%s,' $ACTUAL_PATH

# diff returns a non-zero exit code if files differ or are missing
# This outputs the difference between actual and expected output.
echo
echo "Comparing $EXPECTED_PATH to $ACTUAL_PATH after normalization:"
diff $EXPECTED_PATH $ACTUAL_PATH
EXIT_CODE=$?
if [ "$EXIT_CODE" == 0 ]; then
	echo "Files $EXPECTED_PATH and output $ACTUAL_PATH are identical"
    rm $ACTUAL_PATH
fi
exit $EXIT_CODE
