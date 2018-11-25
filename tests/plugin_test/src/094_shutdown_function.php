<?php

register_shutdown_function('notafunction');  // Fails to emit PhanUndeclaredFunctionInCallable
register_shutdown_function(['MissingClass', 'notafunction']);  // Fails to emit PhanUndeclaredFunctionInCallable or anything else
