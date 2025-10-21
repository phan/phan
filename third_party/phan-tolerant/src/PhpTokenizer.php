<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser;

// If this predates PHP 7.4, T_COALESCE_EQUAL and T_FN are unavailable.
// The replacement value is arbitrary - it just has to be different from other values of token constants.
define(__NAMESPACE__ . '\T_COALESCE_EQUAL', defined('T_COALESCE_EQUAL') ? constant('T_COALESCE_EQUAL') : 'T_COALESCE_EQUAL');
define(__NAMESPACE__ . '\T_FN', defined('T_FN') ? constant('T_FN') : 'T_FN');
// If this predates PHP 8.0, T_MATCH is unavailable. The replacement value is arbitrary - it just has to be different from other values of token constants.
define(__NAMESPACE__ . '\T_MATCH', defined('T_MATCH') ? constant('T_MATCH') : 'T_MATCH');
define(__NAMESPACE__ . '\T_NULLSAFE_OBJECT_OPERATOR', defined('T_NULLSAFE_OBJECT_OPERATOR') ? constant('T_NULLSAFE_OBJECT_OPERATOR') : 'T_NULLSAFE_OBJECT_OPERATOR');
define(__NAMESPACE__ . '\T_ATTRIBUTE', defined('T_ATTRIBUTE') ? constant('T_ATTRIBUTE') : 'T_ATTRIBUTE');
// If this predates PHP 8.1, T_ENUM is unavailable. The replacement value is arbitrary - it just has to be different from other values of token constants.
define(__NAMESPACE__ . '\T_ENUM', defined('T_ENUM') ? constant('T_ENUM') : 'T_ENUM');
define(__NAMESPACE__ . '\T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG', defined('T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG') ? constant('T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG') : 'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG');
define(__NAMESPACE__ . '\T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG', defined('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG') ? constant('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG') : 'T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG');
define(__NAMESPACE__ . '\T_READONLY', defined('T_READONLY') ? constant('T_READONLY') : 'T_READONLY');

/**
 * Tokenizes content using PHP's built-in `token_get_all`, and converts to "lightweight" Token representation.
 *
 * Initially we tried hand-spinning the lexer (see `experiments/Lexer.php`), but we had difficulties optimizing
 * performance (especially when working with Unicode characters.)
 *
 * Class PhpTokenizer
 * @package Microsoft\PhpParser
 */
class PhpTokenizer implements TokenStreamProviderInterface {
    public $pos;
    public $endOfFilePos;

    private $tokensArray;

    public function __construct($content) {
        $this->tokensArray = $this->getTokensArrayFromContent($content);
        $this->endOfFilePos = \count($this->tokensArray) - 1;
        $this->pos = 0;
    }

    public function scanNextToken() : Token {
        return $this->pos >= $this->endOfFilePos
            ? $this->tokensArray[$this->endOfFilePos]
            : $this->tokensArray[$this->pos++];
    }

    public function getCurrentPosition() : int {
        return $this->pos;
    }

    public function setCurrentPosition(int $pos) {
        $this->pos = $pos;
    }

    public function getEndOfFilePosition() : int {
        return $this->endOfFilePos;
    }

    public function getTokensArray() : array {
        return $this->tokensArray;
    }

    /**
     * Return an array of Token object instances created from $content.
     *
     * This method is optimized heavily - this processes every single token being created.
     *
     * @param string $content the raw php code
     * @param ?int $parseContext can be SourceElements when extracting doc comments
     * @param int $initialPos
     * @param bool $treatCommentsAsTrivia
     * @return Token[]
     */
    public static function getTokensArrayFromContent(
        $content, $parseContext = null, $initialPos = 0, $treatCommentsAsTrivia = true
    ) : array {
        if ($parseContext !== null) {
            // If needed, add a prefix so that token_get_all will tokenize the remaining $contents
            $prefix = self::PARSE_CONTEXT_TO_PREFIX[$parseContext];
            $content = $prefix . $content;
        }

        $tokens = static::tokenGetAll($content, $parseContext);

        $arr = [];
        $fullStart = $start = $pos = $initialPos;
        if ($parseContext !== null) {
            // If needed, skip over the prefix we added for token_get_all and remove those tokens.
            // This was moved out of the below main loop as an optimization.
            // (the common case of parsing an entire file uses a null parseContext)
            foreach ($tokens as $i => $token) {
                unset($tokens[$i]);
                if (\is_array($token)) {
                    $pos += \strlen($token[1]);
                } else {
                    $pos += \strlen($token);
                }
                if (\strlen($prefix) < $pos) {
                    $fullStart = $start = $pos = $initialPos;
                    break;
                }
            }
        }

        // Convert tokens from token_get_all to Token instances,
        // skipping whitespace and (usually, when parseContext is null) comments.
        foreach ($tokens as $token) {
            if (\is_array($token)) {
                $tokenKind = $token[0];
                $strlen = \strlen($token[1]);
            } else {
                $pos += \strlen($token);
                $newTokenKind = self::TOKEN_MAP[$token] ?? TokenKind::Unknown;
                $arr[] = new Token($newTokenKind, $fullStart, $start, $pos - $fullStart);
                $start = $fullStart = $pos;
                continue;
            }

            $pos += $strlen;

            // Optimization note: In PHP < 7.2, the switch statement would check case by case,
            // so putting the most common cases first is slightly faster
            switch ($tokenKind) {
                case \T_WHITESPACE:
                    $start += $strlen;
                    break;
                case \T_STRING:
                    $name = \strtolower($token[1]);
                    $newTokenKind = TokenStringMaps::RESERVED_WORDS[$name] ?? TokenKind::Name;
                    $arr[] = new Token($newTokenKind, $fullStart, $start, $pos - $fullStart);
                    $start = $fullStart = $pos;
                    break;
                case \T_OPEN_TAG:
                    $arr[] = new Token(TokenKind::ScriptSectionStartTag, $fullStart, $start, $pos-$fullStart);
                    $start = $fullStart = $pos;
                    break;
                case \PHP_VERSION_ID >= 80000 ? \T_NAME_QUALIFIED : -1000:
                case \PHP_VERSION_ID >= 80000 ? \T_NAME_FULLY_QUALIFIED : -1001:
                    // NOTE: This switch is called on every token of every file being parsed, so this traded performance for readability.
                    //
                    // PHP's Opcache is able to optimize switches that are exclusively known longs,
                    // but not switches that mix strings and longs or have unknown longs.
                    // Longs are only known if they're declared within the same *class* or an internal constant (tokenizer).
                    //
                    // For some reason, the SWITCH_LONG opcode was not generated when the expression was part of a class constant.
                    // (seen with php -d opcache.opt_debug_level=0x20000)
                    //
                    // Use negative values because that's not expected to overlap with token kinds that token_get_all() will return.
                    //
                    // T_NAME_* was added in php 8.0 to forbid whitespace between parts of names.
                    // Here, emulate the tokenization of php 7 by splitting it up into 1 or more tokens.
                    foreach (\explode('\\', $token[1]) as $i => $name) {
                        if ($i) {
                            $arr[] = new Token(TokenKind::BackslashToken, $fullStart, $start, 1 + $start - $fullStart);
                            $start++;
                            $fullStart = $start;
                        }
                        if ($name === '') {
                            continue;
                        }
                        // TODO: TokenStringMaps::RESERVED_WORDS[$name] ?? TokenKind::Name for compatibility?
                        $len = \strlen($name);
                        $arr[] = new Token(TokenKind::Name, $fullStart, $start, $len + $start - $fullStart);
                        $start += $len;
                        $fullStart = $start;
                    }
                    break;
                case \PHP_VERSION_ID >= 80000 ? \T_NAME_RELATIVE : -1002:
                    // This is a namespace-relative name: namespace\...
                    foreach (\explode('\\', $token[1]) as $i => $name) {
                        $len = \strlen($name);
                        if (!$i) {
                            $arr[] = new Token(TokenKind::NamespaceKeyword, $fullStart, $start, $len + $start - $fullStart);
                            $start += $len;
                            $fullStart = $start;
                            continue;
                        }
                        $arr[] = new Token(TokenKind::BackslashToken, $fullStart, $start, 1);
                        $start++;

                        // TODO: TokenStringMaps::RESERVED_WORDS[$name] ?? TokenKind::Name for compatibility?
                        $arr[] = new Token(TokenKind::Name, $start, $start, $len);

                        $start += $len;
                        $fullStart = $start;
                    }
                    break;
                case \T_COMMENT:
                case \T_DOC_COMMENT:
                    if ($treatCommentsAsTrivia) {
                        $start += $strlen;
                        break;
                    }
                    // fall through
                default:
                    $newTokenKind = self::TOKEN_MAP[$tokenKind] ?? TokenKind::Unknown;
                    $arr[] = new Token($newTokenKind, $fullStart, $start, $pos - $fullStart);
                    $start = $fullStart = $pos;
                    break;
            }
        }

        $arr[] = new Token(TokenKind::EndOfFileToken, $fullStart, $start, $pos - $fullStart);
        return $arr;
    }

    /**
     * @param string $content the raw php code
     * @param ?int $parseContext can be SourceElements when extracting doc comments.
     *                           Having this available may be useful for subclasses to decide whether or not to post-process results, cache results, etc.
     * @return array[]|string[] an array of tokens. When concatenated, these tokens must equal $content.
     *
     * This exists so that it can be overridden in subclasses, e.g. to cache the result of tokenizing entire files.
     * Applications using tolerant-php-parser may often end up needing to use the token stream for other reasons that are hard to do in the resulting AST,
     * such as iterating over T_COMMENTS, checking for inline html,
     * looking up all tokens (including skipped tokens) on a given line, etc.
     */
    protected static function tokenGetAll(string $content, $parseContext): array
    {
        return @\token_get_all($content);
    }

    const TOKEN_MAP = [
        T_CLASS_C => TokenKind::Name,
        T_DIR => TokenKind::Name,
        T_FILE => TokenKind::Name,
        T_FUNC_C => TokenKind::Name,
        T_METHOD_C => TokenKind::Name,
        T_NS_C => TokenKind::Name,
        T_TRAIT_C => TokenKind::Name,
        T_LINE => TokenKind::Name,

        T_STRING => TokenKind::Name,
        T_VARIABLE => TokenKind::VariableName,

        T_ABSTRACT => TokenKind::AbstractKeyword,
        T_LOGICAL_AND => TokenKind::AndKeyword,
        T_ARRAY => TokenKind::ArrayKeyword,
        T_AS => TokenKind::AsKeyword,
        T_BREAK => TokenKind::BreakKeyword,
        T_CALLABLE => TokenKind::CallableKeyword,
        T_CASE => TokenKind::CaseKeyword,
        T_CATCH => TokenKind::CatchKeyword,
        T_CLASS => TokenKind::ClassKeyword,
        T_CLONE => TokenKind::CloneKeyword,
        T_CONST => TokenKind::ConstKeyword,
        T_CONTINUE => TokenKind::ContinueKeyword,
        T_DECLARE => TokenKind::DeclareKeyword,
        T_DEFAULT => TokenKind::DefaultKeyword,
        T_DO => TokenKind::DoKeyword,
        T_ECHO => TokenKind::EchoKeyword,
        T_ELSE => TokenKind::ElseKeyword,
        T_ELSEIF => TokenKind::ElseIfKeyword,
        T_EMPTY => TokenKind::EmptyKeyword,
        T_ENDDECLARE => TokenKind::EndDeclareKeyword,
        T_ENDFOR => TokenKind::EndForKeyword,
        T_ENDFOREACH => TokenKind::EndForEachKeyword,
        T_ENDIF => TokenKind::EndIfKeyword,
        T_ENDSWITCH => TokenKind::EndSwitchKeyword,
        T_ENDWHILE => TokenKind::EndWhileKeyword,
        T_ENUM => TokenKind::EnumKeyword,
        T_EVAL => TokenKind::EvalKeyword,
        T_EXIT => TokenKind::ExitKeyword,
        T_EXTENDS => TokenKind::ExtendsKeyword,
        T_FINAL => TokenKind::FinalKeyword,
        T_FINALLY => TokenKind::FinallyKeyword,
        T_FOR => TokenKind::ForKeyword,
        T_FOREACH => TokenKind::ForeachKeyword,
        T_FN => TokenKind::FnKeyword,
        T_FUNCTION => TokenKind::FunctionKeyword,
        T_GLOBAL => TokenKind::GlobalKeyword,
        T_GOTO => TokenKind::GotoKeyword,
        T_HALT_COMPILER => TokenKind::HaltCompilerKeyword,
        T_IF => TokenKind::IfKeyword,
        T_IMPLEMENTS => TokenKind::ImplementsKeyword,
        T_INCLUDE => TokenKind::IncludeKeyword,
        T_INCLUDE_ONCE => TokenKind::IncludeOnceKeyword,
        T_INSTANCEOF => TokenKind::InstanceOfKeyword,
        T_INSTEADOF => TokenKind::InsteadOfKeyword,
        T_INTERFACE => TokenKind::InterfaceKeyword,
        T_ISSET => TokenKind::IsSetKeyword,
        T_LIST => TokenKind::ListKeyword,
        T_MATCH => TokenKind::MatchKeyword,
        T_NAMESPACE => TokenKind::NamespaceKeyword,
        T_NEW => TokenKind::NewKeyword,
        T_LOGICAL_OR => TokenKind::OrKeyword,
        T_PRINT => TokenKind::PrintKeyword,
        T_PRIVATE => TokenKind::PrivateKeyword,
        T_PROTECTED => TokenKind::ProtectedKeyword,
        T_PUBLIC => TokenKind::PublicKeyword,
        T_READONLY => TokenKind::ReadonlyKeyword,
        T_REQUIRE => TokenKind::RequireKeyword,
        T_REQUIRE_ONCE => TokenKind::RequireOnceKeyword,
        T_RETURN => TokenKind::ReturnKeyword,
        T_STATIC => TokenKind::StaticKeyword,
        T_SWITCH => TokenKind::SwitchKeyword,
        T_THROW => TokenKind::ThrowKeyword,
        T_TRAIT => TokenKind::TraitKeyword,
        T_TRY => TokenKind::TryKeyword,
        T_UNSET => TokenKind::UnsetKeyword,
        T_USE => TokenKind::UseKeyword,
        T_VAR => TokenKind::VarKeyword,
        T_WHILE => TokenKind::WhileKeyword,
        T_LOGICAL_XOR => TokenKind::XorKeyword,
        T_YIELD => TokenKind::YieldKeyword,
        T_YIELD_FROM => TokenKind::YieldFromKeyword,

        "[" => TokenKind::OpenBracketToken,
        "]" => TokenKind::CloseBracketToken,
        "(" => TokenKind::OpenParenToken,
        ")" => TokenKind::CloseParenToken,
        "{" => TokenKind::OpenBraceToken,
        "}" => TokenKind::CloseBraceToken,
        "." => TokenKind::DotToken,
        T_OBJECT_OPERATOR => TokenKind::ArrowToken,
        T_NULLSAFE_OBJECT_OPERATOR => TokenKind::QuestionArrowToken,
        T_ATTRIBUTE => TokenKind::AttributeToken,
        T_INC => TokenKind::PlusPlusToken,
        T_DEC => TokenKind::MinusMinusToken,
        T_POW => TokenKind::AsteriskAsteriskToken,
        "*" => TokenKind::AsteriskToken,
        "+" => TokenKind::PlusToken,
        "-" => TokenKind::MinusToken,
        "~" => TokenKind::TildeToken,
        "!" => TokenKind::ExclamationToken,
        "$" => TokenKind::DollarToken,
        "/" => TokenKind::SlashToken,
        "%" => TokenKind::PercentToken,
        T_SL => TokenKind::LessThanLessThanToken,
        T_SR => TokenKind::GreaterThanGreaterThanToken,
        "<" => TokenKind::LessThanToken,
        ">" => TokenKind::GreaterThanToken,
        T_IS_SMALLER_OR_EQUAL => TokenKind::LessThanEqualsToken,
        T_IS_GREATER_OR_EQUAL => TokenKind::GreaterThanEqualsToken,
        T_IS_EQUAL => TokenKind::EqualsEqualsToken,
        T_IS_IDENTICAL => TokenKind::EqualsEqualsEqualsToken,
        T_IS_NOT_EQUAL => TokenKind::ExclamationEqualsToken,
        T_IS_NOT_IDENTICAL => TokenKind::ExclamationEqualsEqualsToken,
        "^" => TokenKind::CaretToken,
        "|" => TokenKind::BarToken,
        "&" => TokenKind::AmpersandToken,
        T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG => TokenKind::AmpersandToken,
        T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG => TokenKind::AmpersandToken,
        T_BOOLEAN_AND => TokenKind::AmpersandAmpersandToken,
        T_BOOLEAN_OR => TokenKind::BarBarToken,
        ":" => TokenKind::ColonToken,
        ";" => TokenKind::SemicolonToken,
        "=" => TokenKind::EqualsToken,
        T_POW_EQUAL => TokenKind::AsteriskAsteriskEqualsToken,
        T_MUL_EQUAL => TokenKind::AsteriskEqualsToken,
        T_DIV_EQUAL => TokenKind::SlashEqualsToken,
        T_MOD_EQUAL => TokenKind::PercentEqualsToken,
        T_PLUS_EQUAL => TokenKind::PlusEqualsToken,
        T_MINUS_EQUAL => TokenKind::MinusEqualsToken,
        T_CONCAT_EQUAL => TokenKind::DotEqualsToken,
        T_SL_EQUAL => TokenKind::LessThanLessThanEqualsToken,
        T_SR_EQUAL => TokenKind::GreaterThanGreaterThanEqualsToken,
        T_AND_EQUAL => TokenKind::AmpersandEqualsToken,
        T_XOR_EQUAL => TokenKind::CaretEqualsToken,
        T_OR_EQUAL => TokenKind::BarEqualsToken,
        "," => TokenKind::CommaToken,
        namespace\T_COALESCE_EQUAL => TokenKind::QuestionQuestionEqualsToken,
        T_COALESCE => TokenKind::QuestionQuestionToken,
        T_SPACESHIP => TokenKind::LessThanEqualsGreaterThanToken,
        T_ELLIPSIS => TokenKind::DotDotDotToken,
        T_NS_SEPARATOR => TokenKind::BackslashToken,
        T_PAAMAYIM_NEKUDOTAYIM => TokenKind::ColonColonToken,
        T_DOUBLE_ARROW => TokenKind::DoubleArrowToken, // TODO missing from spec

        "@" => TokenKind::AtSymbolToken,
        "`" => TokenKind::BacktickToken,
        "?" => TokenKind::QuestionToken,

        T_LNUMBER => TokenKind::IntegerLiteralToken,

        T_DNUMBER => TokenKind::FloatingLiteralToken,

        T_OPEN_TAG => TokenKind::ScriptSectionStartTag,
        T_OPEN_TAG_WITH_ECHO => TokenKind::ScriptSectionStartWithEchoTag,
        T_CLOSE_TAG => TokenKind::ScriptSectionEndTag,

        T_INLINE_HTML => TokenKind::InlineHtml,

        "\"" => TokenKind::DoubleQuoteToken,
        "'" => TokenKind::SingleQuoteToken,
        T_ENCAPSED_AND_WHITESPACE => TokenKind::EncapsedAndWhitespace,
        T_DOLLAR_OPEN_CURLY_BRACES => TokenKind::DollarOpenBraceToken,
        T_CURLY_OPEN => TokenKind::OpenBraceDollarToken,
        T_CONSTANT_ENCAPSED_STRING => TokenKind::StringLiteralToken,

        T_ARRAY_CAST        => TokenKind::ArrayCastToken,
        T_BOOL_CAST         => TokenKind::BoolCastToken,
        T_DOUBLE_CAST       => TokenKind::DoubleCastToken,
        T_INT_CAST          => TokenKind::IntCastToken,
        T_OBJECT_CAST       => TokenKind::ObjectCastToken,
        T_STRING_CAST       => TokenKind::StringCastToken,
        T_UNSET_CAST        => TokenKind::UnsetCastToken,

        T_START_HEREDOC     => TokenKind::HeredocStart,
        T_END_HEREDOC       => TokenKind::HeredocEnd,
        T_STRING_VARNAME    => TokenKind::StringVarname,
        T_COMMENT           => TokenKind::CommentToken,
        T_DOC_COMMENT       => TokenKind::DocCommentToken,
        T_NUM_STRING        => TokenKind::IntegerLiteralToken
    ];

    const PARSE_CONTEXT_TO_PREFIX = [
        ParseContext::SourceElements => "<?php "
    ];
}
