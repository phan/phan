<?php declare(strict_types = 1);
namespace Phan\Tests;

use Phan\ForkPool;

/**
 * @requires extension pcntl
 */
class ForkPoolTest extends BaseTest
{
    /**
     * Test that workers are able to send their data back
     * to the parent process.
     */
    public function testBasicForkJoin()
    {
        $data = [
            [1, 2, 3, 4],
            [5, 6, 7, 8],
            [9, 10, 11, 12],
            [13, 14, 15, 16],
        ];

        $worker_data = [];
        $pool = new ForkPool($data,
            function() { },
            function($i, $data) use(&$worker_data) {
                $worker_data[] = $data;
            },
            function() use(&$worker_data) {
                return $worker_data;
            });

        $this->assertEquals($data, $pool->wait());
    }

    /**
     * Test that the startup function works.
     */
    public function testStartupFunction()
    {
        $did_startup = false;
        $pool = new ForkPool(
            [[1], [2], [3], [4]],
            function() use(&$did_startup) {
                $did_startup = true;
            },
            function($i, $data) {
            },
            function() use(&$did_startup){
                return $did_startup;
            });

        $this->assertEquals(
            [true, true, true, true],
            $pool->wait());
    }
}
