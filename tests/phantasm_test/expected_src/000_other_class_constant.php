<?php
echo /* OtherClass::NOTIFICATION_MESSAGE */ ("Ding!\n");
if ( /* OtherClass::DEBUG */ (false)) {
    echo "This will be optimized out by opcache if provably false\n";
}
