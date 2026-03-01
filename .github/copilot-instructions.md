# Copilot Review Instructions for Phan

## Project Philosophy

Phan is a static analyzer for PHP that follows two core principles:

### 1. Minimize False Positives

Phan attempts to **prove incorrectness rather than correctness**. We would rather miss a real issue than emit a false positive. When reviewing changes:

- If a change adds a new warning, consider whether it could fire on correct code. Even rare false positives are unacceptable for non-opt-in warnings.
- Suppressing a warning in edge cases is preferred over emitting a spurious one.
- New issue types that may have false positives should be gated behind `strict_*` config options or placed in a non-default plugin.

### 2. Performance

Phan must be **fast enough to run on large codebases**. All code should keep performance in mind:

- Avoid unnecessary allocations, redundant traversals, or O(n²) patterns in hot paths.
- If a feature would significantly slow down analysis of normal PHP code, it should either not be added or should go in a non-default plugin.
- Prefer simple, direct solutions over abstractions that add overhead.

## Review Guidelines

- Focus review comments on correctness, false positive risk, and performance impact.
- Do not suggest renaming functions, adding docblocks, or other cosmetic changes unless they fix a real problem.
- Do not suggest adding error handling for scenarios that cannot occur in practice.
- Test suggestions should focus on covering the specific bug or feature, not on exhaustive edge case coverage.
