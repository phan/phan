<?php

class Node312 {
    /** @var ?Node312 */
    public $next;
}

function test312(Node312 $head) {
  for ($node = $head; $node; $node = $next) {  // false positive PhanRedundantConditionInLoop
    $next = $node->next;
    $node->next = null;
  }
}

function test312Buggy(Node312 $head) {
  for ($node = $head; $node; $node = strlen($next ?: 0)) {  // false positive PhanRedundantConditionInLoop
    $next = $node->next;
    $node->next = null;
  }
}
