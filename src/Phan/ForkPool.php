<?php declare(strict_types=1);
namespace Phan;

/**
 * Fork off to n-processes and divide up tasks between
 * each process.
 */
class ForkPool {

    /** @var int[] */
    private $child_pid_list = [];

    /**
     * @param int $pool_size
     * The number of worker processes to create
     *
     * @param array $task_data_iterator
     * An array of task data items to be divided up among the
     * workers
     *
     * @param \Closure $startup_closure
     * A closure to execute upon starting a child
     *
     * @param \Closure $task_closure
     * A method to execute on each task data
     *
     * @param \Closure $shutdown_closure
     * A closure to execute upon shutting down a child
     */
    public function __construct(
        array $process_task_data_iterator,
        \Closure $startup_closure,
        \Closure $task_closure,
        \Closure $shutdown_closure
    ) {

        $pool_size = count($process_task_data_iterator);

        assert($pool_size > 1,
            'The pool size must be >= 2 to use the fork pool.');

        assert(extension_loaded('pcntl'),
            'The pcntl extension must be loaded in order for Phan to be able to fork.'
        );

        // We'll keep track of if this is the parent process
        // so that we can tell who will be doing the waiting
        $is_parent = false;

        // Fork as many times as requested to get the given
        // pool size
        for ($proc_id = 0; $proc_id < $pool_size; $proc_id++) {
            // Fork
            $pid = 0;
            if (($pid = pcntl_fork()) < 0) {
                error_log(posix_strerror(posix_get_last_error()));
                exit(EXIT_FAILURE);
            }

            // Parent
            if ($pid > 0) {
                $is_parent = true;
                $this->child_pid_list[] = $pid;
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

        // Execute anything the children wanted to execute upon
        // starting up
        $startup_closure();

        // Get the work for this process
        $task_data_iterator = array_values($process_task_data_iterator)[$proc_id];
        foreach ($task_data_iterator as $i => $task_data) {
            $task_closure($i, $task_data);
        }

        // Execute each child's shutdown closure before
        // exiting the process
        $shutdown_closure();

        // Children exit after completing their work
        exit(EXIT_SUCCESS);
    }

    /**
     * Wait for all child processes to complete
     */
    public function wait() {
        // Wait for all children to return
        foreach ($this->child_pid_list as $child_pid) {
            if (pcntl_waitpid($child_pid, $status) < 0) {
                error_log(posix_strerror(posix_get_last_error()));
            }

            // Check to see if the child died a graceful death
            $status = 0;
            if (pcntl_wifsignaled($status)) {
                $return_code = pcntl_wexitstatus($status);
                $term_sig = pcntl_wtermsig($status);
                error_log("Child terminated with return code $return_code and signal $term_sig");
            }
        }

    }

}
