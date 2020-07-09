<?php

/**
 * @param bool $foo description @phan-mandatory-param
 * @param string $bar description @phan-mandatory-param
 */
function test182(bool $foo = false, string $bar = '') {
    var_dump([$foo, $bar]);
}
test182();
test182(true);
class Base182 {
    public function process() {
        echo "Processing nothing\n";
    }
}

class Other182 {
    /**
     * @param string $secondField @phan-mandatory-param this is required for invocations but needs to maintain method signature compatibility
     * @param string $field @phan-mandatory-param this is required for invocations but needs to maintain method signature compatibility
     */
    public function process($field = null, $secondField = null) {
        var_export($field);
        var_export($secondField);
    }
}
(new Other182())->process();
(new Base182())->process();
(new Other182())->process('a', 'b');
