<?php
class V007Base
{
    public function go(): int { return 1; }
}
// Class alternates of a parent (and so method alternates)
if (PHP_VERSION_ID > 80000) {
    class V007Alt
    {
        public function alt(): int { return 1; }
    }
} else {
    class V007Alt
    {
        public function alt(): string { return 'a'; }
    }
}
