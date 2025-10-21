<?php
// A MissingToken should be generated for the missing `;` or close php tag.
// NOTE: token_get_all() will yield up to 3 tokens after T_HALT_COMPILER,
// no matter what those tokens happen to be, so tolerant-php-parser combines unexpected tokens into T_INLINE_HTML
// so that no subsequent statements get emitted.
// (T_HALT_COMPILER is forbidden in other node types)
// In this invalid AST, treat "     + 1;\n" as the inline data after the missing semicolon.
__halt_compiler()     + 1;
