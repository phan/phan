<?php
// SplHeap and SplPriorityQueue have __serialize() and __unserialize() since PHP 8.6
function serialize_heaps_010(SplMinHeap $heap, SplPriorityQueue $queue): array {
    $data = $heap->__serialize();
    $heap->__unserialize($data);
    $queue->__unserialize($queue->__serialize());
    return $data;
}
