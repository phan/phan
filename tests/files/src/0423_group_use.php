<?php
use function ast\{parse_code, parse_file, printf};
use function ast\sprintf;
use const ast\{AST_CLASS_CONST, flags\MAGIC_NAMESPACE, flags\MISSING_NAMESPACE};

echo strlen(AST_CLASS_CONST);
echo strlen(MISSING_NAMESPACE);
printf("This is not the global printf\n");
echo sprintf("This is not the global sprintf\n");
var_export(parse_code('<?php return;', 50));
