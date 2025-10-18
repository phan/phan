<?php

declare(strict_types=1);

/* @phan-file-suppress PhanUnusedVariable */

function regression_cast_and_assignment(): void
{
    $empty = (object)[];
    $empty->status = 3;
    '@phan-debug-var $empty';

    $with_status = (object)['status' => 'string'];
    $status_value = $with_status->status;
    '@phan-debug-var $status_value';
}

/** @param \stdClass{foo:string} $x */
function takesShapedObject(\stdClass $x): void
{
    echo $x->foo;
}

/** @return \stdClass{bar:bool,foo?:string} */
function returnsShapedObject(): \stdClass
{
    $obj = (object)['foo' => 'bar'];
    if (random_int(0, 1) !== 0) {
        return $obj; // Missing bar
    }
    $obj->bar = true;
    '@phan-debug-var $obj';
    return $obj;
}

/** @return ?\stdClass{foo:int} */
function returnsNullableShape(): ?\stdClass
{
    if (random_int(0, 1) === 0) {
        return null;
    }
    return (object)['foo' => 42];
}

/** @return \stdClass{foo?:int} */
function returnsOptionalFoo(): \stdClass
{
    $obj = (object)[];
    if (random_int(0, 1) !== 0) {
        $obj->foo = random_int(0, 100);
    }
    return $obj;
}

(static function (\stdClass $unshapedStdClass): void {
    $s = (object)['bar' => 42];
    '@phan-debug-var $s';

    $setProp = $s->bar;
    '@phan-debug-var $setProp';
    $unsetProp = $s->foo;
    '@phan-debug-var $unsetProp';

    takesShapedObject($s);
    takesShapedObject((object)['foo' => []]);
    takesShapedObject((object)['foo' => 'a']);
    takesShapedObject($unshapedStdClass);

    $ret = returnsShapedObject();
    '@phan-debug-var $ret';
    $barProp = $ret->bar;
    $fooProp = $ret->foo;
    '@phan-debug-var $barProp, $fooProp';

takesShapedObject($ret);
})(new \stdClass());

$maybeFoo = (object)[];
if (random_int(0, 1) !== 0) {
    $maybeFoo->foo = 1;
}
'@phan-debug-var $maybeFoo';

$disjoint = random_int(0, 1) !== 0 ? (object)['foo' => 1] : (object)['bar' => 2];
'@phan-debug-var $disjoint';
$maybeDisjointFoo = $disjoint->foo;
'@phan-debug-var $maybeDisjointFoo';
takesShapedObject($disjoint);

$mergedOptionalFoo = random_int(0, 1) !== 0 ? returnsOptionalFoo() : returnsShapedObject();
'@phan-debug-var $mergedOptionalFoo';

$nullableShapeOrPlain = random_int(0, 1) !== 0 ? returnsNullableShape() : new \stdClass();
'@phan-debug-var $nullableShapeOrPlain';
