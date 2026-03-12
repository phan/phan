# PhoundPlugin Integration Tests

Tests for `src/Phan/Plugin/Internal/PhoundPlugin.php`.

## How it works

`test.sh` runs Phan on the PHP files in `src/`, which populates a SQLite database
(`~/phound.db`) via PhoundPlugin. The script then queries the database for each source
file and compares the output against the corresponding expected file in `expected/`.

Each source file `src/NNN_name.php` has a matching `expected/NNN_name.php.expected`.

## Running tests

```bash
cd tests/phound_test
bash test.sh           # compare against expected output
bash test.sh --update  # regenerate expected files from current DB output
```

## Adding a new test

1. Create a new file in `src/` following the naming convention: `NNN_phound_description.php`
2. **Give it a unique namespace** (see below)
3. Run `bash test.sh --update` to generate the expected output file
4. Review the generated `expected/NNN_phound_description.php.expected` for correctness
5. Run `bash test.sh` to confirm it passes

## Namespace requirement

Each test file must declare a unique namespace. This is required because the
per-file expected output relies on filtering the SQLite database by filepath.

Tables with a `filepath` column (e.g., `classes`, `signatures`) are filtered directly.
But relationship tables (`class_relationships`, `class_interfaces`, `class_traits`, etc.)
don't have a `filepath` column — they only store class names. The test script filters
them with subqueries like:

```sql
WHERE child IN (SELECT name FROM classes WHERE filepath = 'src/NNN_...')
```

Without namespaces, if two test files both define a class called `Base`, they'd share
the same entry in these tables, and the subquery would pull data from the wrong file
into the expected output. Unique namespaces prevent this.
