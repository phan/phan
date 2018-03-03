<?php

namespace MyNS;

use function ast\{
    parse_code,
    parse_file
};
var_export(parse_code('<?php echo "A";', 50));
