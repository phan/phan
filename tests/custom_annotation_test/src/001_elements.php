<?php

// A custom annotation on each kind of element: the plugin is called in the parse phase (PhanPluginCustomAnnotationSeen),
// and getCustomAnnotations() of the element returns it in the analysis phase (PhanPluginCustomAnnotationRead).

/**
 * @phan-x-test-mark on the class
 */
class AnnotatedClass
{
    /** @phan-x-test-mark on a class constant */
    public const MARKED = 1;

    /**
     * @phan-x-test-mark on a property
     */
    public $marked_property = 1;

    /** @phan-x-test-mark on a static property */
    public static $marked_static = 2;

    public $unmarked_property = 3;

    public function __construct(
        /** @phan-x-test-mark on a promoted property */
        public int $promoted = 0
    ) {
    }

    /**
     * @param int $x
     * @phan-x-test-mark on a method
     */
    public function markedMethod(int $x): int
    {
        return $x;
    }

    /** @phan-x-test-mark on a static method */
    public static function markedStaticMethod(): void
    {
    }
}

/** @phan-x-test-mark on the interface */
interface AnnotatedInterface
{
    /** @phan-x-test-mark on an interface method */
    public function interfaceMethod(): void;
}

/** @phan-x-test-mark on the trait */
trait AnnotatedTrait
{
}

/** @phan-x-test-mark on the enum */
enum AnnotatedEnum: string
{
    /** @phan-x-test-mark on an enum case */
    case First = 'first';

    /** @phan-x-test-mark on an enum constant */
    public const OTHER = 'other';
}

/** @phan-x-test-mark on a global constant */
const ANNOTATED_CONSTANT = 3;

/** @phan-x-test-mark on a function */
function annotated_function(): int
{
    return 1;
}

function use_elements(AnnotatedClass $a, AnnotatedInterface $i): void
{
    $closure = /** @phan-x-test-mark on a closure */ function (): int {
        return 1;
    };
    $arrow = /** @phan-x-test-mark on an arrow function */ fn(): int => 2;
    echo $closure() + $arrow();
    echo $a->markedMethod(1);
    AnnotatedClass::markedStaticMethod();
    $i->interfaceMethod();
    echo $a->marked_property, AnnotatedClass::$marked_static, $a->promoted, $a->unmarked_property;
    echo AnnotatedClass::MARKED;
    echo AnnotatedEnum::First->value, AnnotatedEnum::OTHER;
    echo ANNOTATED_CONSTANT;
    echo annotated_function();
}

function make_annotated_class(): AnnotatedClass
{
    return new AnnotatedClass();
}
