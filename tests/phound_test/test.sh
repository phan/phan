#!/usr/bin/env bash
set -u

# Usage: bash test.sh [--update]
#   --update: regenerate expected files from actual DB output instead of comparing

UPDATE=false
if [ "${1:-}" = "--update" ]; then
    UPDATE=true
fi

if ! type sqlite3 >/dev/null; then
    echo "sqlite3, which is necessary for this test, is not installed!"
    exit 1
fi

if [ ! -d expected ]; then
    echo "Error: must run this script from tests/phound_test folder"
    exit 1
fi

echo "Running phan in '$PWD' ..."

rm -rf ~/phound.db

# We use the polyfill parser because it behaves consistently in all php versions.
if ! ../../phan --force-polyfill-parser --memory-limit 1G --analyze-twice ; then
    echo "Phan found some errors - this is unexpected"
    exit 1
fi

DB=~/phound.db

# Helper: print a section header, followed by content (if non-empty)
section() {
    echo "<-----------> $1 <----------->"
    if [ -n "${2:-}" ]; then echo "$2"; fi
}

if type colordiff >/dev/null 2>&1; then
    DIFF="colordiff"
else
    DIFF="diff"
fi

FAILED=0

for src_file in src/*.php; do
    filename=$(basename "$src_file")
    FILE_PATH="src/${filename}"
    expected_file="expected/${filename}.expected"

    # Query each table filtered to this source file.
    # Tables with filepath columns are filtered directly.
    # Relationship tables use subqueries to filter by the classes/interfaces/traits
    # defined in this file.
    PF_CALLSITES=$(sqlite3 "$DB" "SELECT * FROM callsites WHERE callsite LIKE '${FILE_PATH}:%' ORDER BY cast(substr(callsite, instr(callsite, ':') + 1) as integer), element, type")

    PF_CLASSES=$(sqlite3 "$DB" "SELECT * FROM classes WHERE filepath = '${FILE_PATH}' ORDER BY name")

    PF_CLASS_INTERFACES=$(sqlite3 "$DB" "SELECT class, interface FROM class_interfaces WHERE class IN (SELECT name FROM classes WHERE filepath = '${FILE_PATH}') ORDER BY class, interface")

    PF_CLASS_RELATIONSHIPS=$(sqlite3 "$DB" "SELECT parent, child FROM class_relationships WHERE child IN (SELECT name FROM classes WHERE filepath = '${FILE_PATH}') ORDER BY parent, child")

    PF_CLASS_TRAITS=$(sqlite3 "$DB" "SELECT class, trait FROM class_traits WHERE class IN (SELECT name FROM classes WHERE filepath = '${FILE_PATH}') ORDER BY class, trait")

    PF_INTERFACES=$(sqlite3 "$DB" "SELECT * FROM interfaces WHERE filepath = '${FILE_PATH}' ORDER BY name")

    PF_INTERFACE_RELATIONSHIPS=$(sqlite3 "$DB" "SELECT parent, child FROM interface_relationships WHERE child IN (SELECT name FROM interfaces WHERE filepath = '${FILE_PATH}') ORDER BY parent, child")

    PF_TRAITS=$(sqlite3 "$DB" "SELECT * FROM traits WHERE filepath = '${FILE_PATH}' ORDER BY name")

    PF_TRAIT_TRAITS=$(sqlite3 "$DB" "SELECT trait, uses_trait FROM trait_traits WHERE trait IN (SELECT name FROM traits WHERE filepath = '${FILE_PATH}') ORDER BY trait, uses_trait")

    PF_SIGNATURES=$(sqlite3 -separator $'\t' "$DB" "SELECT fqsen, kind, class_fqsen, name, type, is_static, visibility, filepath, lineno FROM signatures WHERE filepath = '${FILE_PATH}' ORDER BY fqsen, kind")

    PF_PARAMETERS=$(sqlite3 -separator $'\t' "$DB" "SELECT p.fqsen, p.idx, p.name, p.type, p.is_variadic, p.is_reference, p.is_optional, p.default_repr FROM parameters p WHERE p.fqsen IN (SELECT DISTINCT fqsen FROM signatures WHERE filepath = '${FILE_PATH}') ORDER BY p.fqsen, p.idx")

    ACTUAL="$(section "Callsites" "$PF_CALLSITES")
$(section "Classes" "$PF_CLASSES")
$(section "Class Interfaces" "$PF_CLASS_INTERFACES")
$(section "Class Relationships" "$PF_CLASS_RELATIONSHIPS")
$(section "Class Traits" "$PF_CLASS_TRAITS")
$(section "Interfaces" "$PF_INTERFACES")
$(section "Interface Relationships" "$PF_INTERFACE_RELATIONSHIPS")
$(section "Traits" "$PF_TRAITS")
$(section "Trait Traits" "$PF_TRAIT_TRAITS")
$(section "Signatures" "$PF_SIGNATURES")
$(section "Parameters" "$PF_PARAMETERS")"

    if [ "$UPDATE" = true ]; then
        echo "$ACTUAL" > "$expected_file"
        echo "Updated $expected_file"
    else
        if [ ! -f "$expected_file" ]; then
            echo "FAIL: $filename (expected file missing: $expected_file)"
            FAILED=1
            continue
        fi
        if $DIFF "$expected_file" <(echo "$ACTUAL") > /dev/null 2>&1; then
            echo "PASS: $filename"
        else
            echo "FAIL: $filename"
            $DIFF "$expected_file" <(echo "$ACTUAL")
            FAILED=1
        fi
    fi
done

if [ "$UPDATE" = true ]; then
    echo "All expected files updated."
    exit 0
fi

echo ""
if [ $FAILED -eq 0 ]; then
    echo "All tests passed!"
else
    echo "Some tests FAILED."
    exit 1
fi
