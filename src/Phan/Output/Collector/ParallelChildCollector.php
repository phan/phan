<?php declare(strict_types=1);
namespace Phan\Output\Collector;

use Phan\IssueInstance;
use Phan\Output\IssueCollectorInterface;

/**
 * A ParallelChildCollector will collect issues as normal,
 * but will send them all to a message queue for collection
 * by a ParallelParentCollector instead of holding on to
 * them itself.
 */
class ParallelChildCollector implements IssueCollectorInterface
{
    /**
     * @var Resource
     */
    private $message_queue_resource;

    /**
     * Create a ParallelChildCollector that will collect
     * issues as normal, but emit them to a message queue
     * for collection by a
     * \Phan\Output\Collector\ParallelParentCollector.
     */
    public function __construct()
    {
        \assert(
            extension_loaded('sysvsem'),
            'PHP must be compiled with --enable-sysvsem in order to use -j(>=2).'
        );

        \assert(
            extension_loaded('sysvmsg'),
            'PHP must be compiled with --enable-sysvmsg in order to use -j(>=2).'
        );

        // Create a message queue for this process group
        $message_queue_key = posix_getpgid(posix_getpid());
        $this->message_queue_resource =
            msg_get_queue($message_queue_key);
    }

    /**
     * Collect issue
     * @param IssueInstance $issue
     */
    public function collectIssue(IssueInstance $issue)
    {
        $error_code = 0;

        // Send messages along to the message queue
        // that is hopefully being listened to by a
        // ParallelParentCollector.
        $success = msg_send(
            $this->message_queue_resource,
            ParallelParentCollector::MESSAGE_TYPE_ISSUE,
            $issue,
            true,
            true,
            $error_code
        );

        // Send a signal to the parent process that we
        // sent a message and it may wish to collect it
        // posix_kill(posix_getppid(), SIGUSR1);
        // pcntl_signal_dispatch();

        // Make sure that the message was successfully
        // sent
        \assert(
            $success,
            "msg_send failed with error code '$error_code'"
        );
    }

    /**
     * @return IssueInstance[]
     */
    public function getCollectedIssues():array
    {
        // This collector should not be used for collecting
        // issues. Instead, it proxies all messages on to a
        // message queue.
        return [];
    }

    /**
     * Remove all collected issues (from the parse phase) for the given file paths.
     * Called from daemon mode.
     *
     * @param string[] $files - the relative paths to those files
     * @return void
     */
    public function removeIssuesForFiles(array $files)
    {
        return;  // Never going to be called - daemon mode isn't combined with parallel execution.
    }

    /**
     * This method has not effect on a ParallelChildCollector.
     */
    public function reset()
    {
    }
}
