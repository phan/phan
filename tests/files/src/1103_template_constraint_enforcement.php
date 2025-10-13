<?php
/**
 * @phan-file-suppress PhanPluginNoCommentOnClass
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnClass
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnProtectedProperty
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnFunction
 * @phan-file-suppress PhanPluginUseReturnValueNoopVoid
 * @phan-file-suppress PhanTemplateTypeNotUsedInFunctionReturn
 * @phan-file-suppress PhanUnusedGlobalFunctionParameter
 * @phan-file-suppress PhanUnusedPublicMethodParameter
 */

class Base {}
class Derived extends Base {}
class AlsoDerived extends Base {}
class NotBase {}

/**
 * @template T of Base
 */
interface Repository
{
    /**
     * @param T $value
     */
    public function add($value): void;
}

/**
 * @implements Repository<Derived>
 */
class GoodRepository implements Repository
{
    public function add($value): void
    {
        // ok: Derived satisfies Base
    }
}

/**
 * @implements Repository<NotBase>
 */
class BadRepository implements Repository
{
    public function add($value): void
    {
        // violation: NotBase not a subtype of Base
    }
}

/**
 * @template T of Base
 */
class GenericCollection
{
    /** @var T */
    protected $value;
}

/**
 * @extends GenericCollection<AlsoDerived>
 */
class GoodCollection extends GenericCollection
{
}

/**
 * @extends GenericCollection<NotBase>
 */
class BadCollection extends GenericCollection
{
}

/**
 * @template T of Base
 */
trait HolderTrait
{
    /** @var T */
    private $item;
}

/**
 * @use HolderTrait<Derived>
 */
class GoodHolder
{
    use HolderTrait;
}

/**
 * @use HolderTrait<NotBase>
 */
class BadHolder
{
    use HolderTrait;
}

/**
 * @template T of Base
 * @param T $value
 */
function takeBase($value): void {}

takeBase(new Derived());
takeBase(new NotBase());
