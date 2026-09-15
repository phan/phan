<?php

declare(strict_types=1);

namespace Phan\Tests;

use Phan\Analysis;
use Phan\CodeBase;
use Phan\Language\FQSEN\FullyQualifiedGlobalConstantName;
use Phan\Library\FileCache;
use Phan\Output\Collector\BufferingCollector;
use Phan\Phan;

/**
 * Tests that a constant declared with define() in several files keeps working in daemon mode
 * when the files declaring it are changed or removed (the undo tracker promotes another declaration).
 *
 * @see https://github.com/phan/phan/issues/5542
 * @phan-file-suppress PhanThrowTypeAbsentForCall
 */
final class GlobalConstantDeclarationUndoTest extends TestBase
{
    /** @var string */
    private $dir;

    /** @var int used to give every written file a distinct modification time */
    private $mtime;

    protected function setUp(): void
    {
        $this->dir = \sys_get_temp_dir() . '/phan_define_undo_' . \getmypid();
        \mkdir($this->dir);
        $this->mtime = \time() - 1000;
        Phan::setIssueCollector(new BufferingCollector());
    }

    protected function tearDown(): void
    {
        foreach (\glob($this->dir . '/*.php') ?: [] as $file) {
            \unlink($file);
        }
        \rmdir($this->dir);
    }

    private function writeFile(string $name, string $contents): string
    {
        $path = $this->dir . '/' . $name;
        \file_put_contents($path, $contents);
        \touch($path, $this->mtime);
        $this->mtime += 10;
        // Daemon mode refreshes the cache itself when a file changes.
        FileCache::clear();
        return $path;
    }

    private static function describe(CodeBase $code_base, FullyQualifiedGlobalConstantName $fqsen): string
    {
        if (!$code_base->hasGlobalConstantWithFQSEN($fqsen)) {
            return 'undeclared';
        }
        $constant = $code_base->getGlobalConstantByFQSEN($fqsen);
        return $constant->getUnionType()->getDebugRepresentation() . '@' . \basename($constant->getFileRef()->getFile());
    }

    public function testAlternateDeclarationIsPromotedWhenFileChanges(): void
    {
        $a = $this->writeFile('a.php', "<?php\ndefine('DC5542', 1);\n");
        $b = $this->writeFile('b.php', "<?php\ndefine('DC5542', 'x');\n");
        $c = $this->writeFile('c.php', "<?php\nif (rand()) { define('DC5542', null); }\n");

        $code_base = new CodeBase([], [], [], [], []);
        $code_base->enableUndoTracking();
        $tracker = $code_base->getUndoTracker();
        $this->assertNotNull($tracker);
        $fqsen = FullyQualifiedGlobalConstantName::fromFullyQualifiedString('\DC5542');

        $files = [$a, $b, $c];
        foreach ($files as $file) {
            Analysis::parseFile($code_base, $file);
        }
        $this->assertSame("'x'|1|null(real=int|null|string)@a.php", self::describe($code_base, $fqsen));

        // The file with the first declaration changes its value: b.php is promoted while a.php is re-parsed.
        $this->writeFile('a.php', "<?php\ndefine('DC5542', 2.5);\n");
        $changed = $tracker->updateFileList($code_base, $files, []);
        $this->assertSame([$a], $changed);
        $this->assertSame("'x'|null(real=null|string)@b.php", self::describe($code_base, $fqsen));
        foreach ($changed as $file) {
            Analysis::parseFile($code_base, $file);
        }
        $this->assertSame("'x'|2.5|null(real=float|null|string)@b.php", self::describe($code_base, $fqsen));

        // The promoted file is removed from the project: the next declaration is promoted.
        $files = [$a, $c];
        $this->assertSame([], $tracker->updateFileList($code_base, $files, []));
        $this->assertSame("2.5|null(real=float|null)@c.php", self::describe($code_base, $fqsen));

        // c.php stops declaring the constant.
        $this->writeFile('c.php', "<?php\necho 'nothing';\n");
        foreach ($tracker->updateFileList($code_base, $files, []) as $file) {
            Analysis::parseFile($code_base, $file);
        }
        $this->assertSame("2.5(real=float)@a.php", self::describe($code_base, $fqsen));

        // The last file declaring it is removed.
        $tracker->updateFileList($code_base, [$c], []);
        $this->assertSame('undeclared', self::describe($code_base, $fqsen));
    }
}
