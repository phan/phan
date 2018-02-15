<?php

/**
 * @return bool
 */
function foreach420(stdClass $params) {
    foreach ($params->results as $reviewItem) {
        // NOTE: PHP would have already resolved $params->results - This should not cause a warning above.
        $params = [ ];
    }
    return true;
}
