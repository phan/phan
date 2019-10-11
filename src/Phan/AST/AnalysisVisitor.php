<?php declare(strict_types=1);

namespace Phan\AST;

use Phan\AST\Visitor\KindVisitorImplementation;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\FQSEN;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Suggestion;

/**
 * A visitor used for analysis.
 *
 * In addition to calling the corresponding visit*() method for the passed in \ast\Node's kind,
 * this contains helper methods to emit issues.
 */
abstract class AnalysisVisitor extends KindVisitorImplementation
{
    /**
     * @var CodeBase
     * The code base within which we're operating
     * @phan-read-only
     */
    protected $code_base;

    /**
     * @var Context
     * The context in which the node we're going to be looking
     * at exists.
     */
    protected $context;

    /**
     * @param CodeBase $code_base
     * The code base within which we're operating
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     */
    public function __construct(
        CodeBase $code_base,
        Context $context
    ) {
        $this->context = $context;
        $this->code_base = $code_base;
    }

    /**
     * @param string $issue_type
     * The type of issue to emit such as Issue::ParentlessClass
     *
     * @param int $lineno
     * The line number where the issue was found
     *
     * @param int|string|FQSEN|UnionType|Type ...$parameters
     * Template parameters for the issue's error message
     *
     * @see PluginAwarePostAnalysisVisitor::emitPluginIssue if you are using this from a plugin.
     */
    protected function emitIssue(
        string $issue_type,
        int $lineno,
        ...$parameters
    ) : void {
        Issue::maybeEmitWithParameters(
            $this->code_base,
            $this->context,
            $issue_type,
            $lineno,
            $parameters
        );
    }

    /**
     * @param string $issue_type
     * The type of issue to emit such as Issue::ParentlessClass
     *
     * @param int $lineno
     * The line number where the issue was found
     *
     * @param list<int|string|FQSEN|UnionType|Type> $parameters
     * Template parameters for the issue's error message
     *
     * @param ?Suggestion $suggestion
     * A suggestion (may be null)
     */
    protected function emitIssueWithSuggestion(
        string $issue_type,
        int $lineno,
        array $parameters,
        ?Suggestion $suggestion
    ) : void {
        Issue::maybeEmitWithParameters(
            $this->code_base,
            $this->context,
            $issue_type,
            $lineno,
            $parameters,
            $suggestion
        );
    }

    /**
     * Check if an issue type (different from the one being emitted) should be suppressed.
     *
     * This is useful for ensuring that TypeMismatchProperty also suppresses PhanPossiblyNullTypeMismatchProperty,
     * for example.
     */
    protected function shouldSuppressIssue(string $issue_type, int $lineno) : bool
    {
        return Issue::shouldSuppressIssue(
            $this->code_base,
            $this->context,
            $issue_type,
            $lineno,
            []
        );
    }
}
