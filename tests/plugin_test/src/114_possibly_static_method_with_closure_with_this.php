<?php

class PSMClazz114 {
    public function examplePublic() {
        (function() {
            var_export($this);
        })();
    }

    protected function exampleProtected() {
        (function() {
            var_export($this);
        })();
    }

    private function examplePrivate() {
        (function() {
            var_export($this);
        })();
    }

    public function main() {
        $this->examplePublic();
        $this->exampleProtected();
        $this->examplePrivate();
    }
}

(new PSMClazz114())->main();
