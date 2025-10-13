<?php

/**
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnClass
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */

/**
 * @template-contravariant T
 */
interface Sink
{
    /**
     * @param callable(T):void $consumer
     */
    public function accept(callable $consumer): void;

    /**
     * @param callable():T $producer
     */
    public function invalidProducer(callable $producer): void;
}

