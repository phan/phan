#!/usr/bin/env bash
# The fixture suite of CustomAnnotationCapability (annotations that plugins declare in doc comments).
#
# The test plugins in .phan/plugins emit an issue for each custom annotation in the parse phase (PhanPluginCustomAnnotationSeen,
# PhanPluginCustomAnnotationSecond), and one for each use of an element of src/ in the analysis phase with the annotations that
# the element returns from getCustomAnnotations() (PhanPluginCustomAnnotationRead).
# Runs Phan on src/ (with the classes of external/, which is parsed but excluded from analysis, as roots) without lazy entries
# (PHAN_LAZY_INHERITED_METHODS=0), with them (PHAN_LAZY_INHERITED_METHODS=1) and in verify mode (PHAN_LAZY_INHERIT_VERIFY=1),
# and with --processes 2 if pcntl is available. Each run must print the expected output, verify mode must find no violation,
# the extended state digests of the runs without lazy entries and in verify mode must be identical, and the run with
# lazy entries must have created a copy of a method lazily.
# Then it checks the output of --help-annotations, and that Phan fails to load a plugin that declares an invalid name
# or the name of an annotation of Phan.
EXPECTED_PATH=expected/all_output.expected
if [ ! -d expected  ]; then
	echo "Error: must run this script from tests/custom_annotation_test folder" 1>&2
	exit 1
fi
for path in $(echo expected/*.php.expected | LC_ALL=C sort); do cat $path; done > $EXPECTED_PATH || exit 1

PHP_BINARY=${PHP_BINARY:-php}
FAILURES=0
TMP_DIR=$(mktemp -d)
trap 'rm -rf "$TMP_DIR"' EXIT

# run_mode <label> <mode> <extra phan args...>
# mode: off (no lazy entries), on, verify
function run_mode {
	local label="$1" mode="$2"
	shift 2
	local env_args=(-u PHAN_LAZY_INHERITED_METHODS -u PHAN_LAZY_INHERIT_VERIFY -u PHAN_DISABLE_LAZY_INHERIT -u PHAN_DUMP_METHOD_STATE_DIGEST -u PHAN_DUMP_METHOD_STATE_DIGEST_EXTENDED)
	case "$mode" in
		off) env_args+=(PHAN_LAZY_INHERITED_METHODS=0 PHAN_DUMP_METHOD_STATE_DIGEST_EXTENDED=1);;
		on) env_args+=(PHAN_LAZY_INHERITED_METHODS=1);;
		verify) env_args+=(PHAN_LAZY_INHERIT_VERIFY=1);;
	esac
	if [[ "$mode" != on ]]; then
		env_args+=(PHAN_DUMP_METHOD_STATE_DIGEST="$TMP_DIR/$label.digest")
	fi
	local actual="$TMP_DIR/$label.actual"
	echo "Running phan in '$PWD' ($label: $mode) ..."
	env "${env_args[@]}" ../../phan --no-progress-bar --memory-limit 1G "$@" > "$actual" 2> "$TMP_DIR/$label.stderr"
	local exit_code=$?
	sed -i -e 's/src\\/src\//g' "$actual"
	if [[ "$exit_code" != 1 ]]; then
		# Phan exits with 1 if it reports issues
		echo "Unexpected exit code $exit_code for $label (expected 1: there are issues)" 1>&2
		cat "$TMP_DIR/$label.stderr" 1>&2
		FAILURES=$((FAILURES + 1))
	fi
	if ! diff "$EXPECTED_PATH" "$actual"; then
		echo "The output of $label differs from $EXPECTED_PATH" 1>&2
		FAILURES=$((FAILURES + 1))
	fi
	if [[ "$mode" == verify ]]; then
		if grep 'PHAN_LAZY_INHERIT_VERIFY: .* (declared in ' "$TMP_DIR/$label.stderr" 1>&2 || \
			! grep -q '^PHAN_LAZY_INHERIT_VERIFY: [1-9][0-9]* lazy entries created, .*: 0 violation(s)$' "$TMP_DIR/$label.stderr"; then
			echo "PHAN_LAZY_INHERIT_VERIFY=1 found violations (or created no lazy entry) for $label:" 1>&2
			cat "$TMP_DIR/$label.stderr" 1>&2
			FAILURES=$((FAILURES + 1))
		fi
	fi
}

# compare_digests <label without lazy entries> <label in verify mode>
function compare_digests {
	if ! cmp "$TMP_DIR/$1.digest" "$TMP_DIR/$2.digest"; then
		echo "The extended state digests of $1 and $2 differ:" 1>&2
		diff "$TMP_DIR/$1.digest" "$TMP_DIR/$2.digest" | head -20 1>&2
		FAILURES=$((FAILURES + 1))
	fi
}

for mode in off on verify; do
	run_mode "$mode" "$mode"
done
compare_digests off verify

# The copies of the methods of ExternalBase in LocalChild must have been created lazily, when the test plugin looked them up
# (counters of LazyInheritedMethods, see --phase-timings-json)
echo "Checking the counters of lazy_inherited_methods ..."
env -u PHAN_LAZY_INHERIT_VERIFY -u PHAN_DISABLE_LAZY_INHERIT -u PHAN_DUMP_METHOD_STATE_DIGEST PHAN_LAZY_INHERITED_METHODS=1 ../../phan --no-progress-bar --memory-limit 1G --phase-timings-json "$TMP_DIR/timings.json" > "$TMP_DIR/timings.actual" 2>/dev/null
"$PHP_BINARY" -r '
$report = json_decode((string)file_get_contents($argv[1]), true);
$notes = [];
array_walk_recursive($report, function ($value, $key) use (&$notes) {
    if (is_string($key) && str_starts_with($key, "lazy_")) {
        $notes[$key] = ($notes[$key] ?? 0) + (is_numeric($value) ? $value : 0);
    }
});
$missing = [];
foreach (["lazy_links", "lazy_materialized_late_lookup"] as $name) {
    if (($notes[$name] ?? 0) <= 0) {
        $missing[] = $name;
    }
}
if ($missing) {
    fwrite(STDERR, "These counters of lazy_inherited_methods are 0: " . implode(", ", $missing) . "\n");
    exit(1);
}
' "$TMP_DIR/timings.json" || FAILURES=$((FAILURES + 1))

# With analysis workers (the annotations are recorded in the parse phase, before the workers are forked)
if "$PHP_BINARY" -r 'exit(function_exists("pcntl_fork") ? 0 : 1);'; then
	for mode in off on; do
		run_mode "processes_$mode" "$mode" --processes 2
	done
else
	echo "Skipping the runs with --processes 2 (pcntl is not available)"
fi

# --help-annotations prints (to stderr) the annotations of Phan, then those that the plugins declare.
# Without a config file (-n) there are no plugins, and the output ends after the annotations of Phan.
echo "Checking the output of --help-annotations ..."
../../phan -n --help-annotations > "$TMP_DIR/help_builtin.out" 2> "$TMP_DIR/help_builtin.err"
exit_code=$?
if [[ "$exit_code" != 0 || -s "$TMP_DIR/help_builtin.out" ]] || ! grep -q '^Annotations specific to Phan:$' "$TMP_DIR/help_builtin.err" || grep -q 'Annotations declared by plugins' "$TMP_DIR/help_builtin.err"; then
	echo "Unexpected output of phan -n --help-annotations (exit code $exit_code):" 1>&2
	cat "$TMP_DIR/help_builtin.out" "$TMP_DIR/help_builtin.err" 1>&2
	FAILURES=$((FAILURES + 1))
fi
../../phan --help-annotations > "$TMP_DIR/help.out" 2> "$TMP_DIR/help.err"
exit_code=$?
(cat "$TMP_DIR/help_builtin.err" expected/help_annotations_plugins.expected) > "$TMP_DIR/help.expected"
if [[ "$exit_code" != 0 || -s "$TMP_DIR/help.out" ]] || ! diff "$TMP_DIR/help.expected" "$TMP_DIR/help.err"; then
	echo "Unexpected output of phan --help-annotations with the test plugins (exit code $exit_code), expected the output of phan -n --help-annotations followed by expected/help_annotations_plugins.expected" 1>&2
	cat "$TMP_DIR/help.out" 1>&2
	FAILURES=$((FAILURES + 1))
fi

# expect_plugin_failure <label> <expected message> <phan args...>
# Phan must exit with 1 and report the message when it loads the plugins.
# (-d error_log= makes error_log() write to stderr, also if php.ini sets error_log)
function expect_plugin_failure {
	local label="$1" message="$2"
	shift 2
	echo "Checking that Phan fails to load the plugins ($label) ..."
	"$PHP_BINARY" -d error_log= ../../phan --no-progress-bar "$@" > "$TMP_DIR/$label.out" 2> "$TMP_DIR/$label.err"
	local exit_code=$?
	if [[ "$exit_code" != 1 ]] || ! grep -qF "$message" "$TMP_DIR/$label.err"; then
		echo "Expected exit code 1 and the message '$message' for $label, got exit code $exit_code:" 1>&2
		cat "$TMP_DIR/$label.out" "$TMP_DIR/$label.err" 1>&2
		FAILURES=$((FAILURES + 1))
	fi
}
INVALID_NAME_MESSAGE='Plugin InvalidNameTestPlugin declares the annotation "phan-Invalid-Name" in getCustomAnnotations(): the name of an annotation must match /^phan-[a-z0-9_-]+$/'
BUILTIN_NAME_MESSAGE='Plugin BuiltinNameTestPlugin declares the annotation "phan-param" in getCustomAnnotations(), which is an annotation of Phan itself'
expect_plugin_failure invalid_name "$INVALID_NAME_MESSAGE" --plugin invalid_plugins/InvalidNameTestPlugin.php
expect_plugin_failure builtin_name "$BUILTIN_NAME_MESSAGE" --plugin invalid_plugins/BuiltinNameTestPlugin.php
expect_plugin_failure help_invalid_name "$INVALID_NAME_MESSAGE" --help-annotations --plugin invalid_plugins/InvalidNameTestPlugin.php
expect_plugin_failure help_builtin_name "$BUILTIN_NAME_MESSAGE" -n --help-annotations --plugin invalid_plugins/BuiltinNameTestPlugin.php

if [[ "$FAILURES" != 0 ]]; then
	echo "$FAILURES check(s) of the custom annotation fixture suite failed" 1>&2
	exit 1
fi
echo "The output of all runs matches $EXPECTED_PATH, with no violation and identical digests, and --help-annotations and the plugin load failures are as expected"
exit 0
