<?php declare(strict_types=1);

namespace Phan\Library;

use Phan\Plugin\Internal\IssueFixingPlugin\FileContents;

/**
 * Represents the cached contents of a given file, and various ways to access that file.
 *
 * This is used under the circumstances such as the following:
 *
 * - Checking for (at)phan-suppress-line annotations at runtime - Many checks to the same file will often be in cache
 * - Checking the tokens/text of the file for purposes such as checking for expressions that are incompatible in PHP5.
 * - `--automatic-fix`
 * @suppress PhanDeprecatedClass FileContents will be removed in a future release.
 */
class FileCacheEntry extends FileContents
{
}
