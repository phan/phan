<?php
// The grammar allows trailing commas in expression lists, even for the default keyword. (see Zend/zend_language_parser.y)
$a = match(1) {
    default,
        => $b['field'],
    SOME_CONST, OTHER_CONST,
        => null,
};
