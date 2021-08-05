<?php // NOTE: This won't work properly in Phan 4, upgrade to Phan 5
function example(ArrayAccess&Countable $arr) {
    var_export($arr[0]);
    var_export(count($arr));
    // Not typed as Traversable
    foreach ($arr as $invalid) {
        var_dump($invalid);
    }
    var_dump(new $arr);  // TODO: Stop emitting PhanTypeExpectedObjectOrClassName?
    var_dump(clone $arr);
}
class Uncountable implements Countable {
    public function count(): int {
        return 0;
    }
}
example(new ArrayObject());
example(new Uncountable());
