# Cerebrum

> OpenWolf's learning memory. Updated automatically as the AI learns from interactions.
> Do not edit manually unless correcting an error.
> Last updated: 2026-04-04

## User Preferences

<!-- How the user likes things done. Code style, tools, patterns, communication. -->

## Key Learnings
- (2026-10-06) In this environment `gh pr create` fails with "Resource not accessible by personal access token (createPullRequest)" and `git push` to both phan/phan.wiki.git and phan/wiki.git returns 403 for joeuser12, although pushing branches to phan/phan works. After pushing a branch, hand the user the compare URL and a saved PR body, and leave the wiki commit local for them to push.

- **Project:** phan
- **Description:** Phan is a static analyzer for PHP that prefers to minimize false-positives. Phan attempts to prove incorrectness rather than correctness.

## Do-Not-Repeat
- (2026-10-08) `./phan` self-analysis passing is NOT enough before pushing: CI also runs `./tests/run_test __FakeSelfFallbackTest`, which adds plugins such as AvoidableGetterPlugin (flags `$this->getFoo()` inside the trait/class that owns `$foo`). Run `__FakeSelfTest` AND `__FakeSelfFallbackTest` locally before pushing changes under src/.
- (2026-09-05) When appending a completion marker to a log (`echo exit=$? >> log`), first `echo` a newline: PHPUnit's progress output has no trailing newline, so `^exit=` never matches and `until grep -q` wait loops spin until the OOM killer stops them. Prefer `printf '\nexit=%s\n'` and grep without `^`.
- (2026-09-05) `git stash` with a clean tracked tree is a no-op, so a following `git stash pop` applies the user's OLDEST-FIRST existing stash@{0} (this repo has ~59 stashes). Never pair stash/pop for temporary base-branch comparisons; use `git checkout <ref> -- <file>` + `git checkout HEAD -- <file>`, or `git worktree`.

<!-- Mistakes made and corrected. Each entry prevents the same mistake recurring. -->
<!-- Format: [YYYY-MM-DD] Description of what went wrong and what to do instead. -->

## Decision Log
- (2026-10-06) Conditional return types are stored as data (`FunctionTrait::$conditional_return_type`) and resolved inside `getDependentReturnType()`, NOT installed as a closure at parse time: `Method::cloneWithTemplateParameterTypeMap` also runs at call time after template/plugin closures exist, and `addClosureForDependentTemplateType` returns early when a closure exists. Plugin closures keep priority; the template closure composes the conditional as its base type.
- (2026-10-06) Comment-tag regexes in `Comment/Builder.php` must be anchored to the start of the line (`^[\s\/*]*@tag`): an unanchored `@return (` matched examples inside description text of other annotations during self-analysis.
- (2026-09-05) Phan picks an internal function's alternate signature (`'name\'N'` keys in FunctionSignatureMap) by argument count; add a new alternate rather than trying to model "variadic arrays then a required callback" in one signature (that shape mis-types the callback as the variadic).
- (2026-09-05) `ArrayShapeType::canCastToList()` means list-*compatible*, not always-a-list: optional keys are independent, so `array{0?:T,1?:U}` permits `[1=>$u]`. Don't treat canCastToList() as proof that a value is a list.
- (2026-09-05) When adding a public non-static method to `UnionType`, also add an override in `EmptyUnionType` — `tests/Phan/Language/EmptyUnionTypeTest::testMethods` fails otherwise ("unexpected declaring class").

<!-- Significant technical decisions with rationale. Why X was chosen over Y. -->

- (2026-07-20, user correction) The phan repo's GitHub default branch is **v6**, not v5. Environment/git-status snapshots claiming "main branch: v5" are stale. Consequence: `fixes #N` closing keywords in commits/PRs auto-close issues when merged into v6.

## Learnings (2026-09-15)
- A full `./vendor/bin/phpunit` run stops printing at `LanguageServerIntegrationTest` (exit 0, no summary) even on a clean tree. Run `--filter '/^(?!.*LanguageServerIntegrationTest)/'` and `--filter LanguageServerIntegrationTest` separately to get real summaries.
- `Config::AST_VERSION` aliases `\define('Phan\AST_VERSION', 120)` — anything that gives define()d constants literal real types will make `Config::AST_VERSION >= 120` a PhanRedundantValueComparison in self-analysis.

## Learnings (2026-09-29)
- Testing a PR in a `git worktree` with `ln -s ../phan/vendor vendor` fails with "Cannot redeclare function phan_output_ast_installation_instructions()": composer's `autoload_real.php`/`autoload_static.php` resolve `$baseDir` from the *real* path of `vendor/composer`, so classes load from the main checkout while `tests/bootstrap.php` loads the worktree's `src/Phan/Bootstrap.php`. Fix: copy `vendor/composer/`, `vendor/autoload.php` and `vendor/bin/` as real files into the worktree and symlink every other `vendor/*` package.
- `.phan/config.php` `directory_list` includes `tests/Phan`, so new PHPUnit test classes are self-analyzed. `@internal` methods (`ConfigPluginSet::reset()`, `Config::reset()`) are exempt from `PhanAccessMethodInternal` when called from the same namespace, so a test declared in a production namespace (e.g. `namespace Phan\Plugin;`) silently skips that check; tests belong in `Phan\Tests\...` (composer autoload-dev) with an explicit suppression.
- To self-analyze one file with the full plugin set without a multi-minute run: `./phan --memory-limit 2G --no-progress-bar --include-analysis-file-list path/to/file.php` (parses `directory_list`, analyzes only the listed file).
