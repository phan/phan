<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser;

use Closure;
use Microsoft\PhpParser\Node\AnonymousFunctionUseClause;
use Microsoft\PhpParser\Node\ArrayElement;
use Microsoft\PhpParser\Node\Attribute;
use Microsoft\PhpParser\Node\AttributeGroup;
use Microsoft\PhpParser\Node\CaseStatementNode;
use Microsoft\PhpParser\Node\CatchClause;
use Microsoft\PhpParser\Node\ClassBaseClause;
use Microsoft\PhpParser\Node\ClassInterfaceClause;
use Microsoft\PhpParser\Node\ClassMembersNode;
use Microsoft\PhpParser\Node\ConstElement;
use Microsoft\PhpParser\Node\EnumCaseDeclaration;
use Microsoft\PhpParser\Node\EnumInterfaceClause;
use Microsoft\PhpParser\Node\EnumMembers;
use Microsoft\PhpParser\Node\Expression;
use Microsoft\PhpParser\Node\Expression\{
    AnonymousFunctionCreationExpression,
    ArgumentExpression,
    ArrayCreationExpression,
    ArrowFunctionCreationExpression,
    AssignmentExpression,
    BinaryExpression,
    BracedExpression,
    CallExpression,
    CastExpression,
    CloneExpression,
    EmptyIntrinsicExpression,
    ErrorControlExpression,
    EvalIntrinsicExpression,
    ExitIntrinsicExpression,
    IssetIntrinsicExpression,
    MatchExpression,
    MemberAccessExpression,
    ParenthesizedExpression,
    PrefixUpdateExpression,
    PrintIntrinsicExpression,
    ListIntrinsicExpression,
    ObjectCreationExpression,
    ScriptInclusionExpression,
    PostfixUpdateExpression,
    ScopedPropertyAccessExpression,
    SubscriptExpression,
    TernaryExpression,
    ThrowExpression,
    UnaryExpression,
    UnaryOpExpression,
    Variable,
    YieldExpression
};
use Microsoft\PhpParser\Node\StaticVariableDeclaration;
use Microsoft\PhpParser\Node\ClassConstDeclaration;
use Microsoft\PhpParser\Node\DeclareDirective;
use Microsoft\PhpParser\Node\DelimitedList;
use Microsoft\PhpParser\Node\ElseClauseNode;
use Microsoft\PhpParser\Node\ElseIfClauseNode;
use Microsoft\PhpParser\Node\FinallyClause;
use Microsoft\PhpParser\Node\ForeachKey;
use Microsoft\PhpParser\Node\ForeachValue;
use Microsoft\PhpParser\Node\InterfaceBaseClause;
use Microsoft\PhpParser\Node\InterfaceMembers;
use Microsoft\PhpParser\Node\MatchArm;
use Microsoft\PhpParser\Node\MissingDeclaration;
use Microsoft\PhpParser\Node\MissingMemberDeclaration;
use Microsoft\PhpParser\Node\NamespaceAliasingClause;
use Microsoft\PhpParser\Node\NamespaceUseGroupClause;
use Microsoft\PhpParser\Node\NumericLiteral;
use Microsoft\PhpParser\Node\ParenthesizedIntersectionType;
use Microsoft\PhpParser\MissingToken;
use Microsoft\PhpParser\Node\PropertyDeclaration;
use Microsoft\PhpParser\Node\PropertyElement;
use Microsoft\PhpParser\Node\PropertyHook;
use Microsoft\PhpParser\Node\PropertyHookList;
use Microsoft\PhpParser\Node\ReservedWord;
use Microsoft\PhpParser\Node\StringLiteral;
use Microsoft\PhpParser\Node\MethodDeclaration;
use Microsoft\PhpParser\Node\Parameter;
use Microsoft\PhpParser\Node\QualifiedName;
use Microsoft\PhpParser\Node\RelativeSpecifier;
use Microsoft\PhpParser\Node\SourceFileNode;
use Microsoft\PhpParser\Node\Statement\{
    ClassDeclaration,
    ConstDeclaration,
    CompoundStatementNode,
    FunctionStaticDeclaration,
    GlobalDeclaration,
    BreakOrContinueStatement,
    DeclareStatement,
    DoStatement,
    EchoStatement,
    EmptyStatement,
    EnumDeclaration,
    ExpressionStatement,
    ForeachStatement,
    ForStatement,
    FunctionDeclaration,
    GotoStatement,
    HaltCompilerStatement,
    IfStatementNode,
    InlineHtml,
    InterfaceDeclaration,
    NamespaceDefinition,
    NamespaceUseDeclaration,
    NamedLabelStatement,
    ReturnStatement,
    SwitchStatementNode,
    TraitDeclaration,
    TryStatement,
    UnsetStatement,
    WhileStatement
};
use Microsoft\PhpParser\Node\TraitMembers;
use Microsoft\PhpParser\Node\TraitSelectOrAliasClause;
use Microsoft\PhpParser\Node\TraitUseClause;
use Microsoft\PhpParser\Node\UseVariableName;
use Microsoft\PhpParser\Node\NamespaceUseClause;

class Parser {
    /** @var TokenStreamProviderInterface */
    private $lexer;

    private $currentParseContext;
    public $sourceFile;

    private $nameOrKeywordOrReservedWordTokens;
    private $nameOrReservedWordTokens;
    private $nameOrStaticOrReservedWordTokens;
    private $reservedWordTokens;
    private $keywordTokens;
    private $argumentStartTokensSet;
    // TODO consider validating parameter and return types on post-parse instead so we can be more permissive
    private $parameterTypeDeclarationTokens;
    private $returnTypeDeclarationTokens;

    public function __construct() {
        $this->reservedWordTokens = \array_values(TokenStringMaps::RESERVED_WORDS);
        $this->keywordTokens = \array_values(TokenStringMaps::KEYWORDS);
        $this->argumentStartTokensSet = \array_flip(TokenStringMaps::KEYWORDS);
        unset($this->argumentStartTokensSet[TokenKind::YieldFromKeyword]);
        $this->argumentStartTokensSet[TokenKind::DotDotDotToken] = '...';
        $this->nameOrKeywordOrReservedWordTokens = \array_merge([TokenKind::Name], $this->keywordTokens, $this->reservedWordTokens);
        $this->nameOrReservedWordTokens = \array_merge([TokenKind::Name], $this->reservedWordTokens);
        $this->nameOrStaticOrReservedWordTokens = \array_merge([TokenKind::Name, TokenKind::StaticKeyword], $this->reservedWordTokens);
        $this->parameterTypeDeclarationTokens =
            [TokenKind::ArrayKeyword, TokenKind::CallableKeyword, TokenKind::BoolReservedWord,
            TokenKind::FloatReservedWord, TokenKind::IntReservedWord, TokenKind::StringReservedWord,
            TokenKind::ObjectReservedWord, TokenKind::NullReservedWord, TokenKind::FalseReservedWord,
            TokenKind::TrueReservedWord, TokenKind::IterableReservedWord, TokenKind::MixedReservedWord,
            TokenKind::VoidReservedWord, TokenKind::NeverReservedWord]; // TODO update spec
        $this->returnTypeDeclarationTokens = \array_merge([TokenKind::StaticKeyword], $this->parameterTypeDeclarationTokens);
    }

    /**
     * This method exists so that it can be overridden in subclasses.
     * Any subclass must return a token stream that is equivalent to the contents in $fileContents for this to work properly.
     *
     * Possible reasons for applications to override the lexer:
     *
     * - Imitate token stream of a newer/older PHP version (e.g. T_FN is only available in php 7.4)
     * - Reuse the result of token_get_all to create a Node again.
     * - Reuse the result of token_get_all in a different library.
     */
    protected function makeLexer(string $fileContents): TokenStreamProviderInterface
    {
        return TokenStreamProviderFactory::GetTokenStreamProvider($fileContents);
    }

    /**
     * Generates AST from source file contents. Returns an instance of SourceFileNode, which is always the top-most
     * Node-type of the tree.
     *
     * @param string $fileContents
     * @return SourceFileNode
     */
    public function parseSourceFile(string $fileContents, ?string $uri = null) : SourceFileNode {
        $this->lexer = $this->makeLexer($fileContents);

        $this->reset();

        $sourceFile = new SourceFileNode();
        $this->sourceFile = $sourceFile;
        $sourceFile->fileContents = $fileContents;
        $sourceFile->uri = $uri;
        $sourceFile->statementList = [];
        if ($this->getCurrentToken()->kind !== TokenKind::EndOfFileToken) {
            $inlineHTML = $this->parseInlineHtml($sourceFile);
            $sourceFile->statementList[] = $inlineHTML;
            if ($inlineHTML->echoStatement) {
                $sourceFile->statementList[] = $inlineHTML->echoStatement;
                $inlineHTML->echoStatement->parent = $sourceFile;
                $inlineHTML->echoStatement = null;
            }
        }
        $sourceFile->statementList =
            \array_merge($sourceFile->statementList, $this->parseList($sourceFile, ParseContext::SourceElements));

        $this->sourceFile->endOfFileToken = $this->eat1(TokenKind::EndOfFileToken);
        $this->advanceToken();

        $sourceFile->parent = null;

        return $sourceFile;
    }

    private function reset() {
        $this->advanceToken();

        // Stores the current parse context, which includes the current and enclosing lists.
        $this->currentParseContext = 0;
    }

    /**
     * Parse a list of elements for a given ParseContext until a list terminator associated
     * with that ParseContext is reached. Additionally abort parsing when an element is reached
     * that is invalid in the current context, but valid in an enclosing context. If an element
     * is invalid in both current and enclosing contexts, generate a SkippedToken, and continue.
     * @param Node $parentNode
     * @param int $listParseContext
     * @return array
     */
    private function parseList($parentNode, int $listParseContext) {
        $savedParseContext = $this->currentParseContext;
        $this->currentParseContext |= 1 << $listParseContext;
        $parseListElementFn = $this->getParseListElementFn($listParseContext);

        $nodeArray = [];
        while (!$this->isListTerminator($listParseContext)) {
            if ($this->isValidListElement($listParseContext, $this->getCurrentToken())) {
                $element = $parseListElementFn($parentNode);
                $nodeArray[] = $element;
                if ($element instanceof Node) {
                    $element->parent = $parentNode;
                    if ($element instanceof InlineHtml && $element->echoStatement) {
                        $nodeArray[] = $element->echoStatement;
                        $element->echoStatement->parent = $parentNode;
                        $element->echoStatement = null;
                    }
                }
                continue;
            }

            // Error handling logic:
            // The current parse context does not know how to handle the current token,
            // so check if the enclosing contexts know what to do. If so, we assume that
            // the list has completed parsing, and return to the enclosing context.
            //
            // Example:
            //     class A {
            //         function foo() {
            //            return;
            //      // } <- MissingToken (generated when we try to "eat" the closing brace)
            //
            //         public function bar() {
            //         }
            //     }
            //
            // In the case above, the Method ParseContext doesn't know how to handle "public", but
            // the Class ParseContext will know what to do with it. So we abort the Method ParseContext,
            // and return to the Class ParseContext. This enables us to generate a tree with a single
            // class that contains two method nodes, even though there was an error present in the first method.
            if ($this->isCurrentTokenValidInEnclosingContexts()) {
                break;
            }

            // None of the enclosing contexts know how to handle the token. Generate a
            // SkippedToken, and continue parsing in the current context.
            // Example:
            //     class A {
            //         function foo() {
            //            return;
            //            & // <- SkippedToken
            //         }
            //     }
            $token = new SkippedToken($this->getCurrentToken());
            $nodeArray[] = $token;
            $this->advanceToken();
        }

        $this->currentParseContext = $savedParseContext;

        return $nodeArray;
    }

    private function isListTerminator(int $parseContext) {
        $tokenKind = $this->getCurrentToken()->kind;
        if ($tokenKind === TokenKind::EndOfFileToken) {
            // Being at the end of the file ends all lists.
            return true;
        }

        switch ($parseContext) {
            case ParseContext::SourceElements:
                return false;

            case ParseContext::InterfaceMembers:
            case ParseContext::ClassMembers:
            case ParseContext::BlockStatements:
            case ParseContext::TraitMembers:
            case ParseContext::EnumMembers:
                return $tokenKind === TokenKind::CloseBraceToken;
            case ParseContext::SwitchStatementElements:
                return $tokenKind === TokenKind::CloseBraceToken || $tokenKind === TokenKind::EndSwitchKeyword;
            case ParseContext::IfClause2Elements:
                return
                    $tokenKind === TokenKind::ElseIfKeyword ||
                    $tokenKind === TokenKind::ElseKeyword ||
                    $tokenKind === TokenKind::EndIfKeyword;

            case ParseContext::WhileStatementElements:
                return $tokenKind === TokenKind::EndWhileKeyword;

            case ParseContext::CaseStatementElements:
                return
                    $tokenKind === TokenKind::CaseKeyword ||
                    $tokenKind === TokenKind::DefaultKeyword;

            case ParseContext::ForStatementElements:
                return
                    $tokenKind === TokenKind::EndForKeyword;

            case ParseContext::ForeachStatementElements:
                return $tokenKind === TokenKind::EndForEachKeyword;

            case ParseContext::DeclareStatementElements:
                return $tokenKind === TokenKind::EndDeclareKeyword;
        }
        // TODO warn about unhandled parse context
        return false;
    }

    private function isValidListElement($context, Token $token) {

        // TODO
        switch ($context) {
            case ParseContext::SourceElements:
            case ParseContext::BlockStatements:
            case ParseContext::IfClause2Elements:
            case ParseContext::CaseStatementElements:
            case ParseContext::WhileStatementElements:
            case ParseContext::ForStatementElements:
            case ParseContext::ForeachStatementElements:
            case ParseContext::DeclareStatementElements:
                return $this->isStatementStart($token);

            case ParseContext::ClassMembers:
                return $this->isClassMemberDeclarationStart($token);

            case ParseContext::TraitMembers:
                return $this->isTraitMemberDeclarationStart($token);

            case ParseContext::EnumMembers:
                return $this->isEnumMemberDeclarationStart($token);

            case ParseContext::InterfaceMembers:
                return $this->isInterfaceMemberDeclarationStart($token);

            case ParseContext::SwitchStatementElements:
                return
                    $token->kind === TokenKind::CaseKeyword ||
                    $token->kind === TokenKind::DefaultKeyword;
        }
        return false;
    }

    private function getParseListElementFn($context) {
        switch ($context) {
            case ParseContext::SourceElements:
            case ParseContext::BlockStatements:
            case ParseContext::IfClause2Elements:
            case ParseContext::CaseStatementElements:
            case ParseContext::WhileStatementElements:
            case ParseContext::ForStatementElements:
            case ParseContext::ForeachStatementElements:
            case ParseContext::DeclareStatementElements:
                return $this->parseStatementFn();
            case ParseContext::ClassMembers:
                return $this->parseClassElementFn();

            case ParseContext::TraitMembers:
                return $this->parseTraitElementFn();

            case ParseContext::InterfaceMembers:
                return $this->parseInterfaceElementFn();

            case ParseContext::EnumMembers:
                return $this->parseEnumElementFn();

            case ParseContext::SwitchStatementElements:
                return $this->parseCaseOrDefaultStatement();
            default:
                throw new \Exception("Unrecognized parse context");
        }
    }

    /**
     * Aborts parsing list when one of the parent contexts understands something
     * @return bool
     */
    private function isCurrentTokenValidInEnclosingContexts() {
        for ($contextKind = 0; $contextKind < ParseContext::Count; $contextKind++) {
            if ($this->isInParseContext($contextKind)) {
                if ($this->isValidListElement($contextKind, $this->getCurrentToken()) || $this->isListTerminator($contextKind)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function isInParseContext($contextToCheck) {
        return ($this->currentParseContext & (1 << $contextToCheck));
    }

    /**
     * Retrieve the current token, and check that it's of the expected TokenKind.
     * If so, advance and return the token. Otherwise return a MissingToken for
     * the expected token.
     * @param int|int[] ...$kinds
     * @return Token
     */
    private function eat(...$kinds) {
        $token = $this->token;
        if (\is_array($kinds[0])) {
            $kinds = $kinds[0];
        }
        foreach ($kinds as $kind) {
            if ($token->kind === $kind) {
                $this->token = $this->lexer->scanNextToken();
                return $token;
            }
        }
        // TODO include optional grouping for token kinds
        return new MissingToken($kinds[0], $token->fullStart);
    }

    /**
     * Retrieve the current token, and check that it's of the kind $kind.
     * If so, advance and return the token. Otherwise return a MissingToken for
     * the expected token.
     *
     * This is faster than calling eat() if there is a single token.
     *
     * @param int $kind
     * @return Token
     */
    private function eat1($kind) {
        $token = $this->token;
        if ($token->kind === $kind) {
            $this->token = $this->lexer->scanNextToken();
            return $token;
        }
        // TODO include optional grouping for token kinds
        return new MissingToken($kind, $token->fullStart);
    }

    /**
     * @param int|int[] ...$kinds (Can provide a single value with a list of kinds, or multiple kinds)
     * @return Token|null
     */
    private function eatOptional(...$kinds) {
        $token = $this->token;
        if (\is_array($kinds[0])) {
            $kinds = $kinds[0];
        }
        if (\in_array($token->kind, $kinds)) {
            $this->token = $this->lexer->scanNextToken();
            return $token;
        }
        return null;
    }

    /**
     * @param int $kind a single kind
     * @return Token|null
     */
    private function eatOptional1($kind) {
        $token = $this->token;
        if ($token->kind === $kind) {
            $this->token = $this->lexer->scanNextToken();
            return $token;
        }
        return null;
    }

    private $token;

    private function getCurrentToken() : Token {
        return $this->token;
    }

    private function advanceToken() {
        $this->token = $this->lexer->scanNextToken();
    }

    private function parseStatement($parentNode) {
        return ($this->parseStatementFn())($parentNode);
    }

    private function parseStatementFn() {
        return function ($parentNode) {
            $token = $this->getCurrentToken();
            switch ($token->kind) {
                // compound-statement
                case TokenKind::OpenBraceToken:
                    return $this->parseCompoundStatement($parentNode);

                // labeled-statement
                case TokenKind::Name:
                    if ($this->lookahead(TokenKind::ColonToken)) {
                        return $this->parseNamedLabelStatement($parentNode);
                    }
                    break;

                // selection-statement
                case TokenKind::IfKeyword:
                    return $this->parseIfStatement($parentNode);
                case TokenKind::SwitchKeyword:
                    return $this->parseSwitchStatement($parentNode);

                // iteration-statement
                case TokenKind::WhileKeyword: // while-statement
                    return $this->parseWhileStatement($parentNode);
                case TokenKind::DoKeyword: // do-statement
                    return $this->parseDoStatement($parentNode);
                case TokenKind::ForKeyword: // for-statement
                    return $this->parseForStatement($parentNode);
                case TokenKind::ForeachKeyword: // foreach-statement
                    return $this->parseForeachStatement($parentNode);

                // jump-statement
                case TokenKind::GotoKeyword: // goto-statement
                    return $this->parseGotoStatement($parentNode);
                case TokenKind::ContinueKeyword: // continue-statement
                case TokenKind::BreakKeyword: // break-statement
                    return $this->parseBreakOrContinueStatement($parentNode);
                case TokenKind::ReturnKeyword: // return-statement
                    return $this->parseReturnStatement($parentNode);

                // try-statement
                case TokenKind::TryKeyword:
                    return $this->parseTryStatement($parentNode);

                // declare-statement
                case TokenKind::DeclareKeyword:
                    return $this->parseDeclareStatement($parentNode);

                // attribute before statement or anonymous function
                case TokenKind::AttributeToken:
                    return $this->parseAttributeStatement($parentNode);

                // function-declaration
                case TokenKind::FunctionKeyword:
                    // Check that this is not an anonymous-function-creation-expression
                    if ($this->lookahead($this->nameOrKeywordOrReservedWordTokens) || $this->lookahead(TokenKind::AmpersandToken, $this->nameOrKeywordOrReservedWordTokens)) {
                        return $this->parseFunctionDeclaration($parentNode);
                    }
                    break;

                // class-declaration
                case TokenKind::FinalKeyword:
                case TokenKind::AbstractKeyword:
                case TokenKind::ReadonlyKeyword:
                    // fallthrough
                case TokenKind::ClassKeyword:
                    return $this->parseClassDeclaration($parentNode);

                // interface-declaration
                case TokenKind::InterfaceKeyword:
                    return $this->parseInterfaceDeclaration($parentNode);

                // namespace-definition
                case TokenKind::NamespaceKeyword:
                    if (!$this->lookahead(TokenKind::BackslashToken)) {
                        // TODO add error handling for the case where a namespace definition does not occur in the outer-most scope
                        return $this->parseNamespaceDefinition($parentNode);
                    }
                    break;

                // namespace-use-declaration
                case TokenKind::UseKeyword:
                    return $this->parseNamespaceUseDeclaration($parentNode);

                case TokenKind::SemicolonToken:
                    return $this->parseEmptyStatement($parentNode);

                case TokenKind::EchoKeyword:
                    return $this->parseEchoStatement($parentNode);

                // trait-declaration
                case TokenKind::TraitKeyword:
                    return $this->parseTraitDeclaration($parentNode);

                case TokenKind::EnumKeyword:
                    return $this->parseEnumDeclaration($parentNode);

                // global-declaration
                case TokenKind::GlobalKeyword:
                    return $this->parseGlobalDeclaration($parentNode);

                // const-declaration
                case TokenKind::ConstKeyword:
                    return $this->parseConstDeclaration($parentNode);

                // function-static-declaration
                case TokenKind::StaticKeyword:
                    // Check that this is not an anonymous-function-creation-expression
                    if (!$this->lookahead([TokenKind::FunctionKeyword, TokenKind::FnKeyword, TokenKind::OpenParenToken, TokenKind::ColonColonToken])) {
                        return $this->parseFunctionStaticDeclaration($parentNode);
                    }
                    break;

                case TokenKind::ScriptSectionEndTag:
                    return $this->parseInlineHtml($parentNode);

                case TokenKind::UnsetKeyword:
                    return $this->parseUnsetStatement($parentNode);

                case TokenKind::HaltCompilerKeyword:
                    if ($parentNode instanceof SourceFileNode) {
                        return $this->parseHaltCompilerStatement($parentNode);
                    }
                    // __halt_compiler is a fatal compile error anywhere other than the top level.
                    // It won't be seen elsewhere in other programs - warn about the token being unexpected.
                    $this->advanceToken();
                    return new SkippedToken($token);
            }

            $expressionStatement = new ExpressionStatement();
            $expressionStatement->parent = $parentNode;
            $expressionStatement->expression = $this->parseExpression($expressionStatement, true);
            $expressionStatement->semicolon = $this->eatSemicolonOrAbortStatement();
            return $expressionStatement;
        };
    }

    private function parseClassElementFn() {
        return function ($parentNode) {
            $modifiers = $this->parseModifiers();

            $token = $this->getCurrentToken();
            switch ($token->kind) {
                case TokenKind::ConstKeyword:
                    return $this->parseClassConstDeclaration($parentNode, $modifiers);

                case TokenKind::FunctionKeyword:
                    return $this->parseMethodDeclaration($parentNode, $modifiers);

                case TokenKind::QuestionToken:
                    return $this->parseRemainingPropertyDeclarationOrMissingMemberDeclaration(
                        $parentNode,
                        $modifiers,
                        $this->eat1(TokenKind::QuestionToken)
                    );
                case TokenKind::VariableName:
                    return $this->parsePropertyDeclaration($parentNode, $modifiers);

                case TokenKind::UseKeyword:
                    return $this->parseTraitUseClause($parentNode);

                case TokenKind::AttributeToken:
                    return $this->parseAttributeStatement($parentNode);

                default:
                    return $this->parseRemainingPropertyDeclarationOrMissingMemberDeclaration($parentNode, $modifiers);
            }
        };
    }

    /** @return Token[] */
    private function parseClassModifiers(): array {
        $modifiers = [];
        while ($token = $this->eatOptional(TokenKind::AbstractKeyword, TokenKind::FinalKeyword, TokenKind::ReadonlyKeyword)) {
            $modifiers[] = $token;
        }
        return $modifiers;
    }

    private function parseClassDeclaration($parentNode) : Node {
        $classNode = new ClassDeclaration(); // TODO verify not nested
        $classNode->parent = $parentNode;
        $classNode->abstractOrFinalModifier = $this->eatOptional(TokenKind::AbstractKeyword, TokenKind::FinalKeyword, TokenKind::ReadonlyKeyword);
        $classNode->modifiers = $this->parseClassModifiers();
        $classNode->classKeyword = $this->eat1(TokenKind::ClassKeyword);
        $classNode->name = $this->eat($this->nameOrReservedWordTokens); // TODO should be any
        $classNode->name->kind = TokenKind::Name;
        $classNode->classBaseClause = $this->parseClassBaseClause($classNode);
        $classNode->classInterfaceClause = $this->parseClassInterfaceClause($classNode);
        $classNode->classMembers = $this->parseClassMembers($classNode);
        return $classNode;
    }

    private function parseClassMembers($parentNode) : ClassMembersNode {
        $classMembers = new ClassMembersNode();
        $classMembers->openBrace = $this->eat1(TokenKind::OpenBraceToken);
        $classMembers->classMemberDeclarations = $this->parseList($classMembers, ParseContext::ClassMembers);
        $classMembers->closeBrace = $this->eat1(TokenKind::CloseBraceToken);
        $classMembers->parent = $parentNode;
        return $classMembers;
    }

    private function parseFunctionDeclaration($parentNode) {
        $functionNode = new FunctionDeclaration();
        $this->parseFunctionType($functionNode);
        $functionNode->parent = $parentNode;
        return $functionNode;
    }

    /**
     * @return Node
     */
    private function parseAttributeExpression($parentNode) {
        $attributeGroups = $this->parseAttributeGroups(null);
        // Warn about invalid syntax for attributed declarations
        // Lookahead for static, function, or fn for the only type of expressions that can have attributes (anonymous functions)
        if (in_array($this->token->kind, [TokenKind::FunctionKeyword, TokenKind::FnKeyword], true) ||
            $this->token->kind === TokenKind::StaticKeyword && $this->lookahead([TokenKind::FunctionKeyword, TokenKind::FnKeyword])) {
            $expression = $this->parsePrimaryExpression($parentNode);
        } else {
            // Create a MissingToken so that diagnostics indicate that the attributes did not match up with an expression/declaration.
            $expression = new MissingDeclaration();
            $expression->parent = $parentNode;
            $expression->declaration = new MissingToken(TokenKind::Expression, $this->token->fullStart);
        }
        if ($expression instanceof AnonymousFunctionCreationExpression ||
            $expression instanceof ArrowFunctionCreationExpression ||
            $expression instanceof MissingDeclaration) {
            $expression->attributes = $attributeGroups;
            foreach ($attributeGroups as $attributeGroup) {
                $attributeGroup->parent = $expression;
            }
        }
        return $expression;
    }

    /**
     * Precondition: The next token is an AttributeToken
     * @return Node
     */
    private function parseAttributeStatement($parentNode) {
        $attributeGroups = $this->parseAttributeGroups(null);
        if ($parentNode instanceof ClassMembersNode) {
            // Create a class element or a MissingMemberDeclaration
            $statement = $this->parseClassElementFn()($parentNode);
        } elseif ($parentNode instanceof TraitMembers) {
            // Create a trait element or a MissingMemberDeclaration
            $statement = $this->parseTraitElementFn()($parentNode);
        } elseif ($parentNode instanceof EnumMembers) {
            // Create a enum element or a MissingMemberDeclaration
            $statement = $this->parseEnumElementFn()($parentNode);
        } elseif ($parentNode instanceof InterfaceMembers) {
            // Create an interface element or a MissingMemberDeclaration
            $statement = $this->parseInterfaceElementFn()($parentNode);
        } else {
            // Classlikes, anonymous functions, global functions, and arrow functions can have attributes. Global constants cannot.
            if (in_array($this->token->kind, [TokenKind::ReadonlyKeyword, TokenKind::ClassKeyword, TokenKind::TraitKeyword, TokenKind::InterfaceKeyword, TokenKind::AbstractKeyword, TokenKind::FinalKeyword, TokenKind::FunctionKeyword, TokenKind::FnKeyword, TokenKind::EnumKeyword], true) ||
                $this->token->kind === TokenKind::StaticKeyword && $this->lookahead([TokenKind::FunctionKeyword, TokenKind::FnKeyword])) {
                $statement = $this->parseStatement($parentNode);
            } else {
                // Create a MissingToken so that diagnostics indicate that the attributes did not match up with an expression/declaration.
                $statement = new MissingDeclaration();
                $statement->parent = $parentNode;
                $statement->declaration = new MissingToken(TokenKind::Expression, $this->token->fullStart);
            }
        }

        if ($statement instanceof FunctionLike ||
            $statement instanceof ClassDeclaration ||
            $statement instanceof TraitDeclaration ||
            $statement instanceof EnumDeclaration ||
            $statement instanceof EnumCaseDeclaration ||
            $statement instanceof InterfaceDeclaration ||
            $statement instanceof ClassConstDeclaration ||
            $statement instanceof PropertyDeclaration ||
            $statement instanceof MissingDeclaration ||
            $statement instanceof MissingMemberDeclaration) {

            $statement->attributes = $attributeGroups;
            foreach ($attributeGroups as $attributeGroup) {
                $attributeGroup->parent = $statement;
            }
        }
        return $statement;
    }

    /**
     * @param Node|null $parentNode
     * @return AttributeGroup[]
     */
    private function parseAttributeGroups($parentNode): array
    {
        $attributeGroups = [];
        while ($attributeToken = $this->eatOptional1(TokenKind::AttributeToken)) {
            $attributeGroup = new AttributeGroup();
            $attributeGroup->startToken = $attributeToken;
            $attributeGroup->attributes = $this->parseAttributeElementList($attributeGroup)
                ?: (new MissingToken(TokenKind::Name, $this->token->fullStart));
            $attributeGroup->endToken = $this->eat1(TokenKind::CloseBracketToken);
            $attributeGroup->parent = $parentNode;
            $attributeGroups[] = $attributeGroup;
        }
        return $attributeGroups;
    }

    /**
     * @return DelimitedList\AttributeElementList|null
     */
    private function parseAttributeElementList(AttributeGroup $parentNode) {
        return $this->parseDelimitedList(
            DelimitedList\AttributeElementList::class,
            TokenKind::CommaToken,
            $this->isQualifiedNameStartFn(),
            $this->parseAttributeFn(),
            $parentNode,
            false);
    }

    private function parseAttributeFn()
    {
        return function ($parentNode): Attribute {
            $attribute = new Attribute();
            $attribute->parent = $parentNode;
            $attribute->name = $this->parseQualifiedName($attribute);
            $attribute->openParen = $this->eatOptional1(TokenKind::OpenParenToken);
            if ($attribute->openParen) {
                $attribute->argumentExpressionList = $this->parseArgumentExpressionList($attribute);
                $attribute->closeParen = $this->eat1(TokenKind::CloseParenToken);
            }
            return $attribute;
        };
    }

    private function parseMethodDeclaration($parentNode, $modifiers) {
        $methodDeclaration = new MethodDeclaration();
        $methodDeclaration->modifiers = $modifiers;
        $this->parseFunctionType($methodDeclaration, true);
        $methodDeclaration->parent = $parentNode;
        return $methodDeclaration;
    }

    private function parseParameterFn() {
        return function ($parentNode) {
            $parameter = new Parameter();
            $parameter->parent = $parentNode;
            if ($this->token->kind === TokenKind::AttributeToken) {
                $parameter->attributes = $this->parseAttributeGroups($parameter);
            }
            // Note that parameter modifiers are allowed to be repeated by the parser in php 8.1 (it is a compiler error)
            //
            // TODO: Remove the visibilityToken in a future backwards incompatible release
            $parameter->visibilityToken = $this->eatOptional([TokenKind::PublicKeyword, TokenKind::ProtectedKeyword, TokenKind::PrivateKeyword]);
            $parameter->modifiers = $this->parseParameterModifiers() ?: null;

            $parameter->questionToken = $this->eatOptional1(TokenKind::QuestionToken);
            $parameter->typeDeclarationList = $this->tryParseParameterTypeDeclarationList($parameter);
            if ($parameter->typeDeclarationList) {
                $children = $parameter->typeDeclarationList->children;
                if (end($children) instanceof MissingToken && ($children[\count($children) - 2]->kind ?? null) === TokenKind::AmpersandToken) {
                    array_pop($parameter->typeDeclarationList->children);
                    $parameter->byRefToken = array_pop($parameter->typeDeclarationList->children);
                }
            } elseif ($parameter->questionToken) {
                // TODO ParameterType?
                $parameter->typeDeclarationList = new MissingToken(TokenKind::PropertyType, $this->token->fullStart);
            }
            if (!$parameter->byRefToken) {
                $parameter->byRefToken = $this->eatOptional1(TokenKind::AmpersandToken);
            }
            // TODO add post-parse rule that prevents assignment
            // TODO add post-parse rule that requires only last parameter be variadic
            $parameter->dotDotDotToken = $this->eatOptional1(TokenKind::DotDotDotToken);
            $parameter->variableName = $this->eat1(TokenKind::VariableName);
            $parameter->equalsToken = $this->eatOptional1(TokenKind::EqualsToken);
            if ($parameter->equalsToken !== null) {
                // TODO add post-parse rule that checks for invalid assignments
                $parameter->default = $this->parseExpression($parameter);
            }
            return $parameter;
        };
    }

    /**
     * @param ArrowFunctionCreationExpression|AnonymousFunctionCreationExpression|FunctionDeclaration|MethodDeclaration $parentNode a node with FunctionReturnType trait
     */
    private function parseAndSetReturnTypeDeclarationList($parentNode) {
        $returnTypeList = $this->parseReturnTypeDeclarationList($parentNode);
        if (!$returnTypeList)  {
            $parentNode->returnTypeList = new MissingToken(TokenKind::ReturnType, $this->token->fullStart);
            return;
        }
        $parentNode->returnTypeList = $returnTypeList;
    }

    const TYPE_DELIMITER_TOKENS = [
        TokenKind::BarToken,
        TokenKind::AmpersandToken,
    ];

    /**
     * Attempt to parse the return type after the `:` and optional `?` token.
     *
     * TODO: Consider changing the return type to a new class TypeList in a future major release?
     * ParenthesizedIntersectionType is not a qualified name.
     * @return DelimitedList\QualifiedNameList|null
     */
    private function parseReturnTypeDeclarationList($parentNode) {
        return $this->parseUnionTypeDeclarationList(
            $parentNode,
            function ($token): bool {
                return \in_array($token->kind, $this->returnTypeDeclarationTokens, true) ||
                    $this->isQualifiedNameStart($token);
            },
            function ($parentNode) {
                return $this->parseReturnTypeDeclaration($parentNode);
            },
            TokenKind::ReturnType
        );
    }

    private function parseReturnTypeDeclaration($parentNode) {
        return $this->eatOptional($this->returnTypeDeclarationTokens)
            ?? $this->parseQualifiedName($parentNode);
    }

    private function tryParseParameterTypeDeclaration($parentNode) {
        $parameterTypeDeclaration =
            $this->eatOptional($this->parameterTypeDeclarationTokens) ?? $this->parseQualifiedName($parentNode);
        return $parameterTypeDeclaration;
    }

    /**
     * Parse a union type such as A, A|B, A&B, A|(B&C), rejecting invalid syntax combinations.
     *
     * @param Node $parentNode
     * @param Closure(Token):bool $isTypeStart
     * @param Closure(Node):(Node|Token|null) $parseType
     * @param int $expectedTypeKind expected kind for token type
     * @return DelimitedList\QualifiedNameList|null
     */
    private function parseUnionTypeDeclarationList($parentNode, Closure $isTypeStart, Closure $parseType, int $expectedTypeKind) {
        $result = new DelimitedList\QualifiedNameList();
        $token = $this->getCurrentToken();
        $delimiter = self::TYPE_DELIMITER_TOKENS;
        do {
            if ($token->kind === TokenKind::OpenParenToken || $isTypeStart($token)) {
                // Forbid mixing A&(B&C) if '&' was already seen
                $openParen = in_array(TokenKind::BarToken, $delimiter, true)
                    ? $this->eatOptional(TokenKind::OpenParenToken)
                    : null;
                if ($openParen) {
                    $element = $this->parseParenthesizedIntersectionType($result, $openParen, $isTypeStart, $parseType);
                    // Forbid mixing (A&B)&C by forbidding `&` separator after a parenthesized intersection type.
                    $delimiter = [TokenKind::BarToken];
                } else {
                    $element = $parseType($result);
                }
                $result->addElement($element);
            } else {
                break;
            }

            $delimiterToken = $this->eatOptional($delimiter);
            if ($delimiterToken !== null) {
                $result->addElement($delimiterToken);
                $delimiter = [$delimiterToken->kind];
            }
            $token = $this->getCurrentToken();
        } while ($delimiterToken !== null);

        $result->parent = $parentNode;
        if ($result->children === null) {
            return null;
        }

        if (in_array(end($result->children)->kind ?? null, $delimiter, true)) {
            // Add a MissingToken so that this will warn about `function () : T| {}`
            $result->children[] = new MissingToken($expectedTypeKind, $this->token->fullStart);
        } elseif (count($result->children) === 1 && $result->children[0] instanceof ParenthesizedIntersectionType) {
            // dnf types with parenthesized intersection types are a union type of at least 2 types.
            $result->children[] = new MissingToken(TokenKind::BarToken, $this->token->fullStart);
        }
        return $result;
    }

    /**
     * @param Node $parentNode
     * @param Token $openParen
     * @param Closure(Token):bool $isTypeStart
     * @param Closure(Node):(Node|Token|null) $parseType
     */
    private function parseParenthesizedIntersectionType($parentNode, Token $openParen, Closure $isTypeStart, Closure $parseType): ParenthesizedIntersectionType {
        $node = new ParenthesizedIntersectionType();
        $node->parent = $parentNode;
        $node->openParen = $openParen;
        $node->children = $this->parseDelimitedList(
            DelimitedList\QualifiedNameList::class,
            TokenKind::AmpersandToken,
            $isTypeStart,
            $parseType,
            $node,
            true);
        if ($node->children) {
            // https://wiki.php.net/rfc/dnf_types
            if ((end($node->children->children)->kind ?? null) === TokenKind::OpenParenToken) {
                // Add a MissingToken so that this will Warn about `function (A|(B&) $x) {}`
                $node->children->children[] = new MissingToken(TokenKind::Name, $this->token->fullStart);
            } elseif (count($node->children->children) === 1) {
                // Must have at least 2 parts for A|(B&C)
                $node->children->children[] = new MissingToken(TokenKind::AmpersandToken, $this->token->fullStart);
            }
        } else {
            // Having less than 2 types (no types) in A|() is a parse error
            $node->children = new MissingToken(TokenKind::Name, $this->token->fullStart);
        }
        $node->closeParen = $this->eat(TokenKind::CloseParenToken);
        return $node;
    }

    /**
     * @param Node|null $parentNode
     * @return DelimitedList\QualifiedNameList|null
     */
    private function tryParseParameterTypeDeclarationList($parentNode) {
        return $this->parseUnionTypeDeclarationList(
            $parentNode,
            function ($token) {
                return \in_array($token->kind, $this->parameterTypeDeclarationTokens, true) ||
                    $this->isQualifiedNameStart($token);
            },
            function ($parentNode) {
                return $this->tryParseParameterTypeDeclaration($parentNode);
            },
            TokenKind::Name
        );
    }

    private function parseCompoundStatement($parentNode) {
        $compoundStatement = new CompoundStatementNode();
        $compoundStatement->openBrace = $this->eat1(TokenKind::OpenBraceToken);
        $compoundStatement->statements =  $this->parseList($compoundStatement, ParseContext::BlockStatements);
        $compoundStatement->closeBrace = $this->eat1(TokenKind::CloseBraceToken);
        $compoundStatement->parent = $parentNode;
        return $compoundStatement;
    }

    private function isClassMemberDeclarationStart(Token $token) {
        switch ($token->kind) {
            // const-modifier
            case TokenKind::ConstKeyword:

            // visibility-modifier
            case TokenKind::PublicKeyword:
            case TokenKind::ProtectedKeyword:
            case TokenKind::PrivateKeyword:

            // static-modifier
            case TokenKind::StaticKeyword:

            // readonly-modifier
            case TokenKind::ReadonlyKeyword:

            // class-modifier
            case TokenKind::AbstractKeyword:
            case TokenKind::FinalKeyword:

            case TokenKind::VarKeyword:

            case TokenKind::FunctionKeyword:

            case TokenKind::UseKeyword:

            // attributes
            case TokenKind::AttributeToken:
                return true;

        }

        return false;
    }

    private function isStatementStart(Token $token) {
        // https://github.com/php/php-langspec/blob/master/spec/19-grammar.md#statements
        switch ($token->kind) {
            // Compound Statements
            case TokenKind::OpenBraceToken:

            // Labeled Statements
            case TokenKind::Name:
//            case TokenKind::CaseKeyword: // TODO update spec
//            case TokenKind::DefaultKeyword:

            // Expression Statements
            case TokenKind::SemicolonToken:
            case TokenKind::IfKeyword:
            case TokenKind::SwitchKeyword:

            // Iteration Statements
            case TokenKind::WhileKeyword:
            case TokenKind::DoKeyword:
            case TokenKind::ForKeyword:
            case TokenKind::ForeachKeyword:

            // Jump Statements
            case TokenKind::GotoKeyword:
            case TokenKind::ContinueKeyword:
            case TokenKind::BreakKeyword:
            case TokenKind::ReturnKeyword:
            case TokenKind::ThrowKeyword:

            // The try Statement
            case TokenKind::TryKeyword:

            // The declare Statement
            case TokenKind::DeclareKeyword:

            // const-declaration
            case TokenKind::ConstKeyword:

            // function-definition
            case TokenKind::FunctionKeyword:

            // class-declaration
            case TokenKind::ClassKeyword:
            case TokenKind::AbstractKeyword:
            case TokenKind::FinalKeyword:
            case TokenKind::ReadonlyKeyword:

            // interface-declaration
            case TokenKind::InterfaceKeyword:

            // trait-declaration
            case TokenKind::TraitKeyword:

            // enum-declaration
            case TokenKind::EnumKeyword:

            // namespace-definition
            case TokenKind::NamespaceKeyword:

            // namespace-use-declaration
            case TokenKind::UseKeyword:

            // global-declaration
            case TokenKind::GlobalKeyword:

            // function-static-declaration
            case TokenKind::StaticKeyword:

            case TokenKind::ScriptSectionEndTag:

            // attributes
            case TokenKind::AttributeToken:

            // __halt_compiler
            case TokenKind::HaltCompilerKeyword:
                return true;

            default:
                return $this->isExpressionStart($token);
        }
    }

    private function isExpressionStart($token) {
        return ($this->isExpressionStartFn())($token);
    }

    private function isExpressionStartFn() {
        return function ($token) {
            switch ($token->kind) {
                // Script Inclusion Expression
                case TokenKind::RequireKeyword:
                case TokenKind::RequireOnceKeyword:
                case TokenKind::IncludeKeyword:
                case TokenKind::IncludeOnceKeyword:

                // yield-expression
                case TokenKind::YieldKeyword:
                case TokenKind::YieldFromKeyword:

                // object-creation-expression
                case TokenKind::NewKeyword:
                case TokenKind::CloneKeyword:
                    return true;

                // unary-op-expression
                case TokenKind::PlusToken:
                case TokenKind::MinusToken:
                case TokenKind::ExclamationToken:
                case TokenKind::TildeToken:

                // error-control-expression
                case TokenKind::AtSymbolToken:

                // prefix-increment-expression
                case TokenKind::PlusPlusToken:
                // prefix-decrement-expression
                case TokenKind::MinusMinusToken:
                    return true;

                // variable-name
                case TokenKind::VariableName:
                case TokenKind::DollarToken:
                    return true;

                // qualified-name
                case TokenKind::Name:
                case TokenKind::BackslashToken:
                    return true;
                case TokenKind::NamespaceKeyword:
                    // TODO currently only supports qualified-names, but eventually parse namespace declarations
                    return $this->isNamespaceKeywordStartOfExpression($token);

                // literal
                case TokenKind::DecimalLiteralToken: // TODO merge dec, oct, hex, bin, float -> NumericLiteral
                case TokenKind::OctalLiteralToken:
                case TokenKind::HexadecimalLiteralToken:
                case TokenKind::BinaryLiteralToken:
                case TokenKind::FloatingLiteralToken:
                case TokenKind::InvalidOctalLiteralToken:
                case TokenKind::InvalidHexadecimalLiteral:
                case TokenKind::InvalidBinaryLiteral:
                case TokenKind::IntegerLiteralToken:

                case TokenKind::StringLiteralToken:

                case TokenKind::SingleQuoteToken:
                case TokenKind::DoubleQuoteToken:
                case TokenKind::HeredocStart:
                case TokenKind::BacktickToken:

                // array-creation-expression
                case TokenKind::ArrayKeyword:
                case TokenKind::OpenBracketToken:

                // intrinsic-construct
                case TokenKind::EchoKeyword:
                case TokenKind::ListKeyword:
                case TokenKind::UnsetKeyword:

                // intrinsic-operator
                case TokenKind::EmptyKeyword:
                case TokenKind::EvalKeyword:
                case TokenKind::ExitKeyword:
                case TokenKind::DieKeyword:
                case TokenKind::IsSetKeyword:
                case TokenKind::PrintKeyword:

                // ( expression )
                case TokenKind::OpenParenToken:
                case TokenKind::ArrayCastToken:
                case TokenKind::BoolCastToken:
                case TokenKind::DoubleCastToken:
                case TokenKind::IntCastToken:
                case TokenKind::ObjectCastToken:
                case TokenKind::StringCastToken:
                case TokenKind::UnsetCastToken:
                case TokenKind::MatchKeyword:

                // anonymous-function-creation-expression
                case TokenKind::StaticKeyword:
                case TokenKind::FunctionKeyword:
                case TokenKind::FnKeyword:
                case TokenKind::AttributeToken:
                    return true;
            }
            return \in_array($token->kind, $this->reservedWordTokens, true);
        };
    }

    /**
     * Handles the fact that $token may either be getCurrentToken or the token immediately before it in isExpressionStartFn().
     * An expression can be namespace\CONST, namespace\fn(), or namespace\ClassName
     */
    private function isNamespaceKeywordStartOfExpression(Token $token) : bool {
        $nextToken = $this->getCurrentToken();
        if ($nextToken->kind === TokenKind::BackslashToken) {
            return true;
        }
        if ($nextToken !== $token) {
            return false;
        }
        $oldPosition = $this->lexer->getCurrentPosition();
        $nextToken = $this->lexer->scanNextToken();
        $this->lexer->setCurrentPosition($oldPosition);
        return $nextToken->kind === TokenKind::BackslashToken;
    }

    /**
     * @param Node $parentNode
     * @return Token|MissingToken|Node
     */
    private function parsePrimaryExpression($parentNode) {
        $token = $this->getCurrentToken();
        switch ($token->kind) {
            // variable-name
            case TokenKind::VariableName: // TODO special case $this
            case TokenKind::DollarToken:
                return $this->parseSimpleVariable($parentNode);

            // qualified-name
            case TokenKind::Name: // TODO Qualified name
            case TokenKind::BackslashToken:
            case TokenKind::NamespaceKeyword:
                return $this->parseQualifiedName($parentNode);

            case TokenKind::DecimalLiteralToken: // TODO merge dec, oct, hex, bin, float -> NumericLiteral
            case TokenKind::OctalLiteralToken:
            case TokenKind::HexadecimalLiteralToken:
            case TokenKind::BinaryLiteralToken:
            case TokenKind::FloatingLiteralToken:
            case TokenKind::InvalidOctalLiteralToken:
            case TokenKind::InvalidHexadecimalLiteral:
            case TokenKind::InvalidBinaryLiteral:
            case TokenKind::IntegerLiteralToken:
                return $this->parseNumericLiteralExpression($parentNode);

            case TokenKind::StringLiteralToken:
                return $this->parseStringLiteralExpression($parentNode);

            case TokenKind::DoubleQuoteToken:
            case TokenKind::SingleQuoteToken:
            case TokenKind::HeredocStart:
            case TokenKind::BacktickToken:
                return $this->parseStringLiteralExpression2($parentNode);

            // TODO constant-expression

            // array-creation-expression
            case TokenKind::ArrayKeyword:
            case TokenKind::OpenBracketToken:
                return $this->parseArrayCreationExpression($parentNode);

            // intrinsic-construct
            case TokenKind::ListKeyword:
                return $this->parseListIntrinsicExpression($parentNode);

            // intrinsic-operator
            case TokenKind::EmptyKeyword:
                return $this->parseEmptyIntrinsicExpression($parentNode);
            case TokenKind::EvalKeyword:
                return $this->parseEvalIntrinsicExpression($parentNode);

            case TokenKind::ExitKeyword:
            case TokenKind::DieKeyword:
                return $this->parseExitIntrinsicExpression($parentNode);

            case TokenKind::IsSetKeyword:
                return $this->parseIssetIntrinsicExpression($parentNode);

            case TokenKind::PrintKeyword:
                return $this->parsePrintIntrinsicExpression($parentNode);

            // ( expression )
            case TokenKind::OpenParenToken:
                return $this->parseParenthesizedExpression($parentNode);

            // anonymous-function-creation-expression
            case TokenKind::AttributeToken:
                return $this->parseAttributeExpression($parentNode);

            case TokenKind::StaticKeyword:
                // handle `static::`, `static(`, `new static;`, `instanceof static`
                if (!$this->lookahead([TokenKind::FunctionKeyword, TokenKind::FnKeyword])) {
                    // TODO: Should this check the parent type to reject `$x = static;`, `$x = static();`, etc.
                    return $this->parseStaticQualifiedName($parentNode);
                }
                // Could be `static function` anonymous function creation expression, so flow through
            case TokenKind::FunctionKeyword:
            case TokenKind::FnKeyword:
                return $this->parseAnonymousFunctionCreationExpression($parentNode);

            case TokenKind::TrueReservedWord:
            case TokenKind::FalseReservedWord:
            case TokenKind::NullReservedWord:
                // handle `true::`, `true(`, `true\`
                if ($this->lookahead([TokenKind::BackslashToken, TokenKind::ColonColonToken, TokenKind::OpenParenToken])) {
                    return $this->parseQualifiedName($parentNode);
                }
                return $this->parseReservedWordExpression($parentNode);
            case TokenKind::MatchKeyword:
                return $this->parseMatchExpression($parentNode);
        }
        if (\in_array($token->kind, TokenStringMaps::RESERVED_WORDS)) {
            return $this->parseQualifiedName($parentNode);
        }

        return new MissingToken(TokenKind::Expression, $token->fullStart);
    }

    private function parseEmptyStatement($parentNode) {
        $emptyStatement = new EmptyStatement();
        $emptyStatement->parent = $parentNode;
        $emptyStatement->semicolon = $this->eat1(TokenKind::SemicolonToken);
        return $emptyStatement;
    }

    private function parseStringLiteralExpression($parentNode) {
        // TODO validate input token
        $expression = new StringLiteral();
        $expression->parent = $parentNode;
        $expression->children = $this->getCurrentToken(); // TODO - merge string types
        $this->advanceToken();
        return $expression;
    }

    private function parseStringLiteralExpression2($parentNode): StringLiteral {
        // TODO validate input token
        $expression = new StringLiteral();
        $expression->parent = $parentNode;
        $expression->startQuote = $this->eat(TokenKind::SingleQuoteToken, TokenKind::DoubleQuoteToken, TokenKind::HeredocStart, TokenKind::BacktickToken);
        $expression->children = [];

        while (true) {
            switch ($this->getCurrentToken()->kind) {
                case TokenKind::DollarOpenBraceToken:
                case TokenKind::OpenBraceDollarToken:
                    $expression->children[] = $this->eat(TokenKind::DollarOpenBraceToken, TokenKind::OpenBraceDollarToken);
                    /**
                     * @phpstan-ignore-next-line "Strict comparison using
                     * === between 403|404 and 408 will always evaluate to
                     * false" is wrong because those tokens were eaten above
                     */
                    if ($this->getCurrentToken()->kind === TokenKind::StringVarname) {
                        $expression->children[] = $this->parseComplexDollarTemplateStringExpression($expression);
                    } else {
                        $expression->children[] = $this->parseExpression($expression);
                    }
                    $expression->children[] = $this->eat1(TokenKind::CloseBraceToken);
                    break;
                case $startQuoteKind = $expression->startQuote->kind:
                case TokenKind::EndOfFileToken:
                case TokenKind::HeredocEnd:
                    $expression->endQuote = $this->eat($startQuoteKind, TokenKind::HeredocEnd);
                    return $expression;
                case TokenKind::VariableName:
                    $expression->children[] = $this->parseTemplateStringExpression($expression);
                    break;
                default:
                    $expression->children[] = $this->getCurrentToken();
                    $this->advanceToken();
                    break;
            }
        }
    }

    /**
     * This is used to parse the contents of `"${...}"` expressions.
     *
     * Supported: x, x[0], x[$y]
     * Not supported: $x->p1, x[0][1], etc.
     * @see parseTemplateStringExpression
     *
     * Precondition: getCurrentToken()->kind === TokenKind::StringVarname
     */
    private function parseComplexDollarTemplateStringExpression($parentNode) {
        $var = $this->parseSimpleVariable($parentNode);
        $token = $this->getCurrentToken();
        if ($token->kind === TokenKind::OpenBracketToken) {
            return $this->parseTemplateStringSubscriptExpression($var);
        }
        return $var;
    }

    /**
     * Double-quoted and heredoc strings support a basic set of expression types, described in http://php.net/manual/en/language.types.string.php#language.types.string.parsing
     * Supported: $x, $x->p, $x[0], $x[$y]
     * Not supported: $x->p1->p2, $x[0][1], etc.
     * Since there is a relatively small finite set of allowed forms, I implement it here rather than trying to reuse the general expression parsing code.
     */
    private function parseTemplateStringExpression($parentNode) {
        $token = $this->getCurrentToken();
        if ($token->kind === TokenKind::VariableName) {
            $var = $this->parseSimpleVariable($parentNode);
            $token = $this->getCurrentToken();
            if ($token->kind === TokenKind::OpenBracketToken) {
                return $this->parseTemplateStringSubscriptExpression($var);
            } else if ($token->kind === TokenKind::ArrowToken || $token->kind === TokenKind::QuestionArrowToken) {
                return $this->parseTemplateStringMemberAccessExpression($var);
            } else {
                return $var;
            }
        }

        return null;
    }

    private function parseTemplateStringSubscriptExpression($postfixExpression) : SubscriptExpression {
        $subscriptExpression = new SubscriptExpression();
        $subscriptExpression->parent = $postfixExpression->parent;
        $postfixExpression->parent = $subscriptExpression;

        $subscriptExpression->postfixExpression = $postfixExpression;
        $subscriptExpression->openBracketOrBrace = $this->eat1(TokenKind::OpenBracketToken); // Only [] syntax is supported, not {}
        $token = $this->getCurrentToken();
        if ($token->kind === TokenKind::VariableName) {
            $subscriptExpression->accessExpression = $this->parseSimpleVariable($subscriptExpression);
        } elseif ($token->kind === TokenKind::IntegerLiteralToken) {
            $subscriptExpression->accessExpression = $this->parseNumericLiteralExpression($subscriptExpression);
        } elseif ($token->kind === TokenKind::StringLiteralToken) {
            // TODO: investigate if this should add other uncommon types of tokens for strings/numbers mentioned in parsePrimaryExpression()
            $subscriptExpression->accessExpression = $this->parseStringLiteralExpression($subscriptExpression);
        } elseif ($token->kind === TokenKind::Name) {
            $subscriptExpression->accessExpression = $this->parseTemplateStringSubscriptStringLiteral($subscriptExpression);
        } else {
            $subscriptExpression->accessExpression = new MissingToken(TokenKind::Expression, $token->fullStart);
        }

        $subscriptExpression->closeBracketOrBrace = $this->eat1(TokenKind::CloseBracketToken);

        return $subscriptExpression;
    }

    private function parseTemplateStringSubscriptStringLiteral($parentNode) : StringLiteral {
        $expression = new StringLiteral();
        $expression->parent = $parentNode;
        $expression->children = $this->eat1(TokenKind::Name);
        return $expression;
    }

    private function parseTemplateStringMemberAccessExpression($expression) : MemberAccessExpression {
        $memberAccessExpression = new MemberAccessExpression();
        $memberAccessExpression->parent = $expression->parent;
        $expression->parent = $memberAccessExpression;

        $memberAccessExpression->dereferencableExpression = $expression;
        $memberAccessExpression->arrowToken = $this->eat(TokenKind::ArrowToken, TokenKind::QuestionArrowToken);
        $memberAccessExpression->memberName = $this->eat1(TokenKind::Name);

        return $memberAccessExpression;
    }

    private function parseNumericLiteralExpression($parentNode) {
        $numericLiteral = new NumericLiteral();
        $numericLiteral->parent = $parentNode;
        $numericLiteral->children = $this->getCurrentToken();
        $this->advanceToken();
        return $numericLiteral;
    }

    private function parseReservedWordExpression($parentNode) {
        $reservedWord = new ReservedWord();
        $reservedWord->parent = $parentNode;
        $reservedWord->children = $this->getCurrentToken();
        $this->advanceToken();
        return $reservedWord;
    }

    private function isModifier($token): bool {
        switch ($token->kind) {
            // class-modifier
            case TokenKind::AbstractKeyword:
            case TokenKind::FinalKeyword:

            // visibility-modifier
            case TokenKind::PublicKeyword:
            case TokenKind::ProtectedKeyword:
            case TokenKind::PrivateKeyword:

            // static-modifier
            case TokenKind::StaticKeyword:

            // readonly-modifier
            case TokenKind::ReadonlyKeyword:

            // var
            case TokenKind::VarKeyword:
                return true;
        }
        return false;
    }

    private function isParameterModifier($token): bool {
        switch ($token->kind) {
            // visibility-modifier
            case TokenKind::PublicKeyword:
            case TokenKind::ProtectedKeyword:
            case TokenKind::PrivateKeyword:

            // readonly-modifier
            case TokenKind::ReadonlyKeyword:

                return true;
        }
        return false;
    }

    /** @return Token[] */
    private function parseParameterModifiers(): array {
        $modifiers = [];
        $token = $this->getCurrentToken();
        while ($this->isParameterModifier($token)) {
            $modifiers[] = $token;
            $this->advanceToken();
            $token = $this->getCurrentToken();
        }
        return $modifiers;
    }

    /** @return Token[] */
    private function parseModifiers(): array {
        $modifiers = [];
        $token = $this->getCurrentToken();
        while ($this->isModifier($token)) {
            $modifiers[] = $token;
            $this->advanceToken();
            $token = $this->getCurrentToken();
        }
        return $modifiers;
    }

    private function isParameterStartFn() {
        return function ($token) {
            switch ($token->kind) {
                case TokenKind::DotDotDotToken:

                // qualified-name
                case TokenKind::Name: // http://php.net/manual/en/language.namespaces.rules.php
                case TokenKind::BackslashToken:
                case TokenKind::NamespaceKeyword:

                case TokenKind::AmpersandToken:

                case TokenKind::VariableName:

                // nullable-type
                case TokenKind::QuestionToken:

                // parameter promotion
                case TokenKind::PublicKeyword:
                case TokenKind::ProtectedKeyword:
                case TokenKind::PrivateKeyword:
                case TokenKind::AttributeToken:

                // dnf types (A&B)|C
                case TokenKind::OpenParenToken:
                    return true;
            }

            // scalar-type
            return \in_array($token->kind, $this->parameterTypeDeclarationTokens, true);
        };
    }

    /**
     * @template TDelimitedList of DelimitedList
     * @param class-string<TDelimitedList> $className (name of subclass of DelimitedList)
     * @param int|int[] $delimiter
     * @param callable $isElementStartFn
     * @param callable $parseElementFn
     * @param Node $parentNode
     * @param bool $allowEmptyElements
     * @return TDelimitedList|null instance of $className
     */
    private function parseDelimitedList($className, $delimiter, $isElementStartFn, $parseElementFn, $parentNode, $allowEmptyElements = false) {
        // TODO consider allowing empty delimiter to be more tolerant
        $node = new $className();
        $token = $this->getCurrentToken();
        do {
            if ($isElementStartFn($token)) {
                $node->addElement($parseElementFn($node));
            } elseif (!$allowEmptyElements || !$this->checkAnyToken($delimiter)) {
                break;
            }

            $delimiterToken = $this->eatOptional($delimiter);
            if ($delimiterToken !== null) {
                $node->addElement($delimiterToken);
            }
            $token = $this->getCurrentToken();
            // TODO ERROR CASE - no delimiter, but a param follows
        } while ($delimiterToken !== null);

        $node->parent = $parentNode;
        if ($node->children === null) {
            return null;
        }
        return $node;
    }

    /**
     * @internal
     */
    const QUALIFIED_NAME_START_TOKENS = [
        TokenKind::BackslashToken,
        TokenKind::NamespaceKeyword,
        TokenKind::Name,
    ];

    private function isQualifiedNameStart($token) {
        return \in_array($token->kind, self::QUALIFIED_NAME_START_TOKENS, true);
    }

    private function isQualifiedNameStartFn() {
        return function ($token) {
            return \in_array($token->kind, self::QUALIFIED_NAME_START_TOKENS, true);
        };
    }

    private function isQualifiedNameStartForCatchFn() {
        return function ($token) {
            // Unfortunately, catch(int $x) is *syntactically valid* php which `php --syntax-check` would accept.
            // (tolerant-php-parser is concerned with syntax, not semantics)
            return \in_array($token->kind, self::QUALIFIED_NAME_START_TOKENS, true) ||
                \in_array($token->kind, $this->reservedWordTokens, true);
        };
    }

    /**
     * @return QualifiedName
     */
    private function parseStaticQualifiedName($parentNode) {
        $node = new QualifiedName();
        $token = $this->eat(TokenKind::StaticKeyword);
        $token->kind = TokenKind::Name;
        $node->parent = $parentNode;
        $node->nameParts = [$token];
        return $node;
    }

    /**
     * @return QualifiedName|null - returns null for invalid qualified names such as `static\` (use parseStaticQualifiedName for that)
     */
    private function parseQualifiedName($parentNode) {
        return ($this->parseQualifiedNameFn())($parentNode);
    }

    private function parseQualifiedNameFn() {
        return function ($parentNode) {
            $node = new QualifiedName();
            $node->parent = $parentNode;
            $node->relativeSpecifier = $this->parseRelativeSpecifier($node);
            if (!isset($node->relativeSpecifier)) {
                $node->globalSpecifier = $this->eatOptional1(TokenKind::BackslashToken);
            }

            $nameParts =
                $this->parseDelimitedList(
                    DelimitedList\QualifiedNameParts::class,
                    TokenKind::BackslashToken,
                    function ($token) {
                        // a\static() <- INVALID (but not checked for right now)
                        // new a\static() <- INVALID
                        // new static() <- VALID
                        // a\static\b <- INVALID
                        // a\function <- INVALID
                        // a\true\b <-VALID
                        // a\b\true <-VALID
                        // a\static::b <-VALID
                        // TODO more tests
                        return $this->lookahead(TokenKind::BackslashToken)
                            ? in_array($token->kind, $this->nameOrReservedWordTokens)
                            : in_array($token->kind, $this->nameOrStaticOrReservedWordTokens);
                    },
                    function ($parentNode) {
                        $name = $this->lookahead(TokenKind::BackslashToken)
                            ? $this->eat($this->nameOrReservedWordTokens)
                            : $this->eat($this->nameOrStaticOrReservedWordTokens); // TODO support keyword name
                        $name->kind = TokenKind::Name; // bool/true/null/static should not be treated as keywords in this case
                        return $name;
                    }, $node);
            if ($nameParts === null && $node->globalSpecifier === null && $node->relativeSpecifier === null) {
                return null;
            }

            $node->nameParts = $nameParts ? $nameParts->children : [];

            return $node;
        };
    }

    private function parseRelativeSpecifier($parentNode): ?RelativeSpecifier {
        $node = new RelativeSpecifier();
        $node->parent = $parentNode;
        $node->namespaceKeyword = $this->eatOptional1(TokenKind::NamespaceKeyword);
        if ($node->namespaceKeyword !== null) {
            $node->backslash = $this->eat1(TokenKind::BackslashToken);
        }
        if (isset($node->backslash)) {
            return $node;
        }
        return null;
    }

    /**
     * @param MethodDeclaration|FunctionDeclaration|AnonymousFunctionCreationExpression $functionDeclaration
     */
    private function parseFunctionType(Node $functionDeclaration, $canBeAbstract = false, $isAnonymous = false) {

        $functionDeclaration->functionKeyword = $this->eat1(TokenKind::FunctionKeyword);
        $functionDeclaration->byRefToken = $this->eatOptional1(TokenKind::AmpersandToken);
        $functionDeclaration->name = $isAnonymous
            ? $this->eatOptional($this->nameOrKeywordOrReservedWordTokens)
            : $this->eat($this->nameOrKeywordOrReservedWordTokens);

        if (isset($functionDeclaration->name)) {
            $functionDeclaration->name->kind = TokenKind::Name;
        }

        if ($isAnonymous && isset($functionDeclaration->name)) {
            // Anonymous functions should not have names
            $functionDeclaration->name = new SkippedToken($functionDeclaration->name); // TODO instead handle this during post-walk
        }

        $functionDeclaration->openParen = $this->eat1(TokenKind::OpenParenToken);
        $functionDeclaration->parameters = $this->parseDelimitedList(
            DelimitedList\ParameterDeclarationList::class,
            TokenKind::CommaToken,
            $this->isParameterStartFn(),
            $this->parseParameterFn(),
            $functionDeclaration);
        $functionDeclaration->closeParen = $this->eat1(TokenKind::CloseParenToken);
        if ($isAnonymous) {
            $functionDeclaration->anonymousFunctionUseClause = $this->parseAnonymousFunctionUseClause($functionDeclaration);
        }

        if ($this->checkToken(TokenKind::ColonToken)) {
            $functionDeclaration->colonToken = $this->eat1(TokenKind::ColonToken);
            $functionDeclaration->questionToken = $this->eatOptional1(TokenKind::QuestionToken);
            $this->parseAndSetReturnTypeDeclarationList($functionDeclaration);
        }

        if ($canBeAbstract) {
            $functionDeclaration->compoundStatementOrSemicolon = $this->eatOptional1(TokenKind::SemicolonToken);
        }

        if (!isset($functionDeclaration->compoundStatementOrSemicolon)) {
            $functionDeclaration->compoundStatementOrSemicolon = $this->parseCompoundStatement($functionDeclaration);
        }
    }

    private function parseNamedLabelStatement($parentNode) {
        $namedLabelStatement = new NamedLabelStatement();
        $namedLabelStatement->parent = $parentNode;
        $namedLabelStatement->name = $this->eat1(TokenKind::Name);
        $namedLabelStatement->colon = $this->eat1(TokenKind::ColonToken);
        // A named label is a statement on its own. E.g. `while (false) label: echo "test";`
        // is parsed as `while (false) { label: } echo "test";
        return $namedLabelStatement;
    }

    /**
     * @param int|int[] ...$expectedKinds an array of one or more kinds/sets of allowed kinds in each position
     */
    private function lookahead(...$expectedKinds) : bool {
        $startPos = $this->lexer->getCurrentPosition();
        $startToken = $this->token;
        $succeeded = true;
        foreach ($expectedKinds as $kind) {
            $token = $this->lexer->scanNextToken();
            $currentPosition = $this->lexer->getCurrentPosition();
            $endOfFilePosition = $this->lexer->getEndOfFilePosition();
            if (\is_array($kind)) {
                $succeeded = false;
                foreach ($kind as $kindOption) {
                    if ($currentPosition <= $endOfFilePosition && $token->kind === $kindOption) {
                        $succeeded = true;
                        break;
                    }
                }
            } else {
                if ($currentPosition > $endOfFilePosition || $token->kind !== $kind) {
                    $succeeded = false;
                    break;
                }
            }
        }
        $this->lexer->setCurrentPosition($startPos);
        $this->token = $startToken;
        return $succeeded;
    }

    /** @param int $expectedKind */
    private function checkToken($expectedKind) : bool {
        return $this->getCurrentToken()->kind === $expectedKind;
    }

    /** @param int|int[] $expectedKind */
    private function checkAnyToken($expectedKind) : bool {
        $kind = $this->getCurrentToken()->kind;
        return \is_array($expectedKind) ? \in_array($kind, $expectedKind, true) : $kind === $expectedKind;
    }

    private function parseIfStatement($parentNode) {
        $ifStatement = new IfStatementNode();
        $ifStatement->parent = $parentNode;
        $ifStatement->ifKeyword = $this->eat1(TokenKind::IfKeyword);
        $ifStatement->openParen = $this->eat1(TokenKind::OpenParenToken);
        $ifStatement->expression = $this->parseExpression($ifStatement);
        $ifStatement->closeParen = $this->eat1(TokenKind::CloseParenToken);
        $curTokenKind = $this->getCurrentToken()->kind;
        if ($curTokenKind === TokenKind::ColonToken) {
            $ifStatement->colon = $this->eat1(TokenKind::ColonToken);
            $ifStatement->statements = $this->parseList($ifStatement, ParseContext::IfClause2Elements);
        } else if ($curTokenKind !== TokenKind::ScriptSectionEndTag) {
            // Fix #246 : properly parse `if (false) ?\>echoed text\<?php`
            $ifStatement->statements = $this->parseStatement($ifStatement);
        }
        $ifStatement->elseIfClauses = []; // TODO - should be some standard for empty arrays vs. null?
        while ($this->checkToken(TokenKind::ElseIfKeyword)) {
            $ifStatement->elseIfClauses[] = $this->parseElseIfClause($ifStatement);
        }

        if ($this->checkToken(TokenKind::ElseKeyword)) {
            $ifStatement->elseClause = $this->parseElseClause($ifStatement);
        }

        $ifStatement->endifKeyword = $this->eatOptional1(TokenKind::EndIfKeyword);
        if ($ifStatement->endifKeyword) {
            $ifStatement->semicolon = $this->eatSemicolonOrAbortStatement();
        }

        return $ifStatement;
    }

    private function parseElseIfClause($parentNode) {
        $elseIfClause = new ElseIfClauseNode();
        $elseIfClause->parent = $parentNode;
        $elseIfClause->elseIfKeyword = $this->eat1(TokenKind::ElseIfKeyword);
        $elseIfClause->openParen = $this->eat1(TokenKind::OpenParenToken);
        $elseIfClause->expression = $this->parseExpression($elseIfClause);
        $elseIfClause->closeParen = $this->eat1(TokenKind::CloseParenToken);
        $curTokenKind = $this->getCurrentToken()->kind;
        if ($curTokenKind === TokenKind::ColonToken) {
            $elseIfClause->colon = $this->eat1(TokenKind::ColonToken);
            $elseIfClause->statements = $this->parseList($elseIfClause, ParseContext::IfClause2Elements);
        } elseif ($curTokenKind !== TokenKind::ScriptSectionEndTag) {
            $elseIfClause->statements = $this->parseStatement($elseIfClause);
        }
        return $elseIfClause;
    }

    private function parseElseClause($parentNode) {
        $elseClause = new ElseClauseNode();
        $elseClause->parent = $parentNode;
        $elseClause->elseKeyword = $this->eat1(TokenKind::ElseKeyword);
        $curTokenKind = $this->getCurrentToken()->kind;
        if ($curTokenKind === TokenKind::ColonToken) {
            $elseClause->colon = $this->eat1(TokenKind::ColonToken);
            $elseClause->statements = $this->parseList($elseClause, ParseContext::IfClause2Elements);
        } elseif ($curTokenKind !== TokenKind::ScriptSectionEndTag) {
            $elseClause->statements = $this->parseStatement($elseClause);
        }
        return $elseClause;
    }

    private function parseSwitchStatement($parentNode) {
        $switchStatement = new SwitchStatementNode();
        $switchStatement->parent = $parentNode;
        $switchStatement->switchKeyword = $this->eat1(TokenKind::SwitchKeyword);
        $switchStatement->openParen = $this->eat1(TokenKind::OpenParenToken);
        $switchStatement->expression = $this->parseExpression($switchStatement);
        $switchStatement->closeParen = $this->eat1(TokenKind::CloseParenToken);
        $switchStatement->openBrace = $this->eatOptional1(TokenKind::OpenBraceToken);
        $switchStatement->colon = $this->eatOptional1(TokenKind::ColonToken);
        $switchStatement->caseStatements = $this->parseList($switchStatement, ParseContext::SwitchStatementElements);
        if ($switchStatement->colon !== null) {
            $switchStatement->endswitch = $this->eat1(TokenKind::EndSwitchKeyword);
            $switchStatement->semicolon = $this->eatSemicolonOrAbortStatement();
        } else {
            $switchStatement->closeBrace = $this->eat1(TokenKind::CloseBraceToken);
        }

        return $switchStatement;
    }

    private function parseCaseOrDefaultStatement() {
        return function ($parentNode) {
            $caseStatement = new CaseStatementNode();
            $caseStatement->parent = $parentNode;
            // TODO add error checking
            $caseStatement->caseKeyword = $this->eat(TokenKind::CaseKeyword, TokenKind::DefaultKeyword);
            if ($caseStatement->caseKeyword->kind === TokenKind::CaseKeyword) {
                $caseStatement->expression = $this->parseExpression($caseStatement);
            }
            $caseStatement->defaultLabelTerminator = $this->eat(TokenKind::ColonToken, TokenKind::SemicolonToken);
            $caseStatement->statementList = $this->parseList($caseStatement, ParseContext::CaseStatementElements);
            return $caseStatement;
        };
    }

    private function parseWhileStatement($parentNode) {
        $whileStatement = new WhileStatement();
        $whileStatement->parent = $parentNode;
        $whileStatement->whileToken = $this->eat1(TokenKind::WhileKeyword);
        $whileStatement->openParen = $this->eat1(TokenKind::OpenParenToken);
        $whileStatement->expression = $this->parseExpression($whileStatement);
        $whileStatement->closeParen = $this->eat1(TokenKind::CloseParenToken);
        $whileStatement->colon = $this->eatOptional1(TokenKind::ColonToken);
        if ($whileStatement->colon !== null) {
            $whileStatement->statements = $this->parseList($whileStatement, ParseContext::WhileStatementElements);
            $whileStatement->endWhile = $this->eat1(TokenKind::EndWhileKeyword);
            $whileStatement->semicolon = $this->eatSemicolonOrAbortStatement();
        } elseif (!$this->checkToken(TokenKind::ScriptSectionEndTag)) {
            $whileStatement->statements = $this->parseStatement($whileStatement);
        }
        return $whileStatement;
    }

    /**
     * @param Node $parentNode
     * @param bool $force
     * @return Expression|MissingToken|array - The expression, or a missing token, or (if $force) an array containing a missed and skipped token
     */
    private function parseExpression($parentNode, $force = false) {
        $token = $this->getCurrentToken();
        if ($token->kind === TokenKind::EndOfFileToken) {
            return new MissingToken(TokenKind::Expression, $token->fullStart);
        }

        // Equivalent to (parseExpressionFn())($parentNode)
        $expression = $this->parseBinaryExpressionOrHigher(0, $parentNode);
        if ($force && $expression instanceof MissingToken) {
            $expression = [$expression, new SkippedToken($token)];
            $this->advanceToken();
        }

        return $expression;
    }

    private function parseExpressionFn() {
        return function ($parentNode) {
            return $this->parseBinaryExpressionOrHigher(0, $parentNode);
        };
    }

    /**
     * @param Node $parentNode
     * @return UnaryExpression|MissingToken|Variable|ThrowExpression
     */
    private function parseUnaryExpressionOrHigher($parentNode) {
        $token = $this->getCurrentToken();
        switch ($token->kind) {
            // unary-op-expression
            case TokenKind::PlusToken:
            case TokenKind::MinusToken:
            case TokenKind::ExclamationToken:
            case TokenKind::TildeToken:
                return $this->parseUnaryOpExpression($parentNode);

            // error-control-expression
            case TokenKind::AtSymbolToken:
                return $this->parseErrorControlExpression($parentNode);

            // prefix-increment-expression
            case TokenKind::PlusPlusToken:
            // prefix-decrement-expression
            case TokenKind::MinusMinusToken:
                return $this->parsePrefixUpdateExpression($parentNode);

            case TokenKind::ArrayCastToken:
            case TokenKind::BoolCastToken:
            case TokenKind::DoubleCastToken:
            case TokenKind::IntCastToken:
            case TokenKind::ObjectCastToken:
            case TokenKind::StringCastToken:
            case TokenKind::UnsetCastToken:
                return $this->parseCastExpression($parentNode);

            case TokenKind::OpenParenToken:
                // TODO remove duplication
                if ($this->lookahead(
                    [TokenKind::ArrayKeyword,
                    TokenKind::BinaryReservedWord,
                    TokenKind::BoolReservedWord,
                    TokenKind::BooleanReservedWord,
                    TokenKind::DoubleReservedWord,
                    TokenKind::IntReservedWord,
                    TokenKind::IntegerReservedWord,
                    TokenKind::FloatReservedWord,
                    TokenKind::ObjectReservedWord,
                    TokenKind::RealReservedWord,
                    TokenKind::StringReservedWord,
                    TokenKind::UnsetKeyword], TokenKind::CloseParenToken)) {
                    return $this->parseCastExpressionGranular($parentNode);
                }
                break;

/*

            case TokenKind::BacktickToken:
                return $this->parseShellCommandExpression($parentNode);

            case TokenKind::OpenParenToken:
                // TODO
//                return $this->parseCastExpressionGranular($parentNode);
                break;*/

            // object-creation-expression (postfix-expression)
            case TokenKind::NewKeyword:
                return $this->parseObjectCreationExpression($parentNode);

            // clone-expression (postfix-expression)
            case TokenKind::CloneKeyword:
                return $this->parseCloneExpression($parentNode);

            case TokenKind::YieldKeyword:
            case TokenKind::YieldFromKeyword:
                return $this->parseYieldExpression($parentNode);

            // include-expression
            // include-once-expression
            // require-expression
            // require-once-expression
            case TokenKind::IncludeKeyword:
            case TokenKind::IncludeOnceKeyword:
            case TokenKind::RequireKeyword:
            case TokenKind::RequireOnceKeyword:
                return $this->parseScriptInclusionExpression($parentNode);
            case TokenKind::ThrowKeyword: // throw-statement will become an expression in php 8.0
                return $this->parseThrowExpression($parentNode);
        }

        $expression = $this->parsePrimaryExpression($parentNode);
        return $this->parsePostfixExpressionRest($expression);
    }

    /**
     * @param int $precedence
     * @param Node $parentNode
     * @return Expression|MissingToken
     */
    private function parseBinaryExpressionOrHigher($precedence, $parentNode) {
        $leftOperand = $this->parseUnaryExpressionOrHigher($parentNode);

        [$prevNewPrecedence, $prevAssociativity] = self::UNKNOWN_PRECEDENCE_AND_ASSOCIATIVITY;

        while (true) {
            $token = $this->getCurrentToken();

            [$newPrecedence, $associativity] = $this->getBinaryOperatorPrecedenceAndAssociativity($token);

            // Expressions using operators w/o associativity (equality, relational, instanceof)
            // cannot reference identical expression types within one of their operands.
            //
            // Example:
            //   $a < $b < $c // CASE 1: INVALID
            //   $a < $b === $c < $d // CASE 2: VALID
            //
            // In CASE 1, it is expected that we stop parsing the expression after the $b token.
            if ($prevAssociativity === Associativity::None && $prevNewPrecedence === $newPrecedence) {
                break;
            }

            // Precedence and associativity properties determine whether we recurse, and continue
            // building up the current operand, or whether we pop out.
            //
            // Example:
            //   $a + $b + $c // CASE 1: additive-expression (left-associative)
            //   $a = $b = $c // CASE 2: equality-expression (right-associative)
            //
            // CASE 1:
            // The additive-expression is left-associative, which means we expect the grouping to be:
            //   ($a + $b) + $c
            //
            // Because both + operators have the same precedence, and the + operator is left associative,
            // we expect the second + operator NOT to be consumed because $newPrecedence > $precedence => FALSE
            //
            // CASE 2:
            // The equality-expression is right-associative, which means we expect the grouping to be:
            //   $a = ($b = $c)
            //
            // Because both = operators have the same precedence, and the = operator is right-associative,
            // we expect the second = operator to be consumed because $newPrecedence >= $precedence => TRUE
            $shouldConsumeCurrentOperator =
                $associativity === Associativity::Right ?
                    $newPrecedence >= $precedence:
                    $newPrecedence > $precedence;

            if (!$shouldConsumeCurrentOperator) {
                break;
            }

            // Unlike every other binary expression, exponentiation operators take precedence over unary operators.
            //
            // Example:
            //   -3**2 => -9
            //
            // In these cases, we strip the UnaryExpression operator, and reassign $leftOperand to
            // $unaryExpression->operand.
            //
            // After we finish building the BinaryExpression, we rebuild the UnaryExpression so that it includes
            // the original operator, and the newly constructed exponentiation-expression as the operand.
            $shouldOperatorTakePrecedenceOverUnary = false;
            switch ($token->kind) {
                case TokenKind::AsteriskAsteriskToken:
                    $shouldOperatorTakePrecedenceOverUnary = $leftOperand instanceof UnaryExpression;
                    break;
                case TokenKind::EqualsToken:
                case TokenKind::AsteriskAsteriskEqualsToken:
                case TokenKind::AsteriskEqualsToken:
                case TokenKind::SlashEqualsToken:
                case TokenKind::PercentEqualsToken:
                case TokenKind::PlusEqualsToken:
                case TokenKind::MinusEqualsToken:
                case TokenKind::DotEqualsToken:
                case TokenKind::LessThanLessThanEqualsToken:
                case TokenKind::GreaterThanGreaterThanEqualsToken:
                case TokenKind::AmpersandEqualsToken:
                case TokenKind::CaretEqualsToken:
                case TokenKind::BarEqualsToken:
                case TokenKind::QuestionQuestionEqualsToken:
                    // Workarounds for https://github.com/Microsoft/tolerant-php-parser/issues/19#issue-201714377
                    // Parse `!$a = $b` as `!($a = $b)` - PHP constrains the Left Hand Side of an assignment to a variable. A unary operator (`@`, `!`, etc.) is not a variable.
                    // Instanceof has similar constraints for the LHS.
                    // So does `!$a += $b`
                    // TODO: Any other operators?
                    if ($leftOperand instanceof UnaryOpExpression) {
                        $shouldOperatorTakePrecedenceOverUnary = true;
                    }
                    break;
                case TokenKind::InstanceOfKeyword:
                    // Unlike assignment, the instanceof operator doesn't have restrictions on what can go in the left hand side.
                    // `!` is the only unary operator with lower precedence than instanceof.
                    if ($leftOperand instanceof UnaryOpExpression) {
                        if ($leftOperand->operator->kind === TokenKind::ExclamationToken) {
                            $shouldOperatorTakePrecedenceOverUnary = true;
                        }
                    }
                    break;
                case TokenKind::QuestionToken:
                    /** @phpstan-ignore-next-line This seems impossible, questionToken is always set AFAICS but ignoring to be safe */
                    if ($parentNode instanceof TernaryExpression && !isset($parentNode->questionToken)) {
                        // Workaround to parse "a ? b : c ? d : e" as "(a ? b : c) ? d : e"
                        break 2;
                    }
                    break;
            }

            if ($shouldOperatorTakePrecedenceOverUnary) {
                /** @var UnaryOpExpression $unaryExpression */
                $unaryExpression = $leftOperand;
                $leftOperand = $unaryExpression->operand;
            }

            $this->advanceToken();

            if ($token->kind === TokenKind::EqualsToken) {
                $byRefToken = $this->eatOptional1(TokenKind::AmpersandToken);
            }

            $leftOperand = $token->kind === TokenKind::QuestionToken ?
                $this->parseTernaryExpression($leftOperand, $token, $parentNode) :
                $this->makeBinaryExpression(
                    $leftOperand,
                    $token,
                    $byRefToken ?? null,
                    $this->parseBinaryExpressionOrHigher($newPrecedence, null),
                    $parentNode);

            // Rebuild the unary expression if we deconstructed it earlier.
            if ($shouldOperatorTakePrecedenceOverUnary) {
                /** @var UnaryOpExpression $unaryExpression */
                $leftOperand->parent = $unaryExpression;
                $unaryExpression->operand = $leftOperand;
                $leftOperand = $unaryExpression;
            }

            // Hold onto these values, so we know whether we've hit duplicate non-associative operators,
            // and need to terminate early.
            $prevNewPrecedence = $newPrecedence;
            $prevAssociativity = $associativity;
        }
        return $leftOperand;
    }

    const OPERATOR_PRECEDENCE_AND_ASSOCIATIVITY =
        [
            // logical-inc-OR-expression-2 (L)
            TokenKind::OrKeyword => [6, Associativity::Left],

            // logical-exc-OR-expression-2 (L)
            TokenKind::XorKeyword=> [7, Associativity::Left],

            // logical-AND-expression-2 (L)
            TokenKind::AndKeyword=> [8, Associativity::Left],

            // simple-assignment-expression (R)
            // TODO byref-assignment-expression
            TokenKind::EqualsToken => [9, Associativity::Right],

            // compound-assignment-expression (R)
            TokenKind::AsteriskAsteriskEqualsToken => [9, Associativity::Right],
            TokenKind::AsteriskEqualsToken => [9, Associativity::Right],
            TokenKind::SlashEqualsToken => [9, Associativity::Right],
            TokenKind::PercentEqualsToken => [9, Associativity::Right],
            TokenKind::PlusEqualsToken => [9, Associativity::Right],
            TokenKind::MinusEqualsToken => [9, Associativity::Right],
            TokenKind::DotEqualsToken => [9, Associativity::Right],
            TokenKind::LessThanLessThanEqualsToken => [9, Associativity::Right],
            TokenKind::GreaterThanGreaterThanEqualsToken => [9, Associativity::Right],
            TokenKind::AmpersandEqualsToken => [9, Associativity::Right],
            TokenKind::CaretEqualsToken => [9, Associativity::Right],
            TokenKind::BarEqualsToken => [9, Associativity::Right],
            TokenKind::QuestionQuestionEqualsToken => [9, Associativity::Right],

            // TODO conditional-expression (L)
            TokenKind::QuestionToken => [10, Associativity::Left],
//            TokenKind::ColonToken => [9, Associativity::Left],

        // TODO coalesce-expression (R)
        TokenKind::QuestionQuestionToken => [9, Associativity::Right],

        // pipe-expression (L)
        TokenKind::PipeToken => [11, Associativity::Left],

        //logical-inc-OR-expression-1 (L)
        TokenKind::BarBarToken => [12, Associativity::Left],

            // logical-AND-expression-1 (L)
            TokenKind::AmpersandAmpersandToken => [13, Associativity::Left],

            // bitwise-inc-OR-expression (L)
            TokenKind::BarToken => [14, Associativity::Left],

            // bitwise-exc-OR-expression (L)
            TokenKind::CaretToken => [15, Associativity::Left],

            // bitwise-AND-expression (L)
            TokenKind::AmpersandToken => [16, Associativity::Left],

            // equality-expression (X)
            TokenKind::EqualsEqualsToken => [17, Associativity::None],
            TokenKind::ExclamationEqualsToken => [17, Associativity::None],
            TokenKind::LessThanGreaterThanToken => [17, Associativity::None],
            TokenKind::EqualsEqualsEqualsToken => [17, Associativity::None],
            TokenKind::ExclamationEqualsEqualsToken => [17, Associativity::None],
            TokenKind::LessThanEqualsGreaterThanToken => [17, Associativity::None],

            // relational-expression (X)
            TokenKind::LessThanToken => [18, Associativity::None],
            TokenKind::GreaterThanToken => [18, Associativity::None],
            TokenKind::LessThanEqualsToken => [18, Associativity::None],
            TokenKind::GreaterThanEqualsToken => [18, Associativity::None],

            // shift-expression (L)
            TokenKind::LessThanLessThanToken => [19, Associativity::Left],
            TokenKind::GreaterThanGreaterThanToken => [19, Associativity::Left],

            // additive-expression (L)
            TokenKind::PlusToken => [20, Associativity::Left],
            TokenKind::MinusToken => [20, Associativity::Left],
            TokenKind::DotToken =>[20, Associativity::Left],

            // multiplicative-expression (L)
            TokenKind::AsteriskToken => [21, Associativity::Left],
            TokenKind::SlashToken => [21, Associativity::Left],
            TokenKind::PercentToken => [21, Associativity::Left],

            // instanceof-expression (X)
            TokenKind::InstanceOfKeyword => [22, Associativity::None],

            // exponentiation-expression (R)
            TokenKind::AsteriskAsteriskToken => [23, Associativity::Right]
        ];

    const UNKNOWN_PRECEDENCE_AND_ASSOCIATIVITY = [-1, -1];

    private function getBinaryOperatorPrecedenceAndAssociativity($token) {
        return self::OPERATOR_PRECEDENCE_AND_ASSOCIATIVITY[$token->kind] ?? self::UNKNOWN_PRECEDENCE_AND_ASSOCIATIVITY;
    }

    /**
     * @internal Do not use outside this class, this may be changed or removed.
     */
    const KNOWN_ASSIGNMENT_TOKEN_SET = [
        TokenKind::AsteriskAsteriskEqualsToken => true,
        TokenKind::AsteriskEqualsToken => true,
        TokenKind::SlashEqualsToken => true,
        TokenKind::PercentEqualsToken => true,
        TokenKind::PlusEqualsToken => true,
        TokenKind::MinusEqualsToken => true,
        TokenKind::DotEqualsToken => true,
        TokenKind::LessThanLessThanEqualsToken => true,
        TokenKind::GreaterThanGreaterThanEqualsToken => true,
        TokenKind::AmpersandEqualsToken => true,
        TokenKind::CaretEqualsToken => true,
        TokenKind::BarEqualsToken => true,
        TokenKind::QuestionQuestionEqualsToken => true,
        // InstanceOf has other remaining issues, but this heuristic is an improvement for many common cases such as `$x && $y = $z`
    ];

    /**
     * @param Token|Node $leftOperand
     * @param Token $operatorToken
     * @param Token|null $byRefToken
     * @param Token|Node $rightOperand
     * @param Node $parentNode
     * @return BinaryExpression|AssignmentExpression
     */
    private function makeBinaryExpression($leftOperand, $operatorToken, $byRefToken, $rightOperand, $parentNode) {
        $assignmentExpression = $operatorToken->kind === TokenKind::EqualsToken;
        if ($assignmentExpression || \array_key_exists($operatorToken->kind, self::KNOWN_ASSIGNMENT_TOKEN_SET)) {
            if ($leftOperand instanceof BinaryExpression) {
                if (!\array_key_exists($leftOperand->operator->kind, self::KNOWN_ASSIGNMENT_TOKEN_SET)) {
                    // Handle cases without parenthesis, such as $x ** $y === $z, as $x ** ($y === $z)
                    return $this->shiftBinaryOperands($leftOperand, $operatorToken, $byRefToken, $rightOperand, $parentNode);
                }
            } elseif ($leftOperand instanceof UnaryOpExpression || $leftOperand instanceof ErrorControlExpression) {
                return $this->shiftUnaryOperands($leftOperand, $operatorToken, $byRefToken, $rightOperand, $parentNode);
            }
        }
        $binaryExpression = $assignmentExpression ? new AssignmentExpression() : new BinaryExpression();
        $binaryExpression->parent = $parentNode;
        if ($leftOperand instanceof Node) {
            $leftOperand->parent = $binaryExpression;
        }
        if ($rightOperand instanceof Node) {
            $rightOperand->parent = $binaryExpression;
        }
        $binaryExpression->leftOperand = $leftOperand;
        $binaryExpression->operator = $operatorToken;
        if ($binaryExpression instanceof AssignmentExpression && isset($byRefToken)) {
            $binaryExpression->byRef = $byRefToken;
        }
        $binaryExpression->rightOperand = $rightOperand;
        return $binaryExpression;
    }

    /**
     * @param ErrorControlExpression|UnaryOpExpression $leftOperand
     */
    private function shiftUnaryOperands(UnaryExpression $leftOperand, $operatorToken, $byRefToken, $rightOperand, $parentNode) {
        $outerUnaryOpExpression = clone($leftOperand);
        $inner = $this->makeBinaryExpression(
            $leftOperand->operand,
            $operatorToken,
            $byRefToken,
            $rightOperand,
            $outerUnaryOpExpression
        );
        // Either ErrorControlExpression or a UnaryOpExpression
        $outerUnaryOpExpression->parent = $parentNode;
        // TODO should this binaryExpression be wrapped in a UnaryExpression?
        $outerUnaryOpExpression->operand = $inner;

        return $outerUnaryOpExpression;
    }

    private function shiftBinaryOperands(BinaryExpression $leftOperand, $operatorToken, $byRefToken, $rightOperand, $parentNode) {
        $inner = $this->makeBinaryExpression(
            $leftOperand->rightOperand,
            $operatorToken,
            $byRefToken,
            $rightOperand,
            $parentNode
        );
        $outer = $this->makeBinaryExpression(
            $leftOperand->leftOperand,
            $leftOperand->operator,
            null,
            $inner,
            $parentNode
        );
        $inner->parent = $outer;
        return $outer;
    }

    private function parseDoStatement($parentNode) {
        $doStatement = new DoStatement();
        $doStatement->parent = $parentNode;
        $doStatement->do = $this->eat1(TokenKind::DoKeyword);
        $doStatement->statement = $this->parseStatement($doStatement);
        $doStatement->whileToken = $this->eat1(TokenKind::WhileKeyword);
        $doStatement->openParen = $this->eat1(TokenKind::OpenParenToken);
        $doStatement->expression = $this->parseExpression($doStatement);
        $doStatement->closeParen = $this->eat1(TokenKind::CloseParenToken);
        $doStatement->semicolon = $this->eatSemicolonOrAbortStatement();
        return $doStatement;
    }

    private function parseForStatement($parentNode) {
        $forStatement = new ForStatement();
        $forStatement->parent = $parentNode;
        $forStatement->for = $this->eat1(TokenKind::ForKeyword);
        $forStatement->openParen = $this->eat1(TokenKind::OpenParenToken);
        $forStatement->forInitializer = $this->parseExpressionList($forStatement); // TODO spec is redundant
        $forStatement->exprGroupSemicolon1 = $this->eat1(TokenKind::SemicolonToken);
        $forStatement->forControl = $this->parseExpressionList($forStatement);
        $forStatement->exprGroupSemicolon2 = $this->eat1(TokenKind::SemicolonToken);
        $forStatement->forEndOfLoop = $this->parseExpressionList($forStatement);
        $forStatement->closeParen = $this->eat1(TokenKind::CloseParenToken);
        $forStatement->colon = $this->eatOptional1(TokenKind::ColonToken);
        if ($forStatement->colon !== null) {
            $forStatement->statements = $this->parseList($forStatement, ParseContext::ForStatementElements);
            $forStatement->endFor = $this->eat1(TokenKind::EndForKeyword);
            $forStatement->endForSemicolon = $this->eatSemicolonOrAbortStatement();
        } elseif (!$this->checkToken(TokenKind::ScriptSectionEndTag)) {
            $forStatement->statements = $this->parseStatement($forStatement);
        }
        return $forStatement;
    }

    private function parseForeachStatement($parentNode) {
        $foreachStatement = new ForeachStatement();
        $foreachStatement->parent = $parentNode;
        $foreachStatement->foreach = $this->eat1(TokenKind::ForeachKeyword);
        $foreachStatement->openParen = $this->eat1(TokenKind::OpenParenToken);
        $foreachStatement->forEachCollectionName = $this->parseExpression($foreachStatement);
        $foreachStatement->asKeyword = $this->eat1(TokenKind::AsKeyword);
        $foreachStatement->foreachKey = $this->tryParseForeachKey($foreachStatement);
        $foreachStatement->foreachValue = $this->parseForeachValue($foreachStatement);
        $foreachStatement->closeParen = $this->eat1(TokenKind::CloseParenToken);
        $foreachStatement->colon = $this->eatOptional1(TokenKind::ColonToken);
        if ($foreachStatement->colon !== null) {
            $foreachStatement->statements = $this->parseList($foreachStatement, ParseContext::ForeachStatementElements);
            $foreachStatement->endForeach = $this->eat1(TokenKind::EndForEachKeyword);
            $foreachStatement->endForeachSemicolon = $this->eatSemicolonOrAbortStatement();
        } elseif (!$this->checkToken(TokenKind::ScriptSectionEndTag)) {
            $foreachStatement->statements = $this->parseStatement($foreachStatement);
        }
        return $foreachStatement;
    }

    private function tryParseForeachKey($parentNode) {
        if (!$this->isExpressionStart($this->getCurrentToken())) {
            return null;
        }

        $startPos = $this->lexer->getCurrentPosition();
        $startToken = $this->getCurrentToken();
        $foreachKey = new ForeachKey();
        $foreachKey->parent = $parentNode;
        $foreachKey->expression = $this->parseExpression($foreachKey);

        if (!$this->checkToken(TokenKind::DoubleArrowToken)) {
            $this->lexer->setCurrentPosition($startPos);
            $this->token = $startToken;
            return null;
        }

        $foreachKey->arrow = $this->eat1(TokenKind::DoubleArrowToken);
        return $foreachKey;
    }

    private function parseForeachValue($parentNode) {
        $foreachValue = new ForeachValue();
        $foreachValue->parent = $parentNode;
        $foreachValue->ampersand = $this->eatOptional1(TokenKind::AmpersandToken);
        $foreachValue->expression = $this->parseExpression($foreachValue);
        return $foreachValue;
    }

    private function parseGotoStatement($parentNode) {
        $gotoStatement = new GotoStatement();
        $gotoStatement->parent = $parentNode;
        $gotoStatement->goto = $this->eat1(TokenKind::GotoKeyword);
        $gotoStatement->name = $this->eat1(TokenKind::Name);
        $gotoStatement->semicolon = $this->eatSemicolonOrAbortStatement();
        return $gotoStatement;
    }

    private function parseBreakOrContinueStatement($parentNode) {
        // TODO should be error checking if on top level
        $continueStatement = new BreakOrContinueStatement();
        $continueStatement->parent = $parentNode;
        $continueStatement->breakOrContinueKeyword = $this->eat(TokenKind::ContinueKeyword, TokenKind::BreakKeyword);

        if ($this->isExpressionStart($this->getCurrentToken())) {
            $continueStatement->breakoutLevel = $this->parseExpression($continueStatement);
        }

        $continueStatement->semicolon = $this->eatSemicolonOrAbortStatement();

        return $continueStatement;
    }

    private function parseReturnStatement($parentNode) {
        $returnStatement = new ReturnStatement();
        $returnStatement->parent = $parentNode;
        $returnStatement->returnKeyword = $this->eat1(TokenKind::ReturnKeyword);
        if ($this->isExpressionStart($this->getCurrentToken())) {
            $returnStatement->expression = $this->parseExpression($returnStatement);
        }
        $returnStatement->semicolon = $this->eatSemicolonOrAbortStatement();

        return $returnStatement;
    }

    /** @return ThrowExpression */
    private function parseThrowExpression($parentNode) {
        $throwExpression = new ThrowExpression();
        $throwExpression->parent = $parentNode;
        $throwExpression->throwKeyword = $this->eat1(TokenKind::ThrowKeyword);
        // TODO error for failures to parse expressions when not optional
        $throwExpression->expression = $this->parseExpression($throwExpression);

        return $throwExpression;
    }

    private function parseTryStatement($parentNode) {
        $tryStatement = new TryStatement();
        $tryStatement->parent = $parentNode;
        $tryStatement->tryKeyword = $this->eat1(TokenKind::TryKeyword);
        $tryStatement->compoundStatement = $this->parseCompoundStatement($tryStatement); // TODO verifiy this is only compound

        $tryStatement->catchClauses = []; // TODO - should be some standard for empty arrays vs. null?
        while ($this->checkToken(TokenKind::CatchKeyword)) {
            $tryStatement->catchClauses[] = $this->parseCatchClause($tryStatement);
        }

        if ($this->checkToken(TokenKind::FinallyKeyword)) {
            $tryStatement->finallyClause = $this->parseFinallyClause($tryStatement);
        }

        return $tryStatement;
    }

    private function parseCatchClause($parentNode) {
        $catchClause = new CatchClause();
        $catchClause->parent = $parentNode;
        $catchClause->catch = $this->eat1(TokenKind::CatchKeyword);
        $catchClause->openParen = $this->eat1(TokenKind::OpenParenToken);
        $catchClause->qualifiedNameList = $this->parseQualifiedNameCatchList($catchClause) ?? new MissingToken(TokenKind::QualifiedName, $this->token->fullStart); // TODO generate missing token or error if null
        $catchClause->variableName = $this->eatOptional1(TokenKind::VariableName);
        $catchClause->closeParen = $this->eat1(TokenKind::CloseParenToken);
        $catchClause->compoundStatement = $this->parseCompoundStatement($catchClause);

        return $catchClause;
    }

    private function parseFinallyClause($parentNode) {
        $finallyClause = new FinallyClause();
        $finallyClause->parent = $parentNode;
        $finallyClause->finallyToken = $this->eat1(TokenKind::FinallyKeyword);
        $finallyClause->compoundStatement = $this->parseCompoundStatement($finallyClause);

        return $finallyClause;
    }

    private function parseDeclareStatement($parentNode) {
        $declareStatement = new DeclareStatement();
        $declareStatement->parent = $parentNode;
        $declareStatement->declareKeyword = $this->eat1(TokenKind::DeclareKeyword);
        $declareStatement->openParen = $this->eat1(TokenKind::OpenParenToken);
        $this->parseAndSetDeclareDirectiveList($declareStatement);
        $declareStatement->closeParen = $this->eat1(TokenKind::CloseParenToken);

        if ($this->checkToken(TokenKind::SemicolonToken)) {
            $declareStatement->semicolon = $this->eatSemicolonOrAbortStatement();
        } elseif ($this->checkToken(TokenKind::ColonToken)) {
            $declareStatement->colon = $this->eat1(TokenKind::ColonToken);
            $declareStatement->statements = $this->parseList($declareStatement, ParseContext::DeclareStatementElements);
            $declareStatement->enddeclareKeyword = $this->eat1(TokenKind::EndDeclareKeyword);
            $declareStatement->semicolon = $this->eatSemicolonOrAbortStatement();
        } else {
            $declareStatement->statements = $this->parseStatement($declareStatement);
        }

        return $declareStatement;
    }

    /**
     * @param DeclareStatement $parentNode
     */
    private function parseAndSetDeclareDirectiveList($parentNode) {
        $declareDirectiveList = $this->parseDeclareDirectiveList($parentNode);

        $parentNode->declareDirectiveList = $declareDirectiveList ?? new MissingToken(TokenKind::Name, $this->token->fullStart);
    }

    /**
     * @param DeclareStatement $parentNode
     * @return DelimitedList\DeclareDirectiveList|null
     */
    private function parseDeclareDirectiveList($parentNode) {
        $declareDirectiveList = $this->parseDelimitedList(
            DelimitedList\DeclareDirectiveList::class,
            TokenKind::CommaToken,
            function ($token) {
                return $token->kind === TokenKind::Name;
            },
            $this->parseDeclareDirectiveFn(),
            $parentNode,
            false
        );

        return $declareDirectiveList;
    }

    private function parseDeclareDirectiveFn() {
        return function ($parentNode) {
            $declareDirective = new DeclareDirective();
            $declareDirective->parent = $parentNode;
            $declareDirective->name = $this->eat1(TokenKind::Name);
            $declareDirective->equals = $this->eat1(TokenKind::EqualsToken);
            $declareDirective->literal =
                $this->eat(
                    TokenKind::FloatingLiteralToken,
                    TokenKind::IntegerLiteralToken,
                    TokenKind::DecimalLiteralToken,
                    TokenKind::OctalLiteralToken,
                    TokenKind::HexadecimalLiteralToken,
                    TokenKind::BinaryLiteralToken,
                    TokenKind::InvalidOctalLiteralToken,
                    TokenKind::InvalidHexadecimalLiteral,
                    TokenKind::InvalidBinaryLiteral,
                    TokenKind::StringLiteralToken
                ); // TODO simplify

            return $declareDirective;
        };
    }

    private function parseSimpleVariable($parentNode) {
        return ($this->parseSimpleVariableFn())($parentNode);
    }

    private function parseSimpleVariableFn() {
        return function ($parentNode) {
            $token = $this->getCurrentToken();
            $variable = new Variable();
            $variable->parent = $parentNode;

            if ($token->kind === TokenKind::DollarToken) {
                $variable->dollar = $this->eat1(TokenKind::DollarToken);
                $token = $this->getCurrentToken();

                switch ($token->kind) {
                    case TokenKind::OpenBraceToken:
                        $variable->name = $this->parseBracedExpression($variable);
                        break;
                    case TokenKind::VariableName:
                    case TokenKind::StringVarname:
                    case TokenKind::DollarToken:
                        $variable->name = $this->parseSimpleVariable($variable);
                        break;
                    default:
                        $variable->name = new MissingToken(TokenKind::VariableName, $token->fullStart);
                        break;
                }
            } elseif ($token->kind === TokenKind::VariableName || $token->kind === TokenKind::StringVarname) {
                // TODO consider splitting into dollar and name.
                // StringVarname is the variable name without $, used in a template string e.g. `"${foo}"`
                $variable->name = $this->eat(TokenKind::VariableName, TokenKind::StringVarname);
            } else {
                $variable->name = new MissingToken(TokenKind::VariableName, $token->fullStart);
            }

            return $variable;
        };
    }

    private function parseYieldExpression($parentNode) {
        $yieldExpression = new YieldExpression();
        $yieldExpression->parent = $parentNode;
        $yieldExpression->yieldOrYieldFromKeyword = $this->eat(
            TokenKind::YieldFromKeyword,
            TokenKind::YieldKeyword
            );
        if ($yieldExpression->yieldOrYieldFromKeyword->kind === TokenKind::YieldFromKeyword) {
            // Don't use parseArrayElement. E.g. `yield from &$varName` or `yield from $key => $varName` are both syntax errors
            $arrayElement = new ArrayElement();
            $arrayElement->parent = $yieldExpression;
            $arrayElement->elementValue = $this->parseExpression($arrayElement);
            $yieldExpression->arrayElement = $arrayElement;
        } else {
            // This is always an ArrayElement for backwards compatibilitiy.
            // TODO: Can this be changed to a non-ArrayElement in a future release?
            if ($this->isExpressionStart($this->getCurrentToken())) {
                // Both `yield expr;` and `yield;` are possible.
                $yieldExpression->arrayElement = $this->parseArrayElement($yieldExpression);
            } else {
                $yieldExpression->arrayElement = null;
            }
        }

        return $yieldExpression;
    }

    private function parseScriptInclusionExpression($parentNode) {
        $scriptInclusionExpression = new ScriptInclusionExpression();
        $scriptInclusionExpression->parent = $parentNode;
        $scriptInclusionExpression->requireOrIncludeKeyword =
            $this->eat(
                TokenKind::RequireKeyword, TokenKind::RequireOnceKeyword,
                TokenKind::IncludeKeyword, TokenKind::IncludeOnceKeyword
                );
        $scriptInclusionExpression->expression = $this->parseExpression($scriptInclusionExpression);
        return $scriptInclusionExpression;
    }

    /** @return EchoStatement */
    private function parseEchoStatement($parentNode) {
        $echoStatement = new EchoStatement();
        $echoStatement->parent = $parentNode;
        $echoStatement->echoKeyword = $this->eat1(TokenKind::EchoKeyword);
        $echoStatement->expressions =
            $this->parseExpressionList($echoStatement);
        $echoStatement->semicolon = $this->eatSemicolonOrAbortStatement();
        return $echoStatement;
    }

    private function parseListIntrinsicExpression($parentNode) {
        $listExpression = new ListIntrinsicExpression();
        $listExpression->parent = $parentNode;
        $listExpression->listKeyword = $this->eat1(TokenKind::ListKeyword);
        $listExpression->openParen = $this->eat1(TokenKind::OpenParenToken);
        // TODO - parse loosely as ArrayElementList, and validate parse tree later
        $listExpression->listElements =
            $this->parseArrayElementList($listExpression, DelimitedList\ListExpressionList::class);
        $listExpression->closeParen = $this->eat1(TokenKind::CloseParenToken);

        return $listExpression;
    }

    private function isArrayElementStart($token) {
        return ($this->isArrayElementStartFn())($token);
    }

    private function isArrayElementStartFn() {
        return function ($token) {
            return $token->kind === TokenKind::AmpersandToken || $token->kind === TokenKind::DotDotDotToken || $this->isExpressionStart($token);
        };
    }

    private function parseArrayElement($parentNode) {
        return ($this->parseArrayElementFn())($parentNode);
    }

    private function parseArrayElementFn() {
        return function ($parentNode) {
            $arrayElement = new ArrayElement();
            $arrayElement->parent = $parentNode;

            if ($this->checkToken(TokenKind::AmpersandToken)) {
                $arrayElement->byRef = $this->eat1(TokenKind::AmpersandToken);
                $arrayElement->elementValue = $this->parseExpression($arrayElement);
            } elseif ($this->checkToken(TokenKind::DotDotDotToken)) {
                $arrayElement->dotDotDot = $this->eat1(TokenKind::DotDotDotToken);
                $arrayElement->elementValue = $this->parseExpression($arrayElement);
            } else {
                $expression = $this->parseExpression($arrayElement);
                if ($this->checkToken(TokenKind::DoubleArrowToken)) {
                    $arrayElement->elementKey = $expression;
                    $arrayElement->arrowToken = $this->eat1(TokenKind::DoubleArrowToken);
                    $arrayElement->byRef = $this->eatOptional1(TokenKind::AmpersandToken); // TODO not okay for list expressions
                    $arrayElement->elementValue = $this->parseExpression($arrayElement);
                } else {
                    $arrayElement->elementValue = $expression;
                }
            }

            return $arrayElement;
        };
    }

    private function parseExpressionList($parentExpression) {
        return $this->parseDelimitedList(
            DelimitedList\ExpressionList::class,
            TokenKind::CommaToken,
            $this->isExpressionStartFn(),
            $this->parseExpressionFn(),
            $parentExpression
        );
    }

    private function parseUnsetStatement($parentNode) {
        $unsetStatement = new UnsetStatement();
        $unsetStatement->parent = $parentNode;

        $unsetStatement->unsetKeyword = $this->eat1(TokenKind::UnsetKeyword);
        $unsetStatement->openParen = $this->eat1(TokenKind::OpenParenToken);
        $unsetStatement->expressions = $this->parseExpressionList($unsetStatement);
        $unsetStatement->closeParen = $this->eat1(TokenKind::CloseParenToken);
        $unsetStatement->semicolon = $this->eatSemicolonOrAbortStatement();
        return $unsetStatement;
    }

    private function parseHaltCompilerStatement($parentNode) {
        $haltCompilerStatement = new HaltCompilerStatement();
        $haltCompilerStatement->parent = $parentNode;

        $haltCompilerStatement->haltCompilerKeyword = $this->eat1(TokenKind::HaltCompilerKeyword);
        $haltCompilerStatement->openParen = $this->eat1(TokenKind::OpenParenToken);
        $haltCompilerStatement->closeParen = $this->eat1(TokenKind::CloseParenToken);
        // There is an implicit ';' before the closing php tag.
        $haltCompilerStatement->semicolonOrCloseTag = $this->eat(TokenKind::SemicolonToken, TokenKind::ScriptSectionEndTag);
        // token_get_all() will return up to 3 tokens after __halt_compiler regardless of whether they're the right ones.
        // For invalid php snippets, combine the remaining tokens into InlineHtml
        $remainingTokens = [];
        while ($this->token->kind !== TokenKind::EndOfFileToken) {
            $remainingTokens[] = $this->token;
            $this->advanceToken();
        }
        if ($remainingTokens) {
            $firstToken = $remainingTokens[0];
            $lastToken = end($remainingTokens);
            $haltCompilerStatement->data = new Token(TokenKind::InlineHtml, $firstToken->fullStart, $firstToken->fullStart, $lastToken->fullStart + $lastToken->length - $firstToken->fullStart);
        }
        return $haltCompilerStatement;
    }

    private function parseArrayCreationExpression($parentNode) {
        $arrayExpression = new ArrayCreationExpression();
        $arrayExpression->parent = $parentNode;

        $arrayExpression->arrayKeyword = $this->eatOptional1(TokenKind::ArrayKeyword);

        $arrayExpression->openParenOrBracket = $arrayExpression->arrayKeyword !== null
            ? $this->eat1(TokenKind::OpenParenToken)
            : $this->eat1(TokenKind::OpenBracketToken);

        $arrayExpression->arrayElements = $this->parseArrayElementList($arrayExpression, DelimitedList\ArrayElementList::class);

        $arrayExpression->closeParenOrBracket = $arrayExpression->arrayKeyword !== null
            ? $this->eat1(TokenKind::CloseParenToken)
            : $this->eat1(TokenKind::CloseBracketToken);

        return $arrayExpression;
    }

    private function parseArrayElementList($listExpression, $className) {
        return $this->parseDelimitedList(
            $className,
            TokenKind::CommaToken,
            $this->isArrayElementStartFn(),
            $this->parseArrayElementFn(),
            $listExpression,
            true
        );
    }

    private function parseEmptyIntrinsicExpression($parentNode) {
        $emptyExpression = new EmptyIntrinsicExpression();
        $emptyExpression->parent = $parentNode;

        $emptyExpression->emptyKeyword = $this->eat1(TokenKind::EmptyKeyword);
        $emptyExpression->openParen = $this->eat1(TokenKind::OpenParenToken);
        $emptyExpression->expression = $this->parseExpression($emptyExpression);
        $emptyExpression->closeParen = $this->eat1(TokenKind::CloseParenToken);

        return $emptyExpression;
    }

    private function parseEvalIntrinsicExpression($parentNode) {
        $evalExpression = new EvalIntrinsicExpression();
        $evalExpression->parent = $parentNode;

        $evalExpression->evalKeyword = $this->eat1(TokenKind::EvalKeyword);
        $evalExpression->openParen = $this->eat1(TokenKind::OpenParenToken);
        $evalExpression->expression = $this->parseExpression($evalExpression);
        $evalExpression->closeParen = $this->eat1(TokenKind::CloseParenToken);

        return $evalExpression;
    }

    private function parseParenthesizedExpression($parentNode) {
        $parenthesizedExpression = new ParenthesizedExpression();
        $parenthesizedExpression->parent = $parentNode;

        $parenthesizedExpression->openParen = $this->eat1(TokenKind::OpenParenToken);
        $parenthesizedExpression->expression = $this->parseExpression($parenthesizedExpression);
        $parenthesizedExpression->closeParen = $this->eat1(TokenKind::CloseParenToken);

        return $parenthesizedExpression;
    }

    private function parseExitIntrinsicExpression($parentNode) {
        $exitExpression = new ExitIntrinsicExpression();
        $exitExpression->parent = $parentNode;

        $exitExpression->exitOrDieKeyword = $this->eat(TokenKind::ExitKeyword, TokenKind::DieKeyword);
        $exitExpression->openParen = $this->eatOptional1(TokenKind::OpenParenToken);
        if ($exitExpression->openParen !== null) {
            if ($this->isExpressionStart($this->getCurrentToken())) {
                $exitExpression->expression = $this->parseExpression($exitExpression);
            }
            $exitExpression->closeParen = $this->eat1(TokenKind::CloseParenToken);
        }

        return $exitExpression;
    }

    private function parsePrintIntrinsicExpression($parentNode) {
        $printExpression = new PrintIntrinsicExpression();
        $printExpression->parent = $parentNode;

        $printExpression->printKeyword = $this->eat1(TokenKind::PrintKeyword);
        $printExpression->expression = $this->parseExpression($printExpression);

        return $printExpression;
    }

    private function parseIssetIntrinsicExpression($parentNode) {
        $issetExpression = new IssetIntrinsicExpression();
        $issetExpression->parent = $parentNode;

        $issetExpression->issetKeyword = $this->eat1(TokenKind::IsSetKeyword);
        $issetExpression->openParen = $this->eat1(TokenKind::OpenParenToken);
        $issetExpression->expressions = $this->parseExpressionList($issetExpression);
        $issetExpression->closeParen = $this->eat1(TokenKind::CloseParenToken);

        return $issetExpression;
    }

    private function parseUnaryOpExpression($parentNode) {
        $unaryOpExpression = new UnaryOpExpression();
        $unaryOpExpression->parent = $parentNode;
        $unaryOpExpression->operator =
            $this->eat(TokenKind::PlusToken, TokenKind::MinusToken, TokenKind::ExclamationToken, TokenKind::TildeToken);
        $unaryOpExpression->operand = $this->parseUnaryExpressionOrHigher($unaryOpExpression);

        return $unaryOpExpression;
    }

    private function parseErrorControlExpression($parentNode) {
        $errorControlExpression = new ErrorControlExpression();
        $errorControlExpression->parent = $parentNode;

        $errorControlExpression->operator = $this->eat1(TokenKind::AtSymbolToken);
        $errorControlExpression->operand = $this->parseUnaryExpressionOrHigher($errorControlExpression);

        return $errorControlExpression;
    }

    private function parsePrefixUpdateExpression($parentNode) {
        $prefixUpdateExpression = new PrefixUpdateExpression();
        $prefixUpdateExpression->parent = $parentNode;

        $prefixUpdateExpression->incrementOrDecrementOperator = $this->eat(TokenKind::PlusPlusToken, TokenKind::MinusMinusToken);

        $prefixUpdateExpression->operand = $this->parsePrimaryExpression($prefixUpdateExpression);

        if (!($prefixUpdateExpression->operand instanceof MissingToken)) {
            $prefixUpdateExpression->operand = $this->parsePostfixExpressionRest($prefixUpdateExpression->operand, false);
        }

        // TODO also check operand expression validity
        return $prefixUpdateExpression;
    }

    private function parsePostfixExpressionRest($expression, $allowUpdateExpression = true) {
        $tokenKind = $this->getCurrentToken()->kind;

        // `--$a++` is invalid
        if ($allowUpdateExpression &&
            ($tokenKind === TokenKind::PlusPlusToken ||
            $tokenKind === TokenKind::MinusMinusToken)) {
            return $this->parseParsePostfixUpdateExpression($expression);
        }

        // TODO write tons of tests
        if (!($expression instanceof Variable ||
            $expression instanceof ParenthesizedExpression ||
            $expression instanceof QualifiedName ||
            $expression instanceof CallExpression ||
            $expression instanceof MemberAccessExpression ||
            $expression instanceof SubscriptExpression ||
            $expression instanceof ScopedPropertyAccessExpression ||
            $expression instanceof StringLiteral ||
            $expression instanceof ArrayCreationExpression
        )) {
            return $expression;
        }
        if ($tokenKind === TokenKind::ColonColonToken) {
            $expression = $this->parseScopedPropertyAccessExpression($expression, null);
            return $this->parsePostfixExpressionRest($expression);
        }

        $tokenKind = $this->getCurrentToken()->kind;

        if ($tokenKind === TokenKind::OpenBraceToken ||
            $tokenKind === TokenKind::OpenBracketToken) {
            $expression = $this->parseSubscriptExpression($expression);
            return $this->parsePostfixExpressionRest($expression);
        }

        if ($expression instanceof ArrayCreationExpression) {
            // Remaining postfix expressions are invalid, so abort
            return $expression;
        }

        if ($tokenKind === TokenKind::ArrowToken || $tokenKind === TokenKind::QuestionArrowToken) {
            $expression = $this->parseMemberAccessExpression($expression);
            return $this->parsePostfixExpressionRest($expression);
        }

        if ($tokenKind === TokenKind::OpenParenToken && !$this->isParsingUnparenthesizedObjectCreationExpression($expression)) {
            $expression = $this->parseCallExpressionRest($expression);

            if (!$this->checkToken(TokenKind::OpenParenToken)) {
                return $this->parsePostfixExpressionRest($expression);
            }
            if (
                $expression instanceof ParenthesizedExpression ||
                $expression instanceof CallExpression ||
                $expression instanceof SubscriptExpression) {
                // Continue parsing the remaining brackets for expressions
                // such as `(new Foo())()`, `foo()()`, `foo()['index']()`
                return $this->parsePostfixExpressionRest($expression);
            }
            return $expression;
        }

        // Reached the end of the postfix-expression, so return
        return $expression;
    }

    private function parseMemberName($parentNode) {
        $token = $this->getCurrentToken();
        switch ($token->kind) {
            case TokenKind::Name:
                $this->advanceToken(); // TODO all names should be Nodes
                return $token;
            case TokenKind::VariableName:
            case TokenKind::DollarToken:
                return $this->parseSimpleVariable($parentNode); // TODO should be simple-variable
            case TokenKind::OpenBraceToken:
                return $this->parseBracedExpression($parentNode);

            default:
                if (\in_array($token->kind, $this->nameOrKeywordOrReservedWordTokens)) {
                    $this->advanceToken();
                    $token->kind = TokenKind::Name;
                    return $token;
                }
        }
        return new MissingToken(TokenKind::MemberName, $token->fullStart);
    }

    private function isArgumentExpressionStartFn() {
        return function ($token) {
            return
                isset($this->argumentStartTokensSet[$token->kind]) || $this->isExpressionStart($token);
        };
    }

    private function parseArgumentExpressionFn() {
        return function ($parentNode) {
            $argumentExpression = new ArgumentExpression();
            $argumentExpression->parent = $parentNode;

            $nextToken = $this->lexer->getTokensArray()[$this->lexer->getCurrentPosition()] ?? null;
            if ($nextToken && $nextToken->kind === TokenKind::ColonToken) {
                $name = $this->token;
                $this->advanceToken();
                if ($name->kind === TokenKind::YieldFromKeyword || !\in_array($name->kind, $this->nameOrKeywordOrReservedWordTokens)) {
                    $name = new SkippedToken($name);
                } else {
                    $name->kind = TokenKind::Name;
                }
                $argumentExpression->name = $name;
                $argumentExpression->colonToken = $this->eat1(TokenKind::ColonToken);
            } else {
                $argumentExpression->dotDotDotToken = $this->eatOptional1(TokenKind::DotDotDotToken);
            }
            $argumentExpression->expression = $this->parseExpression($argumentExpression);
            return $argumentExpression;
        };
    }

    private function parseCallExpressionRest($expression) {
        $callExpression = new CallExpression();
        $callExpression->parent = $expression->parent;
        $expression->parent = $callExpression;
        $callExpression->callableExpression = $expression;
        $callExpression->openParen = $this->eat1(TokenKind::OpenParenToken);
        $callExpression->argumentExpressionList =
            $this->parseArgumentExpressionList($callExpression);
        $callExpression->closeParen = $this->eat1(TokenKind::CloseParenToken);
        return $callExpression;
    }

    private function parseParsePostfixUpdateExpression($prefixExpression) {
        $postfixUpdateExpression = new PostfixUpdateExpression();
        $postfixUpdateExpression->operand = $prefixExpression;
        $postfixUpdateExpression->parent = $prefixExpression->parent;
        $prefixExpression->parent = $postfixUpdateExpression;
        $postfixUpdateExpression->incrementOrDecrementOperator =
            $this->eat(TokenKind::PlusPlusToken, TokenKind::MinusMinusToken);
        return $postfixUpdateExpression;
    }

    private function parseBracedExpression($parentNode) {
        $bracedExpression = new BracedExpression();
        $bracedExpression->parent = $parentNode;

        $bracedExpression->openBrace = $this->eat1(TokenKind::OpenBraceToken);
        $bracedExpression->expression = $this->parseExpression($bracedExpression);
        $bracedExpression->closeBrace = $this->eat1(TokenKind::CloseBraceToken);

        return $bracedExpression;
    }

    private function parseSubscriptExpression($expression) : SubscriptExpression {
        $subscriptExpression = new SubscriptExpression();
        $subscriptExpression->parent = $expression->parent;
        $expression->parent = $subscriptExpression;

        $subscriptExpression->postfixExpression = $expression;
        $subscriptExpression->openBracketOrBrace = $this->eat(TokenKind::OpenBracketToken, TokenKind::OpenBraceToken);
        $subscriptExpression->accessExpression = $this->isExpressionStart($this->getCurrentToken())
            ? $this->parseExpression($subscriptExpression)
            : null; // TODO error if used in a getter

        if ($subscriptExpression->openBracketOrBrace->kind === TokenKind::OpenBraceToken) {
            $subscriptExpression->closeBracketOrBrace = $this->eat1(TokenKind::CloseBraceToken);
        } else {
            $subscriptExpression->closeBracketOrBrace = $this->eat1(TokenKind::CloseBracketToken);
        }

        return $subscriptExpression;
    }

    private function parseMemberAccessExpression($expression):MemberAccessExpression {
        $memberAccessExpression = new MemberAccessExpression();
        $memberAccessExpression->parent = $expression->parent;
        $expression->parent = $memberAccessExpression;

        $memberAccessExpression->dereferencableExpression = $expression;
        $memberAccessExpression->arrowToken = $this->eat(TokenKind::ArrowToken, TokenKind::QuestionArrowToken);
        $memberAccessExpression->memberName = $this->parseMemberName($memberAccessExpression);

        return $memberAccessExpression;
    }

    /**
     * @param Node|null $expression
     * @param Node|null $fallbackParentNode (Workaround for the invalid AST `use TraitName::foo as ::x`)
     */
    private function parseScopedPropertyAccessExpression($expression, $fallbackParentNode): ScopedPropertyAccessExpression {
        $scopedPropertyAccessExpression = new ScopedPropertyAccessExpression();
        $scopedPropertyAccessExpression->parent = $expression->parent ?? $fallbackParentNode;

        if ($expression instanceof Node) {
            $expression->parent = $scopedPropertyAccessExpression;

            // scopeResolutionQualifier does not accept `Node` but
            // `Expression|QualifiedName|Token`. I'm not sure if we can depend
            // on that being the case.
            //
            // @phpstan-ignore-next-line
            $scopedPropertyAccessExpression->scopeResolutionQualifier = $expression;
        }

        $scopedPropertyAccessExpression->doubleColon = $this->eat1(TokenKind::ColonColonToken);
        $scopedPropertyAccessExpression->memberName = $this->parseMemberName($scopedPropertyAccessExpression);

        return $scopedPropertyAccessExpression;
    }

    public function isParsingUnparenthesizedObjectCreationExpression($expression) {
        if (!$this->isParsingObjectCreationExpression) {
            return false;
        }
        if ($expression instanceof Token) {
            return true;
        }
        while ($expression->parent) {
            $expression = $expression->parent;
            if ($expression instanceof ObjectCreationExpression) {
                return true;
            } elseif ($expression instanceof ParenthesizedExpression) {
                return false;
            }
        }
    }

    private $isParsingObjectCreationExpression = false;

    private function parseObjectCreationExpression($parentNode) {
        $objectCreationExpression = new ObjectCreationExpression();
        $objectCreationExpression->parent = $parentNode;
        $objectCreationExpression->newKeword = $this->eat1(TokenKind::NewKeyword);
        // TODO - add tests for this scenario
        $oldIsParsingObjectCreationExpression = $this->isParsingObjectCreationExpression;
        $this->isParsingObjectCreationExpression = true;

        if ($this->getCurrentToken()->kind === TokenKind::AttributeToken) {
            // Attributes such as `new #[MyAttr] class` can only be used with anonymous class declarations.
            // But handle this like $objectCreationExpression->classMembers and leave it up to the applications to detect the invalid combination.
            $objectCreationExpression->attributes = $this->parseAttributeGroups($objectCreationExpression);
        }
        $objectCreationExpression->classTypeDesignator =
            $this->eatOptional1(TokenKind::ClassKeyword) ??
            $this->parseExpression($objectCreationExpression);

        $this->isParsingObjectCreationExpression = $oldIsParsingObjectCreationExpression;

        $objectCreationExpression->openParen = $this->eatOptional1(TokenKind::OpenParenToken);
        if ($objectCreationExpression->openParen !== null) {
            $objectCreationExpression->argumentExpressionList = $this->parseArgumentExpressionList($objectCreationExpression);
            $objectCreationExpression->closeParen = $this->eat1(TokenKind::CloseParenToken);
        }

        $objectCreationExpression->classBaseClause = $this->parseClassBaseClause($objectCreationExpression);
        $objectCreationExpression->classInterfaceClause = $this->parseClassInterfaceClause($objectCreationExpression);

        if ($this->getCurrentToken()->kind === TokenKind::OpenBraceToken) {
            $objectCreationExpression->classMembers = $this->parseClassMembers($objectCreationExpression);
        }

        return $objectCreationExpression;
    }

    /**
     * @return DelimitedList\ArgumentExpressionList|null
     */
    private function parseArgumentExpressionList($parentNode) {
        $list = $this->parseDelimitedList(
            DelimitedList\ArgumentExpressionList::class,
            TokenKind::CommaToken,
            $this->isArgumentExpressionStartFn(),
            $this->parseArgumentExpressionFn(),
            $parentNode
        );
        $children = $list->children ?? null;
        if (is_array($children) && \count($children) === 1) {
            $arg = $children[0];
            if ($arg instanceof ArgumentExpression) {
                if ($arg->dotDotDotToken && $arg->expression instanceof MissingToken && !$arg->colonToken && !$arg->name) {
                    $arg->expression = null;
                }
            }
        }
        return $list;
    }

    /**
     * @param Node|Token $leftOperand (should only be a token for invalid ASTs)
     * @param Token $questionToken
     * @param Node $fallbackParentNode
     */
    private function parseTernaryExpression($leftOperand, $questionToken, $fallbackParentNode):TernaryExpression {
        $ternaryExpression = new TernaryExpression();
        if ($leftOperand instanceof Node) {
            $ternaryExpression->parent = $leftOperand->parent;
            $leftOperand->parent = $ternaryExpression;
        } else {
            $ternaryExpression->parent = $fallbackParentNode;
        }
        $ternaryExpression->condition = $leftOperand;
        $ternaryExpression->questionToken = $questionToken;
        $ternaryExpression->ifExpression = $this->isExpressionStart($this->getCurrentToken()) ? $this->parseExpression($ternaryExpression) : null;
        $ternaryExpression->colonToken = $this->eat1(TokenKind::ColonToken);
        $ternaryExpression->elseExpression = $this->parseBinaryExpressionOrHigher(9, $ternaryExpression);
        $leftOperand = $ternaryExpression;
        return $leftOperand;
    }

    private function parseClassInterfaceClause($parentNode) {
        $classInterfaceClause = new ClassInterfaceClause();
        $classInterfaceClause->parent = $parentNode;
        $classInterfaceClause->implementsKeyword = $this->eatOptional1(TokenKind::ImplementsKeyword);

        if ($classInterfaceClause->implementsKeyword === null) {
            return null;
        }

        $classInterfaceClause->interfaceNameList =
            $this->parseQualifiedNameList($classInterfaceClause);
        return $classInterfaceClause;
    }

    private function parseClassBaseClause($parentNode) {
        $classBaseClause = new ClassBaseClause();
        $classBaseClause->parent = $parentNode;

        $classBaseClause->extendsKeyword = $this->eatOptional1(TokenKind::ExtendsKeyword);
        if ($classBaseClause->extendsKeyword === null) {
            return null;
        }
        $classBaseClause->baseClass = $this->parseQualifiedName($classBaseClause) ?? new MissingToken(TokenKind::QualifiedName, $this->token->fullStart);

        return $classBaseClause;
    }

    private function parseClassConstDeclaration($parentNode, $modifiers) {
        $classConstDeclaration = new ClassConstDeclaration();
        $classConstDeclaration->parent = $parentNode;

        $classConstDeclaration->modifiers = $modifiers;
        $classConstDeclaration->constKeyword = $this->eat1(TokenKind::ConstKeyword);
        $classConstDeclaration->constElements = $this->parseConstElements($classConstDeclaration);
        $classConstDeclaration->semicolon = $this->eat1(TokenKind::SemicolonToken);

        return $classConstDeclaration;
    }

    private function parseEnumCaseDeclaration($parentNode) {
        $enumCaseDeclaration = new EnumCaseDeclaration();
        $enumCaseDeclaration->parent = $parentNode;
        $enumCaseDeclaration->caseKeyword = $this->eat1(TokenKind::CaseKeyword);
        $enumCaseDeclaration->name = $this->eat($this->nameOrKeywordOrReservedWordTokens);
        $enumCaseDeclaration->equalsToken = $this->eatOptional1(TokenKind::EqualsToken);
        if ($enumCaseDeclaration->equalsToken !== null) {
            // TODO add post-parse rule that checks for invalid assignments
            $enumCaseDeclaration->assignment = $this->parseExpression($enumCaseDeclaration);
        }
        $enumCaseDeclaration->semicolon = $this->eat1(TokenKind::SemicolonToken);

        return $enumCaseDeclaration;
    }

    /**
     * @param Node $parentNode
     * @param Token[] $modifiers
     * @param Token|null $questionToken
     */
    private function parseRemainingPropertyDeclarationOrMissingMemberDeclaration($parentNode, $modifiers, $questionToken = null)
    {
        $typeDeclarationList = $this->tryParseParameterTypeDeclarationList(null);
        if ($this->getCurrentToken()->kind !== TokenKind::VariableName) {
            return $this->makeMissingMemberDeclaration($parentNode, $modifiers, $questionToken, $typeDeclarationList);
        }
        return $this->parsePropertyDeclaration($parentNode, $modifiers, $questionToken, $typeDeclarationList);
    }

    /**
     * @param Node $parentNode
     * @param Token[] $modifiers
     * @param Token|null $questionToken
     * @param DelimitedList\QualifiedNameList|null $typeDeclarationList
     */
    private function parsePropertyDeclaration($parentNode, $modifiers, $questionToken = null, $typeDeclarationList = null) {
        $propertyDeclaration = new PropertyDeclaration();
        $propertyDeclaration->parent = $parentNode;

        $propertyDeclaration->modifiers = $modifiers;
        $propertyDeclaration->questionToken = $questionToken;
        if ($typeDeclarationList) {
            $propertyDeclaration->typeDeclarationList = $typeDeclarationList;
            $typeDeclarationList->parent = $propertyDeclaration;
        } elseif ($questionToken) {
            $propertyDeclaration->typeDeclarationList = new MissingToken(TokenKind::PropertyType, $this->token->fullStart);
        }
        $propertyDeclaration->propertyElements = $this->parsePropertyElementList($propertyDeclaration);
        $requiresSemicolon = true;
        foreach ($propertyDeclaration->propertyElements->children ?? [] as $child) {
            if ($child instanceof PropertyElement && $child->hookList !== null) {
                $requiresSemicolon = false;
                break;
            }
        }
        if ($requiresSemicolon) {
            $propertyDeclaration->semicolon = $this->eat1(TokenKind::SemicolonToken);
        } else {
            $propertyDeclaration->semicolon = $this->eatOptional1(TokenKind::SemicolonToken);
        }

        return $propertyDeclaration;
    }

    private function parsePropertyElementList($parentNode): DelimitedList\PropertyElementList
    {
        return $this->parseDelimitedList(
            DelimitedList\PropertyElementList::class,
            TokenKind::CommaToken,
            $this->isPropertyElementStartFn(),
            function ($list) {
                return $this->parsePropertyElement($list);
            },
            $parentNode
        ) ?? new DelimitedList\PropertyElementList();
    }

    private function isPropertyElementStartFn()
    {
        return function (Token $token): bool {
            return $token->kind === TokenKind::VariableName || $token->kind === TokenKind::DollarToken;
        };
    }

    private function parsePropertyElement($parentNode): PropertyElement
    {
        $element = new PropertyElement();
        $element->parent = $parentNode;
        $element->variable = $this->parseSimpleVariable($element);
        $element->equalsToken = $this->eatOptional1(TokenKind::EqualsToken);
        if ($element->equalsToken !== null) {
            $element->initializer = $this->parseExpression($element);
        }
        if ($this->getCurrentToken()->kind === TokenKind::OpenBraceToken) {
            $element->hookList = $this->parsePropertyHookList($element);
        }
        return $element;
    }

    private function parsePropertyHookList(PropertyElement $parentNode): PropertyHookList
    {
        $hookList = new PropertyHookList();
        $hookList->parent = $parentNode;
        $hookList->openBrace = $this->eat1(TokenKind::OpenBraceToken);
        $hookList->hooks = [];

        while (true) {
            $token = $this->getCurrentToken();
            if ($token->kind === TokenKind::CloseBraceToken || $token->kind === TokenKind::EndOfFileToken) {
                break;
            }
            $previousFullStart = $token->fullStart;
            $hook = $this->parsePropertyHook($hookList);
            if ($hook instanceof PropertyHook) {
                $hookList->hooks[] = $hook;
            } else {
                // Ensure forward progress to avoid infinite loops.
                $this->advanceToken();
            }
            if ($this->getCurrentToken()->fullStart === $previousFullStart) {
                // No progress was made; advance once to prevent infinite loops.
                $this->advanceToken();
            }
        }

        $hookList->closeBrace = $this->eat1(TokenKind::CloseBraceToken);
        return $hookList;
    }

    private function parsePropertyHook(PropertyHookList $parentNode): ?PropertyHook
    {
        $hook = new PropertyHook();
        $hook->parent = $parentNode;

        if ($this->getCurrentToken()->kind === TokenKind::AttributeToken) {
            $hook->attributes = $this->parseAttributeGroups($hook);
        }

        $token = $this->getCurrentToken();
        if ($token->kind === TokenKind::Name) {
            $text = \strtolower(\trim($token->getText($this->sourceFile->fileContents)));
            if (\in_array($text, ['get', 'set', 'init'], true)) {
                $hook->hookKeyword = $token;
                $this->advanceToken();
            } else {
                $hook->hookKeyword = new MissingToken(TokenKind::Name, $token->fullStart);
                return $hook;
            }
        } else {
            $hook->hookKeyword = new MissingToken(TokenKind::Name, $token->fullStart);
            return $hook;
        }

        if ($this->checkToken(TokenKind::OpenParenToken)) {
            $hook->openParen = $this->eat1(TokenKind::OpenParenToken);
            $hook->parameterList = $this->parseDelimitedList(
                DelimitedList\ParameterDeclarationList::class,
                TokenKind::CommaToken,
                $this->isParameterStartFn(),
                $this->parseParameterFn(),
                $hook
            );
            $hook->closeParen = $this->eat1(TokenKind::CloseParenToken);
        }

        if ($this->checkToken(TokenKind::DoubleArrowToken)) {
            $hook->arrowToken = $this->eat1(TokenKind::DoubleArrowToken);
            $hook->expression = $this->parseExpression($hook);
            $hook->semicolon = $this->eatOptional1(TokenKind::SemicolonToken) ?? new MissingToken(TokenKind::SemicolonToken, $this->getCurrentToken()->fullStart);
        } else {
            $hook->compoundStatement = $this->parseCompoundStatement($hook);
        }

        return $hook;
    }

    /**
     * Parse a comma separated qualified name list (e.g. interfaces implemented by a class)
     *
     * @param Node $parentNode
     * @return DelimitedList\QualifiedNameList
     */
    private function parseQualifiedNameList($parentNode) {
        return $this->parseDelimitedList(
            DelimitedList\QualifiedNameList::class,
            TokenKind::CommaToken,
            $this->isQualifiedNameStartFn(),
            $this->parseQualifiedNameFn(),
            $parentNode);
    }

    private function parseQualifiedNameCatchList($parentNode) {
        // catch blocks don't support intersection types.
        $result = $this->parseDelimitedList(
            DelimitedList\QualifiedNameList::class,
            TokenKind::BarToken,
            $this->isQualifiedNameStartForCatchFn(),
            $this->parseQualifiedNameFn(),
            $parentNode);

        // Add a MissingToken so that this will Warn about `catch (T| $x) {}`
        // TODO: Make this a reusable abstraction?
        if ($result && (end($result->children)->kind ?? null) === TokenKind::BarToken) {
            $result->children[] = new MissingToken(TokenKind::Name, $this->token->fullStart);
        }
        return $result;
    }

    private function parseInterfaceDeclaration($parentNode): InterfaceDeclaration {
        $interfaceDeclaration = new InterfaceDeclaration(); // TODO verify not nested
        $interfaceDeclaration->parent = $parentNode;
        $interfaceDeclaration->interfaceKeyword = $this->eat1(TokenKind::InterfaceKeyword);
        $interfaceDeclaration->name = $this->eat1(TokenKind::Name);
        $interfaceDeclaration->interfaceBaseClause = $this->parseInterfaceBaseClause($interfaceDeclaration);
        $interfaceDeclaration->interfaceMembers = $this->parseInterfaceMembers($interfaceDeclaration);
        return $interfaceDeclaration;
    }

    private function parseInterfaceMembers($parentNode) : InterfaceMembers {
        $interfaceMembers = new InterfaceMembers();
        $interfaceMembers->openBrace = $this->eat1(TokenKind::OpenBraceToken);
        $interfaceMembers->interfaceMemberDeclarations = $this->parseList($interfaceMembers, ParseContext::InterfaceMembers);
        $interfaceMembers->closeBrace = $this->eat1(TokenKind::CloseBraceToken);
        $interfaceMembers->parent = $parentNode;
        return $interfaceMembers;
    }

    private function isInterfaceMemberDeclarationStart(Token $token) {
        switch ($token->kind) {
            // visibility-modifier
            case TokenKind::PublicKeyword:
            case TokenKind::ProtectedKeyword:
            case TokenKind::PrivateKeyword:

            // static-modifier
            case TokenKind::StaticKeyword:

            // readonly-modifier
            case TokenKind::ReadonlyKeyword:

            // class-modifier
            case TokenKind::AbstractKeyword:
            case TokenKind::FinalKeyword:

            case TokenKind::ConstKeyword:

            case TokenKind::FunctionKeyword:

            case TokenKind::AttributeToken:
                return true;
        }
        return false;
    }

    private function parseInterfaceElementFn() {
        return function ($parentNode) {
            $modifiers = $this->parseModifiers();

            $token = $this->getCurrentToken();
            switch ($token->kind) {
                case TokenKind::ConstKeyword:
                    return $this->parseClassConstDeclaration($parentNode, $modifiers);

                case TokenKind::FunctionKeyword:
                    return $this->parseMethodDeclaration($parentNode, $modifiers);

                case TokenKind::AttributeToken:
                    return $this->parseAttributeStatement($parentNode);

                default:
                    $missingInterfaceMemberDeclaration = new MissingMemberDeclaration();
                    $missingInterfaceMemberDeclaration->parent = $parentNode;
                    $missingInterfaceMemberDeclaration->modifiers = $modifiers;
                    return $missingInterfaceMemberDeclaration;
            }
        };
    }

    private function parseInterfaceBaseClause($parentNode) {
        $interfaceBaseClause = new InterfaceBaseClause();
        $interfaceBaseClause->parent = $parentNode;

        $interfaceBaseClause->extendsKeyword = $this->eatOptional1(TokenKind::ExtendsKeyword);
        if (isset($interfaceBaseClause->extendsKeyword)) {
            $interfaceBaseClause->interfaceNameList = $this->parseQualifiedNameList($interfaceBaseClause);
        } else {
            return null;
        }

        return $interfaceBaseClause;
    }

    private function parseNamespaceDefinition($parentNode) {
        $namespaceDefinition = new NamespaceDefinition();
        $namespaceDefinition->parent = $parentNode;

        $namespaceDefinition->namespaceKeyword = $this->eat1(TokenKind::NamespaceKeyword);

        if (!$this->checkToken(TokenKind::NamespaceKeyword)) {
            $namespaceDefinition->name = $this->parseQualifiedName($namespaceDefinition);
        }

        if ($this->checkToken(TokenKind::OpenBraceToken)) {
            $namespaceDefinition->compoundStatementOrSemicolon = $this->parseCompoundStatement($namespaceDefinition);
        } else {
            if (!$namespaceDefinition->name) {
                // only optional with compound statement block
                $namespaceDefinition->name = new MissingToken(TokenKind::QualifiedName, $this->token->fullStart);
            }
            $namespaceDefinition->compoundStatementOrSemicolon = $this->eatSemicolonOrAbortStatement();
        }

        return $namespaceDefinition;
    }

    private function parseNamespaceUseDeclaration($parentNode) {
        $namespaceUseDeclaration = new NamespaceUseDeclaration();
        $namespaceUseDeclaration->parent = $parentNode;
        $namespaceUseDeclaration->useKeyword = $this->eat1(TokenKind::UseKeyword);
        $namespaceUseDeclaration->functionOrConst = $this->eatOptional(TokenKind::FunctionKeyword, TokenKind::ConstKeyword);
        $namespaceUseDeclaration->useClauses = $this->parseNamespaceUseClauseList($namespaceUseDeclaration);
        $namespaceUseDeclaration->semicolon = $this->eatSemicolonOrAbortStatement();
        return $namespaceUseDeclaration;
    }

    private function parseNamespaceUseClauseList($parentNode): ?DelimitedList\NamespaceUseClauseList {
        return $this->parseDelimitedList(
            DelimitedList\NamespaceUseClauseList::class,
            TokenKind::CommaToken,
            function ($token) {
                return $this->isQualifiedNameStart($token) || $token->kind === TokenKind::FunctionKeyword || $token->kind === TokenKind::ConstKeyword;
            },
            function ($parentNode) {
                $namespaceUseClause = new NamespaceUseClause();
                $namespaceUseClause->parent = $parentNode;
                $namespaceUseClause->namespaceName = $this->parseQualifiedName($namespaceUseClause);
                if ($this->checkToken(TokenKind::OpenBraceToken)) {
                    $namespaceUseClause->openBrace = $this->eat1(TokenKind::OpenBraceToken);
                    $namespaceUseClause->groupClauses = $this->parseNamespaceUseGroupClauseList($namespaceUseClause);
                    $namespaceUseClause->closeBrace = $this->eat1(TokenKind::CloseBraceToken);
                } else {
                    if (!$namespaceUseClause->namespaceName) {
                        $namespaceUseClause->namespaceName = new MissingToken(TokenKind::QualifiedName, $this->token->fullStart);
                    }
                    if ($this->checkToken(TokenKind::AsKeyword)) {
                        $namespaceUseClause->namespaceAliasingClause = $this->parseNamespaceAliasingClause($namespaceUseClause);
                    }
                }

                return $namespaceUseClause;
            },
            $parentNode
        );
    }

    private function parseNamespaceUseGroupClauseList($parentNode): ?DelimitedList\NamespaceUseGroupClauseList {
        return $this->parseDelimitedList(
            DelimitedList\NamespaceUseGroupClauseList::class,
            TokenKind::CommaToken,
            function ($token) {
                return $this->isQualifiedNameStart($token) || $token->kind === TokenKind::FunctionKeyword || $token->kind === TokenKind::ConstKeyword;
            },
            function ($parentNode) {
                $namespaceUseGroupClause = new NamespaceUseGroupClause();
                $namespaceUseGroupClause->parent = $parentNode;

                $namespaceUseGroupClause->functionOrConst = $this->eatOptional(TokenKind::FunctionKeyword, TokenKind::ConstKeyword);
                $namespaceUseGroupClause->namespaceName = $this->parseQualifiedName($namespaceUseGroupClause) ?? new MissingToken(TokenKind::QualifiedName, $this->token->fullStart);
                if ($this->checkToken(TokenKind::AsKeyword)) {
                    $namespaceUseGroupClause->namespaceAliasingClause = $this->parseNamespaceAliasingClause($namespaceUseGroupClause);
                }

                return $namespaceUseGroupClause;
            },
            $parentNode
        );
    }

    private function parseNamespaceAliasingClause($parentNode) {
        $namespaceAliasingClause = new NamespaceAliasingClause();
        $namespaceAliasingClause->parent = $parentNode;
        $namespaceAliasingClause->asKeyword = $this->eat1(TokenKind::AsKeyword);
        $namespaceAliasingClause->name = $this->eat1(TokenKind::Name);
        return $namespaceAliasingClause;
    }

    private function parseTraitDeclaration($parentNode) {
        $traitDeclaration = new TraitDeclaration();
        $traitDeclaration->parent = $parentNode;

        $traitDeclaration->traitKeyword = $this->eat1(TokenKind::TraitKeyword);
        $traitDeclaration->name = $this->eat1(TokenKind::Name);

        $traitDeclaration->traitMembers = $this->parseTraitMembers($traitDeclaration);

        return $traitDeclaration;
    }

    private function parseTraitMembers($parentNode) {
        $traitMembers = new TraitMembers();
        $traitMembers->parent = $parentNode;

        $traitMembers->openBrace = $this->eat1(TokenKind::OpenBraceToken);

        $traitMembers->traitMemberDeclarations = $this->parseList($traitMembers, ParseContext::TraitMembers);

        $traitMembers->closeBrace = $this->eat1(TokenKind::CloseBraceToken);

        return $traitMembers;
    }

    private function isTraitMemberDeclarationStart($token) {
        switch ($token->kind) {
            // property-declaration
            case TokenKind::VariableName:

            // modifiers
            case TokenKind::PublicKeyword:
            case TokenKind::ProtectedKeyword:
            case TokenKind::PrivateKeyword:
            case TokenKind::VarKeyword:
            case TokenKind::StaticKeyword:
            case TokenKind::AbstractKeyword:
            case TokenKind::FinalKeyword:
            case TokenKind::ReadonlyKeyword:
            case TokenKind::ConstKeyword:

            // method-declaration
            case TokenKind::FunctionKeyword:

            // trait-use-clauses
            case TokenKind::UseKeyword:

            // attributes
            case TokenKind::AttributeToken:
                return true;
        }
        return false;
    }

    private function parseTraitElementFn() {
        return function ($parentNode) {
            $modifiers = $this->parseModifiers();

            $token = $this->getCurrentToken();
            switch ($token->kind) {
                case TokenKind::ConstKeyword:
                    return $this->parseClassConstDeclaration($parentNode, $modifiers);

                case TokenKind::FunctionKeyword:
                    return $this->parseMethodDeclaration($parentNode, $modifiers);

                case TokenKind::QuestionToken:
                    return $this->parseRemainingPropertyDeclarationOrMissingMemberDeclaration(
                        $parentNode,
                        $modifiers,
                        $this->eat1(TokenKind::QuestionToken)
                    );
                case TokenKind::VariableName:
                    return $this->parsePropertyDeclaration($parentNode, $modifiers);

                case TokenKind::UseKeyword:
                    return $this->parseTraitUseClause($parentNode);

                case TokenKind::AttributeToken:
                    return $this->parseAttributeStatement($parentNode);

                default:
                    return $this->parseRemainingPropertyDeclarationOrMissingMemberDeclaration($parentNode, $modifiers);
            }
        };
    }

    private function parseEnumDeclaration($parentNode) {
        $enumDeclaration = new EnumDeclaration();
        $enumDeclaration->parent = $parentNode;

        $enumDeclaration->enumKeyword = $this->eat1(TokenKind::EnumKeyword);
        $enumDeclaration->name = $this->eat1(TokenKind::Name);
        $enumDeclaration->colonToken = $this->eatOptional1(TokenKind::ColonToken);
        if ($enumDeclaration->colonToken !== null) {
            $enumDeclaration->enumType = $this->tryParseParameterTypeDeclaration($enumDeclaration)
                ?: new MissingToken(TokenKind::EnumType, $this->token->fullStart);
        }

        $enumDeclaration->enumInterfaceClause = $this->parseEnumInterfaceClause($enumDeclaration);
        $enumDeclaration->enumMembers = $this->parseEnumMembers($enumDeclaration);

        return $enumDeclaration;
    }

    private function parseEnumInterfaceClause(EnumDeclaration $enumDeclaration): ?EnumInterfaceClause {
        $enumInterfaceClause = new EnumInterfaceClause();
        $enumInterfaceClause->parent = $enumDeclaration;
        $enumInterfaceClause->implementsKeyword = $this->eatOptional1(TokenKind::ImplementsKeyword);

        if ($enumInterfaceClause->implementsKeyword === null) {
            return null;
        }

        $enumInterfaceClause->interfaceNameList =
            $this->parseQualifiedNameList($enumInterfaceClause);
        return $enumInterfaceClause;
    }


    private function parseEnumMembers($parentNode) {
        $enumMembers = new EnumMembers();
        $enumMembers->parent = $parentNode;

        $enumMembers->openBrace = $this->eat1(TokenKind::OpenBraceToken);

        $enumMembers->enumMemberDeclarations = $this->parseList($enumMembers, ParseContext::EnumMembers);

        $enumMembers->closeBrace = $this->eat1(TokenKind::CloseBraceToken);

        return $enumMembers;
    }

    private function isEnumMemberDeclarationStart($token) {
        switch ($token->kind) {
            // modifiers
            case TokenKind::PublicKeyword:
            case TokenKind::ProtectedKeyword:
            case TokenKind::PrivateKeyword:
            case TokenKind::StaticKeyword:
            case TokenKind::AbstractKeyword:
            case TokenKind::FinalKeyword:

            // method-declaration
            case TokenKind::FunctionKeyword:

            // trait-use-clauses (enums can use traits)
            case TokenKind::UseKeyword:

            // cases and constants
            case TokenKind::CaseKeyword:
            case TokenKind::ConstKeyword:

            // attributes
            case TokenKind::AttributeToken:
                return true;
        }
        return false;
    }

    private function parseEnumElementFn() {
        return function ($parentNode) {
            $modifiers = $this->parseModifiers();

            $token = $this->getCurrentToken();
            switch ($token->kind) {
                // TODO: CaseKeyword
                case TokenKind::CaseKeyword:
                    return $this->parseEnumCaseDeclaration($parentNode);

                case TokenKind::ConstKeyword:
                    return $this->parseClassConstDeclaration($parentNode, $modifiers);

                case TokenKind::FunctionKeyword:
                    return $this->parseMethodDeclaration($parentNode, $modifiers);

                case TokenKind::QuestionToken:
                    return $this->parseRemainingPropertyDeclarationOrMissingMemberDeclaration(
                        $parentNode,
                        $modifiers,
                        $this->eat1(TokenKind::QuestionToken)
                    );
                case TokenKind::VariableName:
                    return $this->parsePropertyDeclaration($parentNode, $modifiers);

                case TokenKind::UseKeyword:
                    return $this->parseTraitUseClause($parentNode);

                case TokenKind::AttributeToken:
                    return $this->parseAttributeStatement($parentNode);

                default:
                    return $this->parseRemainingPropertyDeclarationOrMissingMemberDeclaration($parentNode, $modifiers);
            }
        };
    }


    /**
     * @param Node $parentNode
     * @param Token[] $modifiers
     * @param Token $questionToken
     * @param DelimitedList\QualifiedNameList|null $typeDeclarationList
     */
    private function makeMissingMemberDeclaration($parentNode, $modifiers, $questionToken = null, $typeDeclarationList = null) {
        $missingTraitMemberDeclaration = new MissingMemberDeclaration();
        $missingTraitMemberDeclaration->parent = $parentNode;
        $missingTraitMemberDeclaration->modifiers = $modifiers;
        $missingTraitMemberDeclaration->questionToken = $questionToken;
        if ($typeDeclarationList) {
            $missingTraitMemberDeclaration->typeDeclarationList = $typeDeclarationList;
            $missingTraitMemberDeclaration->typeDeclarationList->parent = $missingTraitMemberDeclaration;
        } elseif ($questionToken) {
            $missingTraitMemberDeclaration->typeDeclarationList = new MissingToken(TokenKind::PropertyType, $this->token->fullStart);
        }
        return $missingTraitMemberDeclaration;
    }

    private function parseTraitUseClause($parentNode) {
        $traitUseClause = new TraitUseClause();
        $traitUseClause->parent = $parentNode;

        $traitUseClause->useKeyword = $this->eat1(TokenKind::UseKeyword);
        $traitUseClause->traitNameList = $this->parseQualifiedNameList($traitUseClause);

        $traitUseClause->semicolonOrOpenBrace = $this->eat(TokenKind::OpenBraceToken, TokenKind::SemicolonToken);
        if ($traitUseClause->semicolonOrOpenBrace->kind === TokenKind::OpenBraceToken) {
            $traitUseClause->traitSelectAndAliasClauses = $this->parseTraitSelectAndAliasClauseList($traitUseClause);
            $traitUseClause->closeBrace = $this->eat1(TokenKind::CloseBraceToken);
        }

        return $traitUseClause;
    }

    private function parseTraitSelectAndAliasClauseList($parentNode) {
        return $this->parseDelimitedList(
            DelimitedList\TraitSelectOrAliasClauseList::class,
            TokenKind::SemicolonToken,
            $this->isQualifiedNameStartFn(),
            $this->parseTraitSelectOrAliasClauseFn(),
            $parentNode
        );
    }

    private function parseTraitSelectOrAliasClauseFn() {
        return function ($parentNode) {
            $traitSelectAndAliasClause = new TraitSelectOrAliasClause();
            $traitSelectAndAliasClause->parent = $parentNode;
            $traitSelectAndAliasClause->name = // TODO update spec
                $this->parseQualifiedNameOrScopedPropertyAccessExpression($traitSelectAndAliasClause);

            $traitSelectAndAliasClause->asOrInsteadOfKeyword = $this->eat(TokenKind::AsKeyword, TokenKind::InsteadOfKeyword);
            $traitSelectAndAliasClause->modifiers = $this->parseModifiers(); // TODO accept all modifiers, verify later

            if ($traitSelectAndAliasClause->asOrInsteadOfKeyword->kind === TokenKind::InsteadOfKeyword) {
                $traitSelectAndAliasClause->targetNameList = $this->parseQualifiedNameList($traitSelectAndAliasClause);
            } else {
                $traitSelectAndAliasClause->targetNameList =
                    $this->parseQualifiedNameOrScopedPropertyAccessExpression($traitSelectAndAliasClause);
            }

            // TODO errors for insteadof/as
            return $traitSelectAndAliasClause;
        };
    }

    private function parseQualifiedNameOrScopedPropertyAccessExpression($parentNode) {
        $qualifiedNameOrScopedProperty = $this->parseQualifiedName($parentNode);
        if ($this->getCurrentToken()->kind === TokenKind::ColonColonToken) {
            $qualifiedNameOrScopedProperty = $this->parseScopedPropertyAccessExpression($qualifiedNameOrScopedProperty, $parentNode);
        }
        return $qualifiedNameOrScopedProperty;
    }

    private function parseGlobalDeclaration($parentNode) {
        $globalDeclaration = new GlobalDeclaration();
        $globalDeclaration->parent = $parentNode;

        $globalDeclaration->globalKeyword = $this->eat1(TokenKind::GlobalKeyword);
        $globalDeclaration->variableNameList = $this->parseDelimitedList(
            DelimitedList\VariableNameList::class,
            TokenKind::CommaToken,
            $this->isVariableNameStartFn(),
            $this->parseSimpleVariableFn(),
            $globalDeclaration
        );

        $globalDeclaration->semicolon = $this->eatSemicolonOrAbortStatement();

        return $globalDeclaration;
    }

    private function parseFunctionStaticDeclaration($parentNode) {
        $functionStaticDeclaration = new FunctionStaticDeclaration();
        $functionStaticDeclaration->parent = $parentNode;

        $functionStaticDeclaration->staticKeyword = $this->eat1(TokenKind::StaticKeyword);
        $functionStaticDeclaration->staticVariableNameList = $this->parseDelimitedList(
            DelimitedList\StaticVariableNameList::class,
            TokenKind::CommaToken,
            function ($token) {
                return $token->kind === TokenKind::VariableName;
            },
            $this->parseStaticVariableDeclarationFn(),
            $functionStaticDeclaration
        );
        $functionStaticDeclaration->semicolon = $this->eatSemicolonOrAbortStatement();

        return $functionStaticDeclaration;
    }

    private function isVariableNameStartFn() {
        return function ($token) {
            return $token->kind === TokenKind::VariableName || $token->kind === TokenKind::DollarToken;
        };
    }

    private function parseStaticVariableDeclarationFn() {
        return function ($parentNode) {
            $staticVariableDeclaration = new StaticVariableDeclaration();
            $staticVariableDeclaration->parent = $parentNode;
            $staticVariableDeclaration->variableName = $this->eat1(TokenKind::VariableName);
            $staticVariableDeclaration->equalsToken = $this->eatOptional1(TokenKind::EqualsToken);
            if ($staticVariableDeclaration->equalsToken !== null) {
                // TODO add post-parse rule that checks for invalid assignments
                $staticVariableDeclaration->assignment = $this->parseExpression($staticVariableDeclaration);
            }
            return $staticVariableDeclaration;
        };
    }

    private function parseConstDeclaration($parentNode) {
        $constDeclaration = new ConstDeclaration();
        $constDeclaration->parent = $parentNode;

        $constDeclaration->constKeyword = $this->eat1(TokenKind::ConstKeyword);
        $constDeclaration->constElements = $this->parseConstElements($constDeclaration);
        $constDeclaration->semicolon = $this->eatSemicolonOrAbortStatement();

        return $constDeclaration;
    }

    private function parseConstElements($parentNode) {
        return $this->parseDelimitedList(
            DelimitedList\ConstElementList::class,
            TokenKind::CommaToken,
            function ($token) {
                return \in_array($token->kind, $this->nameOrKeywordOrReservedWordTokens);
            },
            $this->parseConstElementFn(),
            $parentNode
        );
    }

    private function parseConstElementFn() {
        return function ($parentNode) {
            $constElement = new ConstElement();
            $constElement->parent = $parentNode;
            $constElement->name = $this->getCurrentToken();
            $this->advanceToken();
            $constElement->name->kind = TokenKind::Name; // to support keyword names
            $constElement->equalsToken = $this->eat1(TokenKind::EqualsToken);
            // TODO add post-parse rule that checks for invalid assignments
            $constElement->assignment = $this->parseExpression($constElement);
            return $constElement;
        };
    }

    private function parseCastExpression($parentNode) {
        $castExpression = new CastExpression();
        $castExpression->parent = $parentNode;
        $castExpression->castType = $this->eat(
            TokenKind::ArrayCastToken,
            TokenKind::BoolCastToken,
            TokenKind::DoubleCastToken,
            TokenKind::IntCastToken,
            TokenKind::ObjectCastToken,
            TokenKind::StringCastToken,
            TokenKind::UnsetCastToken
        );

        $castExpression->operand = $this->parseUnaryExpressionOrHigher($castExpression);

        return $castExpression;
    }

    private function parseCastExpressionGranular($parentNode) {
        $castExpression = new CastExpression();
        $castExpression->parent = $parentNode;

        $castExpression->openParen = $this->eat1(TokenKind::OpenParenToken);
        $castExpression->castType = $this->eat(
            TokenKind::ArrayKeyword,
            TokenKind::BinaryReservedWord,
            TokenKind::BoolReservedWord,
            TokenKind::BooleanReservedWord,
            TokenKind::DoubleReservedWord,
            TokenKind::IntReservedWord,
            TokenKind::IntegerReservedWord,
            TokenKind::FloatReservedWord,
            TokenKind::ObjectReservedWord,
            TokenKind::RealReservedWord,
            TokenKind::StringReservedWord,
            TokenKind::UnsetKeyword
        );
        $castExpression->closeParen = $this->eat1(TokenKind::CloseParenToken);
        $castExpression->operand = $this->parseUnaryExpressionOrHigher($castExpression);

        return $castExpression;
    }

    private function parseAnonymousFunctionCreationExpression($parentNode) {
        $staticModifier = $this->eatOptional1(TokenKind::StaticKeyword);
        if ($this->getCurrentToken()->kind === TokenKind::FnKeyword) {
            return $this->parseArrowFunctionCreationExpression($parentNode, $staticModifier);
        }
        $anonymousFunctionCreationExpression = new AnonymousFunctionCreationExpression();
        $anonymousFunctionCreationExpression->parent = $parentNode;

        $anonymousFunctionCreationExpression->staticModifier = $staticModifier;
        $this->parseFunctionType($anonymousFunctionCreationExpression, false, true);

        return $anonymousFunctionCreationExpression;
    }

    private function parseArrowFunctionCreationExpression($parentNode, $staticModifier) : ArrowFunctionCreationExpression {
        $arrowFunction = new ArrowFunctionCreationExpression();
        $arrowFunction->parent = $parentNode;
        $arrowFunction->staticModifier = $staticModifier;

        $arrowFunction->functionKeyword = $this->eat1(TokenKind::FnKeyword);
        $arrowFunction->byRefToken = $this->eatOptional1(TokenKind::AmpersandToken);
        $arrowFunction->name = $this->eatOptional($this->nameOrKeywordOrReservedWordTokens);

        if (isset($arrowFunction->name)) {
            // Anonymous functions should not have names.
            // This is based on the code for AnonymousFunctionCreationExpression.
            $arrowFunction->name->kind = TokenKind::Name;
            $arrowFunction->name = new SkippedToken($arrowFunction->name); // TODO instead handle this during post-walk
        }

        $arrowFunction->openParen = $this->eat1(TokenKind::OpenParenToken);
        $arrowFunction->parameters = $this->parseDelimitedList(
            DelimitedList\ParameterDeclarationList::class,
            TokenKind::CommaToken,
            $this->isParameterStartFn(),
            $this->parseParameterFn(),
            $arrowFunction);
        $arrowFunction->closeParen = $this->eat1(TokenKind::CloseParenToken);

        if ($this->checkToken(TokenKind::ColonToken)) {
            $arrowFunction->colonToken = $this->eat1(TokenKind::ColonToken);
            $arrowFunction->questionToken = $this->eatOptional1(TokenKind::QuestionToken);
            $this->parseAndSetReturnTypeDeclarationList($arrowFunction);
        }

        $arrowFunction->arrowToken = $this->eat1(TokenKind::DoubleArrowToken);
        $arrowFunction->resultExpression = $this->parseExpression($arrowFunction);

        return $arrowFunction;
    }

    private function parseAnonymousFunctionUseClause($parentNode) {
        $anonymousFunctionUseClause = new AnonymousFunctionUseClause();
        $anonymousFunctionUseClause->parent = $parentNode;

        $anonymousFunctionUseClause->useKeyword = $this->eatOptional1(TokenKind::UseKeyword);
        if ($anonymousFunctionUseClause->useKeyword === null) {
            return null;
        }
        $anonymousFunctionUseClause->openParen = $this->eat1(TokenKind::OpenParenToken);
        $anonymousFunctionUseClause->useVariableNameList = $this->parseDelimitedList(
            DelimitedList\UseVariableNameList::class,
            TokenKind::CommaToken,
            function ($token) {
                return $token->kind === TokenKind::AmpersandToken || $token->kind === TokenKind::VariableName;
            },
            function ($parentNode) {
                $useVariableName = new UseVariableName();
                $useVariableName->parent = $parentNode;
                $useVariableName->byRef = $this->eatOptional1(TokenKind::AmpersandToken);
                $useVariableName->variableName = $this->eat1(TokenKind::VariableName);
                return $useVariableName;
            },
            $anonymousFunctionUseClause
        ) ?: (new MissingToken(TokenKind::VariableName, $this->token->fullStart));
        $anonymousFunctionUseClause->closeParen = $this->eat1(TokenKind::CloseParenToken);

        return $anonymousFunctionUseClause;
    }

    private function parseMatchExpression($parentNode) {
        $matchExpression = new MatchExpression();
        $matchExpression->parent = $parentNode;
        $matchExpression->matchToken = $this->eat1(TokenKind::MatchKeyword);
        $matchExpression->openParen = $this->eat1(TokenKind::OpenParenToken);
        $matchExpression->expression = $this->parseExpression($matchExpression);
        $matchExpression->closeParen = $this->eat1(TokenKind::CloseParenToken);
        $matchExpression->openBrace = $this->eat1(TokenKind::OpenBraceToken);
        $matchExpression->arms = $this->parseDelimitedList(
            DelimitedList\MatchExpressionArmList::class,
            TokenKind::CommaToken,
            $this->isMatchConditionStartFn(),
            $this->parseMatchArmFn(),
            $matchExpression);
        $matchExpression->closeBrace = $this->eat1(TokenKind::CloseBraceToken);
        return $matchExpression;
    }

    private function isMatchConditionStartFn() {
        return function ($token) {
            return $token->kind === TokenKind::DefaultKeyword ||
                $this->isExpressionStart($token);
        };
    }

    private function parseMatchArmFn() {
        return function ($parentNode) {
            $matchArm = new MatchArm();
            $matchArm->parent = $parentNode;
            $matchArmConditionList = $this->parseDelimitedList(
                DelimitedList\MatchArmConditionList::class,
                TokenKind::CommaToken,
                $this->isMatchConditionStartFn(),
                $this->parseMatchConditionFn(),
                $matchArm
            );
            $matchArmConditionList->parent = $matchArm;
            $matchArm->conditionList = $matchArmConditionList;
            $matchArm->arrowToken = $this->eat1(TokenKind::DoubleArrowToken);
            $matchArm->body = $this->parseExpression($matchArm);
            return $matchArm;
        };
    }

    private function parseMatchConditionFn() {
        return function ($parentNode) {
            if ($this->token->kind === TokenKind::DefaultKeyword) {
                return $this->eat1(TokenKind::DefaultKeyword);
            }
            return $this->parseExpression($parentNode);
        };
    }

    private function parseCloneExpression($parentNode) {
        $cloneExpression = new CloneExpression();
        $cloneExpression->parent = $parentNode;

        $cloneExpression->cloneKeyword = $this->eat1(TokenKind::CloneKeyword);
        $cloneExpression->expression = $this->parseUnaryExpressionOrHigher($cloneExpression);

        return $cloneExpression;
    }

    private function eatSemicolonOrAbortStatement() {
        if ($this->getCurrentToken()->kind !== TokenKind::ScriptSectionEndTag) {
            return $this->eat1(TokenKind::SemicolonToken);
        }
        return null;
    }

    private function parseInlineHtml($parentNode) {
        $inlineHtml = new InlineHtml();
        $inlineHtml->parent = $parentNode;
        $inlineHtml->scriptSectionEndTag = $this->eatOptional1(TokenKind::ScriptSectionEndTag);
        $inlineHtml->text = $this->eatOptional1(TokenKind::InlineHtml);
        $inlineHtml->scriptSectionStartTag = $this->eatOptional(TokenKind::ScriptSectionStartTag, TokenKind::ScriptSectionStartWithEchoTag);

        // This is the easiest way to represent `<?= "expr", "other" `
        if (($inlineHtml->scriptSectionStartTag->kind ?? null) === TokenKind::ScriptSectionStartWithEchoTag)  {
            $echoStatement = new EchoStatement();
            $expressionList = $this->parseExpressionList($echoStatement) ?? (new MissingToken(TokenKind::Expression, $this->token->fullStart));
            $echoStatement->expressions = $expressionList;

            $echoStatement->semicolon = $this->eatSemicolonOrAbortStatement();
            $echoStatement->parent = $inlineHtml;
            // Deliberately leave echoKeyword as null instead of MissingToken

            $inlineHtml->echoStatement = $echoStatement;
        }

        return $inlineHtml;
    }
}

class Associativity {
    const None = 0;
    const Left = 1;
    const Right = 2;
}
