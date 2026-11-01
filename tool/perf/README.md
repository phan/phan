# tool/perf: Phan benchmarking harness

Scripts for measuring Phan's wall time, CPU and memory, A/B-testing commits, checking
that a change leaves the issue output unchanged, and aggregating phpspy profiles.

All data lives under `${PHAN_PERF_HOME:-$HOME/phan-perf}` (called `$PERF` below), never
in the repository:

```
$PERF/results/<target>/<UTC>-<sha10>[-dirty-<h8>]-j<N>-<variant>[-<label>]/
    env.json  summary.json  warmup-<w>/  run-<k>/{cmd.txt,time.json,mem.json,result.json,
                                                  issues.txt,stderr.txt,stdout.txt,timings.json}
$PERF/results/results.tsv       one row per summarized result directory
$PERF/results/ab/<UTC>-<shaA10>-vs-<shaB10>/{A,B}/   ab.sh runs
$PERF/wt/<sha>/                 detached worktrees created by ab.sh
$PERF/opcache/<sha10>[-dirty-<h8>]/   opcache file cache of the `ci` variant
```

Requirements: `php` with `ast.so` (the scripts run with `php -n`, so nothing comes from
php.ini), GNU time at `/usr/bin/time`, bash. Optional: `systemd-run --user` with the
cgroup v2 memory controller (memory cap and cgroup memory stats; without it runs are
uncapped and `peak_anon_kb` is empty), `taskset` (`--pin`), phpspy.

| script | purpose |
|---|---|
| `bench.sh` | run one target K times (+W discarded warmups) under a memory cap, then summarize |
| `bench_summarize.php` | `summary.json`, a `results.tsv` row, a one-screen report; `--compare A B` |
| `ab.sh` | A/B two commits in detached worktrees, interleaved A,B,A,B; gate verdict |
| `issue_diff.php` | compare two `-m text` issue outputs; classify differences |
| `issue_lib.php` | issue-output normalization shared by issue_diff.php and bench_summarize.php |
| `phpspy_top.php` | top-N self/inclusive tables, per-phase shares and profile diffs from phpspy output |
| `make_subset.php` | deterministic, reference-closed subset of a project's files |
| `pss_sidecar.sh` | per-second Rss/Pss/Private/Shared of a process and its children |

## bench.sh

```
tool/perf/bench.sh -t self|self-quick|self-noisy|project-subset [-j N] [-r K] [-w W]
    [-v ci|nocache|shm|jit] [--phan PATH] [--baseline] [--mem-max 12G] [--pin]
    [--no-timings] [--label X] [-- extra phan args]
```

PHP base command: `php -n -d extension=ast.so -d extension=$PHAN_HELPERS_SO`
(`PHAN_HELPERS_SO` defaults to `/usr/lib/php/20240924/phan_helpers.so`; set it to `none`
to measure without phan_helpers, and add a `--label`). Common Phan arguments:
`--no-progress-bar --no-color -j N -m text -o run-k/issues.txt
--always-exit-successfully-after-analysis`, plus `--phase-timings-json run-k/timings.json`
when the Phan under test lists that option in `--extended-help` (`--no-timings` disables it).

Targets (`--phan` selects the analyzer, default this checkout's `phan`; its directory is
the repo used for git info):

| target | command | default K |
|---|---|---|
| `self` | in `$SELF_ROOT`: `<php> <phan> --project-root-directory $SELF_ROOT -k .phan/config.php` | 5 |
| `self-quick` | `self` + `--quick` (mirrors a `quick_mode=true` project config) | 5 |
| `self-noisy` | `self` + `--analyze-all-files` (about 13k issues; the issue-diff corpus) | 5 |
| `project-subset` | in `$PHAN_PERF_PROJECT_ROOT`: `<php> <phan> --project-root-directory $PHAN_PERF_PROJECT_ROOT -k $SUBSET_DIR/config.frozen.php --file-list $SUBSET_DIR/subset.files` (+ `--load-baseline .phan/baseline.php` with `--baseline`; when `subset.analyze.files` exists, `-k` points at a generated `config.analyze.php`, see below) | 3 |

`--self-root DIR` (env `PHAN_PERF_SELF_ROOT`) sets `$SELF_ROOT`, the phan checkout whose
source the self targets analyze; it defaults to the `--phan` repo. The corpus must be
pinned while the analyzer is the variable: a PR that touches `src/` also changes the files
the self-scan analyzes (a new `src/Phan/Library/PhaseTimer.php` alone moved self-noisy from
12,965 to 12,973 issues), so a before/after issue diff of each checkout analyzing itself
fails for every source change. With a pinned root, `.phan/config.php`, `directory_list`,
`vendor/` and stubs come from `$SELF_ROOT`; plugins listed by name come from the analyzer;
plugins listed by path (e.g. `.phan/plugins/AddNeverReturnTypePlugin.php`) resolve against
the project root and therefore come from `$SELF_ROOT`, so changes to those are not measured.
`exclude_file_list` conditions such as `extension_loaded('phan_helpers')` are evaluated by
the running PHP, as before. Verified: a branch analyzer with `src/Phan/Library/PhaseTimer.php`
and its test, run on the v6 corpus, parsed 1913 files and analyzed 657 at -j1 (the v6 tree
lacks those two files), and its self-noisy -j1 output was IDENTICAL to v6 analyzing itself
(12,975 issues). `env.json` records `self_root`, `self_root_sha`, `self_root_dirty`. bench.sh
warns when an A/B run (`--run-dir`) of a self target has no `--self-root`.

`project-subset` benchmarks a fixed sample of a project (see "Preparing a project subset").
`PHAN_PERF_PROJECT_ROOT` defaults to `$PERF/project-src`, `PHAN_PERF_SUBSET_DIR` to `$PERF/project`,
and `PHAN_PERF_SUBSET_EXTENSIONS` (extra `-d extension=` entries) to
`imap.so mcrypt.so xmlrpc.so`. When `$SUBSET_DIR/subset.analyze.files` exists, bench.sh
writes `config.analyze.php` into the result directory: it requires `config.frozen.php` and
sets `include_analysis_file_list` from the file, so the whole selection is parsed but only
the listed files are analyzed. (The `--include-analysis-file-list` option takes a
comma-separated list of files, not a list file, and a long list would exceed the kernel's
per-argument limit.) `env.json` records `analysis_file_list`, `analysis_file_list_used` and
`analysis_file_count`. Phan checks that list with `in_array` per file and per emitted issue,
a small constant cost that shows up in profiles. On first use the script writes `$SUBSET_DIR/tree.pin`
(HEAD, `git status --porcelain` hash, `composer.lock` hash) and refuses later runs if the
tree no longer matches.

Variants:

| variant | extra php flags |
|---|---|
| `ci` | `-d zend_extension=opcache.so -d opcache.enable_cli=1 -d opcache.interned_strings_buffer=128 -d opcache.file_cache=$PERF/opcache/<build> -d opcache.file_cache_only=1` |
| `shm` | opcache as above without the file cache, `-d opcache.memory_consumption=512` |
| `jit` | `shm` + `-d opcache.jit=tracing -d opcache.jit_buffer_size=128M` |
| `nocache` | none |

`shm`/`jit` raise `memory_consumption` because the 128 MB interned strings buffer is
carved out of it and opcache refuses to start with the 128 MB default. `file_cache_only=1`
disables JIT, so `ci` never JITs. The `ci` cache directory persists per build, so measured
runs (after the warmup) see a warm file cache.

Each run is
`systemd-run --user --scope -p MemoryMax=$MEM_MAX -p MemorySwapMax=0 -- /usr/bin/time -o time.json -f '{json}' php ...`
(`--pin` adds `taskset -c 0-3`). A 0.25 s poller records the scope's `memory.current`,
`memory.stat` anon and `memory.events` oom_kill; `memory.peak` and the final
`memory.events` are read inside the scope just before it exits. `mem.json` holds
`peak_current_kb` (includes page cache), `peak_anon_kb` (the number to watch with
forked workers sharing pages) and `oom_kill`.

A run fails (and stops the series) if the wrapper or Phan exits non-zero, GNU time reports
a signal, `oom_kill > 0`, or stderr matches
`Child terminated with return code|Saw errors for an analysis worker|PHP Fatal error|Allowed memory size`,
or stdout matches `^(PHP )?Fatal error:|Allowed memory size of` (under `php -n`,
display_errors prints fatals to stdout). `--always-exit-successfully-after-analysis` hides
crashed workers from the exit status, hence the output checks.

Preflight (`env.json`): `php -v`, loaded extensions, opcache/JIT status from
`opcache_get_status(false)` under the run's flags, ast and phan_helpers versions and
sha256 of the `.so` files, phan git sha plus `git diff HEAD | sha1sum` (dirty marker),
the file `Phan\CLI` autoloads from (the run aborts if it is outside the repo under test:
a symlinked `vendor/` makes composer load Phan from the symlink target's checkout), load1
(warning above 1.0), nproc, MemAvailable, CPU model, kernel.

Examples:

```
tool/perf/bench.sh -t self -j1                       # 1 warmup + 5 runs, ci variant
tool/perf/bench.sh -t self-noisy -j4 -v nocache -r 3
tool/perf/bench.sh -t project-subset -j4 --mem-max 12G --baseline
PHAN_HELPERS_SO=none tool/perf/bench.sh -t self -j1 --label nohelpers
```

## bench_summarize.php

`bench_summarize.php <result dir> [--no-tsv] [--quiet]` writes `summary.json`: median, min,
max and MAD (median absolute deviation) of `wall_s user_s sys_s maxrss_kb peak_anon_kb
peak_current_kb` over the passing runs, per-phase wall medians and
`serial_fraction`/`imbalance_s`/`efficiency` medians when `timings.json` exists, and the
per-run issue sha1s. Status:

- `OK`
- `FAILED`: some run failed (stats use the passing runs)
- `NONDETERMINISTIC`: passing runs produced different sorted issue lists. At -j1 the
  comparison is exact. At -j>1 it ignores each line's trailing ` (<suggestion>)` (see
  "Known nondeterminism"); `raw_sha1_varies` and the per-run raw sha1s stay in the summary.
- `NO_RUNS`

It appends a row to `$PERF/results/results.tsv` (header written when the file is new):

```
utc phan_sha dirty target variant j runs wall_s user_s sys_s maxrss_kb peak_anon_kb
parse_s classes_s functions_s methods_s analyze_s issues issues_sha1 load1 status label dir
```

Values are medians. `analyze_s` is the `analyze` phase at -j1 and `analyze_wait` at -j>1.
`issues_sha1` is `canon:<sha1>` (suggestion-stripped) when the raw sha1 differs between
runs at -j>1. `label` and `dir` are appended after the planned columns so a row can be
traced back to its directory.

`bench_summarize.php --compare <dir A> <dir B>` prints per-metric median deltas and applies
the noise rule below.

## Noise rule and gate

A speedup counts only if the median wall delta exceeds `max(2 × MAD, 1 %)` of the baseline
(MAD is the larger of the two sides), user CPU moves in the same direction by more than its
own threshold, and the issue output is unchanged.

Gate for every optimization, on `self-noisy` and the project subset:

- -j1: `issue_diff.php` must print `IDENTICAL` (exit 0).
- -j4: only `suggestion-only` groups are tolerated (exit 0 or 2); `real` and
  `union-order-only` fail. Report the suggestion-only count, since a change in that count
  is still worth a look.
- phpunit green, plain self-scan at 0 issues.

Establish noise first: run the same sha twice (`ab.sh <sha> <sha> -- -t self -j1`, and
`-t project-subset -j1 -r 3`).

## ab.sh

```
tool/perf/ab.sh <shaA> <shaB> -- -t self-noisy -j4 -r 5 [other bench.sh args]
```

bench.sh options may be given as `--opt=value` or attached (`-j4`, `-r2`); ab.sh
normalizes them and consumes `-r`/`-w` itself. Both sides run with
`--self-root <A's worktree>` (override with `--self-root DIR`), so the self targets compare
analyzers on one corpus.

For each commit: `git worktree add --detach $PERF/wt/<sha>` if missing, then `vendor/` is
copied (`cp -a`) from the main checkout when `composer.lock` is identical, else
`composer install` runs in the worktree. A symlinked `vendor/` is replaced (see the
autoload note above), and the script checks that `Phan\CLI` resolves inside the worktree.
W warmups per side (`-w`, default 1), then K rounds (`-r`) of A then B, each
`bench.sh --phan $PERF/wt/<sha>/phan -r 1 -w 0` into `$PERF/results/ab/.../{A,B}`. Then
both sides are summarized, `--compare` runs, and `issue_diff.php` compares run-1 of each.
Exit 0 when the gate passes (both summaries `OK`; IDENTICAL at -j1; at most
suggestion-only groups at -j>1), else 1. A `NONDETERMINISTIC` or `FAILED` side fails the
gate even if run-1 of each side happens to match. Git use
is limited to `rev-parse`, `worktree list` and `worktree add`.

## issue_diff.php

```
tool/perf/issue_diff.php A.txt B.txt [--examples 20]
```

Both files are normalized (ANSI escapes stripped, `rtrim`, empty lines dropped, duplicates
kept) and sorted with `strcmp`. Identical: `IDENTICAL <n> <sha1>`, exit 0. Otherwise the
multiset difference is grouped by `file:line IssueType` and each group is classified:

- `suggestion-only`: equal after stripping the trailing ` (<suggestion>)` that
  PlainTextPrinter appends (`(Did you mean ...)`, `(Types inferred after analysis: ...)`).
  The last balanced parenthetical preceded by a space is stripped once. A line ending in
  ` (at column N)` (printed before the suggestion when columns are shown) has no
  suggestion and is kept whole, so a column change is `real`. A message that itself ends
  in ` (...)` cannot be told apart from a suggestion, which only matters if nothing else
  differs. The normalization lives in `issue_lib.php`, shared with bench_summarize.php.
- `union-order-only`: equal after additionally sorting the `|`-separated components of
  union types (at every `<>`/`{}`/`()`/`[]` nesting level). Reported separately, but it
  fails the gate like `real`: `UnionType::__toString` sorts components, so an order change
  means printing or type construction changed and must be investigated.
- `real`: anything else, including a different number of lines in the group.

Output: counts per class and per issue type, and up to N examples per class. Exit 1 if
any group is `real` or `union-order-only`, 2 if all differences are `suggestion-only`. The sha1 is
`sha1(implode("\n", sorted) . "\n")`, the same value as `issues_sha1` in the summary.

## Known nondeterminism

At -j>1 the issue output can differ between runs of the same commit. UnknownElementTypePlugin
registers deferred checks for all methods before the fork and runs them in
`finalizeProcess` in every worker. Only the worker that analyzed the element adds the
` (Types inferred after analysis: ...)` suggestion. The parent's BufferingCollector
deduplicates on file, line, type and message (not the suggestion), and ForkPool appends
worker payloads in arrival order, so whichever duplicate arrives last wins. Observed on
`self-noisy -j4` (117 groups of `PhanPluginUnknown*Type`, e.g.
`vendor/composer/semver/src/Semver.php:109 PhanPluginUnknownClosureReturnType` and
`vendor/netresearch/jsonmapper/src/JsonMapper.php:392 PhanPluginUnknownArrayMethodParamType`),
more often under load. `issue_diff.php` classifies these as `suggestion-only`, and
`bench_summarize.php` ignores suggestions in its -j>1 determinism check.

Separately, -j1 and -j4 outputs differ (self-noisy: 12,975 vs 12,965 issues) because
inferred return types written during analysis are seen by later files in the same
worker; compare runs only at the same -j.

## Profiling with phpspy

-j1 runs every phase in one process, so `phpspy -- cmd` (no sudo needed) sees everything:

```
OUT=$PERF/profiles/<name>; mkdir -p $OUT
phpspy -S -H 49 -n 48 -N 12 -b 32768 -g globals.__phase -O $OUT/child.out -E $OUT/child.err \
    -o $OUT/traces.txt -- php -n -d extension=ast.so -d extension=$PHAN_HELPERS_SO \
    /path/to/phan/phan <target args> -j1 --no-progress-bar
~/src/phpspy/stackcollapse-phpspy.pl $OUT/traces.txt > $OUT/folded.txt
~/src/phpspy/vendor/flamegraph.pl $OUT/folded.txt > $OUT/flame.svg
tool/perf/phpspy_top.php --top 40 $OUT/traces.txt
tool/perf/phpspy_top.php --by-phase $OUT/traces.txt
```

- `-H 99` for the self-scan (about 15-20 s), `-H 49` for a large project subset.
- `-b 32768 -n 48 -N 12` keeps 48 leaf and 12 root frames in a 32 KB buffer, which avoids
  phpspy dropping a whole trace when the buffer overflows.
- `-S` pauses the process while a stack is read: without it about 76 % of reads were
  dropped on the self-scan (`copy_proc_mem: Failed to copy ...`, the stack changed
  mid-read) and the surviving samples were biased toward long internal calls
  (`ast\parse_code` 14 % self vs 3 % with `-S`). With `-S`: 1672 samples in 17 s, 1 error,
  about 10 % more wall time. Profiles give proportions, not absolute times.
- `-g globals.__phase` records the phase name that Phan's phase timers store in
  `$GLOBALS['__phase']` (builds with `--phase-timings-json` support). Without it every
  sample is reported as phase `(none)`. If the `--by-phase` share of a phase differs from
  its timer wall share by more than 5 points, suspect sampling bias (blocked I/O produces
  no samples).
- Use the `ci` or `nocache` flags, not JIT (JIT code does not keep the current opline up to date, so line attribution is unreliable).
- Workers at -j4 need `sudo phpspy -p <pid>` per worker, after `pgrep -P <parent>` shows
  them (ptrace_scope=1).

`phpspy_top.php [--top N] [--by func|class|file|ns] [--phase P] [--by-phase] [--min-pct X]
[--tsv] A [--diff B]` reads phpspy multi-line traces (frames `<depth> <func> <file>:<line>`,
samples separated by a blank line or `# - - - - -`, `# glopeek globals.__phase = <phase>`
lines), phpspy `-1` single-line output, or folded stacks (`a;b;c N`; no file information).
Self counts the leaf frame; inclusive counts each distinct key once per stack. `--phase`
restricts to one phase, `--by-phase` prints sample shares per phase, `--diff B` prints the
self% and inclusive% change per key sorted by |Δself|, `--tsv` emits tab-separated rows.
`.gz` inputs are read directly.

## pss_sidecar.sh

```
php ... phan -j4 ... & tool/perf/pss_sidecar.sh $! $PERF/pss.tsv
```

Every second appends `ts pid role Rss Pss Private_Clean Private_Dirty Shared_Clean
Shared_Dirty MemAvailable` (kB, from `/proc/<pid>/smaps_rollup` and `/proc/meminfo`) for
the parent and each direct child; exits when the parent is gone. Pass the php process, not
a wrapper such as `/usr/bin/time`.

## Preparing a project subset

A full run over a large project can take too long or not fit in memory on a workstation.
The subset target analyzes a fixed, reference-closed sample of the first-party files while
still parsing everything in `directory_list` (as a CI run does). Steps, with data in
`$SUBSET_DIR` (default `$PERF/project`) and the tree in `$PHAN_PERF_PROJECT_ROOT` (default
`$PERF/project-src`):

1. Pin the tree: `git -C <your project> worktree add --detach $PHAN_PERF_PROJECT_ROOT <commit>` and
   `cp -a <your project>/vendor $PHAN_PERF_PROJECT_ROOT/`. bench.sh writes `tree.pin` on first use and
   refuses runs after HEAD, `git status` or `composer.lock` change.
2. Freeze the config. If `.phan/config.php` computes anything at load time (git commands,
   dates, environment), load it once and export the result:
   ```
   cd $PHAN_PERF_PROJECT_ROOT && php -n -r 'require "vendor/autoload.php";
       $c = require ".phan/config.php";
       file_put_contents($argv[1], "<?php\nreturn " . var_export($c, true) . ";\n");' \
       $SUBSET_DIR/config.frozen.php
   ```
   Replace plugin entries that point into `vendor/phan/phan/.phan/plugins/` with the bare
   plugin name (e.g. `'UnreachableCodePlugin'`) so the Phan checkout under test supplies
   the plugin. Keep `directory_list`: parsing it is a fixed cost of every real run.
3. Keep a copy of the project's own analysis file list if it has one; do not regenerate it
   with tools that rewrite other files. Produce the parse universe:
   `cd $PHAN_PERF_PROJECT_ROOT && php -n -d extension=ast.so <phan> -k $SUBSET_DIR/config.frozen.php
   --dump-parsed-file-list > $SUBSET_DIR/universe.txt`.
4. Generate the subset:
   ```
   tool/perf/make_subset.php --root $PHAN_PERF_PROJECT_ROOT --universe $SUBSET_DIR/universe.txt \
       --fixed-prefix vendor/ --fixed-prefix .phan/ --rate 0.12 --salt <name>-v1 \
       --closure hard+soft1 --manifest $SUBSET_DIR/subset.manifest.json \
       --analyze-list $SUBSET_DIR/subset.analyze.files \
       --unresolved-out $SUBSET_DIR/unresolved.tsv > $SUBSET_DIR/subset.files
   ```
   Seeds are first-party files with `hexdec(substr(sha1(salt . path), 0, 8)) % 1000000 <
   rate × 1000000`. The closure adds files declaring what the selection extends, implements
   or uses as a trait, plus the first class of `@extends`/`@implements`/`@mixin`/`@use`
   tags (hard, transitively); then one level of soft references and their hard closure.
   Soft references: `new X`, `X::m`/`X::C`, `X::class`, `instanceof`, `catch`, parameter/
   return/property types, PHPDoc type expressions (`@param`, `@return`, `@var`,
   `@property*`, `@method`, `@throws`, `@template ... of`, generic arguments, and the
   `phan-`/`psalm-`/`phpstan-` variants), attribute classes, and calls to functions declared
   outside classes (resolved through `use function`, the namespace, then the global name).
   `--closure hard+soft1+sig1` follows one more level, but only the declaration-level
   references (signature types, PHPDoc and attributes outside function bodies) of the files
   the soft level added: those are the types Phan infers for calls into soft-added code
   (`PhanUndeclaredClassMethod` / `ClassProperty` on returned objects). On Phan's own
   source as the project (580 first-party files, 85 seeds) the analyzed subset reported 32
   issues (23 `PhanUndeclared*`) with the old scanner, 17 (8) with `hard+soft1`, and 1 (0)
   with `hard+soft1+sig1`.
   Without the closure most references are undeclared and analysis short-circuits.
   `--convention` is a fallback for referenced classes that no universe file declares
   (e.g. created by `class_alias`): `underscore:lib` maps `A_B_C` to `lib/A/B/C.php`,
   `psr4:Acme\Modules\=modules/{1}/src/` maps `Acme\Modules\M\X\Y` to
   `modules/M/src/X/Y.php` (`{1}` takes the first segment after the prefix; without it,
   plain PSR-4). The candidate path is matched case-insensitively against the universe.
   `--unresolved-out` lists referenced names that resolve to nothing, most referenced
   first, with their reference kinds.
   stdout is the parse selection (seeds + hard + soft level); `--analyze-list` writes seeds
   + hard closure, which bench.sh applies as `include_analysis_file_list`. Files added only
   by the soft level are parsed so references resolve, but not analyzed: their own
   references are not closed, and on Phan's own source analyzing them produced 923 of 946
   `PhanUndeclared*` issues.
   Files under a `--fixed-prefix` are indexed for name resolution but never listed: the
   parse selection (stdout) contains first-party files only, because Phan parses the
   fixed-prefix files through `directory_list` anyway. The manifest records the universe
   sha1, a sha1 over every file's content, parameters, seed/closure/output/analyze-list
   counts with both sha1s, distinct referenced names by resolution (`first_party`, `fixed`,
   `convention`, `internal`, `unresolved`), the same split per reference kind
   (`ref_kinds`), and how many files were added through a convention. Output and
   manifest are deterministic for the same inputs. Indexing runs at about 10 MB/s of
   source.
5. Fit check: `bench.sh -t project-subset -j1 -r 1` and `-j4`, with `--mem-max` below your
   free memory. On a large project the -j4 cgroup peak was about 2.7-2.9× the -j1 peak,
   so size `--mem-max` from the -j1 run. Accept when nothing is OOM-killed, `peak_anon_kb` stays well under the cap,
   -j1 wall is 2-4 minutes, and undeclared-symbol issues are a small share
   (`grep -c ' PhanUndeclared' issues.txt` vs `wc -l issues.txt`, aim for under ~10 %);
   otherwise adjust `--rate` (0.08-0.20).
