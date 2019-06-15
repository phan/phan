<?php

class Node312 {
    /** @var ?Node312 */
    public $next;
}

function test312(Node312 $head) {
  for ($node = $head; $node; $node = $next) {  // should not emit PhanRedundantConditionInLoop
    $next = $node->next;
    $node->next = null;
  }
}

function test312Buggy(Node312 $head) {
  for ($node = $head; $node; $node = strlen($next ?: 0)) {  // should not emit PhanRedundantConditionInLoop
    $next = $node->next;
    $node->next = null;
  }
}

function test312InfiniteLoop(Node312 $head) {
  for ($node = $head; $node; $node = $head) {  // false positive PhanRedundantConditionInLoop
    $node->next = null;
  }
}
