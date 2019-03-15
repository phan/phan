<?php

/**
 * @template T
 * @param T $x should warn about not using in return
 */
function badTemplateReturn($x) {
    var_export($x);
}

/**
 * @template T
 * @param T $x
 * @return T[]
 */
function goodTemplateReturn($x) {
    var_export($x);
    return [$x];
}

echo strlen(goodTemplateReturn(new stdClass()));  // should infer \stdClass[]

/**
 * @template T
 * @param T[] $x
 * @return T
 * @throws InvalidArgumentException
 */
function goodTemplateReturnFromArray(array $x) {
    if (count($x) == 0) {
        throw new InvalidArgumentException("Expected one element");
    }
    return reset($x);
}
call_user_func(function () {
    // should warn about these values having types stdClass and int
    $result = goodTemplateReturnFromArray([new stdClass()]);
    echo strlen($result);  // should infer stdClass
    echo strlen(goodTemplateReturnFromArray(['key' => rand(0,10)]));  // should infer int
});
class TemplateOnMethods {
    /**
     * @template T
     * @param T $x
     * Should warn that the return type doesn't use the template
     */
    public static function badTemplateReturn($x) {
        var_export($x);
    }

    /**
     * @template T
     * @param T $x
     * @return T[]
     */
    public static function goodTemplateReturn($x) {
        var_export($x);
        return [$x];
    }

    /**
     * @template T
     * @return T[]
     * Should warn that it can't infer T from params
     */
    public static function missingTemplateParams($x) {
        var_export($x);
        return [$x];
    }

    /**
     * @template T
     * @param T[] $x
     * @return T
     * @throws InvalidArgumentException
     */
    public static function goodTemplateReturnFromArray(array $x) {
        if (count($x) == 0) {
            throw new InvalidArgumentException("Expected one element");
        }
        return reset($x);
    }

    /**
     * @template TKey
     * @template TValue
     * @param iterable<TKey,TValue> $i
     * @return array<int,TKey|TValue>
     *
     * Phan can parse both TKey and TValue
     */
    public static function combinationIterable(iterable $i) {
        $result = [];
        foreach ($i as $k => $v) {
            $result[] = $k;
            $result[] = $v;
        }
        return $result;
    }

    /**
     * @param iterable<string,\stdClass> $it
     */
    public static function assertions(iterable $it) {
        echo strlen(self::goodTemplateReturn(new stdClass()));

        // should warn about these values having types stdClass and int
        $result = self::goodTemplateReturnFromArray([new stdClass()]);
        echo strlen($result);
        echo strlen(self::goodTemplateReturnFromArray(['key' => rand(0,10)]));
        echo strlen(self::missingTemplateParams(['key' => rand(0,10)]));  // should warn about
        echo strlen(self::combinationIterable($it));  // should infer array<int,string|\stdClass>
    }
}

/**
 * The order of (at)template declarations should no longer matter
 * @template R
 * @template L
 */
class TemplateConstructor {
    /** @var L */
    public $left;
    /** @var R */
    public $right;
    /**
     * @param L[] $left
     * @param R[] $right
     */
    public function __construct($left, $right) {
        $this->left = reset($left);
        $this->right = reset($right);
    }
}

$t = new TemplateConstructor([new stdClass()], [new ArrayObject()]);
echo strlen($t->left);
echo strlen($t->right);
