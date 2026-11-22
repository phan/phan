<?php
// The default value of round()'s $mode is the enum case RoundingMode::HalfAwayFromZero.
// Rendering it in an issue message used to throw a TypeError.
echo round();
