<?php
echo OtherClass::NOTIFICATION_MESSAGE;
if (OtherClass::DEBUG) {
    echo "This will be optimized out by opcache if provably false\n";
}
