<?php
class PSMClazz116 {
    public function main() {
        function inner_global_function() {
            var_export($this);
        }
        inner_global_function();
    }
}
(new PSMClazz116())->main();
