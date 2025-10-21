<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 * Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser;

use Microsoft\PhpParser\TokenKind;

class TokenStringMaps {
    const KEYWORDS = [
        "abstract" => TokenKind::AbstractKeyword,
        "and" => TokenKind::AndKeyword,
        "array" => TokenKind::ArrayKeyword,
        "as" => TokenKind::AsKeyword,
        "break" => TokenKind::BreakKeyword,
        "callable" => TokenKind::CallableKeyword,
        "case" => TokenKind::CaseKeyword,
        "catch" => TokenKind::CatchKeyword,
        "class" => TokenKind::ClassKeyword,
        "clone" => TokenKind::CloneKeyword,
        "const" => TokenKind::ConstKeyword,
        "continue" => TokenKind::ContinueKeyword,
        "declare" => TokenKind::DeclareKeyword,
        "default" => TokenKind::DefaultKeyword,
        "die" => TokenKind::DieKeyword,
        "do" => TokenKind::DoKeyword,
        "echo" => TokenKind::EchoKeyword,
        "else" => TokenKind::ElseKeyword,
        "elseif" => TokenKind::ElseIfKeyword,
        "empty" => TokenKind::EmptyKeyword,
        "enddeclare" => TokenKind::EndDeclareKeyword,
        "endfor" => TokenKind::EndForKeyword,
        "endforeach" => TokenKind::EndForEachKeyword,
        "endif" => TokenKind::EndIfKeyword,
        "endswitch" => TokenKind::EndSwitchKeyword,
        "endwhile" => TokenKind::EndWhileKeyword,
        "enum" => TokenKind::EnumKeyword,
        "eval" => TokenKind::EvalKeyword,
        "exit" => TokenKind::ExitKeyword,
        "extends" => TokenKind::ExtendsKeyword,
        "final" => TokenKind::FinalKeyword,
        "finally" => TokenKind::FinallyKeyword,
        "for" => TokenKind::ForKeyword,
        "foreach" => TokenKind::ForeachKeyword,
        "fn" => TokenKind::FnKeyword,
        "function" => TokenKind::FunctionKeyword,
        "global" => TokenKind::GlobalKeyword,
        "goto" => TokenKind::GotoKeyword,
        "if" => TokenKind::IfKeyword,
        "implements" => TokenKind::ImplementsKeyword,
        "include" => TokenKind::IncludeKeyword,
        "include_once" => TokenKind::IncludeOnceKeyword,
        "instanceof" => TokenKind::InstanceOfKeyword,
        "insteadof" => TokenKind::InsteadOfKeyword,
        "interface" => TokenKind::InterfaceKeyword,
        "isset" => TokenKind::IsSetKeyword,
        "list" => TokenKind::ListKeyword,
        "match" => TokenKind::MatchKeyword,
        "namespace" => TokenKind::NamespaceKeyword,
        "new" => TokenKind::NewKeyword,
        "or" => TokenKind::OrKeyword,
        "print" => TokenKind::PrintKeyword,
        "private" => TokenKind::PrivateKeyword,
        "protected" => TokenKind::ProtectedKeyword,
        "public" => TokenKind::PublicKeyword,
        "readonly" => TokenKind::ReadonlyKeyword,
        "require" => TokenKind::RequireKeyword,
        "require_once" => TokenKind::RequireOnceKeyword,
        "return" => TokenKind::ReturnKeyword,
        "static" => TokenKind::StaticKeyword,
        "switch" => TokenKind::SwitchKeyword,
        "throw" => TokenKind::ThrowKeyword,
        "trait" => TokenKind::TraitKeyword,
        "try" => TokenKind::TryKeyword,
        "unset" => TokenKind::UnsetKeyword,
        "use" => TokenKind::UseKeyword,
        "var" => TokenKind::VarKeyword,
        "while" => TokenKind::WhileKeyword,
        "xor" => TokenKind::XorKeyword,
        "yield" => TokenKind::YieldKeyword,
        "yield from" => TokenKind::YieldFromKeyword,


        // TODO soft reserved words?
    ];

    const RESERVED_WORDS = [
        // http://php.net/manual/en/reserved.constants.php
        // TRUE, FALSE, NULL are special predefined constants
        // TODO - also consider adding other constants
        "true" => TokenKind::TrueReservedWord,
        "false" => TokenKind::FalseReservedWord,
        "null" => TokenKind::NullReservedWord,

        // RESERVED WORDS:
        // http://php.net/manual/en/reserved.other-reserved-words.php
        "int" => TokenKind::IntReservedWord,
        "float" => TokenKind::FloatReservedWord,
        "bool" => TokenKind::BoolReservedWord,
        "string" => TokenKind::StringReservedWord,
        "binary" => TokenKind::BinaryReservedWord,
        "boolean" => TokenKind::BooleanReservedWord,
        "double" => TokenKind::DoubleReservedWord,
        "integer" => TokenKind::IntegerReservedWord,
        "object" => TokenKind::ObjectReservedWord,
        "real" => TokenKind::RealReservedWord,
        "void" => TokenKind::VoidReservedWord,
        "iterable" => TokenKind::IterableReservedWord,
        "mixed" => TokenKind::MixedReservedWord,
        "never" => TokenKind::NeverReservedWord,
    ];

    const OPERATORS_AND_PUNCTUATORS = [
        "[" => TokenKind::OpenBracketToken,
        "]" => TokenKind::CloseBracketToken,
        "(" => TokenKind::OpenParenToken,
        ")" => TokenKind::CloseParenToken,
        "{" => TokenKind::OpenBraceToken,
        "}" => TokenKind::CloseBraceToken,
        "." => TokenKind::DotToken,
        "->" => TokenKind::ArrowToken,
        "=>" => TokenKind::DoubleArrowToken,
        "++" => TokenKind::PlusPlusToken,
        "--" => TokenKind::MinusMinusToken,
        "**" => TokenKind::AsteriskAsteriskToken,
        "*" => TokenKind::AsteriskToken,
        "+" => TokenKind::PlusToken,
        "-" => TokenKind::MinusToken,
        "~" => TokenKind::TildeToken,
        "!" => TokenKind::ExclamationToken,
        "$" => TokenKind::DollarToken,
        "/" => TokenKind::SlashToken,
        "%" => TokenKind::PercentToken,
        "<<" => TokenKind::LessThanLessThanToken,
        ">>" => TokenKind::GreaterThanGreaterThanToken,
        "<" => TokenKind::LessThanToken,
        ">" => TokenKind::GreaterThanToken,
        "<=" => TokenKind::LessThanEqualsToken,
        ">=" => TokenKind::GreaterThanEqualsToken,
        "==" => TokenKind::EqualsEqualsToken,
        "===" => TokenKind::EqualsEqualsEqualsToken,
        "!=" => TokenKind::ExclamationEqualsToken,
        "!==" => TokenKind::ExclamationEqualsEqualsToken,
        "^" => TokenKind::CaretToken,
        "|" => TokenKind::BarToken,
        "&" => TokenKind::AmpersandToken,
        "&&" => TokenKind::AmpersandAmpersandToken,
        "||" => TokenKind::BarBarToken,
        "?" => TokenKind::QuestionToken,
        ":" => TokenKind::ColonToken,
        "::" => TokenKind::ColonColonToken,
        ";" => TokenKind::SemicolonToken,
        "=" => TokenKind::EqualsToken,
        "**=" => TokenKind::AsteriskAsteriskEqualsToken,
        "*=" => TokenKind::AsteriskEqualsToken,
        "/=" => TokenKind::SlashEqualsToken,
        "%=" => TokenKind::PercentEqualsToken,
        "+=" => TokenKind::PlusEqualsToken,
        "-=" => TokenKind::MinusEqualsToken,
        ".=" => TokenKind::DotEqualsToken,
        "<<=" => TokenKind::LessThanLessThanEqualsToken,
        ">>=" => TokenKind::GreaterThanGreaterThanEqualsToken,
        "&=" => TokenKind::AmpersandEqualsToken,
        "^=" => TokenKind::CaretEqualsToken,
        "|=" => TokenKind::BarEqualsToken,
        "," => TokenKind::CommaToken,
        "?->" => TokenKind::QuestionArrowToken,
        "??" => TokenKind::QuestionQuestionToken,
        "??=" => TokenKind::QuestionQuestionEqualsToken,
        "<=>" => TokenKind::LessThanEqualsGreaterThanToken,
        "<>" => TokenKind::LessThanGreaterThanToken,
        "..." => TokenKind::DotDotDotToken,
        "\\" => TokenKind::BackslashToken,
        "<?=" => TokenKind::ScriptSectionStartWithEchoTag, // TODO, technically not an operator
        "<?php " => TokenKind::ScriptSectionStartTag, // TODO, technically not an operator
        "<?php\t" => TokenKind::ScriptSectionStartTag, // TODO add tests
        "<?php\n" => TokenKind::ScriptSectionStartTag,
        "<?php\r" => TokenKind::ScriptSectionStartTag,
        "<?php\r\n" => TokenKind::ScriptSectionStartTag,
        "?>" => TokenKind::ScriptSectionEndTag, // TODO, technically not an operator
        "?>\n" => TokenKind::ScriptSectionEndTag, // TODO, technically not an operator
        "?>\r\n" => TokenKind::ScriptSectionEndTag, // TODO, technically not an operator
        "?>\r" => TokenKind::ScriptSectionEndTag, // TODO, technically not an operator
        "@" => TokenKind::AtSymbolToken, // TODO not in spec
        "`" => TokenKind::BacktickToken
    ];

// TODO add new tokens
}
