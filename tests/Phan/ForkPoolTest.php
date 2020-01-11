<?php

declare(strict_types=1);

namespace Phan\Tests;

use Phan\ForkPool;

/**
 * Unit test of the ForkPool
 *
 * @requires extension pcntl
 * @phan-file-suppress PhanAccessMethodInternal
 */
final class ForkPoolTest extends BaseTest
{
    /**
     * Test that workers are able to send their data back
     * to the parent process.
     */
    public function testBasicForkJoin(): void
    {
        $data = [
            [1, 2, 3, 4],
            [5, 6, 7, 8],
            [9, 10, 11, 12],
            [13, 14, 15, 16],
        ];
        $combined_data = [
            1, 2, 3, 4,
            5, 6, 7, 8,
            9, 10, 11, 12,
            13, 14, 15, 16,
        ];

        $worker_data = [];
        $pool = new ForkPool(
            $data,
            static function (): void {
            },
            /**
             * This is called on every value of the arrays passed to workers
             * @param int $unused_i
             * @param int $data
             */
            static function (int $unused_i, int $data, int $count) use (&$worker_data): void {
                if ($count !== 4) {
                    $worker_data[] = "Unexpected count $count";
                }
                $worker_data[] = $data;
            },
            /**
             * @return list<array>
             */
            static function () use (&$worker_data): array {
                return $worker_data;
            }
        );
        $actual_data = $pool->wait();
        \sort($actual_data);

        $this->assertSame($combined_data, $actual_data);
    }

    /**
     * Test that the startup function works.
     */
    public function testStartupFunction(): void
    {
        $did_startup = false;
        $pool = new ForkPool(
            [[1], [2], [3], [4]],
            static function () use (&$did_startup): void {
                $did_startup = true;
            },
            /**
             * @param int $unused_i
             * @param mixed $unused_data
             */
            static function (int $unused_i, $unused_data, int $unused_count): void {
            },
            /**
             * @return array{0:bool}
             */
            static function () use (&$did_startup): array {
                return [$did_startup];
            }
        );

        $this->assertSame(
            [true, true, true, true],
            $pool->wait()
        );
    }
}
