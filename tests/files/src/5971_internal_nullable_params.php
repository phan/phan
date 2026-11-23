<?php
// Parameters of internal functions that PHP declares as nullable accept null
function nullable_params5971(string $s, ?string $encoding, ?int $length, ?int $timestamp, array $a, ?array $options): void {
    echo mb_strlen($s, null), mb_strtoupper($s, $encoding), mb_substr($s, 1, $length, $encoding);
    echo substr($s, 1, $length), substr_count($s, 'a', 0, $length), substr_compare($s, 'a', 0, $length);
    echo date('Y', $timestamp), gmdate('Y', null), idate('Y', $timestamp), mktime(0, null, null);
    var_dump(array_splice($a, 1, $length), array_slice($a, 1, $length));
    var_dump(iconv_substr($s, 1, $length, $encoding), iconv_strlen($s, $encoding));
    var_dump(stream_context_create($options), session_name(null));
    $error = new ErrorException('message', 0, 1, null, $length);
    var_dump($error, Closure::bind(static function (): int { return 1; }, null, null));
}
// Parameters that are not nullable still reject null
function non_nullable_params5971(?string $s, ?int $i): void {
    echo strlen($s), str_repeat('a', $i), mktime($i), substr('abc', $i);
}
