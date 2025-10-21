#Lexical Structure

##Scripts

A [script](04-basic-concepts.md#program-structure) is an ordered sequence of characters. Typically, a
script has a one-to-one correspondence with a file in a file system, but
this correspondence is not required.

Conceptually speaking, a script is translated using the following steps:

1.  Transformation, which converts a script from a particular character
    repertoire and encoding scheme into a sequence of 8-bit characters.

2.  Lexical analysis, which translates a stream of input characters into
    a stream of tokens.

3.  Syntactic analysis, which translates the stream of tokens into
    executable code.

Conforming implementations must accept scripts encoded with the UTF-8
encoding form (as defined by the Unicode standard), and transform them
into a sequence of characters. Implementations can choose to accept and
transform additional character encoding schemes.

##Grammars

This specification shows the syntax of the PHP programming language
using two grammars. The *lexical grammar* defines how source
characters are combined to form white space, comments, and tokens. The
*syntactic grammar* defines how the resulting tokens are combined to
form PHP programs.

The grammars are presented using *grammar productions*, with each one
defining a non-terminal symbol and the possible expansions of that
non-terminal symbol into sequences of non-terminal or terminal symbols.
In productions, non-terminal symbols are shown in slanted type *like
this*, and terminal symbols are shown in a fixed-width font `like this`.

The first line of a grammar production is the name of the non-terminal
symbol being defined, followed by one colon for a syntactic grammar
production, and two colons for a lexical grammar production. Each
successive indented line contains a possible expansion of the
non-terminal given as a sequence of non-terminal or terminal symbols.
For example, the production:

<pre>
  <i>single-line-comment::</i>
    // input-characters<sub>opt</sub>
    #  input-characters<sub>opt</sub>
</pre>

defines the lexical grammar production *single-line-comment* as being
the terminals `//` or `#`, followed by an optional *input-characters*. Each
expansion is listed on a separate line.

Although alternatives are usually listed on separate lines, when there
is a large number, the shorthand phrase “one of” may precede a list of
expansions given on a single line. For example,

<pre>
  <i>hexadecimal-digit:: one of</i>
    0   1   2   3   4   5   6   7   8   9
    a   b   c   d   e   f
    A   B   C   D   E   F
</pre>

##Lexical analysis

###General

The production *input-file* is the root of the lexical structure for a
script. Each script must conform to this production.

**Syntax**

<pre>
  <i>input-file::</i>
    <i>input-element</i>
    <i>input-file   input-element</i>
  <i>input-element::</i>
    <i>comment</i>
    <i>white-space</i>
    <i>token</i>
</pre>

**Defined elsewhere**

* [*comment*](#comments)
* [*white-space*](#white-space)
* [*token*](#tokens)

**Semantics**

The basic elements of a script are comments, white space, and tokens.

The lexical processing of a script involves the reduction of that script
into a sequence of [tokens](#tokens) that becomes the input to the
syntactic analysis. Tokens can be separated by [white space](#white-space) and
delimited [comments](#comments).

Lexical processing always results in the creation of the longest
possible lexical element. (For example, `$a+++++$b` must be parsed as
`$a++ ++ +$b`, which syntactically is invalid).

###Comments

Two forms of comments are supported: *delimited comments* and
*single-line comments*.

**Syntax**

<pre>
  <i>comment::</i>
    <i>single-line-comment</i>
    <i>delimited-comment</i>

  <i>single-line-comment::</i>
    //   <i>input-characters<sub>opt</sub></i>
    #    <i>input-characters<sub>opt</sub></i>

  <i>input-characters::</i>
    <i>input-character</i>
    <i>input-characters   input-character</i>

  <i>input-character::</i>
    Any source character except <i>new-line</i>

  <i>new-line::</i>
    Carriage-return character (U+000D)
    Line-feed character (U+000A)
    Carriage-return character (U+000D) followed by line-feed character (U+000A)

  <i>delimited-comment::</i>
    /*   No characters or any source character sequence except */   */
</pre>

**Semantics**

Except within a string literal or a comment, the characters `/*` start a
delimited comment, which ends with the characters `*/`. Except within a
string literal or a comment, the characters `//` or `#` start a single-line
comment, which ends with a new line. That new line is not part of the
comment. However, if the single-line comment is the last source element
in an embedded script, the trailing new line can be omitted. (Note: this
allows for uses like `<?php ... // ... ?>`).

A delimited comment can occur in any place in a script in which [white
space](#white-space) can occur. (For example;
`/*...*/$c/*...*/=/*...*/567/*...*/;/*...*/` is parsed as `$c=567;`, and
`$k = $i+++/*...*/++$j;` is parsed as `$k = $i+++ ++$j;`).

**Implementation Notes**

During tokenizing, an implementation can treat a delimited comment as
though it was white space.

###White Space

White space consists of an arbitrary combination of one or more
new-line, space and horizontal tab characters.

**Syntax**

<pre>
  <i>white-space::</i>
    <i>white-space-character</i>
    <i>white-space   white-space-character</i>

  <i>white-space-character::</i>
    <i>new-line</i>
    Space character (U+0020)
    Horizontal-tab character (U+0009)
</pre>

**Defined elsewhere**

* [*new-line*](#comments)

**Semantics**

The space and horizontal tab characters are considered *horizontal
white-space characters*.

###Tokens

####General

There are several kinds of source *tokens*:

**Syntax**

<pre>
  <i>token::</i>
    <i>variable-name</i>
    <i>name</i>
    <i>keyword</i>
    <i>literal</i>
    <i>operator-or-punctuator</i>
</pre>

**Defined elsewhere**

* [*variable-name*](#names)
* [*name*](#names)
* [*keyword*](#keywords)
* [*literal*](#general-2)
* [*operator-or-punctuator*](#operators-and-punctuators)

####Names

**Syntax**

<pre>
  <i>variable-name::</i>
    $   <i>name</i>

  <i>namespace-name::</i>
    <i>name</i>
    <i>namespace-name</i>   \   <i>name</i>

  <i>namespace-name-as-a-prefix::</i>
    \
    \<sub>opt</sub>   <i>namespace-name</i>   \
    namespace   \
    namespace   \   <i>namespace-name</i>   \

  <i>qualified-name::</i>
    <i>namespace-name-as-a-prefix<sub>opt</sub>   name</i>

  <i>name::</i>
    <i>name-nondigit</i>
    <i>name   name-nondigit</i>
    <i>name   digit</i>

  <i>name-nondigit::</i>
    <i>nondigit</i>
    one of the characters U+0080–U+00ff

  <i>nondigit:: one of</i>
    _
    a   b   c   d   e   f   g   h   i   j   k   l   m
    n   o   p   q   r   s   t   u   v   w   x   y   z
    A   B   C   D   E   F   G   H   I   J   K   L   M
    N   O   P   Q   R   S   T   U   V   W   X   Y   Z
</pre>

**Defined elsewhere**

* [*digit*](#integer-literals)

**Semantics**

Names are used to identify the following: [constants](06-constants.md#general),
[variables](07-variables.md#general), [labels](11-statements.md#labeled-statements),
[functions](13-functions.md#function-definitions), [classes](14-classes.md#class-declarations),
[class members](14-classes.md#class-members), [interfaces](15-interfaces.md#interface-declarations),
[traits](16-traits.md#general), [namespaces](18-namespaces.md#general),
and names in [heredoc](#heredoc-string-literals) and [nowdoc comments](#nowdoc-string-literals).

A *name* begins with an underscore (_), *name-nondigit*, or extended
name character in the range U+0080–-U+00ff. Subsequent characters can
also include *digits*. A *variable name* is a name with a leading
dollar ($).

Unless stated otherwise ([functions](13-functions.md#function-definitions),
[classes](14-classes.md#class-declarations), [methods](14-classes.md#methods),
[interfaces](15-interfaces.md#interface-declarations), [traits](16-traits.md#trait-declarations),
[namespaces](18-namespaces.md#defining-namespaces), names are case-sensitive, and every character in a name is significant.

Names beginning with two underscores (__) are reserved by the PHP language and should not be defined by the user code.

The following names cannot be used as the names of classes, interfaces, or traits: `bool`, `FALSE`, `float`, `int`, `NULL`, `string`, `TRUE`, and `void`.

The following names are reserved for future use and should not be used as the names of classes, interfaces, or traits: `mixed`, `numeric`, `object`, and `resource`.

With the exception of `class`, all [keywords](09-lexical-structures#keywords) can be used as names for the members of a class, interface, or trait. However, `class` can be used as the name of a property or method.

Variable names and function names (when used in a function-call context)
need not be defined as source tokens; they can also be created at
runtime using the [variable name-creation operator](10-expressions.md#variable-name-creation-operator). (For
example, given `$a = "Total"; $b = 3; $c = $b + 5;`, `${$a.$b.$c} = TRUE;`
is equivalent to `$Total38 = TRUE;`, and `${$a.$b.$c}()` is
equivalent to `Total38()`).

**Examples**

```PHP
const MAX_VALUE = 100;
function getData() { /*...*/ }
class Point { /*...*/ }
interface ICollection { /*...*/ }
```

**Implementation Notes**

An implementation is discouraged from placing arbitrary restrictions on
name lengths.

####Keywords

A *keyword* is a name-like sequence of characters that is reserved, and
cannot be used as a name.

**Syntax**

<pre>
  <i>keyword:: one of</i>
    abstract   and   array   as   break   callable   case   catch   class   clone
    const   continue   declare   default   die   do   echo   else   elseif   empty
    enddeclare   endfor   endforeach   endif   endswitch   endwhile   eval   exit
    extends   final   finally   for   foreach   function   global
    goto   if   implements   include   include_once   instanceof
    insteadof   interface   isset   list   namespace   new   or   print   private
    protected   public   require   require_once   return   static   switch
    throw   trait   try   unset   use   var   while   xor   yield   yield from
</pre>

**Semantics**

Keywords are not case-sensitive.

Note carefully that `yield from` is a single token that contains whitespace. However, [comments](#comments) are not permitted in that whitespace.

Also, all [*magic constants*](06-constants.md#context-dependent-constants) are also treated as keywords.

####Literals

The source code representation of a value is called a *literal*.

#####Integer Literals

**Syntax**

<pre>
  <i>integer-literal::</i>
    <i>decimal-literal</i>
    <i>octal-literal</i>
    <i>hexadecimal-literal</i>
    <i>binary-literal</i>

    <i>decimal-literal::</i>
      <i>nonzero-digit</i>
      <i>decimal-literal   digit</i>

    <i>octal-literal::</i>
      0
      <i>octal-literal   octal-digit</i>

    <i>hexadecimal-literal::</i>
      <i>hexadecimal-prefix   hexadecimal-digit</i>
      <i>hexadecimal-literal   hexadecimal-digit</i>

    <i>hexadecimal-prefix:: one of</i>
      0x  0X

    <i>binary-literal::</i>
      <i>binary-prefix   binary-digit</i>
      <i>binary-literal   binary-digit</i>

    <i>binary-prefix:: one of</i>
      0b  0B

    <i>digit:: one of</i>
      0  1  2  3  4  5  6  7  8  9

    <i>nonzero-digit:: one of</i>
      1  2  3  4  5  6  7  8  9

    <i>octal-digit:: one of</i>
      0  1  2  3  4  5  6  7

    <i>hexadecimal-digit:: one of</i>
      0  1  2  3  4  5  6  7  8  9
      a  b  c  d  e  f
      A  B  C  D  E  F

    <i>binary-digit:: one of</i>
        0  1
</pre>

**Semantics**

The value of a decimal integer literal is computed using base 10; that
of an octal integer literal, base 8; that of a hexadecimal integer
literal, base 16; and that of a binary integer literal, base 2.

If the value represented by *integer-literal* can fit in type int,
that would be the type of the resulting value; otherwise, the type would be float,
as described below.

Since negative numbers are represented in PHP as a negation of a positive
number, the smallest negative value (-2147483648 for 32 bits and -9223372036854775808 for 64 bits)
can not be represented as a decimal integer literal. If the non-negative
value is too large to represent as an `int`, it becomes `float`, which is
then negated.

Literals written using hexadecimal, octal, or binary notations are
considered to have non-negative values.

An integer literal is always a constant expression.

**Examples**

```PHP
$count = 10;      // decimal 10

0b101010 >> 4;    // binary 101010 and decimal 4

0XAF << 023;      // hexadecimal AF and octal 23
```

On an implementation using 32-bit int representation

```
2147483648 -> 2147483648 (too big for int, so is a float)

-2147483648 -> -2147483648 (too big for int, so is a float, negated)

-2147483647 - 1 -> -2147483648 fits in int

0x80000000 -> 2147483648 (too big for int, so is a float)
```

#####Floating-Point Literals

**Syntax**

<pre>
  <i>floating-literal::</i>
    <i>fractional-literal   exponent-part<sub>opt</sub></i>
    <i>digit-sequence   exponent-part</i>

  <i>fractional-literal::</i>
    <i>digit-sequence<sub>opt</sub></i> . <i>digit-sequence</i>
    <i>digit-sequence</i> .

  <i>exponent-part::</i>
    e  <i>sign<sub>opt</sub>   digit-sequence</i>
    E  <i>sign<sub>opt</sub>   digit-sequence</i>

  <i>sign:: one of</i>
    +  -

  <i>digit-sequence::</i>
    <i>digit</i>
    <i>digit-sequence   digit</i>
</pre>

**Defined elsewhere**

* [*digit*](#integer-literals)

**Constraints**

The value of a floating-point literal must be representable by its type.

**Semantics**

The type of a *floating-literal* is `float`.

The constants [`INF`](06-constants.md#core-predefined-constants) and
[`NAN`](06-constants.md#core-predefined-constants) provide access to the
floating-point values for infinity and Not-a-Number, respectively.

A floating point literal is always a constant expression.

**Examples**

```PHP
$values = array(1.23, 3e12, 543.678E-23);
```

#####String Literals

**Syntax**

<pre>
  <i>string-literal::</i>
    <i>single-quoted-string-literal</i>
    <i>double-quoted-string-literal</i>
    <i>heredoc-string-literal</i>
    <i>nowdoc-string-literal</i>
</pre>

**Defined elsewhere**

* [*single-quoted-string-literal*](#single-quoted-string-literals)
* [*double-quoted-string-literal*](#double-quoted-string-literals)
* [*heredoc-string-literal*](#heredoc-string-literals)
* [*nowdoc-string-literal*](#nowdoc-string-literals)

**Semantics**

A string literal is a sequence of zero or more characters delimited in
some fashion. The delimiters are not part of the literal's content.

The type of a string literal is `string`.

######Single-Quoted String Literals

**Syntax**

<pre>
  <i>single-quoted-string-literal::</i>
    <i>b-prefix<sub>opt</sub></i>  ' <i>sq-char-sequence<sub>opt</sub></i>  '

  <i>sq-char-sequence::</i>
    <i>sq-char</i>
    <i>sq-char-sequence   sq-char</i>

  <i>sq-char::</i>
    <i>sq-escape-sequence</i>
    \<i><sub>opt</sub></i>   any member of the source character set except single-quote (') or backslash (\)

  <i>sq-escape-sequence:: one of</i>
    \'   \\

  <i>b-prefix:: one of</i>
    b   B
</pre>

**Semantics**

A single-quoted string literal is a string literal delimited by
single-quotes (`'`, U+0027). The literal can contain any source character except
single-quote (`'`) and backslash (`\\`), which can only be represented by
their corresponding escape sequence.

The optional *b-prefix* is reserved for future use in dealing with
so-called *binary strings*. For now, a *single-quoted-string-literal*
with a *b-prefix* is equivalent to one without.

A single-quoted string literal is always a constant expression.

**Examples**

```
'This text is taken verbatim'

'Can embed a single quote (\') and a backslash (\\) like this'
```

######Double-Quoted String Literals

**Syntax**

<pre>
  <i>double-quoted-string-literal::</i>
    <i>b-prefix<sub>opt</sub></i>  " <i>dq-char-sequence<sub>opt</sub></i>  "

  <i>dq-char-sequence::</i>
    <i>dq-char</i>
    <i>dq-char-sequence   dq-char</i>

  <i>dq-char::</i>
    <i>dq-escape-sequence</i>
    any member of the source character set except double-quote (") or backslash (\)
    \  any member of the source character set except "\$efnrtvxX or <i>octal-digit</i>

  <i>dq-escape-sequence::</i>
    <i>dq-simple-escape-sequence</i>
    <i>dq-octal-escape-sequence</i>
    <i>dq-hexadecimal-escape-sequence</i>
    <i>dq-unicode-escape-sequence</i>

  <i>dq-simple-escape-sequence:: one of</i>
    \"   \\   \$   \e   \f   \n   \r   \t   \v

  <i>dq-octal-escape-sequence::</i>
    \   <i>octal-digit</i>
    \   <i>octal-digit   octal-digit</i>
    \   <i>octal-digit   octal-digit   octal-digit</i>

  <i>dq-hexadecimal-escape-sequence::</i>
    \x  <i>hexadecimal-digit   hexadecimal-digit<sub>opt</sub></i>
    \X  <i>hexadecimal-digit   hexadecimal-digit<sub>opt</sub></i>

  <i>dq-unicode-escape-sequence::</i>
    \u{  codepoint-digits  }

  <i>codepoint-digits::</i>
     <i>hexadecimal-digit</i>
     <i>hexadecimal-digit   codepoint-digits</i>
</pre>

**Defined elsewhere**

* [*octal-digit*](#integer-literals)
* [*hexadecimal-digit*](#integer-literals)
* [*b-prefix*](#single-quoted-string-literals)

**Semantics**

A double-quoted string literal is a string literal delimited by
double-quotes (`"`, U+0022). The literal can contain any source character except
double-quote (`"`) and backslash (`\\`), which can only be represented by
their corresponding escape sequence. Certain other (and sometimes
non-printable) characters can also be expressed as escape sequences.

The optional *b-prefix* is reserved for future use in dealing with
so-called *binary strings*. For now, a *double-quoted-string-literal*
with a *b-prefix* is equivalent to one without.

An escape sequence represents a single-character encoding, as described
in the table below:

Escape sequence | Character name | Unicode character
--------------- | --------------| ------
\$  | Dollar sign | U+0024
\"  | Double quote | U+0022
\\  | Backslash | U+005C
\e  | Escape | U+001B
\f  | Form feed | U+000C
\n  | New line | U+000A
\r  | Carriage Return | U+000D
\t  | Horizontal Tab | U+0009
\v  | Vertical Tab | U+000B
\ooo |  1–3-digit octal digit value ooo
\xhh or \Xhh  | 1–2-digit hexadecimal digit value hh
\u{xxxxxx} | UTF-8 encoding of Unicode codepoint U+xxxxxx | U+xxxxxx

Within a double-quoted string literal, except when recognized as the
start of an escape sequence, a backslash (\\) is retained verbatim.

Within a double-quoted string literal a dollar ($) character not
escaped by a backslash (\\) is handled using a variable substitution rules
described below.

The `\u{xxxxxx}` escape sequence produces the UTF-8 encoding of the Unicode
codepoint with the hexadecimal number specified within the curly braces.
Implementations MUST NOT allow Unicode codepoints beyond U+10FFFF as this is
outside the range UTF-8 can encode (see
[RFC 3629](http://tools.ietf.org/html/rfc3629#section-3)). If a codepoint
larger than U+10FFFF is specified, implementations MUST error.
Implementations MUST pass through `\u` verbatim and not interpret it as an
escape sequence if it is not followed by an opening `{`, but if it is,
implementations MUST produce an error if there is no terminating `}` or the
contents are not a valid codepoint. Implementations MUST support leading zeroes,
but MUST NOT support leading or trailing whitespace for the codepoint between
the opening and terminating braces. Implementations MUST allow Unicode
codepoints that are not Unicode scalar values, such as high and low surrogates.

A Unicode escape sequence cannot be created by variable substitution. For example, given `$v = "41"`,
`"\u{$v}"` results in `"\u41"`, a string of length 4, while `"\u{0$v}"` and `"\u{{$v}}"` contain
ill-formed Unicode escape sequences.

**Variable substitution**

The variable substitution accepts the following syntax:

<pre>
    <i>string-variable::</i>
        <i>variable-name</i>   <i>offset-or-property<sub>opt</sub></i>
        ${   <i>expression</i>   }

    <i>offset-or-property::</i>
        <i>offset-in-string</i>
        <i>property-in-string</i>

    <i>offset-in-string::</i>
        [   <i>name</i>   ]
        [   <i>variable-name</i>   ]
        [   <i>integer-literal</i>   ]

    <i>property-in-string::</i>
        ->   <i>name</i>

</pre>

**Defined elsewhere**

* [*variable-name*](#names)
* [*name*](#names)
* [*integer-literal*](#integer-literals)
* [*expression*](10-expressions.md#general-6)

*expression* works the same way as in [variable name creation operator](10-expressions.md#variable-name-creation-operator).

After the variable defined by the syntax above is evaluated, its value is converted
to string according to the rules of [string conversion](08-conversions.md#converting-to-string-type)
and is substituted into the string in place of the variable substitution expression.

Subscript or property access defined by *offset-in-string* and *property-in-string*
is resolved according to the rules of the [subscript operator](10-expressions.md#subscript-operator)
and [member selection operator](10-expressions.md#member-selection-operator) respectively.
The exception is that *name* inside *offset-in-string* is interpreted as a string literal even if it is not
quoted.

If the character sequence following the `$` does not parse as *name* and does not start with `{`, the `$` character
is instead interpreted verbatim and no variable substitution is performed.

Variable substitution also provides limited support for the evaluation
of expressions. This is done by enclosing an expression in a pair of
matching braces (`{ ... }`). The opening brace must be followed immediately by
a dollar (`$`) without any intervening white space, and that dollar must
begin a variable name. If this is not the case, braces are treated
verbatim. If the opening brace (`{`) is escaped it is not interpreted as a start of
the embedded expression and instead is interpreted verbatim.

The value of the expression is converted to string according to the rules of
[string conversion](08-conversions.md#converting-to-string-type) and is substituted into the string
in place of the substitution expression.

A double-quoted string literal is a constant expression if it does not
contain any variable substitution.

**Examples**

```PHP
$x = 123;
echo ">\$x.$x"."<"; // → >$x.123<
// -----------------------------------------
$colors = array("red", "white", "blue");
$index = 2;
echo "\$colors[$index] contains >$colors[$index]<\n";
  // → $colors[2] contains >blue<
// -----------------------------------------
class C {
    public $p1 = 2;
}
$myC = new C();
echo "\$myC->p1 = >$myC->p1<\n";  // → $myC->p1 = >2<
```

######Heredoc String Literals

**Syntax**

<pre>
  <i>heredoc-string-literal::</i>
    <i>b-prefix<sub>opt</sub></i>   &lt;&lt;&lt;   <i>hd-start-identifier   new-line   hd-body<sub>opt</i>   hd-end-identifier</i>   ;<i><sub>opt</sub>   new-line</i>

  <i>hd-start-identifier::</i>
    <i>name</i>
    "   <i>name</i>  "

  <i>hd-end-identifier::</i>
    <i>name</i>

  <i>hd-body::</i>
    <i>hd-char-sequence<sub>opt</sub>   new-line</i>

  <i>hd-char-sequence::</i>
    <i>hd-char</i>
    <i>hd-char-sequence   hd-char</i>

  <i>hd-char::</i>
    <i>hd-escape-sequence</i>
    any member of the source character set except backslash (\)
    \  any member of the source character set except \$efnrtvxX or <i>octal-digit</i>

  <i>hd-escape-sequence::</i>
    <i>hd-simple-escape-sequence</i>
    <i>dq-octal-escape-sequence</i>
    <i>dq-hexadecimal-escape-sequence</i>
    <i>dq-unicode-escape-sequence</i>

  <i>hd-simple-escape-sequence:: one of</i>
    \\   \$   \e   \f   \n   \r   \t   \v
</pre>

**Defined elsewhere**

* [*name*](#names)
* [*new-line*](#comments)
* [*dq-octal-escape-sequence*](#double-quoted-string-literals)
* [*dq-hexadecimal-escape-sequence*](#double-quoted-string-literals)
* [*b-prefix*](#single-quoted-string-literals)

**Constraints**

The start and end identifier names must be the same. Only horizontal white
space is permitted between `<<<` and the start identifier. No white
space is permitted between the start identifier and the new-line that
follows. No white space is permitted between the new-line and the end
identifier that follows. Except for an optional semicolon (`;`), no
characters—-not even comments or white space-—are permitted between the
end identifier and the new-line that terminates that source line.

**Semantics**

A heredoc string literal is a string literal delimited by
"`<<< name`" and "`name`". The literal can contain any source
character. Certain other (and sometimes non-printable) characters can
also be expressed as escape sequences.

A heredoc literal supports variable substitution as defined for
[double-quoted string literals](#double-quoted-string-literals).

A heredoc string literal is a constant expression if it does not contain
any variable substitution.

The optional *b-prefix* has no effect.

**Examples**

```PHP
$v = 123;
$s = <<<    ID
S'o'me "\"t e\txt; \$v = $v"
Some more text
ID;
echo ">$s<";
// → >S'o'me "\"t e  xt; $v = 123"
// Some more text<
```

######Nowdoc String Literals

**Syntax**

<pre>
  <i>nowdoc-string-literal::</i>
    <i>b-prefix<sub>opt</sub></i>  &lt;&lt;&lt;  '  <i>name</i>  '  <i>new-line  hd-body<sub>opt</sub>   name</i>  ;<i><sub>opt</sub>   new-line</i>
</pre>

**Defined elsewhere**

* [*hd-body*](#heredoc-string-literals)
* [*new-line*](#comments)
* [*b-prefix*](#single-quoted-string-literals)

**Constraints**

The start and end identifier names must be the same.
No white space is permitted between the start identifier name and its
enclosing single quotes (`'`). See also [heredoc string literal](#heredoc-string-literals).

**Semantics**

A nowdoc string literal looks like a [heredoc string literal](#heredoc-string-literals)
except that in the former the start identifier name is
enclosed in single quotes (`'`). The two forms of string literal have the
same semantics and constraints except that a nowdoc string literal is
not subject to variable substitution (like the single-quoted string).

A nowdoc string literal is a constant expression.

The optional *b-prefix* has no effect.

**Examples**

```PHP
$v = 123;
$s = <<<    'ID'
S'o'me "\"t e\txt; \$v = $v"
Some more text
ID;
echo ">$s<\n\n";
// → >S'o'me "\"t e\txt; \$v = $v"
// Some more text<
```

####Operators and Punctuators

**Syntax**

<pre>
  <i>operator-or-punctuator:: one of</i>
    [   ]   (   )   {   }   .   ->   ++   --   **   *   +   -   ~   !
    $   /   %   &lt;&lt;    &gt;&gt;   &lt;   &gt;   &lt;=   &gt;=   ==   ===   !=   !==   ^   |
    &   &&   ||   ?   :   ;   =   **=   *=   /=   %=   +=   -=   .=   &lt;&lt;=
    &gt;&gt;=   &=   ^=   |=   ,   ??   &lt;=&gt;   ...   \
</pre>

**Semantics**

Operators and punctuators are symbols that have independent syntactic
and semantic significance. *Operators* are used in expressions to
describe operations involving one or more *operands*, and that yield a
resulting value, produce a side effect, or some combination thereof.
*Punctuators* are used for grouping and separating.
