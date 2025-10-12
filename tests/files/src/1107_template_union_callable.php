<?php

/**
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnClass
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnFunction
 * @phan-file-suppress PhanPluginNoCommentOnClass
 * @phan-file-suppress PhanPluginNoCommentOnFunction
 * @phan-file-suppress PhanPluginCanUseReturnType
 */

/**
 * @template T of Foo|Bar
 */
class Processor
{
    /**
     * @param T $value
     */
    public function __construct(private $value)
    {
    }

    /**
     * @return T
     */
    public function get()
    {
        return $this->value;
    }
}

class Foo {}
class Bar {}
class Baz {}

function consumeProcessor(Processor $processor): void
{
    $value = $processor->get();
    if ($value instanceof Foo || $value instanceof Bar) {
        return;
    }
}

consumeProcessor(new Processor(new Foo()));
consumeProcessor(new Processor(new Bar()));
consumeProcessor(new Processor(new Baz()));

/**
 * @template-covariant T
 */
interface Factory
{
    /**
     * @return callable(): T
     */
    public function getProducer();

    /**
     * @return callable(int): T
     */
    public function getTypedProducer();
}

/**
 * @template T
 */
class Utils
{
    /**
     * @template U
     * @param U $x
     * @return U
     */
    public static function identity($x)
    {
        return $x;
    }
}

$intResult = Utils::identity(42);
$stringLength = strlen(Utils::identity('str'));

/**
 * @template T
 * @param ?T $value
 * @return T|null
 */
function nullable($value)
{
    return $value;
}
