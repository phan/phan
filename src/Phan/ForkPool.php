<?php declare(strict_types=1);

namespace Phan;

use AssertionError;
use Closure;
use InvalidArgumentException;

use function count;
use function gettype;
use function intval;
use function strlen;

use const EXIT_FAILURE;
use const EXIT_SUCCESS;

/**
 * Fork off to n-processes and divide up tasks between
 * each process.
 */
class ForkPool
{

    /** @var array<int,int> a list of process ids that have been forked*/
    private $child_pid_list = [];

    /** @var array<int,resource> a list of read strings for $this->child_pid_list */
    private $read_streams = [];

    /** @var bool did any of the child processes fail (e.g. crash or send data that couldn't be unserialized) */
    private $did_have_error = false;

    /**
     * @param array<int,array> $process_task_data_iterator
     * An array of task data items to be divided up among the
     * workers. The size of this is the number of forked processes.
     *
     * @param Closure $startup_closure
     * A closure to execute upon starting a child
     *
     * @param Closure $task_closure
     * A method to execute on each task data.
     * This closure must return an array (to be gathered).
     *
     * @param Closure $shutdown_closure
     * A closure to execute upon shutting down a child
     * @throws InvalidArgumentException if count($process_task_data_iterator) < 2
     * @throws AssertionError if pcntl is disabled before using this
     */
    public function __construct(
        array $process_task_data_iterator,
        Closure $startup_closure,
        Closure $task_closure,
        Closure $shutdown_closure
    ) {

        $pool_size = count($process_task_data_iterator);

        if ($pool_size < 2) {
            throw new InvalidArgumentException('The pool size must be >= 2 to use the fork pool.');
        }

        if (!\extension_loaded('pcntl')) {
            throw new AssertionError('The pcntl extension must be loaded in order for Phan to be able to fork.');
        }

        // We'll keep track of if this is the parent process
        // so that we can tell who will be doing the waiting
        $is_parent = false;

        // Fork as many times as requested to get the given
        // pool size
        for ($proc_id = 0; $proc_id < $pool_size; $proc_id++) {
            // Create an IPC socket pair.
            $sockets = \stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
            if (!$sockets) {
                \error_log("unable to create stream socket pair");
                exit(EXIT_FAILURE);
            }

            // Fork
            $pid = \pcntl_fork();
            if ($pid < 0) {
                \error_log(\posix_strerror(\posix_get_last_error()));
                exit(EXIT_FAILURE);
            }

            // Parent
            if ($pid > 0) {
                $is_parent = true;
                $this->child_pid_list[] = $pid;
                $this->read_streams[] = self::streamForParent($sockets);
                continue;
            }

            // Child
            if ($pid === 0) {
                $is_parent = false;
                break;
            }
        }

        // If we're the parent, return
        if ($is_parent) {
            return;
        }

        // Get the write stream for the child.
        $write_stream = self::streamForChild($sockets);

        // Execute anything the children wanted to execute upon
        // starting up
        $startup_closure();

        // Get the work for this process
        $task_data_iterator = \array_values($process_task_data_iterator)[$proc_id];
        foreach ($task_data_iterator as $i => $task_data) {
            $task_closure($i, $task_data);
        }

        // Execute each child's shutdown closure before
        // exiting the process
        $results = $shutdown_closure();

        // Serialize this child's produced results and send them to the parent.
        \fwrite($write_stream, \serialize($results ?: []));

        \fclose($write_stream);

        // Children exit after completing their work
        exit(EXIT_SUCCESS);
    }

    /**
     * Prepare the socket pair to be used in a parent process and
     * return the stream the parent will use to read results.
     *
     * @param array<int,resource> $sockets the socket pair for IPC
     * @return resource
     */
    private static function streamForParent(array $sockets)
    {
        list($for_read, $for_write) = $sockets;

        // The parent will not use the write channel, so it
        // must be closed to prevent deadlock.
        \fclose($for_write);

        // stream_select will be used to read multiple streams, so these
        // must be set to non-blocking mode.
        if (!\stream_set_blocking($for_read, false)) {
            \error_log('unable to set read stream to non-blocking');
            exit(EXIT_FAILURE);
        }

        return $for_read;
    }

    /**
     * Prepare the socket pair to be used in a child process and return
     * the stream the child will use to write results.
     *
     * @param array<int,resource> $sockets the socket pair for IPC.
     * @return resource
     */
    private static function streamForChild(array $sockets)
    {
        list($for_read, $for_write) = $sockets;

        // The while will not use the read channel, so it must
        // be closed to prevent deadlock.
        \fclose($for_read);
        return $for_write;
    }

    /**
     * Read the results that each child process has serialized on their write streams.
     * The results are returned in an array, one for each worker. The order of the results
     * is not maintained.
     *
     * @return array[]
     */
    private function readResultsFromChildren()
    {
        // Create an array of all active streams, indexed by
        // resource id.
        $streams = [];
        foreach ($this->read_streams as $stream) {
            $streams[intval($stream)] = $stream;
        }

        // Create an array for the content received on each stream,
        // indexed by resource id.
        $content = \array_fill_keys(\array_keys($streams), '');

        // Read the data off of all the stream.
        while (count($streams) > 0) {
            $needs_read = \array_values($streams);
            $needs_write = null;
            $needs_except = null;

            // Wait for data on at least one stream.
            $num = \stream_select($needs_read, $needs_write, $needs_except, null /* no timeout */);
            if ($num === false) {
                \error_log("unable to select on read stream");
                exit(EXIT_FAILURE);
            }

            // For each stream that was ready, read the content.
            foreach ($needs_read as $file) {
                $buffer = \fread($file, 1024);
                if ($buffer === false) {
                    \error_log("unable to read from stream of worker process");
                    exit(EXIT_FAILURE);
                }
                if (strlen($buffer) > 0) {
                    $content[intval($file)] .= $buffer;
                }

                // If the stream has closed, stop trying to select on it.
                if (\feof($file)) {
                    \fclose($file);
                    unset($streams[intval($file)]);
                }
            }
        }

        // Unmarshal the content into its original form.
        /**
         * @param string $data
         * @return mixed[]
         */
        return \array_values(\array_map(function ($data) {
            $result = \unserialize($data);
            if (!\is_array($result)) {
                \error_log("Child terminated without returning a serialized array (threw or crashed - not enough memory?): response type=" . gettype($result));
                $this->did_have_error = true;
            }
            return $result;
        }, $content));
    }

    /**
     * Wait for all child processes to complete
     * @return array[]
     */
    public function wait()
    {
        // Read all the streams from child processes into an array.
        $content = $this->readResultsFromChildren();

        // Wait for all children to return
        foreach ($this->child_pid_list as $child_pid) {
            if (\pcntl_waitpid($child_pid, $status) < 0) {
                \error_log(\posix_strerror(\posix_get_last_error()));
            }

            // Check to see if the child died a graceful death
            if (\pcntl_wifsignaled($status)) {
                $return_code = \pcntl_wexitstatus($status);
                $term_sig = \pcntl_wtermsig($status);
                $this->did_have_error = true;
                \error_log("Child terminated with return code $return_code and signal $term_sig");
            }
        }

        return $content;
    }

    /**
     * Returns true if this had an error, e.g. due to memory limits or due to a child process crashing.
     */
    public function didHaveError() : bool
    {
        return $this->did_have_error;
    }
}
