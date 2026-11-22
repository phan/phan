<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\ContextNode;
use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Element\AddressableElement;
use Phan\Language\Element\AddressableElementInterface;
use Phan\Language\Element\ClassConstant;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Comment\CustomAnnotation;
use Phan\Language\Element\EnumCase;
use Phan\Language\Element\Flags;
use Phan\Language\Element\Func;
use Phan\Language\Element\GlobalConstant;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\Language\ElementContext;
use Phan\PluginV3;
use Phan\PluginV3\CustomAnnotationCapability;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * The test plugin of the custom annotation fixture suite (see test.sh).
 *
 * - In the parse phase, it emits PhanPluginCustomAnnotationSeen for each occurrence of an annotation it declared,
 *   on the line of the annotation, with the kind and FQSEN of the element, the text, and the number of annotations
 *   that the element returns from getCustomAnnotations() at that point. The suppressions on the element apply.
 * - In the analysis phase, it emits PhanPluginCustomAnnotationRead where an element of the analyzed code is used
 *   (a call, a property or constant access, `new`), with the annotations that the element it resolves to returns from
 *   getCustomAnnotations(). For a member, that is the copy in the class of the access, e.g. an inherited copy.
 */
final class CustomAnnotationTestPlugin extends PluginV3 implements CustomAnnotationCapability, PostAnalyzeNodeCapability
{
    public function getCustomAnnotations(): array
    {
        return [
            'phan-x-test-mark' => 'Marks an element for the test plugin',
            'phan-x-test-shared' => 'Declared by both test plugins',
            'phan-x-test-empty' => '',
        ];
    }

    public function handleCustomAnnotation(CodeBase $code_base, AddressableElementInterface $element, CustomAnnotation $annotation): void
    {
        if (!$element instanceof AddressableElement) {
            throw new AssertionError('Expected an AddressableElement');
        }
        $own = $element->getCustomAnnotations($code_base);
        if (!in_array($annotation, $own, true)) {
            throw new AssertionError("getCustomAnnotations() of $element does not return the annotation being handled");
        }
        // With these contexts, the suppressions on the element apply to the issue.
        $context = $element instanceof Clazz ? $element->getInternalContext() : new ElementContext($element);
        $this->emitIssue(
            $code_base,
            $context->withLineNumberStart($annotation->getLineno()),
            'PhanPluginCustomAnnotationSeen',
            'Saw @{STRING_LITERAL} on {DETAILS} {CODE} with the text {STRING_LITERAL} ({COUNT} annotation(s) on the element)',
            [$annotation->getName(), self::describeKind($element), (string)$element->getFQSEN(), json_encode($annotation->getText()), count($own)],
            Issue::SEVERITY_LOW
        );
    }

    /**
     * Returns the kind of the element, for the output
     */
    public static function describeKind(AddressableElementInterface $element): string
    {
        if ($element instanceof Clazz) {
            if ($element->isInterface()) {
                return 'interface';
            }
            if ($element->isTrait()) {
                return 'trait';
            }
            return $element->isEnum() ? 'enum' : 'class';
        }
        if ($element instanceof Method) {
            return 'method';
        }
        if ($element instanceof Property) {
            return $element->getPhanFlagsHasState(Flags::IS_PROMOTED_PROPERTY) ? 'promoted property' : 'property';
        }
        if ($element instanceof EnumCase) {
            return 'enum case';
        }
        if ($element instanceof ClassConstant) {
            return 'class constant';
        }
        if ($element instanceof GlobalConstant) {
            return 'global constant';
        }
        if ($element instanceof Func) {
            return $element->isClosure() ? 'closure' : 'function';
        }
        return get_class($element);
    }

    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return CustomAnnotationReadVisitor::class;
    }
}

/**
 * Emits PhanPluginCustomAnnotationRead for the elements that the analyzed code uses.
 */
final class CustomAnnotationReadVisitor extends PluginAwarePostAnalysisVisitor
{
    public function visitMethodCall(Node $node): void
    {
        $this->reportMethod($node, false);
    }

    public function visitNullsafeMethodCall(Node $node): void
    {
        $this->reportMethod($node, false);
    }

    public function visitStaticCall(Node $node): void
    {
        $this->reportMethod($node, true);
    }

    private function reportMethod(Node $node, bool $is_static): void
    {
        $name = $node->children['method'];
        if (!is_string($name)) {
            return;
        }
        try {
            $method = (new ContextNode($this->code_base, $this->context, $node))->getMethod($name, $is_static);
        } catch (Exception) {
            return;
        }
        $this->report($method);
    }

    public function visitProp(Node $node): void
    {
        $this->reportProperty($node, false);
    }

    public function visitStaticProp(Node $node): void
    {
        $this->reportProperty($node, true);
    }

    private function reportProperty(Node $node, bool $is_static): void
    {
        try {
            $property = (new ContextNode($this->code_base, $this->context, $node))->getProperty($is_static);
        } catch (Exception) {
            return;
        }
        $this->report($property);
    }

    public function visitClassConst(Node $node): void
    {
        try {
            $constant = (new ContextNode($this->code_base, $this->context, $node))->getClassConst();
        } catch (Exception) {
            return;
        }
        $this->report($constant);
    }

    public function visitConst(Node $node): void
    {
        try {
            $constant = (new ContextNode($this->code_base, $this->context, $node))->getConst();
        } catch (Exception) {
            return;
        }
        $this->report($constant);
    }

    public function visitCall(Node $node): void
    {
        try {
            foreach ((new ContextNode($this->code_base, $this->context, $node->children['expr']))->getFunctionFromNode() as $function) {
                $this->report($function);
            }
        } catch (Exception) {
            return;
        }
    }

    public function visitNew(Node $node): void
    {
        try {
            $class_list = (new ContextNode($this->code_base, $this->context, $node->children['class']))->getClassList(true);
        } catch (Exception) {
            return;
        }
        foreach ($class_list as $class) {
            $this->report($class);
        }
    }

    private function report(AddressableElementInterface $element): void
    {
        if ($element->isPHPInternal()) {
            return;
        }
        $parts = [];
        foreach ($element->getCustomAnnotations($this->code_base) as $annotation) {
            $parts[] = '@' . $annotation->getName() . '(' . json_encode($annotation->getText()) . ') on line ' . $annotation->getLineno();
        }
        $this->emitPluginIssue(
            $this->code_base,
            $this->context,
            'PhanPluginCustomAnnotationRead',
            'Read {DETAILS} {CODE}: {DETAILS}',
            [CustomAnnotationTestPlugin::describeKind($element), (string)$element->getFQSEN(), $parts ? implode(', ', $parts) : 'no custom annotations'],
            Issue::SEVERITY_LOW
        );
    }
}

return new CustomAnnotationTestPlugin();
