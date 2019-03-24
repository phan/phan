<?php
class PSMClazz110 {
    public function main() {
        function inner_global_function() {
            var_export($this);
        }
        inner_global_function();
    }
}
(new PSMClazz110())->main();
