This directory contains scripts that may be useful to people developing phan itself.

# [`check_signature_map_against_reflection.php`](./check_signature_map_against_reflection.php)

Compares Phan's function signature map for the running PHP version (the base map with the deltas up to that version applied)
with Reflection, for the functions and methods of the extensions bundled with php-src.
Reports differences in the number of parameters, parameter names and by-reference parameters, and the functions and methods missing from the map.
Run it with each supported PHP version, e.g. `/usr/local/php83/bin/php internal/check_signature_map_against_reflection.php`.

[`lib/SignatureReflectionCheckPlugin.php`](./lib/SignatureReflectionCheckPlugin.php) does the same for what Phan actually loads (the map, the bundled stubs and Reflection combined):
`./phan -n --target-php-version 8.6 --plugin internal/lib/SignatureReflectionCheckPlugin.php any_file.php`

# [`update_signature_map_from_reflection.php`](./update_signature_map_from_reflection.php)

Updates the signature map so that the parameters (names, order, optionality, by-reference and variadic flags) match Reflection for the running PHP version,
and replaces return types that contradict Reflection's declared or tentative return type. Phan's more precise phpdoc types are kept.
PHP 8.1 updates `FunctionSignatureMap.php`, newer versions update their `FunctionSignatureMap_phpXY_delta.php`.
Run it with each PHP version in order (8.1, 8.2, ..., 8.6), then check the result with `check_signature_map_against_reflection.php`.

# [`internalsignatures.php`](./internalsignatures.php)

Compares Phan's param/return types for functions/methods with the param/return types found in a local svn checkout of the XML sources of docs.php.net.

Run `php internal/internalsignatures.php help` for more details.

# [`make_phar`](./make_phar)

Installs optimized composer dependencies to make a minimal Phar file, then invokes `package.php`.

# [`package.php`](./package.php)

Creates a phar file from whatever's in src, vendor, etc. Use `./make_phar` instead.

# [`phpcs`](./phpcs)

Checks for violations of the codesniffer code style rules.

# [`phpcbf`](./phpcbf)

Automatically fixes any fixable violations of the code style rules.

# [`sanitycheck.php`](./sanitycheck.php)

This compares reflection signatures with Phan's own internal function signature map.
This assumes the reflection signatures are the latest right now.

# [`update_wiki_issue_types.php`](./update_wiki_issue_types.php)

Updates the local copy of [Issue-Types-Caught-by-Phan.md](./Issue-Types-Caught-by-Phan.md) with any issues that are in Phan but not yet documented.
