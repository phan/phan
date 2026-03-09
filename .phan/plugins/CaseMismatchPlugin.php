<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\ContextNode;
use Phan\Exception\CodeBaseException;
use Phan\Exception\IssueException;
use Phan\Exception\NodeException;
use Phan\Issue;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * Warns when a reference to a class, function, or method uses different casing
 * than the original declaration. While PHP treats these as case-insensitive,
 * inconsistent casing makes grepping harder and can break IDE tooling.
 */
class CaseMismatchPlugin extends PluginV3 implements PostAnalyzeNodeCapability
{
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return CaseMismatchVisitor::class;
    }
}

class CaseMismatchVisitor extends PluginAwarePostAnalysisVisitor
{
    // phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
    public const CaseMismatchClassName = 'PhanPluginCaseMismatchClassName';
    public const CaseMismatchFunctionName = 'PhanPluginCaseMismatchFunctionName';
    public const CaseMismatchMethodName = 'PhanPluginCaseMismatchMethodName';
    public const CaseMismatchNamespace = 'PhanPluginCaseMismatchNamespace';
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

        // AST strips the leading \ for fully-qualified names and stores it in flags.
        $flags = $expression->flags;
        $fqsen_string = $flags === ast\flags\NAME_FQ ? '\\' . $reference_name : $reference_name;

        try {
            $function_fqsen = FullyQualifiedFunctionName::fromStringInContext(
                $fqsen_string,
                $this->context
            );
        } catch (\Exception $e) {
            return;
        }

        if (!$this->code_base->hasFunctionWithFQSEN($function_fqsen)) {
            return;
        }

        $function = $this->code_base->getFunctionByFQSEN($function_fqsen);
        $declared_name = $function->getName();

        $reference_parts = explode('\\', $reference_name);
        $reference_short = array_pop($reference_parts);

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

        // Check namespace casing for qualified function references
        if ($flags === ast\flags\NAME_FQ || $flags === ast\flags\NAME_RELATIVE) {
            $this->checkNamespaceCasing(
                $reference_parts,
                $function_fqsen->getNamespace(),
                $expression->lineno
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

    public function visitMethodCall(Node $node): void
    {
        $this->checkMethodNameCasing($node, false);
    }

    public function visitNullsafeMethodCall(Node $node): void
    {
        $this->checkMethodNameCasing($node, false);
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

        // AST strips the leading \ for fully-qualified names and stores it in flags.
        // We need to add it back for fromStringInContext to resolve correctly.
        $flags = $class_node->flags;
        $fqsen_string = $flags === ast\flags\NAME_FQ ? '\\' . $reference_name : $reference_name;

        try {
            $class_fqsen = FullyQualifiedClassName::fromStringInContext(
                $fqsen_string,
                $this->context
            );
        } catch (\Exception $e) {
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

        // Check short name casing
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

        // Check namespace casing for qualified names
        if ($flags === ast\flags\NAME_FQ || $flags === ast\flags\NAME_RELATIVE) {
            $this->checkNamespaceCasing(
                $reference_parts,
                $class_fqsen->getNamespace(),
                $class_node->lineno
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
            } catch (\Exception $e) {
                return;
            }
            if (!$this->code_base->hasFunctionWithFQSEN($function_fqsen)) {
                return;
            }
            $function = $this->code_base->getFunctionByFQSEN($function_fqsen);
            $declared_name = $function->getName();
            $declared_namespace = $function_fqsen->getNamespace();

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
            } catch (\Exception $e) {
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

    private function checkMethodNameCasing(Node $node, bool $is_static): void
    {
        $method_name = $node->children['method'];
        if (!is_string($method_name)) {
            return;
        }

        // Skip magic methods
        if (isset(FullyQualifiedMethodName::CANONICAL_NAMES[strtolower($method_name)])) {
            return;
        }

        try {
            $context_node = new ContextNode($this->code_base, $this->context, $node);
            $method = $context_node->getMethod($method_name, $is_static);
        } catch (NodeException | CodeBaseException | IssueException $e) {
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

return new CaseMismatchPlugin();
