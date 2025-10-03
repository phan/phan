<?php
class Test49 {
    private const TYPE_MAIL_PREFIX = 'mailto:';
    private const TYPE_INT_PREFIX = 'int:';

    public function save(string $var): string{
        $tmp = explode(':', $var, 2);
        [$prot] = (count($tmp) === 2 ? $tmp : ['file', $var]);
        $scheme = $prot . ':';
        '@phan-debug-var $scheme';

        return match ($scheme) {
        self::TYPE_INT_PREFIX,
            self::TYPE_MAIL_PREFIX,
            'http:',
            'https:' => $var,
            default => $var,
        };
    }
}
