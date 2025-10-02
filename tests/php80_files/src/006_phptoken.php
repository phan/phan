<?php
$x = PhpToken::tokenize('<?php echo "test"; ?>');
foreach ($x as $token) {
    echo "$token->lineno\n";
    echo intdiv([$token->id, $token->text, $token->line, $token->pos], 2);
}
