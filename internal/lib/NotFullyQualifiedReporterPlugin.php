<?php

declare(strict_types=1);

use ast\Node;
use Phan\CodeBase;
use Phan\PluginV3;
use Phan\PluginV3\FinalizeProcessCapability;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * This warns if references to global functions or global constants are not fully qualified.
 *
 * This Plugin hooks into two events:
 *
 * - getPostAnalyzeNodeVisitorClassName
 *   This method returns a class that is called on every AST node from every
 *   file being analyzed
 * - finalize
 */
class NotFullyQualifiedReporterPlugin extends PluginV3 implements
    PostAnalyzeNodeCapability,
    FinalizeProcessCapability
{
    /**
     * Maps namespaces to a set of unqualified function names that have calls made from that namespace.
     * @var array<string,array<string,true>>
     */
    private static $calls = [];

    /**
     * Record an unqualified call made from a namespace
     */
    public static function logUnqualifiedCall(string $namespace, string $name): void
    {
        self::$calls[$namespace][strtolower($name)] = true;
    }

    /**
     * @return string - The name of the visitor that will be called (formerly analyzeNode)
     * @override
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return NotFullyQualifiedReporterVisitor::class;
    }

    public function finalizeProcess(CodeBase $_): void
    {
        echo "<" . "?php declare(strict_types=1);\n";
        ksort(self::$calls);
        foreach (self::$calls as $namespace => $name_set) {
            // TODO: This might be a bug in the php engine - it warns about redeclaring functions,
            // but only when the "namespace X\Y" declaration is "namespace \X\Y"
            $namespace = ltrim($namespace, "\\");
            echo "\n";
            echo "namespace $namespace;\n";
            ksort($name_set);
            foreach ($name_set as $function_name => $_) {
                if (!function_exists($function_name)) {
                    continue;
                }
                $rf = new ReflectionFunction($function_name);
                foreach ($rf->getParameters() as $p) {
                    if ($p->isPassedByReference()) {
                        continue 2;
                    }
                }
                echo "function $function_name(...\$args) { global \$__call_counts; \$key = __FUNCTION__ . ':' . \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file'];\$__call_counts[\$key] = (\$__call_counts[\$key] ?? 0) + 1; return \\$function_name(...\$args); }\n";
            }
        }
        echo <<<'EOT'
$GLOBALS['__call_counts'] = [];
register_shutdown_function(function () {
    asort($GLOBALS['__call_counts']);
    var_dump($GLOBALS['__call_counts']);
});

EOT;
        exit(0);
    }
}

/**
 * When __invoke on this class is called with a node, a method
 * will be dispatched based on the `kind` of the given node.
 *
 * Visitors such as this are useful for defining lots of different
 * checks on a node based on its kind.
 */
class NotFullyQualifiedReporterVisitor extends PluginAwarePostAnalysisVisitor
{
    // Subclasses should declare protected $parent_node_list as an instance property if they need to know the list.

    // @var list<Node> - Set after the constructor is called if an instance property with this name is declared
    // protected $parent_node_list;

    // A plugin's visitors should NOT implement visit(), unless they need to.

    /**
     * @param Node $node
     * A node to analyze of type ast\AST_CALL (call to a global function)
     * @override
     */
    public function visitCall(Node $node): void
    {
        $expression = $node->children['expr'];
        if (!($expression instanceof Node) || $expression->kind !== ast\AST_NAME) {
            return;
        }
        if (($expression->flags & ast\flags\NAME_NOT_FQ) !== ast\flags\NAME_NOT_FQ) {
            // This is namespace\foo() or \NS\foo()
            return;
        }
        if ($this->context->getNamespace() === '\\') {
            // This is in the global namespace and is always fully qualified
            return;
        }
        $function_name = $expression->children['name'];
        if (!is_string($function_name)) {
            // Possibly redundant.
            return;
        }
        // TODO: Probably wrong for ast\parse_code - should check namespace map of USE_NORMAL for 'ast' there.
        // Same for ContextNode->getFunction()
        if ($this->context->hasNamespaceMapFor(\ast\flags\USE_FUNCTION, $function_name)) {
            return;
        }
        NotFullyQualifiedReporterPlugin::logUnqualifiedCall($this->context->getNamespace(), $function_name);
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new NotFullyQualifiedReporterPlugin();
