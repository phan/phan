<?php declare(strict_types=1);
namespace Phan;

use \Phan\Analyze\BreadthFirstVisitor;
use \Phan\Analyze\DepthFirstVisitor;
use \Phan\Analyze\ParseVisitor;
use \Phan\CLI;
use \Phan\CodeBase;
use \Phan\Config;
use \Phan\Debug;
use \Phan\Language\AST\Element;
use \Phan\Language\Context;
use \Phan\Language\FQSEN;
use \ast\Node;

/**
 * This class is the entry point into the static analyzer.
 */
class Phan {
    use \Phan\Analyze\DuplicateClass;
    use \Phan\Analyze\DuplicateFunction;
    use \Phan\Analyze\ParentClassExists;
    use \Phan\Analyze\ParentConstructorCalled;

    /**
     * Analyze the given set of files and emit any issues
     * found to STDOUT.
     *
     * @param CodeBase $code_base
     * A code base needs to be passed in because we require
     * it to be initialized before any classes or files are
     * loaded.
     *
     * @param string[] $file_path_list
     * A list of files to scan
     *
     * @return null
     * We emit messages to STDOUT. Nothing is returned.
     *
     * @see \Phan\CodeBase
     */
    public function analyzeFileList(
        CodeBase $code_base,
        array $file_path_list
    ) {
        $file_count = count($file_path_list);

        // This first pass parses code and populates the
        // global state we'll need for doing a second
        // analysis after.
        foreach ($file_path_list as $i => $file_path) {
            CLI::progress('parse',  ($i+1)/$file_count);

            // Check to see if we need to re-parse this file
            if (!$code_base->isParseUpToDateForFile($file_path)) {
                // Kick out anything we read from the former version
                // of this file
                $code_base->flushDependenciesForFile($file_path);

                // Parse the file
                $this->parseFile($code_base, $file_path);

                // Update the timestamp on when it was last
                // parsed
                $code_base->setParseUpToDateForFile($file_path);
            }
        }

        // Take a pass over all classes verifying various
        // states now that we have the whole state in
        // memory
        $this->analyzeClasses($code_base);

        // Take a pass over all functions verifying
        // various states now that we have the whole
        // state in memory
        $this->analyzeFunctions($code_base);

        // Once we know what the universe looks like we
        // can scan for more complicated issues.
        foreach ($file_path_list as $i => $file_path) {
            CLI::progress('analyze',  ($i+1)/$file_count);

            if (self::isThirdPartyFile($file_path)) {
                continue;
            }

            if (!$code_base->isAnalysisUpToDateForFile($file_path)) {
                // Analyze the file
                $this->analyzeFile($code_base, $file_path);

                // Mark that its analysis is up to date
                $code_base->setAnalysisUpToDateForFile($file_path);
            }
        }

        // Store a serialized version of the code base if there
        // is a configured serialized file path. This allows
        // us to save some time on the next run.
        $code_base->toDisk();

        // Emit all log messages
        Log::display();
    }

    /**
     * This first pass parses code and looks for the subset
     * of issues that can be found without having to have
     * an understanding of the entire code base.
     *
     * @param CodeBase $code_base
     * The CodeBase represents state across the entire
     * code base. This is a mutable object which is
     * populated as we parse files
     *
     * @param string $file_path
     * The full path to a file we'd like to parse
     */
    public function parseFile(
        CodeBase $code_base,
        string $file_path
    ) : Context {

        // Convert the file to an Abstract Syntax Tree
        // before passing it on to the recursive version
        // of this method
        $node = \ast\parse_file(
            $file_path,
            Config::get()->ast_version
        );

        $context =
            (new Context)->withFile($file_path);

        if (empty($node)) {
            Log::err(
                Log::EUNDEF,
                "Empty or missing file  {$file_path}",
                $file_path,
                0
            );
            return $context;
        }

        return $this->parseNodeInContext($node, $context, $code_base);
    }

    /**
     * Parse the given node in the given context populating
     * the code base within the context as a side effect. The
     * returned context is the new context from within the
     * given node.
     *
     * @param Node $node
     * A node to parse and scan for errors
     *
     * @param Context $context
     * The context in which this node exists
     *
     * @param CodeBase $code_base
     * The global code base in which we store all
     * state
     *
     * @return Context
     * The context from within the node is returned
     */
    public function parseNodeInContext(
        Node $node,
        Context $context,
        CodeBase $code_base
    ) : Context {

        // Visit the given node populating the code base
        // with anything we learn and get a new context
        // indicating the state of the world within the
        // given node
        $context =
            (new Element($node))->acceptKindVisitor(
                new ParseVisitor(
                    $context
                        ->withLineNumberStart($node->lineno ?? 0)
                        ->withLineNumberEnd($node->endLineno ?? 0),
                    $code_base
                )
            );

        assert(!empty($context), 'Context cannot be null');

        // Recurse into each child node
        $child_context = $context;
        foreach($node->children ?? [] as $child_node) {

            // Skip any non Node children.
            if (!($child_node instanceof Node)) {
                continue;
            }

            // Step into each child node and get an
            // updated context for the node
            $child_context =
                $this->parseNodeInContext(
                    $child_node,
                    $child_context,
                    $code_base
                );

            assert(!empty($child_context),
                'Context cannot be null');
        }

        // Pass the context back up to our parent
        return $context;
    }

    /**
     * Take a pass over all classes verifying various
     * states.
     *
     * @return null
     */
    private function analyzeClasses(CodeBase $code_base) {

        $class_count = 2 * count($code_base->getClassMap());

        // Take a pass to import all details from ancestors
        $i = 0;
        foreach ($code_base->getClassMap() as $fqsen_string => $clazz) {
            CLI::progress('classes',  ++$i/$class_count);

            // Make sure the parent classes exist
            self::analyzeParentClassExists($code_base, $clazz);

            // The import them
            $clazz->importAncestorClasses($code_base);
        }

        // Run a few checks on all of the classes
        foreach ($code_base->getClassMap() as $fqsen_string => $clazz) {
            CLI::progress('classes',  ++$i/$class_count);

            if ($clazz->getContext()->isInternal()) {
                continue;
            }

            self::analyzeDuplicateClass($code_base, $clazz);
            self::analyzeParentConstructorCalled($code_base, $clazz);
        }
    }

    /**
     * Take a pass over all functions verifying various
     * states.
     *
     * @return null
     */
    private function analyzeFunctions(CodeBase $code_base) {
        $function_count = count($code_base->getMethodMap());
        $i = 0;
        foreach ($code_base->getMethodMap() as $fqsen_string => $method) {
            CLI::progress('functions',  (++$i)/$function_count);

            if ($method->getContext()->isInternal()) {
                continue;
            }

            self::analyzeDuplicateFunction($code_base, $method);
        }
    }

    /**
     * Once we know what the universe looks like we
     * can scan for more complicated issues.
     *
     * @param CodeBase $code_base
     * The global code base holding all state
     *
     * @param string[] $file_path_list
     * A list of files to scan
     *
     * @return null
     */
    public function analyzeFile(
        CodeBase $code_base,
        string $file_path
    ) {
        // Convert the file to an Abstract Syntax Tree
        // before passing it on to the recursive version
        // of this method
        $node = \ast\parse_file(
            $file_path,
            Config::get()->ast_version
        );

        // Set the file on the context
        $context = (new Context)->withFile($file_path);

        // Ensure we have some content
        if (empty($node)) {
            Log::err(
                Log::EUNDEF,
                "Empty or missing file  {$file_path}",
                $file_path,
                0
            );

            return $context;
        }

        // Start recursively analyzing the tree
        return $this->analyzeNodeInContext(
            $node,
            $context,
            $code_base
        );
    }


    /**
     * @param Node $node
     * A node to parse and scan for errors
     *
     * @param Context $context
     * The context in which this node exists
     *
     * @return Context
     * The context from within the node is returned
     */
    public function analyzeNodeInContext(
        Node $node,
        Context $context,
        CodeBase $code_base,
        Node $parent_node = null,
        int $depth = 0
    ) : Context {

        // Visit the given node populating the code base
        // with anything we learn and get a new context
        // indicating the state of the world within the
        // given node
        $child_context =
            (new Element($node))->acceptKindVisitor(
                new DepthFirstVisitor(
                    $context
                        ->withLineNumberStart($node->lineno ?? 0)
                        ->withLineNumberEnd($node->endLineno ?? 0),
                    $code_base
                )
            );

        // Debug::printNodeName($node, $depth);
        // Debug::print((string)$child_context, $depth);

        assert(!empty($context), 'Context cannot be null');

        // Go depth first on that first set of analyses
		foreach($node->children ?? [] as $child_node) {
            // Skip any non Node children.
            if (!($child_node instanceof Node)) {
                continue;
            }

            // Step into each child node and get an
            // updated context for the node
            $child_context =
                $this->analyzeNodeInContext(
                    $child_node,
                    $child_context
                        ->withLineNumberStart($child_node->lineno ?? 0)
                        ->withLineNumberEnd($child_node->endLineno ?? 0),
                    $code_base,
                    $node,
                    $depth + 1
                );
		}

        // Do another pass across siblings
        $context =
            (new Element($node))->acceptKindVisitor(
                new BreadthFirstVisitor(
                    $context
                        ->withLineNumberStart($node->lineno ?? 0)
                        ->withLineNumberEnd($node->endLineno ?? 0),
                    $code_base,
                    $parent_node
                )
            );

        // Pass the context back up to our parent
        return $context;
    }

    /**
     * @return bool
     * True if this file is a member of a third party directory as
     * configured via the CLI flag '-3 [paths]'.
     */
    public static function isThirdPartyFile(string $file_path) : bool {
        foreach (Config::get()->third_party_directory_list
            as $directory
        ) {
            if (0 === strpos($file_path, $directory)) {
                return true;
            }
        }

        return false;
    }
}
