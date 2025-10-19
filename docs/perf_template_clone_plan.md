# Template Method Cloning Optimization Plan

## Goals
- Reduce the number of full `Method::cloneWithTemplateParameterTypeMap()` executions when importing methods through class hierarchies.
- Maintain correctness for template substitutions (respecting override semantics, visibility, reference tracking, and cached metadata).

## Investigation Checklist
- [ ] Trace the call paths through `Clazz::addMethod()` and `Clazz::importAncestorClasses()` to catalogue where identical template maps are repeatedly applied.
- [x] Measure how often `cloneWithTemplateParameterTypeMap()` is called per file during a baseline run (add temporary counters or reuse existing profiling hooks).
- [ ] Confirm whether template maps are stable (identical arrays) for the same method across imports; if not, identify the varying keys/values.
- [ ] Audit fields that must remain unique per clone (e.g. `reference_list`, `parameter_list`, comments) to ensure any memoization does not accidentally share mutable state.

## Proposed Approach
1. Introduce a memoization cache on `Method` keyed by the normalized template map (e.g. stable string hash of `(template_id => type_id)` pairs) to reuse template-substituted union/comment types for new clones. **(in progress — per-method substitution cache is active; exploring higher-level reuse proved neutral and was rolled back).**
2. Avoid unnecessary template substitution work when the map is empty or the method has no template types. **(done — early exit before substitution while still cloning parameters to keep state isolated.)**
3. Extract the expensive `cloneParameterList()` and `Comment` cloning steps into lazily-initialized helpers so cached instances can reuse immutable data without forcing deep copies.
4. Provide instrumentation (e.g. debug counters under `Config::getValue('dump_template_clone_stats')`) to validate hit rate and detect regressions. **(done — stats flag wiring complete)**
5. Detect empty template maps earlier during inheritance/trait import to bypass `cloneWithTemplateParameterTypeMap()` entirely. **(done — `Clazz::addMethod()`/trait import now fall back to a plain clone with an explicit `cloneParameterList()` when the computed template map is empty).**
6. Validate against the existing test suite and collect a fresh phpspy sample to compare the flame width for `cloneWithTemplateParameterTypeMap`.

## Notes
- Baseline stats (`--dump-template-clone-stats` on 2024-10-19) showed 3.76M clone attempts with 99.6 % using an empty map; a handful of vendor methods dominate the non-empty cases, guiding cache targeting.
- Instrumentation now reports counts for `no_template` (early exits) and `cache_hits` to help evaluate memoization effectiveness after each change.
- After introducing the cache/short-circuit, a follow-up run still reported 3.76M clone requests, with `no_template=3,745,782` and `cache_hits=12,159` (~78 % of non-empty maps), confirming we skip most substitution work and reuse results for the busiest call sites.
- Skipping parameter cloning in the empty-map path briefly dropped the full scan to ~406 s (vs. 446 s baseline, 418 s after caching) but introduced false positives; the guard now keeps parameter cloning while moving the empty-map fast path to `Clazz::addMethod()` to retain correctness.

## Open Questions
- How should we handle memoized clones once the owning `CodeBase` mutates (e.g. inferred return types via `ensureClonesReturnType`)? We may need to clone-on-write after memoization.
- Do traits or mixins ever expect distinct clones for the same template map because of downstream mutations? Identify specific scenarios before sharing instances.
- Is the cost dominated by cloning, type substitution, or comment parameter rewriting? Micro-benchmarks may be needed to focus the optimization.
