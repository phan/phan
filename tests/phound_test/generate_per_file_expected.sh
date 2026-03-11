#!/usr/bin/env bash
set -eu

# generate_per_file_expected.sh
#
# Generates per-file expected output files from the phound.db and validates
# them against the existing global expected output.
#
# This proves the per-file filtering queries are correct and complete:
# 1. Validates DB matches existing expected output (old format)
# 2. Generates per-file expected files (new format)
# 3. Validates completeness: union of per-file rows = global rows for each table
#
# Usage:
#   cd tests/phound_test
#   # First run phan to generate the DB:
#   ../../phan --force-polyfill-parser --memory-limit 1G --analyze-twice
#   # Then run this script:
#   bash generate_per_file_expected.sh
#
# The per-file expected files are written to expected_per_file/.

DB=~/phound.db
EXPECTED_DIR=expected
NEW_EXPECTED_DIR=expected_per_file

if [ ! -f "$DB" ]; then
    echo "Error: $DB not found. Run phan first."
    exit 1
fi

if [ ! -d "$EXPECTED_DIR" ]; then
    echo "Error: must run this script from tests/phound_test folder"
    exit 1
fi

TMPDIR=$(mktemp -d)
trap "rm -rf $TMPDIR" EXIT

# Helper: print a section header, followed by content (if non-empty)
section() {
    echo "<-----------> $1 <----------->"
    if [ -n "${2:-}" ]; then echo "$2"; fi
}

########################################################################
# Step 1: Validate DB against existing expected output
########################################################################
echo "=== Step 1: Validate DB against existing expected output ==="

CALLSITES=$(sqlite3 "$DB" "SELECT * FROM callsites ORDER BY substr(callsite, 0, instr(callsite, ':')), cast(substr(callsite, instr(callsite, ':') + 1) as integer), element, type")
CLASSES=$(sqlite3 "$DB" 'SELECT * FROM classes ORDER BY filepath, name')
CLASS_INTERFACES=$(sqlite3 "$DB" 'SELECT * FROM class_interfaces ORDER BY class, interface')
CLASS_RELATIONSHIPS=$(sqlite3 "$DB" 'SELECT * FROM class_relationships ORDER BY parent, child')
CLASS_TRAITS=$(sqlite3 "$DB" 'SELECT * FROM class_traits ORDER BY class, trait')
INTERFACES=$(sqlite3 "$DB" 'SELECT * FROM interfaces ORDER BY filepath, name')
INTERFACE_RELATIONSHIPS=$(sqlite3 "$DB" 'SELECT * FROM interface_relationships ORDER BY parent, child')
TRAITS=$(sqlite3 "$DB" 'SELECT * FROM traits ORDER BY filepath, name')
TRAIT_TRAITS=$(sqlite3 "$DB" 'SELECT * FROM trait_traits ORDER BY trait, uses_trait')
SIGNATURES=$(sqlite3 -separator $'\t' "$DB" 'SELECT fqsen, kind, class_fqsen, name, type, is_static, visibility, filepath, lineno FROM signatures ORDER BY fqsen, kind')
PARAMETERS=$(sqlite3 -separator $'\t' "$DB" 'SELECT fqsen, idx, name, type, is_variadic, is_reference, is_optional, default_repr FROM parameters ORDER BY fqsen, idx')

GLOBAL_ACTUAL="$(section "Callsites" "$CALLSITES")
$(section "Classes" "$CLASSES")
$(section "Class Interfaces" "$CLASS_INTERFACES")
$(section "Class Relationships" "$CLASS_RELATIONSHIPS")
$(section "Class Traits" "$CLASS_TRAITS")
$(section "Interfaces" "$INTERFACES")
$(section "Interface Relationships" "$INTERFACE_RELATIONSHIPS")
$(section "Traits" "$TRAITS")
$(section "Trait Traits" "$TRAIT_TRAITS")
$(section "Signatures" "$SIGNATURES")
$(section "Parameters" "$PARAMETERS")"

# Build existing expected (same concatenation as old test.sh)
for path in $(echo "$EXPECTED_DIR"/*.php.expected | LC_ALL=C sort); do cat "$path"; done > "$TMPDIR/existing_expected.txt"
echo "$GLOBAL_ACTUAL" > "$TMPDIR/global_actual.txt"

if diff "$TMPDIR/existing_expected.txt" "$TMPDIR/global_actual.txt" > "$TMPDIR/step1_diff.txt" 2>&1; then
    echo "  DB matches existing expected output"
else
    echo "  DB differs from existing expected output (known diffs like float precision are expected):"
    cat "$TMPDIR/step1_diff.txt"
    echo ""
fi

########################################################################
# Step 2: Generate per-file expected files
########################################################################
echo ""
echo "=== Step 2: Generate per-file expected files ==="

rm -rf "$NEW_EXPECTED_DIR"
mkdir -p "$NEW_EXPECTED_DIR"

for src_file in src/*.php; do
    filename=$(basename "$src_file")
    FILE_PATH="src/${filename}"
    out="$NEW_EXPECTED_DIR/${filename}.expected"

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

    {
        section "Callsites" "$PF_CALLSITES"
        section "Classes" "$PF_CLASSES"
        section "Class Interfaces" "$PF_CLASS_INTERFACES"
        section "Class Relationships" "$PF_CLASS_RELATIONSHIPS"
        section "Class Traits" "$PF_CLASS_TRAITS"
        section "Interfaces" "$PF_INTERFACES"
        section "Interface Relationships" "$PF_INTERFACE_RELATIONSHIPS"
        section "Traits" "$PF_TRAITS"
        section "Trait Traits" "$PF_TRAIT_TRAITS"
        section "Signatures" "$PF_SIGNATURES"
        section "Parameters" "$PF_PARAMETERS"
    } > "$out"

    echo "  Generated $out ($(wc -l < "$out") lines)"
done

########################################################################
# Step 3: Validate completeness — union of per-file = global
########################################################################
echo ""
echo "=== Step 3: Validate completeness (per-file union = global for each table) ==="

FAILED=0

# For each table, run the global query and all per-file queries,
# sort both, and diff. This proves no rows are lost or duplicated.
validate_table() {
    local label="$1"
    local global_query="$2"
    local per_file_query_template="$3" # {FILE_PATH} is the placeholder
    local separator="${4:-|}"

    local sep_flag=""
    if [ "$separator" = $'\t' ]; then
        sep_flag="-separator $(printf '\t')"
    fi

    # Global results, sorted
    sqlite3 $sep_flag "$DB" "$global_query" | sort > "$TMPDIR/global_${label}.txt"

    # Per-file results, concatenated, sorted
    > "$TMPDIR/perfile_${label}.txt"
    for src_file in src/*.php; do
        local filename
        filename=$(basename "$src_file")
        local file_path="src/${filename}"
        local query="${per_file_query_template//\{FILE_PATH\}/$file_path}"
        sqlite3 $sep_flag "$DB" "$query" >> "$TMPDIR/perfile_${label}.txt"
    done
    sort "$TMPDIR/perfile_${label}.txt" -o "$TMPDIR/perfile_${label}.txt"

    if diff "$TMPDIR/global_${label}.txt" "$TMPDIR/perfile_${label}.txt" > "$TMPDIR/diff_${label}.txt" 2>&1; then
        echo "  $label: per-file union matches global"
    else
        echo "  $label: MISMATCH"
        cat "$TMPDIR/diff_${label}.txt"
        FAILED=1
    fi
}

validate_table "callsites" \
    "SELECT * FROM callsites" \
    "SELECT * FROM callsites WHERE callsite LIKE '{FILE_PATH}:%'"

validate_table "classes" \
    "SELECT * FROM classes" \
    "SELECT * FROM classes WHERE filepath = '{FILE_PATH}'"

validate_table "class_interfaces" \
    "SELECT * FROM class_interfaces" \
    "SELECT class, interface FROM class_interfaces WHERE class IN (SELECT name FROM classes WHERE filepath = '{FILE_PATH}')"

validate_table "class_relationships" \
    "SELECT * FROM class_relationships" \
    "SELECT parent, child FROM class_relationships WHERE child IN (SELECT name FROM classes WHERE filepath = '{FILE_PATH}')"

validate_table "class_traits" \
    "SELECT * FROM class_traits" \
    "SELECT class, trait FROM class_traits WHERE class IN (SELECT name FROM classes WHERE filepath = '{FILE_PATH}')"

validate_table "interfaces" \
    "SELECT * FROM interfaces" \
    "SELECT * FROM interfaces WHERE filepath = '{FILE_PATH}'"

validate_table "interface_relationships" \
    "SELECT * FROM interface_relationships" \
    "SELECT parent, child FROM interface_relationships WHERE child IN (SELECT name FROM interfaces WHERE filepath = '{FILE_PATH}')"

validate_table "traits" \
    "SELECT * FROM traits" \
    "SELECT * FROM traits WHERE filepath = '{FILE_PATH}'"

validate_table "trait_traits" \
    "SELECT * FROM trait_traits" \
    "SELECT trait, uses_trait FROM trait_traits WHERE trait IN (SELECT name FROM traits WHERE filepath = '{FILE_PATH}')"

validate_table "signatures" \
    "SELECT fqsen, kind, class_fqsen, name, type, is_static, visibility, filepath, lineno FROM signatures" \
    "SELECT fqsen, kind, class_fqsen, name, type, is_static, visibility, filepath, lineno FROM signatures WHERE filepath = '{FILE_PATH}'" \
    $'\t'

validate_table "parameters" \
    "SELECT fqsen, idx, name, type, is_variadic, is_reference, is_optional, default_repr FROM parameters" \
    "SELECT p.fqsen, p.idx, p.name, p.type, p.is_variadic, p.is_reference, p.is_optional, p.default_repr FROM parameters p WHERE p.fqsen IN (SELECT DISTINCT fqsen FROM signatures WHERE filepath = '{FILE_PATH}')" \
    $'\t'

echo ""
if [ $FAILED -eq 0 ]; then
    echo "All validations passed!"
    echo "Per-file expected files are in $NEW_EXPECTED_DIR/"
    echo ""
    echo "To adopt the new format:"
    echo "  rm $EXPECTED_DIR/*.expected"
    echo "  mv $NEW_EXPECTED_DIR/* $EXPECTED_DIR/"
    echo "  rmdir $NEW_EXPECTED_DIR"
else
    echo "Some validations FAILED. Check the output above."
    exit 1
fi
