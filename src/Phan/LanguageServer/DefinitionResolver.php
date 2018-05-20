<?php declare(strict_types=1);

namespace Phan\LanguageServer;

use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Exception\CodeBaseException;
use Phan\Exception\NodeException;
use Phan\Exception\IssueException;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use ast;
use ast\Node;

class DefinitionResolver
{
    /**
     * @return Closure(Context,Node):void
     */
    public static function createGoToDefinitionClosure(GoToDefinitionRequest $request, CodeBase $code_base)
    {
        return function (Context $context, Node $node) use ($request, $code_base) {
            // TODO: Better way to be absolutely sure this $node is in the same requested file path?
            // I think it's possible that we'll have more than one Node to check against (with simplify_ast)


            // $location = new Location($go_to_definition_request->getUri(), $node->lineno);

            // Log as strings in case TolerantASTConverter generates the wrong type
            Logger::logInfo(sprintf("Saw a node of kind %s at line %s", (string)$node->kind, (string)$node->lineno));

            switch ($node->kind) {
                case ast\AST_NAME:
                    self::locateClassDefinition($request, $code_base, $context, $node);
                    return;
                case ast\AST_STATIC_PROP:
                case ast\AST_PROP:
                    self::locatePropDefinition($request, $code_base, $context, $node);
                    return;
                case ast\AST_STATIC_CALL:
                case ast\AST_METHOD_CALL:
                    self::locateMethodDefinition($request, $code_base, $context, $node);
                    return;
                case ast\AST_CALL:
                    self::locateFuncDefinition($request, $code_base, $context, $node);
                    return;
                case ast\AST_CLASS_CONST:
                    self::locateClassConstDefinition($request, $code_base, $context, $node);
                    return;
                case ast\AST_CONST:
                    self::locateGlobalConstDefinition($request, $code_base, $context, $node);
                    return;
            }
            // $go_to_definition_request->recordDefinitionLocation(...)
        };
    }

    /**
     * @return void
     */
    public static function locateClassDefinition(GoToDefinitionRequest $request, CodeBase $code_base, Context $context, Node $node)
    {
        $union_type = UnionTypeVisitor::unionTypeFromClassNode($code_base, $context, $node);
        foreach ($union_type->getTypeSet() as $type) {
            if ($type->isNativeType()) {
                continue;
            }
            $class_fqsen = $type->asFQSEN();
            if (!$class_fqsen instanceof FullyQualifiedClassName) {
                continue;
            }
            if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
                continue;
            }
            $class = $code_base->getClassByFQSEN($class_fqsen);
            $request->recordDefinitionElement($class);
        }
    }

    /**
     * @return void
     */
    public static function locatePropDefinition(GoToDefinitionRequest $request, CodeBase $code_base, Context $context, Node $node)
    {
        $is_static = $node->kind === ast\AST_STATIC_PROP;
        try {
            $property = (new ContextNode($code_base, $context, $node))->getProperty($is_static);
        } catch (NodeException $e) {
            return; // ignore
        } catch (IssueException $e) {
            return; // ignore
        } catch (CodeBaseException $e) {
            return; // ignore
        }
        $request->recordDefinitionElement($property);
    }

    /**
     * @param Node $node a node of type AST_CLASS_CONST
     * @return void
     */
    public static function locateClassConstDefinition(GoToDefinitionRequest $request, CodeBase $code_base, Context $context, Node $node)
    {
        $name = $node->children['const'];
        if (!is_string($name)) {
            return;
        }
        if (strtolower($name) === 'class') {
            self::locateClassDefinition($request, $code_base, $context, $node->children['class']);
            return;
        }
        try {
            $class_const = (new ContextNode($code_base, $context, $node))->getClassConst();
        } catch (NodeException $e) {
            return; // ignore
        } catch (IssueException $e) {
            return; // ignore
        } catch (CodeBaseException $e) {
            return; // ignore
        }
        // TODO: Location::fromElement
        $request->recordDefinitionElement($class_const);
    }

    /**
     * @return void
     */
    public static function locateGlobalConstDefinition(GoToDefinitionRequest $request, CodeBase $code_base, Context $context, Node $node)
    {
        try {
            $class_const = (new ContextNode($code_base, $context, $node))->getConst();
        } catch (NodeException $e) {
            return; // ignore
        } catch (IssueException $e) {
            return; // ignore
        } catch (CodeBaseException $e) {
            return; // ignore
        }
        // TODO: Location::fromElement
        $request->recordDefinitionElement($class_const);
    }

    /**
     * @return void
     * @suppress PhanPartialTypeMismatchReturn
     */
    public static function locateMethodDefinition(GoToDefinitionRequest $request, CodeBase $code_base, Context $context, Node $node)
    {
        $is_static = $node->kind === ast\AST_STATIC_CALL;
        $method_name = $node->children['method'];
        if (!is_string($method_name)) {
            return;
        }
        try {
            $method = (new ContextNode($code_base, $context, $node))->getMethod($method_name, $is_static);
        } catch (NodeException $e) {
            // ignore
            return;
        } catch (IssueException $e) {
            // ignore
            return;
        }
        // TODO: Location::fromElement
        $request->recordDefinitionElement($method);
    }

    /**
     * @return void
     * @suppress PhanPartialTypeMismatchReturn
     */
    public static function locateFuncDefinition(GoToDefinitionRequest $request, CodeBase $code_base, Context $context, Node $node)
    {
        try {
            foreach ((new ContextNode($code_base, $context, $node->children['expr']))->getFunctionFromNode() as $function_interface) {
                // TODO: Location::fromElement
                $request->recordDefinitionElement($function_interface);
            }
        } catch (NodeException $e) {
            // ignore
            return;
        } catch (IssueException $e) {
            // ignore
            return;
        }
    }
}
