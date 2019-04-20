<?php
// @phan-file-suppress PhanPluginUndeclaredVariableIsset

class PSMClazz113 {
    public function examplePublic() {
        (static function() {
            var_export(isset($this));
        })();
    }

    protected function exampleProtected() {
        (static function() {
            var_export(isset($this));
        })();
    }

    private function examplePrivate() {
        (static function() {
            var_export(isset($this));
        })();
    }

    public function main() {
        $this->examplePublic();
        $this->exampleProtected();
        $this->examplePrivate();
    }
}

(new PSMClazz113())->main();
