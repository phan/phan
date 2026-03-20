<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Closure;
use Phan\CLI;
use Phan\CodeBase;
use Phan\Language\Element\FunctionInterface;

/**
 * Worklist-based incremental re-analysis for type convergence.
 *
 * After --analyze-twice completes two full passes, this runs additional
 * targeted passes that only re-analyze files downstream of methods/functions
 * whose inferred return types changed between passes.
 */
class ConvergenceWorklist
{
    /** @var CodeBase */
    private $code_base;

    /** @var int */
    private $max_iterations;

    /** @var array<string, string> FQSEN string => union type string */
    private $type_snapshots = [];

    /** @var list<FunctionInterface> all tracked elements */
    private $tracked_elements = [];

    public function __construct(CodeBase $code_base, int $max_iterations)
    {
        $this->code_base = $code_base;
        $this->max_iterations = $max_iterations;
    }

    /**
     * Build the index of file => elements with inferrable return types.
     * Call once before the worklist loop.
     */
    public function buildIndex(): void
    {
        foreach ($this->code_base->getMethodSet() as $method) {
            if ($method->isPHPInternal()) {
                continue;
            }
            $this->tracked_elements[] = $method;
        }
        foreach ($this->code_base->getFunctionMap() as $func) {
            if ($func->isPHPInternal()) {
                continue;
            }
            $this->tracked_elements[] = $func;
        }
    }

    /**
     * Snapshot current return types of all tracked elements.
     */
    public function snapshotTypes(): void
    {
        $this->type_snapshots = [];
        foreach ($this->tracked_elements as $element) {
            $fqsen_string = (string)$element->getFQSEN();
            $this->type_snapshots[$fqsen_string] = $element->getUnionType()->__toString();
        }
    }

    /**
     * Compare current types against the snapshot and return files
     * containing callers of elements whose types changed.
     *
     * @return list<string> deduplicated file paths to re-analyze
     */
    private function getChangedElementFiles(): array
    {
        $files = [];
        foreach ($this->tracked_elements as $element) {
            $fqsen_string = (string)$element->getFQSEN();
            $current_type = $element->getUnionType()->__toString();
            $snapshot_type = $this->type_snapshots[$fqsen_string] ?? '';
            if ($current_type === $snapshot_type) {
                continue;
            }
            // Add all files that reference this element
            foreach ($element->getReferenceList() as $ref) {
                $files[$ref->getFile()] = true;
            }
        }
        return array_keys($files);
    }

    /**
     * Reorder a file list so that files defining called methods are analyzed
     * before files that call them. Uses reference lists from pass 1 to build
     * a call dependency graph and topologically sort the files.
     *
     * @param list<string> $file_list original file list
     * @return list<string> reordered file list
     */
    public function reorderForPass2(array $file_list): array
    {
        // Build dependency graph: consumer_file depends on producer_file
        // when consumer_file calls a method defined in producer_file.
        $deps = [];       // file => array<string, true> (files it depends on)
        $file_set = array_flip($file_list);

        foreach ($this->tracked_elements as $element) {
            $producer_file = $element->getFileRef()->getFile();
            if (!isset($file_set[$producer_file])) {
                continue;
            }
            foreach ($element->getReferenceList() as $ref) {
                $consumer_file = $ref->getFile();
                if (!isset($file_set[$consumer_file]) || $consumer_file === $producer_file) {
                    continue;
                }
                $deps[$consumer_file][$producer_file] = true;
            }
        }

        // Topological sort via Kahn's algorithm
        // Count incoming edges for each file
        $in_degree = [];
        foreach ($file_list as $file) {
            $in_degree[$file] = 0;
        }
        foreach ($deps as $consumer => $producers) {
            $in_degree[$consumer] = count($producers);
        }

        // Build reverse adjacency: producer => list of consumers
        $consumers_of = [];
        foreach ($deps as $consumer => $producers) {
            foreach ($producers as $producer => $_) {
                $consumers_of[$producer][] = $consumer;
            }
        }

        // Start with files that have no dependencies
        $queue = [];
        foreach ($in_degree as $file => $degree) {
            if ($degree === 0) {
                $queue[] = $file;
            }
        }

        $sorted = [];
        $queue_index = 0;
        while ($queue_index < count($queue)) {
            $file = $queue[$queue_index++];
            $sorted[] = $file;
            foreach ($consumers_of[$file] ?? [] as $consumer) {
                $in_degree[$consumer]--;
                if ($in_degree[$consumer] === 0) {
                    $queue[] = $consumer;
                }
            }
        }

        // If there are cycles, append remaining files sorted by ascending
        // in-degree (fewest unsatisfied dependencies first) so that files
        // whose signatures are least likely to change are analyzed first.
        if (count($sorted) < count($file_list)) {
            $sorted_set = array_flip($sorted);
            $remaining = [];
            foreach ($file_list as $file) {
                if (!isset($sorted_set[$file])) {
                    $remaining[] = $file;
                }
            }
            usort($remaining, static function (string $a, string $b) use ($in_degree): int {
                return $in_degree[$a] <=> $in_degree[$b];
            });
            foreach ($remaining as $file) {
                $sorted[] = $file;
            }
        }

        return $sorted;
    }

    /**
     * Run the worklist loop until convergence or max iterations.
     *
     * @param Closure(int, string, int): void $analysis_worker
     * @return array{int, bool} [iterations performed, whether convergence was reached]
     */
    public function run(Closure $analysis_worker): array
    {
        // Detect what changed between pass 1 and pass 2
        $changed_files = $this->getChangedElementFiles();
        $iteration = 0;

        while (count($changed_files) > 0 && $iteration < $this->max_iterations) {
            $iteration++;
            $file_count = count($changed_files);
            CLI::printToStderr("Convergence pass $iteration: re-analyzing $file_count file(s)\n");

            // Snapshot before re-analysis
            $this->snapshotTypes();

            // Reorder so producers are analyzed before consumers
            $changed_files = $this->reorderForPass2($changed_files);

            // Re-analyze each file (progress is reported by $analysis_worker)
            CLI::resetLongProgressState();
            foreach ($changed_files as $i => $file_path) {
                $analysis_worker($i, $file_path, $file_count);
            }

            // Check what changed in this pass
            $changed_files = $this->getChangedElementFiles();
        }

        $converged = count($changed_files) === 0;
        if (!$converged) {
            CLI::printToStderr("Warning: --analyze-until-convergence hit the maximum of $this->max_iterations iterations without converging\n");
        }

        return [$iteration, $converged];
    }
}
