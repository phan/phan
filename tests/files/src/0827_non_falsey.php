<?php
if ($argv[1] === "do") {
  $_SESSION["cfg"] = [];
}

$foo = 23;

if (empty($_SESSION["cfg"]["SECOND_LEVEL"]["THIRD_LEVEL"])) {
  $_SESSION["cfg"] = [];
} elseif ($_SESSION["cfg"]["SECOND_LEVEL"]["THIRD_LEVEL"] != $foo) {
  $_SESSION["cfg"] = [];
}
