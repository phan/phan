<?php

declare(strict_types=1);

namespace Phan\Language\Element\Comment;

use Phan\Language\Element\Comment;
use Phan\Language\UnionType;
use Phan\Library\None;

/**
 * A comment for an empty doc-block or when comment parsing is disabled
 */
final class NullComment extends Comment
{
    public function __construct()
    {
        $none = None::instance();
        $this->throw_union_type = UnionType::empty();
        $this->closure_scope = $none;
        $this->inherited_type = $none;
    }

    /** @var NullComment the only instance of NullComment. Will be immutable. */
    private static $instance;

    /**
     * Returns the immutable NullComment.
     */
    public static function instance(): NullComment
    {
        return self::$instance;
    }

    /**
     * Ensures the static property is set, for users of this class
     * @internal
     */
    public static function init(): void
    {
        self::$instance = new NullComment();
    }
}
NullComment::init();
