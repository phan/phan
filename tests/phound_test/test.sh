#!/usr/bin/env bash
set -u

if ! type sqlite3 >/dev/null; then
    echo "sqlite3, which is necessary for this test, is not installed!"
    exit 1
fi

EXPECTED_PATH=expected/all_output.expected
if [ ! -d expected  ]; then
    echo "Error: must run this script from tests/phound_test folder"
    exit 1
fi
echo "Generating test cases"
for path in $(echo expected/*.php.expected | LC_ALL=C sort); do cat "$path"; done > $EXPECTED_PATH
EXIT_CODE=$?
if [[ $EXIT_CODE != 0 ]]; then
    echo "Failed to concatenate test cases" 1>&2
    exit 1
fi

echo "Running phan in '$PWD' ..."

rm -rf ~/phound.db

# We use the polyfill parser because it behaves consistently in all php versions.
if ! ../../phan --force-polyfill-parser --memory-limit 1G --analyze-twice ; then
    echo "Phan found some errors - this is unexpected"
    exit 1
fi

# Regarding the ORDER BY clause:
# 1) `substr(callsite, 0, instr(callsite, ":"))`
#   This transforms a callsite like `001_my_test_file.php:10`` to `001_my_test_file.php`
# 2) `cast(substr(callsite, instr(callsite, ":") + 1) as integer)`
#   This transforms a callsite like 001_my_test_file.php:10 to `10`.
#   That is, it transforms it to the callsite line number as an integer. It avoids weirdness where,
#   for example, 'foo.php:10' might otherwise appear ahead of 'foo.php:9' when treated as a string.
#
# Together, these order by clauses ensure the output is ordered by file and line number.
CALLSITES=$(sqlite3 ~/phound.db 'SELECT * FROM callsites ORDER BY substr(callsite, 0, instr(callsite, ":")), cast(substr(callsite, instr(callsite, ":") + 1) as integer), element, type')
CLASSES=$(sqlite3 ~/phound.db 'SELECT * FROM classes ORDER BY filepath, name')
CLASS_INTERFACES=$(sqlite3 ~/phound.db 'SELECT * FROM class_interfaces ORDER BY class, interface')
CLASS_RELATIONSHIPS=$(sqlite3 ~/phound.db 'SELECT * FROM class_relationships ORDER BY parent, child')
CLASS_TRAITS=$(sqlite3 ~/phound.db 'SELECT * FROM class_traits ORDER BY class, trait')
INTERFACES=$(sqlite3 ~/phound.db 'SELECT * FROM interfaces ORDER BY filepath, name')
INTERFACE_RELATIONSHIPS=$(sqlite3 ~/phound.db 'SELECT * FROM interface_relationships ORDER BY parent, child')
TRAITS=$(sqlite3 ~/phound.db 'SELECT * FROM traits ORDER BY filepath, name')
TRAIT_TRAITS=$(sqlite3 ~/phound.db 'SELECT * FROM trait_traits ORDER BY trait, uses_trait')
SIGNATURES=$(sqlite3 -separator $'\t' ~/phound.db 'SELECT fqsen, kind, class_fqsen, name, type, is_static, visibility, filepath, lineno FROM signatures ORDER BY fqsen, kind')
PARAMETERS=$(sqlite3 -separator $'\t' ~/phound.db 'SELECT fqsen, idx, name, type, is_variadic, is_reference, is_optional, default_repr FROM parameters ORDER BY fqsen, idx')

ACTUAL="<-----------> Callsites <----------->
$CALLSITES
<-----------> Classes <----------->
$CLASSES
<-----------> Class Interfaces <----------->
$CLASS_INTERFACES
<-----------> Class Relationships <----------->
$CLASS_RELATIONSHIPS
<-----------> Class Traits <----------->
$CLASS_TRAITS
<-----------> Interfaces <----------->
$INTERFACES
<-----------> Interface Relationships <----------->
$INTERFACE_RELATIONSHIPS
<-----------> Traits <----------->
$TRAITS
<-----------> Trait Traits <----------->
$TRAIT_TRAITS
<-----------> Signatures <----------->
$SIGNATURES
<-----------> Parameters <----------->
$PARAMETERS"
# diff returns a non-zero exit code if files differ or are missing
# This outputs the difference between actual and expected output.
echo "$ACTUAL"
echo "Comparing the output:"

if type colordiff >/dev/null 2>&1; then
    DIFF="colordiff"
else
    DIFF="diff"
fi

$DIFF $EXPECTED_PATH <(echo "$ACTUAL")
EXIT_CODE=$?
if [ "$EXIT_CODE" == 0 ]; then
    echo "The sqlite3 DB content matches what was expected"
else
    echo "The sqlite3 DB content does not match what was expected"
    exit $EXIT_CODE
fi
