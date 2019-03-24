<?php

class PSMClazz105 {
    public function examplePublic() {
        var_export($this);
    }

    protected function exampleProtected() {
        var_export($this);
    }

    private function examplePrivate() {
        var_export($this);
    }

    public function main() {
        $this->examplePublic();
        $this->exampleProtected();
        $this->examplePrivate();
    }
}

(new PSMClazz105())->main();
