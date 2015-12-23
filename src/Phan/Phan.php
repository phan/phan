<?php declare(strict_types=1);
namespace Phan;

use \Phan\AST\ChooseContext;
use \Phan\Analyze\BreadthFirstVisitor;
use \Phan\Analyze\DepthFirstVisitor;
use \Phan\Analyze\MergeVisitor;
use \Phan\Analyze\ParseVisitor;
use \Phan\CLI;
use \Phan\CodeBase;
use \Phan\Config;
use \Phan\Debug;
use \Phan\Language\Context;
use \Phan\Language\FQSEN;
use \ast\Node;

/**
 * This class is the entry point into the static analyzer.
 */
class Phan {
    use \Phan\Analyze\DuplicateClass;
    use \Phan\Analyze\DuplicateFunction;
    use \Phan\Analyze\ParameterTypes;
    use \Phan\Analyze\ParentClassExists;
    use \Phan\Analyze\ParentConstructorCalled;
    use \Phan\Analyze\PropertyTypes;
    use \Phan\Analyze\ReferenceCounts;

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

        // We'll construct a set of files that we'll
        // want to run an analysis on
        $analyze_file_path_list = [];

        // This first pass parses code and populates the
        // global state we'll need for doing a second
        // analysis after.
        foreach ($file_path_list as $i => $file_path) {
            CLI::progress('parse',  ($i+1)/$file_count);

            // Check to see if we need to re-parse this file
            if (Config::get()->reanalyze_file_list
                || !$code_base->isParseUpToDateForFile($file_path)) {

                // Save this to the set of files to analyze
                $analyze_file_path_list[] = $file_path;

                // Kick out anything we read from the former version
                // of this file
                $code_base->flushDependenciesForFile(
                    $file_path
                );

                // Parse the file
                $this->parseFile($code_base, $file_path);

                // Update the timestamp on when it was last
                // parsed
                $code_base->setParseUpToDateForFile($file_path);
            }
        }

        // Don't continue on to analysis if the user has
        // chosen to just dump the AST
        if (Config::get()->dump_ast) {
            exit;
        }

        // Take a pass over all classes verifying various
        // states now that we have the whole state in
        // memory
        $this->analyzeClasses($code_base);

        // Take a pass over all functions verifying
        // various states now that we have the whole
        // state in memory
        $this->analyzeFunctions($code_base);

        // We can only save classes, methods, properties and
        // constants after we've merged parent classes in.
        $code_base->store();

        // Once we know what the universe looks like we
        // can scan for more complicated issues.
        $file_count = count($analyze_file_path_list);
        foreach ($analyze_file_path_list as $i => $file_path) {
            CLI::progress('analyze',  ($i+1)/$file_count);

            // We skip anything defined as 3rd party code
            // to save a lil' time
            if (self::isExcludedAnalysisFile($file_path)) {
                continue;
            }

            // Analyze the file
            $this->analyzeFile($code_base, $file_path);
        }

        // Scan through all globally accessible elements
        // in the code base and emit errors for dead
        // code.
        $this->analyzeDeadCode($code_base);

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

        $context = (new Context)->withFile($file_path);

        if (Config::get()->dump_ast) {
            echo $file_path . "\n"
                . str_repeat("\u{00AF}", strlen($file_path))
                . "\n";
            Debug::printNode($node);
            return $context;
        }

        if (empty($node)) {
            Log::err(
                Log::EUNDEF,
                "Empty or missing file  {$file_path}",
                $file_path,
                0
            );
            return $context;
        }

        return $this->parseNodeInContext(
            $code_base,
            $context,
            $node
        );
    }

    /**
     * Parse the given node in the given context populating
     * the code base within the context as a side effect. The
     * returned context is the new context from within the
     * given node.
     *
     * @param CodeBase $code_base
     * The global code base in which we store all
     * state
     *
     * @param Context $context
     * The context in which this node exists
     *
     * @param Node $node
     * A node to parse and scan for errors
     *
     * @return Context
     * The context from within the node is returned
     */
    public function parseNodeInContext(
        CodeBase $code_base,
        Context $context,
        Node $node
    ) : Context {

        // Visit the given node populating the code base
        // with anything we learn and get a new context
        // indicating the state of the world within the
        // given node
        $context = (new ParseVisitor(
            $code_base,
            $context->withLineNumberStart($node->lineno ?? 0)
        ))($node);

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
            $child_context = $this->parseNodeInContext(
                $code_base,
                $child_context,
                $child_node
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
            self::analyzePropertyTypes($code_base, $clazz);
        }
    }

    /**
     * Take a pass over all functions verifying various
     * states.
     *
     * @return null
     */
    private function analyzeFunctions(CodeBase $code_base) {
        $function_count = count($code_base->getMethodMap(), COUNT_RECURSIVE);
        $i = 0;
        foreach ($code_base->getMethodMap() as $fqsen_string => $method_map) {
            foreach ($method_map as $name => $method) {
                CLI::progress('method',  (++$i)/$function_count);

                if ($method->getContext()->isInternal()) {
                    continue;
                }

                self::analyzeDuplicateFunction($code_base, $method);
                self::analyzeParameterTypes($code_base, $method);
            }
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
     * @return Context
     */
    public function analyzeFile(
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
            $code_base,
            $context,
            $node
        );
    }


    /**
     * @param CodeBase $code_base
     * A code base needs to be passed in because we require
     * it to be initialized before any classes or files are
     * loaded.
     *
     * @param Context $context
     * The context in which this node exists
     *
     * @param Node $node
     * A node to parse and scan for errors
     *
     * @return Context
     * The context from within the node is returned
     */
    public function analyzeNodeInContext(
        CodeBase $code_base,
        Context $context,
        Node $node,
        Node $parent_node = null,
        int $depth = 0
    ) : Context {

        // Visit the given node populating the code base
        // with anything we learn and get a new context
        // indicating the state of the world within the
        // given node
        $child_context = (new DepthFirstVisitor(
            $code_base,
            $context->withLineNumberStart($node->lineno ?? 0)
        ))($node);

        assert(!empty($context), 'Context cannot be null');

        // We collect all child context so that the
        // BreadthFirstVisitor can optionally operate on
        // them
        $child_context_list = [];

        // Go depth first on that first set of analyses
		foreach($node->children ?? [] as $child_node) {
            // Skip any non Node children.
            if (!($child_node instanceof Node)) {
                continue;
            }

            /*
            // Some nodes don't share state between child nodes
            // such as conditionals where we treat them as if
            // the branches share no state
            switch ($node->kind) {
            case \ast\AST_IF:
                $child_context = $parent_context;
                break;
            }
            */

            // Step into each child node and get an
            // updated context for the node
            $child_context = $this->analyzeNodeInContext(
                $code_base,
                $child_context
                    ->withLineNumberStart($child_node->lineno ?? 0),
                $child_node,
                $node,
                $depth + 1
            );

            $child_context_list[] = $child_context;
		}

        /*
        // Now that we have n child contexts, we merge them
        // into a single context before doing the breadth
        // first analysis
        $context = MergeVisitor::merge(
            $code_base, $context, $child_context_list, $node
        );
        */

        $context = (new BreadthFirstVisitor(
            $code_base,
            $context->withLineNumberStart($node->lineno ?? 0),
            $parent_node
        ))($node);

        // Pass the context back up to our parent
        return $context;
    }

    /**
     * Take a look at all globally accessible elements and see if
     * we can find any dead code that is never referenced
     *
     * @return void
     */
    public function analyzeDeadCode(CodeBase $code_base) {
        // Check to see if dead code detection is enabled. Keep
        // in mind that the results here are just a guess and
        // we can't tell with certainty that anything is
        // definitely unreferenced.
        if (!Config::get()->dead_code_detection) {
            return;
        }

        self::analyzeReferenceCounts($code_base);
    }

    /**
     * @return bool
     * True if this file is a member of a third party directory as
     * configured via the CLI flag '-3 [paths]'.
     */
    public static function isExcludedAnalysisFile(string $file_path) : bool {
        foreach (Config::get()->exclude_analysis_directory_list
            as $directory
        ) {
            if (0 === strpos($file_path, $directory) || 0 === strpos($file_path, "./$directory")) {
                return true;
            }
        }

        return false;
    }
}
