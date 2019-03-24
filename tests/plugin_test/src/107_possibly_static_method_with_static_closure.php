<?php
// @phan-file-suppress PhanPluginUndeclaredVariableIsset

class PSMClazz107 {
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

(new PSMClazz107())->main();
