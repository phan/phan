<?php

// A variable that a call sets by reference is defined when a comparison with the result of the call has a result,
// also when the comparison is not the first operand of &&. It is still possibly undeclared where the call may not have happened.

namespace NS6007;

function n1(string $u, bool $a): int { if ($a || preg_match('~x~', $u, $m) !== 0) { return count($m); } return 0; }
function n2(string $u, bool $a): int { if (!($a && preg_match('~x~', $u, $m) !== 0)) { return count($m); } return 0; }
function n3(string $u, bool $a): int { if ($a && preg_match('~x~', $u, $m) !== 0) { return 1; } return count($m); }
function n4(string $u, bool $a): int { $r = $a && preg_match('~x~', $u, $m) !== 0; return count($m); }
function n5(string $u, bool $a): int { return ($a ? preg_match('~x~', $u, $m) !== 0 : false) ? 1 : count($m); }
function p1(string $u, bool $a): int { if (!$a || preg_match('~x~', $u, $m) === 0) { return 0; } return count($m); }
function p2(string $u, bool $a): int { return $a && preg_match('~x~', $u, $m) >= 1 ? count($m) : 0; }
function p3(string $u, bool $a): int { return $a && 0 < preg_match('~x~', $u, $m) && $m[0] !== '' ? 1 : 0; }
class K { public function find(string $u, ?array &$out): int { $out = [$u]; return 1; }
  public function p4(string $u, bool $a): int { return $a && $this->find($u, $o) !== 0 && $o ? count($o) : 0; }
  public static function sfind(string $u, ?array &$out): int { $out = [$u]; return 1; }
  public function p5(string $u, bool $a): int { return $a && self::sfind($u, $o) > 0 && $o ? count($o) : 0; }
}
function t4(string $u, bool $a): int { if (!$a || preg_match('~x~', $u, $m) !== 1) { return 0; } '@phan-debug-var $m'; return 1; }
function t5(string $u, bool $a): int { if ($a && preg_match('~x~', $u, $m) !== 0) { '@phan-debug-var $m'; return 1; } return 0; }
function t6(string $u, bool $a): int { if ($a && preg_match('~x~', $u, $m)) { '@phan-debug-var $m'; return 1; } return 0; }
function t7(string $u): int { if (preg_match('~x~', $u, $m)) { '@phan-debug-var $m'; return 1; } return 0; }
function t8(string $u, bool $a): int { if (!$a || !preg_match('~x~', $u, $m)) { return 0; } '@phan-debug-var $m'; return 1; }
function t9(string $u, bool $a): int { if ($a && preg_match('~x~', $u, $m) !== 0 && $m) { '@phan-debug-var $m'; return 1; } return 0; }
