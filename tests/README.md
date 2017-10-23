Folder Layout
=============

Most new tests requiring no additional configuration should go in `files`.

Folders
-------

### fallback\_test

Tests of error-tolerant PHP AST parser (`--use-fallback-parser`)

### files

General tests

### misc

Contains tests of

- ast: `--simplify-ast` (Simplifying AST into an easier to analyze format)
  This tests the resulting AST.
- fallback\_test: `--use-fallback-parser`: Tests of error-tolerant PHP AST parser
  This tests the emitted issues. The parser has its own unit tests.
- rewriting\_test: `--simplify-ast`. This tests the resulting issues emitted by Phan on a simplified AST.

As well as these extension-specific tests that are skipped if the extension isn't loaded:
(TODO: Can we use Phan's stubs to work around the need for this?)

- intl\_files Test cases for issues Phan had analyzing the intl extension.
- soap\_files Test cases for issues Phan had analyzing the SOAP extension.
  (Originally a different folder because SOAP was an optional extension.

### multi\_files

Contain tests of expected behaviour for multi-file project analysis.
This also contains regression tests.
Add a test here if the bug can *only* be reproduced with multiple files.

### Phan

Contains unit tests and integration tests of Phan and its classes.
Some of the other subfolders provide test cases to test classes in the Phan/ folder.
(Many of the other tests are integration tests)

### plugin\_test

Integration tests of all of Phan's plugins' output.

### rasmus\_files

Rasmus's original test cases for Phan.
These get updated when Phan's behavior improves or intentionally changes.

Scripts
-------

`run_test` can be run from the root folder to run individual test suites
