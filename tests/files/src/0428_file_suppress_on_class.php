<?php

/**
 * @phan-file-suppress PhanParamTooFewInternal
 */
class MyClass {
    public function test(string $source) {
        // This is invalid, but we suppressed it globally
        \ast\parse_code();
        // However, passing too many has not been globally suppressed
        \ast\parse_code($source, 50, 'filename.php', 'extra', 'extra');
    }
}

// The phan-file-suppress annotation affects everything below it in the file.
\ast\parse_code('');
