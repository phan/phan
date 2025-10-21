<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser;

class TokenKind {
    const Unknown = 0;
    const EndOfFileToken = 1;

    const Name = 2;
    const VariableName = 3;

    const SkippedToken = 4;
    const MissingToken = 5;
    const QualifiedName = 6;


    const AbstractKeyword = 101;
    const AndKeyword = 102;
    const ArrayKeyword = 103;
    const AsKeyword = 104;
    const BreakKeyword = 105;
    const CallableKeyword = 106;
    const CaseKeyword = 107;
    const CatchKeyword = 108;
    const ClassKeyword = 109;
    const CloneKeyword = 110;
    const ConstKeyword = 111;
    const ContinueKeyword = 112;
    const DeclareKeyword = 113;
    const DefaultKeyword = 114;
    const DieKeyword = 115;
    const DoKeyword = 116;
    const EchoKeyword = 117;
    const ElseKeyword = 118;
    const ElseIfKeyword = 119;
    const EmptyKeyword = 120;
    const EndDeclareKeyword = 121;
    const EndForKeyword = 122;
    const EndForEachKeyword = 123;
    const EndIfKeyword = 124;
    const EndSwitchKeyword = 125;
    const EndWhileKeyword = 126;
    const EvalKeyword = 127;
    const ExitKeyword = 128;
    const ExtendsKeyword = 129;
    const FinalKeyword = 130;
    const FinallyKeyword = 131;
    const ForKeyword = 132;
    const ForeachKeyword = 133;
    const FunctionKeyword = 134;
    const GlobalKeyword = 135;
    const GotoKeyword = 136;
    const IfKeyword = 137;
    const ImplementsKeyword = 138;
    const IncludeKeyword = 139;
    const IncludeOnceKeyword = 140;
    const InstanceOfKeyword = 141;
    const InsteadOfKeyword = 142;
    const InterfaceKeyword = 143;
    const IsSetKeyword = 144;
    const ListKeyword = 145;
    const NamespaceKeyword = 146;
    const NewKeyword = 147;
    const OrKeyword = 148;
    const PrintKeyword = 149;
    const PrivateKeyword = 150;
    const ProtectedKeyword = 151;
    const PublicKeyword = 152;
    const RequireKeyword = 153;
    const RequireOnceKeyword = 154;
    const ReturnKeyword = 155;
    const StaticKeyword = 156;
    const SwitchKeyword = 157;
    const ThrowKeyword = 158;
    const TraitKeyword = 159;
    const TryKeyword = 160;
    const UnsetKeyword = 161;
    const UseKeyword = 162;
    const VarKeyword = 163;
    const WhileKeyword = 164;
    const XorKeyword = 165;
    const YieldKeyword = 166;
    const YieldFromKeyword = 167;
    const FnKeyword = 168;
    const MatchKeyword = 169;
    /** @deprecated use IterableReservedWord */
    const IterableKeyword = self::IterableReservedWord;
    const EnumKeyword = 171;
    const ReadonlyKeyword = 172;
    const HaltCompilerKeyword = 173;

    const OpenBracketToken = 201;
    const CloseBracketToken = 202;
    const OpenParenToken = 203;
    const CloseParenToken = 204;
    const OpenBraceToken = 205;
    const CloseBraceToken = 206;
    const DotToken = 207;
    const ArrowToken = 208;
    const PlusPlusToken = 209;
    const MinusMinusToken = 210;
    const AsteriskAsteriskToken = 211;
    const AsteriskToken = 212;
    const PlusToken = 213;
    const MinusToken = 214;
    const TildeToken = 215;
    const ExclamationToken = 216;
    const DollarToken = 217;
    const SlashToken = 218;
    const PercentToken = 220;
    const LessThanLessThanToken = 221;
    const GreaterThanGreaterThanToken = 222;
    const LessThanToken = 223;
    const GreaterThanToken = 224;
    const LessThanEqualsToken = 225;
    const GreaterThanEqualsToken = 226;
    const EqualsEqualsToken = 227;
    const EqualsEqualsEqualsToken = 228;
    const ExclamationEqualsToken = 229;
    const ExclamationEqualsEqualsToken = 230;
    const CaretToken = 231;
    const BarToken = 232;
    const AmpersandToken = 233;
    const AmpersandAmpersandToken = 234;
    const BarBarToken = 235;
    const ColonToken = 236;
    const SemicolonToken = 237;
    const EqualsToken = 238;
    const AsteriskAsteriskEqualsToken = 239;
    const AsteriskEqualsToken = 240;
    const SlashEqualsToken = 241;
    const PercentEqualsToken = 242;
    const PlusEqualsToken = 243;
    const MinusEqualsToken = 244;
    const DotEqualsToken = 245;
    const LessThanLessThanEqualsToken = 246;
    const GreaterThanGreaterThanEqualsToken = 247;
    const AmpersandEqualsToken = 248;
    const CaretEqualsToken = 249;
    const BarEqualsToken = 250;
    const CommaToken = 251;
    const QuestionQuestionToken = 252;
    const LessThanEqualsGreaterThanToken = 253;
    const DotDotDotToken = 254;
    const BackslashToken = 255;
    const ColonColonToken = 256;
    const DoubleArrowToken = 257; // TODO missing from spec
    const LessThanGreaterThanToken = 258; // TODO missing from spec
    const AtSymbolToken = 259;
    const BacktickToken = 260;
    const QuestionToken = 261;
    const QuestionQuestionEqualsToken = 262;
    const QuestionArrowToken = 263;
    const AttributeToken = 264;

    const DecimalLiteralToken = 301;
    const OctalLiteralToken = 302;
    const HexadecimalLiteralToken = 303;
    const BinaryLiteralToken = 304;
    const FloatingLiteralToken = 305;
    const InvalidOctalLiteralToken = 306;
    const InvalidHexadecimalLiteral = 307;
    const InvalidBinaryLiteral = 308;
    const StringLiteralToken = 309;

    // RESERVED WORDS
    const IntReservedWord = 317;
    const FloatReservedWord = 318;
    const TrueReservedWord = 319;
    const StringReservedWord = 320;
    const BoolReservedWord = 321;
    const NullReservedWord = 322;
    const MixedReservedWord = 340;
    const IterableReservedWord = 170;
    const NeverReservedWord = 341;

    const ScriptSectionStartTag = 323;
    const ScriptSectionEndTag = 324;
    const ScriptSectionStartWithEchoTag = 419;

    // TODO how to handle incremental parsing w/ this?
    const ScriptSectionPrependedText = 325;
    const VoidReservedWord = 326;
    const FalseReservedWord = 327;

    const MemberName = 328;
    const Expression = 329;

    const BinaryReservedWord = 330; // TODO better way
    const BooleanReservedWord = 331;
    const DoubleReservedWord = 332;
    const IntegerReservedWord = 333;
    const ObjectReservedWord = 334;
    const RealReservedWord = 335;
    const ReturnType = 336;
    const InlineHtml = 337;
    const PropertyType = 338;
    const EnumType = 339;

//    const DollarOpenCurly = 339;
    const EncapsedAndWhitespace = 400;
    const SingleQuoteToken = 401;
    const DoubleQuoteToken = 402;
    const DollarOpenBraceToken = 403;
    const OpenBraceDollarToken = 404;
    const CastToken = 405;
    const HeredocStart = 406;
    const HeredocEnd = 407;
    const StringVarname = 408;
    const UnsetCastToken = 409;
    const StringCastToken = 410;
    const ObjectCastToken = 411;
    const IntCastToken = 412;
    const DoubleCastToken = 413;
    const BoolCastToken = 414;
    const ArrayCastToken = 415;
    const IntegerLiteralToken = 416;
    const CommentToken = 417;
    const DocCommentToken = 418;

    // TODO type annotations - PHP7
}
