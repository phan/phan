<?php
echo get_resource_id(0);
if (is_int(get_resource_id(STDIN))) {
    echo "Redundant check\n";
}
