<?php
/**
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnClass
 */

/**
 * @template-covariant T
 */
class ReadOnlyBox
{
    /**
     * @var T
     * @phan-read-only
     */
    private $item;
}

/**
 * @template-contravariant U
 */
class WriteOnlySink
{
    /**
     * @var U
     * @phan-write-only
     */
    private $target;
}
