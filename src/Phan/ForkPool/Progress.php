<?php

declare(strict_types=1);

namespace Phan\ForkPool;

/**
 * Represents the current progress of a forked analysis worker.
 * @phan-immutable
 */
class Progress
{
    /** @var float the fraction of progress made by this worker (0..1) */
    public $progress;

    /** @var int the current memory usage of this worker, in bytes. The total gets overestimated because summing doesn't account for memory sharing. */
    public $cur_mem;

    /** @var int the maximum memory usage of this worker, in bytes. The total gets overestimated because summing doesn't account for memory sharing. */
    public $max_mem;

    /** @var int the number of files this worker has analyzed so far */
    public $analyzed_files;
    /** @var int the total number of files this worker will analyze */
    public $file_count;

    public function __construct(float $progress, int $file_count, int $analyzed_files)
    {
        $this->progress = $progress;
        $this->cur_mem = \memory_get_usage();
        $this->max_mem = \memory_get_peak_usage();
        $this->analyzed_files = $analyzed_files;
        $this->file_count = $file_count;
    }
}
