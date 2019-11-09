<?php
class Example817 {
    public $options = [
        'files' => [],
    ];
    public function processFiles() {
        $opt = $this->options;
        '@phan-debug-var $opt';
        $files = $opt['files'];
        '@phan-debug-var $files';
        if ($this->options['files']) {  // should not emit PhanEmptyForeach
            echo "This has files\n";
        }
    }
}
