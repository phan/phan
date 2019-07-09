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
    /**
     * Remove the leading #!/path/to/interpreter/of/php from a CLI script, if any was found.
     */
    public static function removeShebang(string $file_contents) : string
    {
        if (substr($file_contents, 0, 2) !== "#!") {
            return $file_contents;
        }
        for ($i = 2; $i < strlen($file_contents); $i++) {
            $c = $file_contents[$i];
            if ($c === "\r") {
                if (($file_contents[$i + 1] ?? '') === "\n") {
                    $i++;
                    break;
                }
            } elseif ($c === "\n") {
                break;
            }
        }
        if ($i >= strlen($file_contents)) {
            return '';
        }
        $rest = (string)substr($file_contents, $i + 1);
        if (strcasecmp(substr($rest, 0, 5), "<?php") === 0) {
            // declare(strict_types=1) must be the first part of the script.
            // Even empty php tags aren't allowed prior to it, so avoid adding empty tags if possible.
            return "<?php\n" . substr($rest, 5);
        }
        // Preserve the line numbers by adding a no-op newline instead of the removed shebang
        return "<?php\n?>" . $rest;
    }

}
