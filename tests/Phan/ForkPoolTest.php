<?php declare(strict_types=1);

namespace Phan\Tests;

use Phan\ForkPool;

/**
 * Unit test of the ForkPool
 *
 * @requires extension pcntl
 */
final class ForkPoolTest extends BaseTest
{
    /**
     * Test that workers are able to send their data back
     * to the parent process.
     */
    public function testBasicForkJoin() : void
    {
        $data = [
            [1, 2, 3, 4],
            [5, 6, 7, 8],
            [9, 10, 11, 12],
            [13, 14, 15, 16],
        ];

        $worker_data = [];
        $pool = new ForkPool(
            $data,
            /** @return void */
            static function () : void {
            },
            /**
             * This is called on every value of the arrays passed to workers
             * @param int $unused_i
             * @param int $data
             * @return void
             */
            static function (int $unused_i, int $data) use (&$worker_data) : void {
                $worker_data[] = $data;
            },
            /**
             * @return array<int,array>
             */
            static function () use (&$worker_data) : array {
                return $worker_data;
            }
        );

        $this->assertSame($data, $pool->wait());
    }

    /**
     * Test that the startup function works.
     */
    public function testStartupFunction() : void
    {
        $did_startup = false;
        $pool = new ForkPool(
            [[1], [2], [3], [4]],
            /**
             * @return void
             */
            static function () use (&$did_startup) : void {
                $did_startup = true;
            },
            /**
             * @param int $unused_i
             * @param mixed $unused_data
             * @return void
             */
            static function (int $unused_i, $unused_data) : void {
            },
            /**
             * @return array{0:bool}
             */
            static function () use (&$did_startup) : array {
                return [$did_startup];
            }
        );

        $this->assertSame(
            [[true], [true], [true], [true]],
            $pool->wait()
        );
    }
}
