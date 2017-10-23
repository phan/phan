<?php declare(strict_types=1);
namespace Phan;

/**
 * Utility for profiling Phan runs. Used if the profiler_enabled config setting is true.
 */
trait Profile
{
    /**
     * @var int[][]
     */
    private static $label_delta_map = [];

    /**
     * Measure the clock-time taken to execute the given
     * closure and emit the time with the given label to
     * a log.
     *
     * @param $label
     * A label to emit with the time taken to run the
     * given closure
     *
     * @param \Closure $closure
     * Any closure to measure how long it takes to run
     */
    protected static function time(string $label, \Closure $closure)
    {

        if (!Config::getValue('profiler_enabled')) {
            return $closure();
        }

        static $initialized = false;
        if (!$initialized) {
            self::initialize();
            $initialized = true;
        }

        // Measure the time to execute the given closure
        $start_time = microtime(true);
        $return_value = $closure();
        $end_time = microtime(true);

        // Emit a log message
        $delta = ($end_time - $start_time);

        self::$label_delta_map[$label][] = $delta;

        return $return_value;
    }

    /**
     * Initialize the profiler
     */
    private static function initialize()
    {

        // Create a shutdown function to emit the log when we're
        // all done
        register_shutdown_function(function () {
            $label_metric_map = [];

            // Compute whatever metric we care about
            foreach (self::$label_delta_map as $label => $delta_list) {
                $total_time = array_sum($delta_list);
                $count = count($delta_list);
                $average_time = $total_time/$count;
                $label_metric_map[$label] = [
                    $count,
                    $total_time,
                    $average_time
                ];
            }


            // Sort such that the highest metric value is on top
            uasort($label_metric_map, function ($a, $b) {
                return ($b[1] <=> $a[1]);
            });

            // Print it all out
            foreach ($label_metric_map as $label => $metrics) {
                print $label
                    . "\t"
                    . implode("\t", array_map(function (float $v) {
                        return sprintf("%0.6f", $v);
                    }, $metrics))
                    . "\n";
            }
        });
    }
}
