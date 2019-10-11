<?php declare(strict_types=1);

namespace Phan\Output\Collector;

use AssertionError;
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
     * @var resource a message queue used to receive messages from the child processes in the worker group.
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
        self::assertSharedMemoryCommunicationEnabled();

        $this->message_queue_resource = self::getQueueForProcessGroup();
    }

    /**
     * @return resource the result of msg_get_queue()
     * @throws AssertionError if this could not create a resource with msg_get_queue.
     * @internal
     */
    public static function getQueueForProcessGroup()
    {
        // Create a message queue for this process group
        $message_queue_key = \posix_getpgid(\posix_getpid());
        if (!\is_int($message_queue_key)) {
            throw new AssertionError('Expected posix_getpgid to return a valid id');
        }

        $resource = \msg_get_queue($message_queue_key);
        if (!$resource) {
            throw new AssertionError('Expected msg_get_queue to return a valid resource');
        }
        return $resource;
    }

    /**
     * Assert that the dependencies needed for communicating with the child or parent process are available.
     * @throws AssertionError if PHP modules needed for shared communication aren't loaded
     * @internal
     */
    final public static function assertSharedMemoryCommunicationEnabled() : void
    {
        if (!\extension_loaded('sysvsem')) {
            throw new AssertionError(
                'PHP must be compiled with --enable-sysvsem in order to use -j(>=2).'
            );
        }

        if (!\extension_loaded('sysvmsg')) {
            throw new AssertionError(
                'PHP must be compiled with --enable-sysvmsg in order to use -j(>=2).'
            );
        }
    }

    /**
     * Collect issue
     * @param IssueInstance $issue
     * @throws AssertionError if the message failed to be sent to the parent process
     */
    public function collectIssue(IssueInstance $issue) : void
    {
        $error_code = 0;

        // Send messages along to the message queue
        // that is hopefully being listened to by a
        // ParallelParentCollector.
        $success = \msg_send(
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
        if (!($success)) {
            throw new AssertionError("msg_send failed with error code '$error_code'");
        }
    }

    /**
     * @return list<IssueInstance>
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
     * @param string[] $files @phan-unused-param - the relative paths to those files
     * @override
     */
    public function removeIssuesForFiles(array $files) : void
    {
        return;  // Never going to be called - daemon mode isn't combined with parallel execution.
    }

    /**
     * This method has no effect on a ParallelChildCollector.
     */
    public function reset() : void
    {
    }
}
