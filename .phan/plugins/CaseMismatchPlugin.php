<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\Exception\CodeBaseException;
use Phan\Exception\IssueException;
use Phan\Exception\NodeException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\CodeBase;
use Phan\IssueInstance;
use Phan\Library\FileCacheEntry;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEditSet;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeCallableArgumentCapability;
use Phan\PluginV3\AutomaticFixCapability;
use Phan\PluginV3\IssueEmitter;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * Warns when a reference to a class, function, or method uses different casing
 * than the original declaration. While PHP treats these as case-insensitive,
 * inconsistent casing makes grepping harder and can break IDE tooling.
 */
class CaseMismatchPlugin extends PluginV3 implements PostAnalyzeNodeCapability, AutomaticFixCapability, AnalyzeCallableArgumentCapability
{
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return CaseMismatchVisitor::class;
    }

    /**
     * @return array<string,Closure(CodeBase,FileCacheEntry,IssueInstance):(?FileEditSet)>
     */
    public function getAutomaticFixers(): array
    {
        require_once __DIR__ . '/CaseMismatchPlugin/Fixers.php';
        return [
            CaseMismatchVisitor::CaseMismatchClassName => \Closure::fromCallable([\CaseMismatchPlugin\Fixers::class, 'fixClassName']),
            CaseMismatchVisitor::CaseMismatchFunctionName => \Closure::fromCallable([\CaseMismatchPlugin\Fixers::class, 'fixFunctionName']),
            CaseMismatchVisitor::CaseMismatchMethodName => \Closure::fromCallable([\CaseMismatchPlugin\Fixers::class, 'fixMethodName']),
            CaseMismatchVisitor::CaseMismatchNamespace => \Closure::fromCallable([\CaseMismatchPlugin\Fixers::class, 'fixNamespace']),
        ];
    }

    /**
     * @param CodeBase $code_base @unused-param
     * @suppress PhanPluginPreferNamespaceUseReturnType
     */
    public function getAnalyzeCallableArgumentClosure(CodeBase $code_base): \Closure
    {
        /**
         * @param Node|int|string|float $arg_node
         */
        return static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $unused_callee,
            int $unused_param_index,
            Node|int|string|float $arg_node
        ): void {
            CaseMismatchCallableChecker::checkCallableArg($code_base, $context, $arg_node);
        };
    }
}

/**
 * Visitor that checks for casing mismatches in class, function, method,
 * and namespace references.
 */
class CaseMismatchVisitor extends PluginAwarePostAnalysisVisitor
{
    // phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
    public const CaseMismatchClassName = 'PhanPluginCaseMismatchClassName';
    public const CaseMismatchFunctionName = 'PhanPluginCaseMismatchFunctionName';
    public const CaseMismatchMethodName = 'PhanPluginCaseMismatchMethodName';
    public const CaseMismatchNamespace = 'PhanPluginCaseMismatchNamespace';
    public const CaseMismatchCallableFunction = 'PhanPluginCaseMismatchCallableFunction';
    public const CaseMismatchCallableMethod = 'PhanPluginCaseMismatchCallableMethod';
    public const CaseMismatchCallableClass = 'PhanPluginCaseMismatchCallableClass';
    // phpcs:enable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase

    public function visitNew(Node $node): void
    {
        $class_node = $node->children['class'];
        if ($class_node instanceof Node) {
            $this->checkClassNameCasing($class_node);
        }
    }

    public function visitInstanceof(Node $node): void
    {
        $class_node = $node->children['class'];
        if ($class_node instanceof Node) {
            $this->checkClassNameCasing($class_node);
        }
    }

    public function visitStaticCall(Node $node): void
    {
        $class_node = $node->children['class'];
        if ($class_node instanceof Node) {
            $this->checkClassNameCasing($class_node);
        }
        $this->checkMethodNameCasing($node, true);
    }

    public function visitStaticProp(Node $node): void
    {
        $class_node = $node->children['class'];
        if ($class_node instanceof Node) {
            $this->checkClassNameCasing($class_node);
        }
    }

    public function visitClassName(Node $node): void
    {
        $class_node = $node->children['class'];
        if ($class_node instanceof Node) {
            $this->checkClassNameCasing($class_node);
        }
    }

    public function visitCatch(Node $node): void
    {
        $class_node = $node->children['class'];
        if (!($class_node instanceof Node)) {
            return;
        }
        // AST_CATCH has an AST_NAME_LIST child containing AST_NAME nodes
        foreach ($class_node->children as $name_node) {
            if ($name_node instanceof Node) {
                $this->checkClassNameCasing($name_node);
            }
        }
    }

    public function visitUseTrait(Node $node): void
    {
        $traits_node = $node->children['traits'];
        if (!($traits_node instanceof Node)) {
            return;
        }
        foreach ($traits_node->children as $name_node) {
            if ($name_node instanceof Node) {
                $this->checkClassNameCasing($name_node);
            }
        }
    }

    public function visitCall(Node $node): void
    {
        $expression = $node->children['expr'];
        if (!($expression instanceof Node) || $expression->kind !== ast\AST_NAME) {
            return;
        }
        $reference_name = $expression->children['name'];
        if (!is_string($reference_name)) {
            return;
        }

        $flags = $expression->flags;

        // Resolve the function FQSEN using the same logic as ContextNode::getFunction():
        // For unqualified names: check use map, then try namespace, then global fallback.
        // For FQ names: resolve directly with leading \.
        // For relative names: resolve in current namespace.
        $function_fqsen = $this->resolveFunctionFQSEN($reference_name, $flags);
        if ($function_fqsen === null) {
            return;
        }

        if (!$this->code_base->hasFunctionWithFQSEN($function_fqsen)) {
            return;
        }

        $function = $this->code_base->getFunctionByFQSEN($function_fqsen);
        $declared_name = $function->getName();

        $reference_parts = explode('\\', $reference_name);
        $reference_short = array_pop($reference_parts);

        // For unqualified names resolved via use, compare against the alias casing
        if ($flags === ast\flags\NAME_NOT_FQ) {
            // @phan-suppress-next-line PhanAccessMethodInternal
            $namespace_map = $this->context->getNamespaceMap();
            $entry = $namespace_map[ast\flags\USE_FUNCTION][strtolower($reference_name)] ?? null;
            if ($entry !== null) {
                $alias_name = $entry->original_name;
                // When the alias is a true rename (use function X as Y), compare against the alias
                if (strtolower($alias_name) !== strtolower($declared_name)) {
                    if ($reference_short !== $alias_name && strtolower($reference_short) === strtolower($alias_name)) {
                        $this->emitPluginIssue(
                            $this->code_base,
                            (clone $this->context)->withLineNumberStart($expression->lineno),
                            self::CaseMismatchFunctionName,
                            'Function call {FUNCTION} has a casing mismatch with use alias {FUNCTION}',
                            [$reference_short . '()', $alias_name . '()'],
                            Issue::SEVERITY_LOW
                        );
                    }
                    return;
                }
                // Plain import (no as) — fall through to compare against declaration
            }
        }

        if ($reference_short !== $declared_name && strtolower($reference_short) === strtolower($declared_name)) {
            $this->emitPluginIssue(
                $this->code_base,
                (clone $this->context)->withLineNumberStart($expression->lineno),
                self::CaseMismatchFunctionName,
                'Function call {FUNCTION} has a casing mismatch with declaration {FUNCTION} defined at {FILE}:{LINE}',
                [$reference_short . '()', $declared_name . '()', $function->getContext()->getFile(), $function->getContext()->getLineNumberStart()],
                Issue::SEVERITY_LOW
            );
        }

        // Check namespace casing for qualified function references (FQ, relative, or qualified NAME_NOT_FQ)
        if (count($reference_parts) > 0) {
            $this->checkNamespaceOrAliasCasing(
                $reference_parts,
                $function_fqsen->getNamespace(),
                $expression->lineno,
                $flags
            );
        }
    }

    public function visitUse(Node $node): void
    {
        $use_type = $node->flags;
        // Skip constants — they are case-sensitive
        if ($use_type === ast\flags\USE_CONST) {
            return;
        }

        foreach ($node->children as $use_elem) {
            if (!($use_elem instanceof Node)) {
                continue;
            }
            $name = $use_elem->children['name'];
            if (!is_string($name)) {
                continue;
            }
            // In group use declarations, flags may be on the child element
            $elem_type = $use_type ?: $use_elem->flags;
            if ($elem_type === ast\flags\USE_CONST) {
                continue;
            }
            $this->checkUseStatementCasing($name, $elem_type, $use_elem->lineno);
        }
    }

    public function visitGroupUse(Node $node): void
    {
        $prefix = $node->children['prefix'];
        if (!is_string($prefix)) {
            return;
        }
        $uses_node = $node->children['uses'];
        if (!($uses_node instanceof Node)) {
            return;
        }
        $group_flags = $node->flags;

        foreach ($uses_node->children as $use_elem) {
            if (!($use_elem instanceof Node)) {
                continue;
            }
            $suffix = $use_elem->children['name'];
            if (!is_string($suffix)) {
                continue;
            }
            $elem_type = $group_flags ?: $use_elem->flags;
            if ($elem_type === ast\flags\USE_CONST) {
                continue;
            }
            // Reconstruct the full name: prefix\suffix
            $full_name = $prefix . '\\' . $suffix;
            $this->checkUseStatementCasing($full_name, $elem_type, $use_elem->lineno);
        }
    }

    public function visitClassConst(Node $node): void
    {
        $class_node = $node->children['class'];
        if ($class_node instanceof Node) {
            $this->checkClassNameCasing($class_node);
        }
    }

    public function visitClass(Node $node): void
    {
        $extends_node = $node->children['extends'];
        if ($extends_node instanceof Node) {
            $this->checkClassNameCasing($extends_node);
        }

        $implements_node = $node->children['implements'];
        if ($implements_node instanceof Node) {
            foreach ($implements_node->children as $name_node) {
                if ($name_node instanceof Node) {
                    $this->checkClassNameCasing($name_node);
                }
            }
        }
    }

    public function visitParam(Node $node): void
    {
        $type_node = $node->children['type'];
        if ($type_node instanceof Node) {
            $this->checkTypeNodeCasing($type_node);
        }
    }

    public function visitFuncDecl(Node $node): void
    {
        $return_type = $node->children['returnType'];
        if ($return_type instanceof Node) {
            $this->checkTypeNodeCasing($return_type);
        }
    }

    public function visitMethod(Node $node): void
    {
        $return_type = $node->children['returnType'];
        if ($return_type instanceof Node) {
            $this->checkTypeNodeCasing($return_type);
        }
    }

    public function visitClosure(Node $node): void
    {
        $return_type = $node->children['returnType'];
        if ($return_type instanceof Node) {
            $this->checkTypeNodeCasing($return_type);
        }
    }

    public function visitArrowFunc(Node $node): void
    {
        $return_type = $node->children['returnType'];
        if ($return_type instanceof Node) {
            $this->checkTypeNodeCasing($return_type);
        }
    }

    public function visitPropGroup(Node $node): void
    {
        $type_node = $node->children['type'];
        if ($type_node instanceof Node) {
            $this->checkTypeNodeCasing($type_node);
        }
    }

    public function visitMethodCall(Node $node): void
    {
        $this->checkMethodNameCasing($node, false);
    }

    public function visitNullsafeMethodCall(Node $node): void
    {
        $this->checkMethodNameCasing($node, false);
    }

    private function checkTypeNodeCasing(Node $type_node): void
    {
        switch ($type_node->kind) {
            case ast\AST_NAME:
                $this->checkClassNameCasing($type_node);
                break;
            case ast\AST_NULLABLE_TYPE:
                $inner = $type_node->children['type'];
                if ($inner instanceof Node) {
                    $this->checkTypeNodeCasing($inner);
                }
                break;
            case ast\AST_TYPE_UNION:
            case ast\AST_TYPE_INTERSECTION:
                foreach ($type_node->children as $child) {
                    if ($child instanceof Node) {
                        $this->checkTypeNodeCasing($child);
                    }
                }
                break;
        }
    }

    private function checkClassNameCasing(Node $class_node): void
    {
        if ($class_node->kind !== ast\AST_NAME) {
            return;
        }
        $reference_name = $class_node->children['name'];
        if (!is_string($reference_name)) {
            return;
        }

        // Skip self/static/parent
        $short_lower = strtolower($reference_name);
        if ($short_lower === 'self' || $short_lower === 'static' || $short_lower === 'parent') {
            return;
        }

        // Resolve the class FQSEN based on the name flags.
        $flags = $class_node->flags;
        try {
            if ($flags === ast\flags\NAME_FQ) {
                $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString('\\' . $reference_name);
            } elseif ($flags === ast\flags\NAME_RELATIVE) {
                // NAME_RELATIVE (namespace\Foo) must resolve in the current namespace,
                // bypassing the use map. fromStringInContext doesn't handle this.
                // getNamespace() returns '\' for global, '\Foo\Bar' for named namespaces.
                $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString(
                    rtrim($this->context->getNamespace(), '\\') . '\\' . $reference_name
                );
            } else {
                $class_fqsen = FullyQualifiedClassName::fromStringInContext(
                    $reference_name,
                    $this->context
                );
            }
        } catch (\Exception) {
            return;
        }

        if (!$this->code_base->hasClassWithFQSEN($class_fqsen)) {
            return;
        }

        $class = $this->code_base->getClassByFQSEN($class_fqsen);
        $declared_name = $class->getName();

        // Split reference to get short name and namespace parts
        $reference_parts = explode('\\', $reference_name);
        $reference_short = array_pop($reference_parts);

        // For unqualified names resolved via use, compare against the alias casing
        if ($flags === ast\flags\NAME_NOT_FQ) {
            // @phan-suppress-next-line PhanAccessMethodInternal
            $namespace_map = $this->context->getNamespaceMap();
            $entry = $namespace_map[ast\flags\USE_NORMAL][strtolower($reference_name)] ?? null;
            if ($entry !== null) {
                $alias_name = $entry->original_name;
                // When the alias is a true rename (use X as Y), compare against the alias
                if (strtolower($alias_name) !== strtolower($declared_name)) {
                    if ($reference_short !== $alias_name && strtolower($reference_short) === strtolower($alias_name)) {
                        $this->emitPluginIssue(
                            $this->code_base,
                            (clone $this->context)->withLineNumberStart($class_node->lineno),
                            self::CaseMismatchClassName,
                            'Class reference {CLASS} has a casing mismatch with use alias {CLASS}',
                            [$reference_short, $alias_name],
                            Issue::SEVERITY_LOW
                        );
                    }
                    return;
                }
                // Plain import (no as) — fall through to compare against declaration
            }
        }

        // Check short name casing against declaration
        if ($reference_short !== $declared_name && strtolower($reference_short) === strtolower($declared_name)) {
            $this->emitPluginIssue(
                $this->code_base,
                (clone $this->context)->withLineNumberStart($class_node->lineno),
                self::CaseMismatchClassName,
                'Class reference {CLASS} has a casing mismatch with declaration {CLASS} defined at {FILE}:{LINE}',
                [$reference_short, $declared_name, $class->getContext()->getFile(), $class->getContext()->getLineNumberStart()],
                Issue::SEVERITY_LOW
            );
        }

        // Check namespace casing for qualified names (FQ, relative, or qualified NAME_NOT_FQ)
        if (count($reference_parts) > 0) {
            $this->checkNamespaceOrAliasCasing(
                $reference_parts,
                $class_fqsen->getNamespace(),
                $class_node->lineno,
                $flags
            );
        }
    }

    /**
     * Check casing of a use statement (e.g., `use Foo\Bar\Baz;` or `use function Foo\myFunc;`).
     * Use statements always reference fully-qualified names.
     */
    private function checkUseStatementCasing(string $name, int $use_type, int $lineno): void
    {
        $parts = explode('\\', $name);
        $short_name = array_pop($parts);

        if ($use_type === ast\flags\USE_FUNCTION) {
            $fqsen_string = '\\' . $name;
            try {
                $function_fqsen = FullyQualifiedFunctionName::fromFullyQualifiedString($fqsen_string);
            } catch (\Exception) {
                return;
            }
            if (!$this->code_base->hasFunctionWithFQSEN($function_fqsen)) {
                return;
            }
            $function = $this->code_base->getFunctionByFQSEN($function_fqsen);
            $declared_name = $function->getName();

            if ($short_name !== $declared_name && strtolower($short_name) === strtolower($declared_name)) {
                $this->emitPluginIssue(
                    $this->code_base,
                    (clone $this->context)->withLineNumberStart($lineno),
                    self::CaseMismatchFunctionName,
                    'Use statement for {FUNCTION} has a casing mismatch with declaration {FUNCTION} defined at {FILE}:{LINE}',
                    [$short_name . '()', $declared_name . '()', $function->getContext()->getFile(), $function->getContext()->getLineNumberStart()],
                    Issue::SEVERITY_LOW
                );
            }
            $this->checkNamespaceCasing($parts, $function_fqsen->getNamespace(), $lineno);
        } else {
            // USE_NORMAL — class/interface/trait/enum
            $fqsen_string = '\\' . $name;
            try {
                $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString($fqsen_string);
            } catch (\Exception) {
                return;
            }
            if (!$this->code_base->hasClassWithFQSEN($class_fqsen)) {
                return;
            }
            $class = $this->code_base->getClassByFQSEN($class_fqsen);
            $declared_name = $class->getName();

            if ($short_name !== $declared_name && strtolower($short_name) === strtolower($declared_name)) {
                $this->emitPluginIssue(
                    $this->code_base,
                    (clone $this->context)->withLineNumberStart($lineno),
                    self::CaseMismatchClassName,
                    'Use statement for {CLASS} has a casing mismatch with declaration {CLASS} defined at {FILE}:{LINE}',
                    [$short_name, $declared_name, $class->getContext()->getFile(), $class->getContext()->getLineNumberStart()],
                    Issue::SEVERITY_LOW
                );
            }
            $this->checkNamespaceCasing($parts, $class_fqsen->getNamespace(), $lineno);
        }
    }

    /**
     * Compare namespace segments from the reference against the declared namespace.
     *
     * @param string[] $reference_ns_parts Namespace segments from the reference (may be empty)
     * @param string $declared_namespace The declared namespace (e.g., '\Foo\Bar')
     * @param int $lineno Line number of the reference
     */
    private function checkNamespaceCasing(array $reference_ns_parts, string $declared_namespace, int $lineno): void
    {
        if (count($reference_ns_parts) === 0) {
            return;
        }

        // Parse declared namespace into segments
        $declared_parts = array_filter(explode('\\', $declared_namespace), static function (string $part): bool {
            return $part !== '';
        });
        $declared_parts = array_values($declared_parts);

        // For fully-qualified names, reference_ns_parts aligns with declared_parts directly.
        // For relatively-qualified names, reference_ns_parts are a suffix of declared_parts.
        // We compare from the end of declared_parts.
        $ref_count = count($reference_ns_parts);
        $decl_count = count($declared_parts);

        // Align from the end
        $offset = $decl_count - $ref_count;
        if ($offset < 0) {
            return;
        }

        for ($i = 0; $i < $ref_count; $i++) {
            $ref_segment = $reference_ns_parts[$i];
            $decl_segment = $declared_parts[$offset + $i];
            if ($ref_segment !== $decl_segment && strtolower($ref_segment) === strtolower($decl_segment)) {
                $this->emitPluginIssue(
                    $this->code_base,
                    (clone $this->context)->withLineNumberStart($lineno),
                    self::CaseMismatchNamespace,
                    'Namespace segment {NAMESPACE} has a casing mismatch with declaration {NAMESPACE}',
                    ['\\' . $ref_segment, '\\' . $decl_segment],
                    Issue::SEVERITY_LOW
                );
            }
        }
    }

    /**
     * Check namespace segment casing, handling the case where the first segment
     * of a qualified NAME_NOT_FQ reference is a use alias (e.g., `use Foo\Bar as Pkg; new pkg\Thing();`).
     *
     * @param string[] $reference_ns_parts Namespace segments from the reference
     * @param string $declared_namespace The declared namespace (e.g., '\Foo\Bar')
     * @param int $lineno Line number of the reference
     * @param int $flags AST name flags (NAME_FQ, NAME_NOT_FQ, NAME_RELATIVE)
     */
    private function checkNamespaceOrAliasCasing(
        array $reference_ns_parts,
        string $declared_namespace,
        int $lineno,
        int $flags
    ): void {
        // For qualified NAME_NOT_FQ, the first segment may be a use alias
        if ($flags === ast\flags\NAME_NOT_FQ) {
            $first_segment = $reference_ns_parts[0];
            // @phan-suppress-next-line PhanAccessMethodInternal
            $namespace_map = $this->context->getNamespaceMap();
            $entry = $namespace_map[ast\flags\USE_NORMAL][strtolower($first_segment)] ?? null;
            if ($entry !== null) {
                $alias_name = $entry->original_name;
                if ($first_segment !== $alias_name && strtolower($first_segment) === strtolower($alias_name)) {
                    $this->emitPluginIssue(
                        $this->code_base,
                        (clone $this->context)->withLineNumberStart($lineno),
                        self::CaseMismatchNamespace,
                        'Namespace segment {NAMESPACE} has a casing mismatch with use alias {NAMESPACE}',
                        ['\\' . $first_segment, '\\' . $alias_name],
                        Issue::SEVERITY_LOW
                    );
                }
                // Check remaining segments (after the alias) against the declared namespace
                $remaining = array_slice($reference_ns_parts, 1);
                if (count($remaining) > 0) {
                    $this->checkNamespaceCasing($remaining, $declared_namespace, $lineno);
                }
                return;
            }
        }

        $this->checkNamespaceCasing($reference_ns_parts, $declared_namespace, $lineno);
    }

    /**
     * Resolve a function FQSEN using PHP's name resolution rules:
     * - NAME_FQ: fully qualified (e.g. \Foo\bar)
     * - NAME_RELATIVE: relative to current namespace (e.g. namespace\bar)
     * - NAME_NOT_FQ: check use map, then current namespace, then global fallback
     *
     * @return ?FullyQualifiedFunctionName null if the name cannot be resolved
     */
    private function resolveFunctionFQSEN(string $reference_name, int $flags): ?FullyQualifiedFunctionName
    {
        try {
            if ($flags === ast\flags\NAME_FQ) {
                return FullyQualifiedFunctionName::fromFullyQualifiedString('\\' . $reference_name);
            }

            $namespace = $this->context->getNamespace();

            if (($flags & ast\flags\NAME_RELATIVE) !== 0) {
                return FullyQualifiedFunctionName::make($namespace, $reference_name);
            }

            // NAME_NOT_FQ: check use map first
            if ($this->context->hasNamespaceMapFor(ast\flags\USE_FUNCTION, $reference_name)) {
                $fqsen = $this->context->getNamespaceMapFor(ast\flags\USE_FUNCTION, $reference_name);
                if ($fqsen instanceof FullyQualifiedFunctionName) {
                    return $fqsen;
                }
            }

            // Try current namespace first
            $namespaced_fqsen = FullyQualifiedFunctionName::make($namespace, $reference_name);
            if ($this->code_base->hasFunctionWithFQSEN($namespaced_fqsen)) {
                return $namespaced_fqsen;
            }

            // Fall back to global namespace (unqualified names only).
            // PHP doesn't do global fallback for qualified names (containing \).
            if (str_contains($reference_name, '\\')) {
                return null;
            }
            return FullyQualifiedFunctionName::make('', $reference_name);
        } catch (\Exception) {
            return null;
        }
    }

    private function checkMethodNameCasing(Node $node, bool $is_static): void
    {
        $method_name = $node->children['method'];
        if (!is_string($method_name)) {
            return;
        }

        try {
            $context_node = new ContextNode($this->code_base, $this->context, $node);
            $method = $context_node->getMethod($method_name, $is_static, false);
        } catch (NodeException | CodeBaseException | IssueException) {
            return;
        }

        $declared_name = $method->getName();

        if ($method_name !== $declared_name && strtolower($method_name) === strtolower($declared_name)) {
            $this->emitPluginIssue(
                $this->code_base,
                (clone $this->context)->withLineNumberStart($node->lineno),
                self::CaseMismatchMethodName,
                'Method call {METHOD} has a casing mismatch with declaration {METHOD} defined at {FILE}:{LINE}',
                [$method_name . '()', $declared_name . '()', $method->getContext()->getFile(), $method->getContext()->getLineNumberStart()],
                Issue::SEVERITY_LOW
            );
        }
    }
}

/**
 * Checks casing of callable arguments (string callables and array callables)
 * passed to functions like call_user_func, array_map, usort, etc.
 */
class CaseMismatchCallableChecker
{
    use IssueEmitter;

    /**
     * Check a single callable argument for casing mismatches.
     */
    public static function checkCallableArg(CodeBase $code_base, Context $context, Node|int|float|string $arg): void
    {
        if (is_string($arg)) {
            self::checkStringCallable($code_base, $context, $arg, $context->getLineNumberStart());
            return;
        }
        if (!($arg instanceof Node)) {
            return;
        }
        if ($arg->kind === ast\AST_ARRAY) {
            self::checkArrayCallable($code_base, $context, $arg);
            return;
        }
        // For variables and other expressions, try to resolve to a string value
        $value = (new ContextNode($code_base, $context, $arg))->getEquivalentPHPScalarValue();
        if (is_string($value)) {
            self::checkStringCallable($code_base, $context, $value, $arg->lineno);
        }
    }

    /**
     * Check a string callable like 'myFunc' or 'ClassName::methodName'.
     */
    private static function checkStringCallable(CodeBase $code_base, Context $context, string $callable_string, int $lineno): void
    {
        if (str_contains($callable_string, '::')) {
            [$class_part, $method_part] = explode('::', $callable_string, 2);
            if (in_array(strtolower($class_part), ['self', 'static', 'parent'], true)) {
                // Resolve self/static/parent to the actual class from context, then check method casing
                self::checkSelfStaticParentMethodCallable($code_base, $context, $class_part, $method_part, $lineno);
                return;
            }
            self::checkStaticMethodCallable($code_base, $context, $class_part, $method_part, $lineno);
        } else {
            self::checkFunctionCallable($code_base, $context, $callable_string, $lineno);
        }
    }

    /**
     * Check a plain function name callable like 'myFunc'.
     */
    private static function checkFunctionCallable(CodeBase $code_base, Context $context, string $function_name, int $lineno): void
    {
        try {
            $function_fqsen = FullyQualifiedFunctionName::fromFullyQualifiedString($function_name);
        } catch (\Exception) {
            return;
        }
        if (!$code_base->hasFunctionWithFQSEN($function_fqsen)) {
            return;
        }
        $function = $code_base->getFunctionByFQSEN($function_fqsen);
        $declared_name = $function->getName();

        // Extract just the short name from a potentially namespaced string
        $parts = explode('\\', $function_name);
        $reference_short = array_pop($parts);

        $issue_context = (clone $context)->withLineNumberStart($lineno);

        if ($reference_short !== $declared_name && strtolower($reference_short) === strtolower($declared_name)) {
            self::emitPluginIssue(
                $code_base,
                $issue_context,
                CaseMismatchVisitor::CaseMismatchCallableFunction,
                'Callable {FUNCTION} has a casing mismatch with declaration {FUNCTION} defined at {FILE}:{LINE}',
                [$reference_short . '()', $declared_name . '()', $function->getContext()->getFile(), $function->getContext()->getLineNumberStart()],
                Issue::SEVERITY_LOW
            );
        }

        // Check namespace segments
        if (count($parts) > 0) {
            $declared_namespace = $function_fqsen->getNamespace();
            self::checkNamespaceSegments($code_base, $issue_context, $parts, $declared_namespace);
        }
    }

    /**
     * Check a 'ClassName::methodName' callable.
     */
    private static function checkStaticMethodCallable(
        CodeBase $code_base,
        Context $context,
        string $class_part,
        string $method_part,
        int $lineno
    ): void {
        // Resolve class
        try {
            $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString($class_part);
        } catch (\Exception) {
            return;
        }
        if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
            return;
        }
        $class = $code_base->getClassByFQSEN($class_fqsen);

        $issue_context = (clone $context)->withLineNumberStart($lineno);

        // Check class name casing
        $declared_class_name = $class->getName();
        $class_parts = explode('\\', $class_part);
        $reference_class_short = array_pop($class_parts);

        if ($reference_class_short !== $declared_class_name && strtolower($reference_class_short) === strtolower($declared_class_name)) {
            self::emitPluginIssue(
                $code_base,
                $issue_context,
                CaseMismatchVisitor::CaseMismatchCallableClass,
                'Callable class {CLASS} has a casing mismatch with declaration {CLASS} defined at {FILE}:{LINE}',
                [$reference_class_short, $declared_class_name, $class->getContext()->getFile(), $class->getContext()->getLineNumberStart()],
                Issue::SEVERITY_LOW
            );
        }

        // Check namespace segments for the class part
        if (count($class_parts) > 0) {
            $declared_namespace = $class_fqsen->getNamespace();
            self::checkNamespaceSegments($code_base, $issue_context, $class_parts, $declared_namespace);
        }

        // Check method name casing
        if (!$class->hasMethodWithName($code_base, $method_part, true)) {
            return;
        }
        $method = $class->getMethodByName($code_base, $method_part);
        $declared_method_name = $method->getName();

        if ($method_part !== $declared_method_name && strtolower($method_part) === strtolower($declared_method_name)) {
            self::emitPluginIssue(
                $code_base,
                $issue_context,
                CaseMismatchVisitor::CaseMismatchCallableMethod,
                'Callable method {METHOD} has a casing mismatch with declaration {METHOD} defined at {FILE}:{LINE}',
                [$method_part . '()', $declared_method_name . '()', $method->getContext()->getFile(), $method->getContext()->getLineNumberStart()],
                Issue::SEVERITY_LOW
            );
        }
    }

    /**
     * Check method casing for 'self::method', 'static::method', or 'parent::method' callables.
     */
    private static function checkSelfStaticParentMethodCallable(
        CodeBase $code_base,
        Context $context,
        string $class_keyword,
        string $method_part,
        int $lineno
    ): void {
        if (!$context->isInClassScope()) {
            return;
        }
        try {
            $class_fqsen = $context->getClassFQSEN();
            if (strtolower($class_keyword) === 'parent') {
                if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
                    return;
                }
                $class = $code_base->getClassByFQSEN($class_fqsen);
                if (!$class->hasParentType()) {
                    return;
                }
                $class_fqsen = $class->getParentClassFQSEN();
            }
        } catch (\Exception) {
            return;
        }

        if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
            return;
        }
        $class = $code_base->getClassByFQSEN($class_fqsen);
        if (!$class->hasMethodWithName($code_base, $method_part, true)) {
            return;
        }
        $method = $class->getMethodByName($code_base, $method_part);
        $declared_method_name = $method->getName();

        if ($method_part !== $declared_method_name && strtolower($method_part) === strtolower($declared_method_name)) {
            self::emitPluginIssue(
                $code_base,
                (clone $context)->withLineNumberStart($lineno),
                CaseMismatchVisitor::CaseMismatchCallableMethod,
                'Callable method {METHOD} has a casing mismatch with declaration {METHOD} defined at {FILE}:{LINE}',
                [$method_part . '()', $declared_method_name . '()', $method->getContext()->getFile(), $method->getContext()->getLineNumberStart()],
                Issue::SEVERITY_LOW
            );
        }
    }

    /**
     * Check an array callable like [$obj, 'method'] or ['ClassName', 'method'].
     */
    private static function checkArrayCallable(CodeBase $code_base, Context $context, Node $array_node): void
    {
        $elements = $array_node->children;
        if (count($elements) !== 2) {
            return;
        }

        // Extract the method name from element[1]
        $method_elem = $elements[1];
        $method_name = null;
        if ($method_elem instanceof Node && $method_elem->kind === ast\AST_ARRAY_ELEM) {
            $method_value = $method_elem->children['value'];
            if (is_string($method_value)) {
                $method_name = $method_value;
            } elseif ($method_value instanceof Node) {
                $method_name = (new ContextNode($code_base, $context, $method_value))->getEquivalentPHPScalarValue();
                if (!is_string($method_name)) {
                    $method_name = null;
                }
            }
        }

        // Extract the class from element[0]
        $class_elem = $elements[0];
        $class_expr = null;
        if ($class_elem instanceof Node && $class_elem->kind === ast\AST_ARRAY_ELEM) {
            $class_expr = $class_elem->children['value'];
        }

        if ($method_name === null || $class_expr === null) {
            return;
        }

        $lineno = $array_node->lineno;
        $issue_context = (clone $context)->withLineNumberStart($lineno);

        // Resolve the callable to get the declared method
        $function_list = UnionTypeVisitor::functionLikeListFromNodeAndContext($code_base, $context, $array_node, false);
        if (!$function_list) {
            return;
        }

        foreach ($function_list as $function_like) {
            $declared_method_name = $function_like->getName();
            if ($method_name !== $declared_method_name && strtolower($method_name) === strtolower($declared_method_name)) {
                self::emitPluginIssue(
                    $code_base,
                    $issue_context,
                    CaseMismatchVisitor::CaseMismatchCallableMethod,
                    'Callable method {METHOD} has a casing mismatch with declaration {METHOD} defined at {FILE}:{LINE}',
                    [$method_name . '()', $declared_method_name . '()', $function_like->getContext()->getFile(), $function_like->getContext()->getLineNumberStart()],
                    Issue::SEVERITY_LOW
                );
            }
        }

        // Check class name casing if element[0] is a string
        if (is_string($class_expr)) {
            if (in_array(strtolower($class_expr), ['self', 'static', 'parent'], true)) {
                return;
            }
            try {
                $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString($class_expr);
            } catch (\Exception) {
                return;
            }
            if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
                return;
            }
            $class = $code_base->getClassByFQSEN($class_fqsen);
            $declared_class_name = $class->getName();
            $class_parts = explode('\\', $class_expr);
            $reference_class_short = array_pop($class_parts);

            if ($reference_class_short !== $declared_class_name && strtolower($reference_class_short) === strtolower($declared_class_name)) {
                self::emitPluginIssue(
                    $code_base,
                    $issue_context,
                    CaseMismatchVisitor::CaseMismatchCallableClass,
                    'Callable class {CLASS} has a casing mismatch with declaration {CLASS} defined at {FILE}:{LINE}',
                    [$reference_class_short, $declared_class_name, $class->getContext()->getFile(), $class->getContext()->getLineNumberStart()],
                    Issue::SEVERITY_LOW
                );
            }

            // Check namespace segments for casing mismatches
            if (count($class_parts) > 0) {
                $declared_namespace = $class_fqsen->getNamespace();
                self::checkNamespaceSegments($code_base, $issue_context, $class_parts, $declared_namespace);
            }
        }
    }

    /**
     * Check namespace segments for casing mismatches.
     * @param string[] $reference_parts namespace segments from the reference
     * @param string $declared_namespace the declared namespace (e.g., '\Foo\Bar')
     */
    private static function checkNamespaceSegments(
        CodeBase $code_base,
        Context $context,
        array $reference_parts,
        string $declared_namespace
    ): void {
        $declared_parts = array_values(array_filter(explode('\\', $declared_namespace), static function (string $part): bool {
            return $part !== '';
        }));
        // Filter empty segments (e.g. from leading backslash in '\TestNs\func')
        $reference_parts = array_values(array_filter($reference_parts, static function (string $part): bool {
            return $part !== '';
        }));

        $ref_count = count($reference_parts);
        $decl_count = count($declared_parts);
        $offset = $decl_count - $ref_count;
        if ($offset < 0) {
            return;
        }

        for ($i = 0; $i < $ref_count; $i++) {
            $ref_segment = $reference_parts[$i];
            $decl_segment = $declared_parts[$offset + $i];
            if ($ref_segment !== $decl_segment && strtolower($ref_segment) === strtolower($decl_segment)) {
                self::emitPluginIssue(
                    $code_base,
                    $context,
                    CaseMismatchVisitor::CaseMismatchNamespace,
                    'Namespace segment {NAMESPACE} has a casing mismatch with declaration {NAMESPACE}',
                    ['\\' . $ref_segment, '\\' . $decl_segment],
                    Issue::SEVERITY_LOW
                );
            }
        }
    }
}

return new CaseMismatchPlugin();
