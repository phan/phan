<?php declare(strict_types=1);
namespace Phan;

use Phan\Language\Context;
use Phan\PluginV2\IssueEmitter;
use ast\Node;

/**
 * Plugins can be defined in the config and will have
 * their hooks called at appropriate times during analysis
 * of each file, class, method and function.
 *
 * Plugins must extends this class and return an instance
 * of themselves.
 */
abstract class PluginV2 {
    use IssueEmitter {
        emitPluginIssue as emitIssue;
    }
}
