<?php declare(strict_types=1);

namespace Phan\Tests;

use Phan\CLIBuilder;

/**
 * Unit tests of helper methods in the class CLIBuilder
 */
final class CLIBuilderTest extends BaseTest
{
    public function testSetOptions() : void
    {
        $builder = new CLIBuilder();
        $builder->setOption('quick');
        $builder->setOption('b');
        $builder->setOption('processes', '2');
        $builder->setOption('include-analysis-file-list', ['a.php', 'b.php']);
        $this->assertSame([
            '--quick',
            '-b',
            '--processes',
            '2',
            '--include-analysis-file-list',
            'a.php',
            '--include-analysis-file-list',
            'b.php',
        ], $builder->getArgv());
        $this->assertSame([
            'quick' => false,
            'b' => false,
            'processes' => '2',
            'include-analysis-file-list' => ['a.php', 'b.php'],
        ], $builder->getOpts());
    }

    public function testSetOptionsNumeric() : void
    {
        $builder = new CLIBuilder();
        $builder->setOption('3', 'excluded.php');
        $this->assertSame(['-3', 'excluded.php'], $builder->getArgv());
        $this->assertSame([3 => 'excluded.php'], $builder->getOpts());
    }
}
