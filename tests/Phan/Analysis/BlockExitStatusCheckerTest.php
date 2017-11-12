<?php declare(strict_types = 1);
namespace Phan\Tests\Analysis;

use Phan\Analysis\BlockExitStatusChecker;
use Phan\Config;
use Phan\Tests\BaseTest;

class BlockExitStatusCheckerTest extends BaseTest
{

    /**
     * A short helper function to represent the possible exit statuses
     * which the return value of BlockExitStatusChecker indicates.
     *
     * This shouldn't be used outside of tests.
     */
    private function representStatus(int $status)
    {
        if ($status === 0) {
            return "invalid(0)";
        }

        $parts = [];
        while ($status != 0) {
            // Remove low order bit, and record the representation of that pet.
            $bit = ($status & ($status - 1)) ^ $status;
            $status &= ~$bit;
            switch ($bit) {
                case BlockExitStatusChecker::STATUS_PROCEED:
                    $parts[] = 'proceed';
                    break;
                case BlockExitStatusChecker::STATUS_GOTO:
                    $parts[] = 'goto';
                    break;
                case BlockExitStatusChecker::STATUS_CONTINUE:
                    $parts[] = 'continue';
                    break;
                case BlockExitStatusChecker::STATUS_BREAK:
                    $parts[] = 'break';
                    break;
                case BlockExitStatusChecker::STATUS_THROW:
                    $parts[] = 'throw';
                    break;
                case BlockExitStatusChecker::STATUS_RETURN:
                    $parts[] = 'return';
                    break;
                default:
                    $parts[] = sprintf("invalid(1<<%d)", (int)round(log($bit, 2)));
                    break;
            }
        }
        // Return the representation of possible values, with "proceed" (low order bit) first, and "return" (high order bit) last.
        return implode("/", $parts);
    }

    /**
     * @dataProvider exitStatusProvider
     */
    public function testExitStatus(string $expectedStatusRepresentation, string $codeSnippet)
    {
        $ast = \ast\parse_code("<"."?php " . $codeSnippet, Config::AST_VERSION);
        $status_code = (new BlockExitStatusChecker())($ast);
        $this->assertSame($expectedStatusRepresentation, $this->representStatus($status_code), sprintf("Unexpected status 0x%x\nCode:\n%s\n", $status_code, $codeSnippet));
    }

    public function exitStatusProvider() : array
    {
        return [
            [
                'proceed',
                '',
            ],
            [
                'proceed',
                '"inline string";;;',
            ],
            [
                'return',
                'return 4;',
            ],
            [
                'return',
                'echo "hello, world\n"; return 4;',
            ],
            [
                'return',
                'exit(1);',
            ],
            [
                'return',
                'for ($i = 0; true; $i++) {}',
            ],
            [
                'return',
                'for ($i = 0; ; $i++) {}',
            ],
            [
                'throw',
                'for ($i = 0; ; $i++) {throw new RuntimeException("throw");}',
            ],
            [
                'throw',
                'for ($i = 0; foo($i), true; $i++) {throw new RuntimeException("throw");}',
            ],
            [
                'proceed/throw',
                'for ($i = 0; true, foo($i); $i++) {throw new RuntimeException("throw");}',
            ],
            [
                'return',
                'while (1) {foo();}',
            ],
            [
                'proceed',
                'while (1) {break;}',
            ],
            [
                'proceed',
                'do {break;}while (1);',
            ],
            [
                'proceed',
                'while (cond()) {foo();}',
            ],
            [
                'return',
                'do {foo();} while (1);',
            ],
            [
                'proceed',
                'do {} while (0);',
            ],
            [
                'return',
                'do {} while (1);',
            ],
            [
                'proceed',
                'do {foo();} while (cond());',
            ],
            [
                'return',
                'do {return "value";} while (cond());',
            ],
            [
                'return',
                'while (true) {foo();}',
            ],
            [
                'return',
                'while (1) {if (cond) {continue;} }',
            ],
            [
                'throw',
                'while (1) {if (cond) continue; else if (foo()) { throw new RuntimeException("");}}',
            ],
            [
                'throw',
                'do {if (cond) continue; else if (foo()) { throw new RuntimeException("");}}while (1);',
            ],
            [
                'return',
                'if (cond) return 3; else if (foo()) {return 4;} else { return 5;}',
            ],
            [
                'return',
                'if (cond) return 3; else { if (foo()) {return 4;} return 5;}',
            ],
            [
                'proceed/throw',
                'foreach ($seq as $x) { if ($x) { throw new Exception("e"); }}',
            ],
            [
                'proceed/return',
                'foreach ($seq as $x) { if ($x) { break; } return null;}',
            ],
            [
                'proceed',
                'foreach ($seq as $x) { if ($x) { echo $x; }}',
            ],
            [
                'throw',
                'throw new RuntimeException("message");',
            ],
            [
                'proceed',
                'yield;',
            ],
            [
                'proceed/throw',
                'if ($argc) { throw new RuntimeException("message"); }',
            ],
            [
                'proceed',
                'switch($a) {}',
            ],
            [
                'proceed/return',
                'switch($a) { case 2: return -1; }',
            ],
            [
                'return',
                'switch($a) { case 2: default: return -1; }',
            ],
            [
                'return',
                'switch($a) { case 2: case 4: default: echo "V"; return -1; }',
            ],
            [
                'proceed',
                'switch($a) { case 2: case 4: default: echo "V"; break; }',
            ],
            [
                'throw/return',
                'switch($a) { case 2: throw new RuntimeException("message"); case 3: default: return 4;}',
            ],
            [
                'proceed',
                'if ($argc) { foo();} else { echo "expr"; }',
            ],
            [
                'continue',
                'if ($argc) { continue;} else { continue; }',
            ],
            [
                'break/throw/return',
                'try {return foo();} catch(RuntimeException $e) { break; } catch (InvalidArgumentException $e) { throw new Exception("rethrow", 0, $e); } catch (Exception $e) { return null; }',
            ],
            [
                'return',
                'try {} finally { return 4;}',
            ],
            [
                'proceed/continue/return',
                'try {foo();} catch(RuntimeException $e) { continue; } catch (Exception $e) { return null; }',
            ],
            [
                'proceed/return',
                'try { $a = foo(); } catch(RuntimeException $e) { return; }',
            ],
            [
                'throw/return',
                'try { $a = foo(); throw new InvalidArgumentException("msg");} catch(RuntimeException $e) { return; }',
            ],
            [
                'proceed/return',
                'try { return foo(); } catch(RuntimeException $e) { $x = foo(); }',
            ],
            [
                'proceed/return',
                'try { foo(); } catch(RuntimeException $e) { return; } finally { $a = $otherFn(); }',
            ],
            // Phan doesn't track labels, it doesn't seem to be worth the effort involved.
            [
                'goto/break',
                'if (cond2()) { if (cond()) { goto end; } break; } else { break; } end: ;',
            ],
            [
                'proceed/goto/break',
                'if (cond2()) { if (cond()) { goto end; } break; }  end: ;',
            ],
            // trigger_error can make it throw/exit. The returned code for exit is 'return'
            [
                'return',
                'trigger_error("err msg", E_USER_ERROR);',
            ],
            [
                'throw',
                'TRIGGER_ERROR("err msg", E_RECOVERABLE_ERROR);',
            ],
            [
                'proceed',
                'TRIGGER_ERROR_NOT("err msg", E_RECOVERABLE_ERROR);',
            ],
            [
                'throw',
                '\trigger_error("err msg", E_RECOVERABLE_ERROR);',
            ],
            [
                'proceed',
                'trigger_error("err msg", E_DEPRECATED);',
            ],
            [
                'return',
                '@trigger_error("err msg", E_USER_ERROR);',
            ],
            [
                'proceed',
                '@trigger_error("err msg", E_DEPRECATED);',
            ],
        ];
    }
}
