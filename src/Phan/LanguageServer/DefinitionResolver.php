<?php declare(strict_types=1);

namespace Phan\LanguageServer;

use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\LanguageServer\Protocol\Location;
use Phan\Language\FQSEN\FullyQualifiedClassName;
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
            fwrite(STDERR, "Saw a node: " . \Phan\Debug::nodeToString($node) . "\n");
            switch ($node->kind) {
                case \ast\AST_NAME:
                    self::locateClassDefinition($request, $code_base, $context, $node);
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
            $request->recordDefinitionLocation(Location::fromContext($class->getContext()));
        }
    }
}
