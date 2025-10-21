<?php
// This becomes syntactically valid in php 8.0 for throw expressions. Only the inner throw is executed.
throw throw $e;