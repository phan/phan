This directory contains scripts that may be useful to people developing phan itself.

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
This assumes the reflection signatures are the latest(PHP 7.2) right now.

# [`update_wiki_issue_types.php`](./update_wiki_issue_types.php)

Updates the local copy of [Issue-Types-Caught-by-Phan.md](./Issue-Types-Caught-by-Phan.md) with any issues that are in Phan but not yet documented.
