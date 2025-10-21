# Tolerant PHP Parser
[![CI](https://github.com/microsoft/tolerant-php-parser/actions/workflows/main.yml/badge.svg?branch=main)](https://github.com/microsoft/tolerant-php-parser/actions/workflows/main.yml)

This is an early-stage PHP parser designed, from the beginning, for IDE usage scenarios (see [Design Goals](#design-goals) for more details). There is
still a ton of work to be done, so at this point, this repo mostly serves as
an experiment and the start of a conversation.

![image](https://cloud.githubusercontent.com/assets/762848/19023070/4ab01c92-889a-11e6-9bb5-ec1a6816aba2.png)

This is the v0.1 branch, which changes data structures to support syntax added after the initial 0.0.x release line.

## Get Started
After you've [configured your machine](docs/GettingStarted.md), you can use the parser to generate and work
with the Abstract Syntax Tree (AST) via a friendly API.
```php
<?php
// Autoload required classes
require __DIR__ . "/vendor/autoload.php";

use Microsoft\PhpParser\{DiagnosticsProvider, Node, Parser, PositionUtilities};

// Instantiate new parser instance
$parser = new Parser();

// Return and print an AST from string contents
$astNode = $parser->parseSourceFile('<?php /* comment */ echo "hi!"');
var_dump($astNode);

// Gets and prints errors from AST Node. The parser handles errors gracefully,
// so it can be used in IDE usage scenarios (where code is often incomplete).
$errors = DiagnosticsProvider::getDiagnostics($astNode);
var_dump($errors);

// Traverse all Node descendants of $astNode
foreach ($astNode->getDescendantNodes() as $descendant) {
    if ($descendant instanceof Node\StringLiteral) {
        // Print the Node text (without whitespace or comments)
        var_dump($descendant->getText());

        // All Nodes link back to their parents, so it's easy to navigate the tree.
        $grandParent = $descendant->getParent()->getParent();
        var_dump($grandParent->getNodeKindName());

        // The AST is fully-representative, and round-trippable to the original source.
        // This enables consumers to build reliable formatting and refactoring tools.
        var_dump($grandParent->getLeadingCommentAndWhitespaceText());
    }

    // In addition to retrieving all children or descendants of a Node,
    // Nodes expose properties specific to the Node type.
    if ($descendant instanceof Node\Expression\EchoExpression) {
        $echoKeywordStartPosition = $descendant->echoKeyword->getStartPosition();
        // To cut down on memory consumption, positions are represented as a single integer
        // index into the document, but their line and character positions are easily retrieved.
        $lineCharacterPosition = PositionUtilities::getLineCharacterPositionFromPosition(
            $echoKeywordStartPosition,
            $descendant->getFileContents()
        );
        echo "line: $lineCharacterPosition->line, character: $lineCharacterPosition->character";
    }
}
```

> Note: [the API](docs/ApiDocumentation.md) is not yet finalized, so please file issues let us know what functionality you want exposed,
and we'll see what we can do! Also please file any bugs with unexpected behavior in the parse tree. We're still
in our early stages, and any feedback you have is much appreciated :smiley:.

## Design Goals
* Error tolerant design - in IDE scenarios, code is, by definition, incomplete. In the case that invalid code is entered, the
parser should still be able to recover and produce a valid + complete tree, as well as relevant diagnostics.
* Fast and lightweight (should be able to parse several MB of source code per second,
 to leave room for other features).
  * Memory-efficient data structures
  * Allow for incremental parsing in the future
* Adheres to [PHP language spec](https://github.com/php/php-langspec),
supports both PHP5 and PHP7 grammars
* Generated AST provides properties (fully representative, etc.) necessary for semantic and transformational
operations, which also need to be performant.
  * Fully representative and round-trippable back to the text it was parsed from (all whitespace and comment "trivia" are included in the parse tree)
  * Possible to easily traverse the tree through parent/child nodes
  * [< 100 ms UI response time](https://www.computer.org/csdl/proceedings/afips/1968/5072/00/50720267.pdf),
so each language server operation should be < 50 ms to leave room for all the
 other stuff going on in parallel.
* Simple and maintainable over time - parsers have a tendency to get *really*
 confusing, really fast, so readability and debug-ability is high priority.
* Testable - the parser should produce provably valid parse trees. We achieve this by defining and continuously testing
 a set of invariants about the tree.
* Friendly and descriptive API to make it easy for others to build on.
* Written in PHP - make it as easy as possible for the PHP community to consume and contribute.

## Current Status and Approach
To ensure a sufficient level of correctness at every step of the way, the
parser is being developed using the following incremental approach:

* [x] **Phase 1:** Write lexer that does not support PHP grammar, but supports EOF
and Unknown tokens. Write tests for all invariants.
* [x] **Phase 2:** Support PHP lexical grammar, lots of tests
* [x] **Phase 3:** Write a parser that does not support PHP grammar, but produces tree of
Error Nodes. Write tests for all invariants.
* [x] **Phase 4:** Support PHP syntactic grammar, lots of tests
* [ ] **Phase 5 (in progress :running:):** Real-world validation and optimization
  * [ ] _**Correctness:**_ validate that there are no errors produced on sample codebases, benchmark against other parsers (investigate any instance of disagreement), fuzz-testing
  * [ ] _**Performance:**_ profile, benchmark against large PHP applications
* [ ] **Phase 6:** Finalize API to make it as easy as possible for people to consume.

### Additional notes
A few of the PHP grammatical constructs (namely yield-expression, and template strings)
are not yet supported and there are also other miscellaneous bugs. However, because the parser is error-tolerant,
these errors are handled gracefully, and the resulting tree is otherwise complete. To get a more holistic sense for
where we are, you can run the "validation" test suite (see [Contributing Guidelines](Contributing.md) for more info
on running tests). Or simply, take a look at the current [validation test results](https://travis-ci.org/Microsoft/tolerant-php-parser).

Even though we haven't yet begun the performance optimization stage, we have seen promising results so far,
and have plenty more room for improvement. See [How It Works](docs/HowItWorks.md) for details on our current
approach, and run the [Performance Tests](Contributing.md#running-performance-tests) on your
own machine to see for yourself.

## Learn more
**:dart: [Design Goals](#design-goals)** - learn about the design goals of the project (features, performance metrics, and more).

**:book: [Documentation](docs/GettingStarted.md#getting-started)** - learn how to reference the parser from your project, and how to perform
operations on the AST to answer questions about your code.

**:eyes: [Syntax Visualizer Tool](syntax-visualizer/client#php-parser-syntax-visualizer-tool)** - get a more tangible feel for the AST. Get creative - see if you can break it!

**:chart_with_upwards_trend: [Current Status and Approach](#current-status-and-approach)** - how much of the grammar is supported? Performance? Memory? API stability?

**:wrench: [How it works](docs/HowItWorks.md)** - learn about the architecture, design decisions, and tradeoffs.
  * [Lexer and Parser](docs/HowItWorks.md#lexer)
  * [Error Tolerance Strategy](docs/HowItWorks.md#error-tokens)
  * [Incremental Parsing](docs/HowItWorks.md#incremental-parsing)
  * [Open Questions](docs/HowItWorks.md#open-questions)
  * [Validation Strategy](docs/HowItWorks.md#validation-strategy)

**:sparkling_heart: [Contribute!](Contributing.md)** - learn how to get involved, check out some pointers to educational commits that'll
help you ramp up on the codebase (even if you've never worked on a parser before),
and recommended workflows that make it easier to iterate.

---
This project has adopted the [Microsoft Open Source Code of Conduct](https://opensource.microsoft.com/codeofconduct/).
For more information see the [Code of Conduct FAQ](https://opensource.microsoft.com/codeofconduct/faq/) or contact
[opencode@microsoft.com](mailto:opencode@microsoft.com) with any additional questions or comments.
