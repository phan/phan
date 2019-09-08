<?php declare(strict_types=1);

namespace Phan\Plugin\Internal;

use ast\Node;
use Exception;
use Phan\CLI;
use Phan\CodeBase;
use Phan\Exception\FQSENException;
use Phan\Language\Element\AddressableElement;
use Phan\Language\Element\Clazz;
use Phan\Language\FileRef;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeClassCapability;
use Phan\PluginV3\FinalizeProcessCapability;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;
use Throwable;

/**
 * This plugin only works correctly with Phan -j1
 * see tool/pdep for an intelligent wrapper for it
 */
class DependencyGraphPlugin extends PluginV3 implements
    AnalyzeClassCapability,
    PostAnalyzeNodeCapability,
    FinalizeProcessCapability
{
    /** @var array<int, AddressableElement> */
    private $elements = [];
    /** @var string[] */
    private $class_to_file = [];
    /** @var string[] */
    private $file_to_class = [];
    /** @var array<string,array<string,string>> File-based graph */
    private $fgraph = [];
    /** @var array<string,array<string,string>> Class-based graph */
    private $cgraph = [];
    /** Class types */
    private $ctype = [];
    /** @var int */
    private $depth = 0;

    /** @var array<int,array<string,array<string,string>>> */
    public static $static_calls = [];
    /** @var array<int,array<string,array<string,string>>> */
    public static $static_vars = [];

    /**
     * Build <filename>:<lineno> string
     */
    private function getFileString(FileRef $file_ref):string
    {
        return $file_ref->getFile() . ':' . $file_ref->getLineNumberStart();
    }

    /** Build file<->class mappings */
    public function analyzeClass(CodeBase $unused_code_base, Clazz $class): void
    {
        $this->elements[] = $class;
        $cnode = (string)$class->getFQSEN()->getCanonicalFQSEN();
        if ($class->isInterface()) {
            $ctype = "I";
        } elseif ($class->isTrait()) {
            $ctype = "T";
        } else {
            $ctype = "C";
        }
        $this->ctype[$cnode] = $ctype;
        $this->class_to_file[$cnode] = $this->getFileString($class->getContext());
        $this->file_to_class[$this->getFileString($class->getContext())] = $cnode;
    }

    /**
     * Walk the class graph
     *
     * @param string[][] $newgraph
     * @param string $node
     * @return string[][]
     */
    private function walkcGraph(array $newgraph, string $node, int $depth = 0):array
    {
        static $i = 0;
        static $visited = [];

        if ($this->depth && $depth >= $this->depth) {
            return $newgraph;
        }
        if (!empty($visited[$node])) {
            return $newgraph;
        }
        CLI::progress('graph', (++$i) / \count($this->cgraph), $node);
        if (!\array_key_exists($node, $this->cgraph)) {
            if (!\array_key_exists('\\' . $node, $this->cgraph)) {
                return $newgraph;
            } else {
                $node = '\\' . $node;
            }
        }
        $newgraph[$node] = $this->cgraph[$node];
        $visited[$node] = true;
        foreach ($this->cgraph[$node] as $n => $unused) {
            $newgraph = $this->walkcGraph($newgraph, $n, $depth + 1);
        }
        return $newgraph;
    }

    /**
     * Walk the file graph
     *
     * @param string[][] $newgraph
     * @param string $node
     * @return string[][]
     */
    private function walkfGraph(array $newgraph, string $node, int $depth = 0):array
    {
        static $i = 0;
        static $visited = [];

        if ($this->depth && $depth >= $this->depth) {
            return $newgraph;
        }
        if (!empty($visited[$node])) {
            return $newgraph;
        }
        CLI::progress('graph', (++$i) / \count($this->cgraph), $node);
        if (!\array_key_exists($node, $this->fgraph)) {
            return $newgraph;
        }
        $newgraph[$node] = $this->fgraph[$node];
        $visited[$node] = true;
        foreach ($this->fgraph[$node] as $n => $unused) {
            $newgraph = $this->walkfGraph($newgraph, $n);
        }
        return $newgraph;
    }

    /**
     * Print a graph for debugging purposes
     *
     * @param string[][] $graph
     */
    private function printGraph(array $graph):void
    {
        foreach ($graph as $k => $v) {
            echo "$k\n";
            foreach ($v as $kk => $vv) {
                echo "\t$kk";
                [$t,$lineno] = \explode(':', $vv);
                switch ($t) {
                    case 'C':
                        $type = 'Inheritance';
                        break;
                    case 'I':
                        $type = 'Interface';
                        break;
                    case 'T':
                        $type = 'Trait';
                        break;
                    case 's':
                        $type = 'Static call';
                        break;
                    case 'v':
                        $type = 'Static class variable';
                        break;
                    default:
                        $type = "unknown ($t)";
                        break;
                }
                if (\strstr((string)$kk, '.')) {
                    echo ":$lineno $type\n";
                } else {
                    if (!\array_key_exists($kk, $this->class_to_file)) {
                        continue;
                    }
                    [$file]  = \explode(':', $this->class_to_file[$kk]);
                    echo " - $file:$lineno $type\n";
                }
            }
        }
    }

    /**
     * Build the actual class and file graphs
     */
    public function finalizeProcess(CodeBase $unused_code_base): void
    {
        if (empty($this->elements)) {
            \fwrite(\STDERR, "Nothing to analyze - please run pdep from your top-level project directory" . \PHP_EOL);
            exit(\EXIT_FAILURE);
        }
        // Loop through all the elements and pull out the reference list for each
        foreach ($this->elements as $element) {
            $fnode = $element->getFileRef()->getFile();

            $cnode = (string)$element->getFQSEN()->getCanonicalFQSEN();
            if ($element->isInterface()) {
                $ctype = "I";
            } elseif ($element->isTrait()) {
                $ctype = "T";
            } else {
                $ctype = "C";
            }

            if (empty($this->fgraph[$fnode])) {
                $this->fgraph[$fnode] = [];
            }
            if (empty($this->cgraph[$cnode])) {
                $this->cgraph[$cnode] = [];
            }
            $refs = $element->getReferenceList();
            foreach ($refs as $ref) {
                $depNode = $ref->getFile();
                if (empty($this->file_to_class[$this->getFileString($ref)])) {
                    continue;
                }
                $cdepNode = $this->file_to_class[$this->getFileString($ref)];
                $this->fgraph[$fnode][$depNode] = $ctype . ':' . $ref->getLineNumberStart();
                $this->cgraph[$cnode][$cdepNode] = $ctype . ':' . $ref->getLineNumberStart();
            }
        }
        foreach (self::$static_calls as $c) {
            $cnode = \key($c);
            if (!$cnode) {
                continue;
            }
            if (!\array_key_exists($cnode, $this->class_to_file)) {
                continue;
            }
            [$fnode] = \explode(':', $this->class_to_file[$cnode]);
            $this->fgraph[$fnode][$c[$cnode]['file']]  = 's:' . $c[$cnode]['lineno'];
            $this->cgraph[$cnode][$c[$cnode]['class']] = 's:' . $c[$cnode]['lineno'];
        }
        foreach (self::$static_vars as $c) {
            $cnode = \key($c);
            if (!$cnode) {
                continue;
            }
            if (!\array_key_exists($cnode, $this->class_to_file)) {
                continue;
            }
            [$fnode] = \explode(':', $this->class_to_file["$cnode"]);
            $this->fgraph[$fnode][$c[$cnode]['file']]  = 'v:' . $c[$cnode]['lineno'];
            $this->cgraph[$cnode][$c[$cnode]['class']] = 'v:' . $c[$cnode]['lineno'];
        }

        // $this->printGraph($this->cgraph);
        // $this->printGraph($this->fgraph);

        $cmd  = $_ENV['PDEP_CMD'];
        $mode = $_ENV['PDEP_MODE'];
        $this->depth = (int)$_ENV['PDEP_DEPTH'];
        $args = empty($_ENV['PDEP_ARGS']) ? null : \explode(' ', $_ENV['PDEP_ARGS']);
        $json = false;

        if (\Phan\Phan::$printer instanceof \Phan\Output\Printer\JSONPrinter) {
            $json = true;
        }

        $graph = $this->cgraph;
        if (empty($args)) {
            if ($mode == 'class') {
                $graph = $this->cgraph;
            } elseif ($mode == 'file') {
                $graph = $this->fgraph;
            }
        } else {
            $graph = [];
            foreach ($args as $v) {
                if (empty($v)) {
                    continue;
                }
                if ($mode == 'class') {
                    if (\strstr($v, '.')) {
                        if (!\array_key_exists($v, $this->file_to_class)) {
                            // Probably no lineno specified, do a linear search
                            foreach ($this->file_to_class as $fi => $cl) {
                                [$file] = \explode(':', (string)$fi);
                                if ($file == $v) {
                                    $v = $cl;
                                    goto found;
                                }
                            }
                            \fwrite(\STDERR, "Couldn't find file $v" . \PHP_EOL);
                            exit(\EXIT_FAILURE);
                        }
                        $v = $this->file_to_class[$v];
                    }
                    found:
                    $graph = $this->walkcGraph($graph, $v);
                } elseif ($mode == 'file') {
                    if (!\strstr($v, '.')) {
                        try {
                            $fqsen = FullyQualifiedClassName::fromFullyQualifiedString($v);
                        } catch (FQSENException $e) {
                            \fwrite(\STDERR, "Invalid class fqsen $v: {$e->getMessage()}\n");
                            exit(\EXIT_FAILURE);
                        }
                        $cnode = (string)$fqsen;
                        if (!\array_key_exists($cnode, $this->class_to_file)) {
                            \fwrite(\STDERR, "Couldn't find class $cnode" . \PHP_EOL);
                            exit(\EXIT_FAILURE);
                        }
                        [$v] = \explode(':', $this->class_to_file[$cnode]);
                    }
                    $graph = $this->walkfGraph($graph, $v);
                }
            }
        }
        if (CLI::shouldShowProgress()) {
            // Don't overlap stdout with the progress bar on stderr.
            \fwrite(\STDERR, "\n");
        }
        if ($cmd == 'graph') {
            ($mode == 'class') ? $this->dumpClassDot(\basename((string)\getcwd()), $graph) : $this->dumpFileDot(\basename((string)\getcwd()), $graph);
        } else {
            if ($json) {
                echo \json_encode($graph);
            } else {
                $this->printGraph($graph);
            }
        }
        exit(\EXIT_SUCCESS);
    }

    /**
     * Dump the graphviz dot output for the file graph
     * @param string $title
     * @param ?array<string,array<string,string>> $graph
     */
    private function dumpFileDot(string $title, array $graph = null):void
    {
        if (!$graph || empty($graph)) {
            $graph = $this->fgraph;
        }
        $shapes = '';
        echo "strict digraph $title {\nrankdir=RL\nsplines=ortho\n";
        foreach ($graph as $node => $depNode) {
            $shapes .= "\"$node\" [shape=box]\n";
            foreach ($depNode as $dnode => $val) {
                [$type,$lineno] = \explode(':', (string)$val);
                $style = '';
                if ($type == 's') {
                    $style = ',color=seagreen';
                }
                if ($type == 'v') {
                    $style = ',color=tomato';
                }
                echo "\"$dnode\" -> \"$node\" [taillabel=$lineno,labelfontsize=10,labeldistance=1.4{$style}]\n";
            }
        }
        echo $shapes;
        echo "}\n";
    }

    /**
     * Dump the graphviz dot output for the class graph
     * @param string $title
     * @param ?array<string,array<string,string>> $graph
     */
    private function dumpClassDot(string $title, array $graph = null):void
    {
        if (!$graph) {
            $graph = $this->cgraph;
        }
        echo "strict digraph $title {\nrankdir=RL\nsplines=ortho\n";
        $shapes = '';
        foreach ($graph as $node => $depNode) {
            $shape = "";
            switch ($this->ctype[$node]) {
                case 'C':
                    $shape = "shape=box";
                    break;
                case 'I':
                    $shape = "shape=note,color=blue,fontcolor=blue";
                    break;
                case 'T':
                    $shape = "shape=component,color=purple,fontcolor=purple";
                    break;
            }
            if ($shape) {
                // Defer the shape definitions until after the edges. This tends to give a better node layout
                $shapes .= '"' . \addslashes(\trim($node, "\\")) . "\" [$shape]\n";
            }
            foreach ($depNode as $dnode => $val) {
                [$type] = \explode(':', (string)$val);
                $style = '';
                if ($type == 's') {
                    $style = ' [color=seagreen]';
                }
                if ($type == 'v') {
                    $style = ' [color=tomato]';
                }
                echo '"' . \addslashes(\trim($dnode, "\\")) . '" -> "' . \addslashes(\trim($node, "\\")) . "\"$style\n";
            }
        }
        echo $shapes;
        echo "}\n";
    }

    /** Hook up our visitor class */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return DependencyGraphVisitor::class;
    }
}

/**
 * Our visitor methods live here
 */
class DependencyGraphVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * When we hit an AST_CLASS_CONST
     * @throws Exception
     */
    public function visitClassConst(Node $node):void
    {
        $context = $this->context;
        try {
            $class_fqsen = $context->getClassFQSEN();
        } catch (Throwable $unused_e) {
            return;
        }
        if ($context->isInGlobalScope()) {
            return;
        }
        $called_class = $node->children['class'];
        if (!isset($called_class->children['name'])) {
            return;
        }
        $called_class_name = (string)$called_class->children['name'];
        // None of these add any dependency data we don't already have, so ignore them
        if ($called_class_name == 'self' || $called_class_name == 'parent' || $called_class_name == 'class' || $called_class_name == 'static') {
            return;
        }
        $fqsen = (string)FullyQualifiedClassName::fromStringInContext($called_class_name, $context);
        DependencyGraphPlugin::$static_vars[] = [$fqsen => ['class' => (string)$class_fqsen,'file' => $context->getFile(),'lineno' => $context->getLineNumberStart()]];
    }

    /**
     * When we hit an AST_STATIC_CALL
     * @throws Exception
     */
    public function visitStaticCall(Node $node):void
    {
        $context = $this->context;
        try {
            $class_fqsen = $context->getClassFQSEN();
        } catch (Throwable $unused_e) {
            return;
        }
        if ($context->isInGlobalScope()) {
            return;
        }
        $called_class = $node->children['class'];
        if (!isset($called_class->children['name'])) {
            return;
        }
        $called_class_name = (string)$called_class->children['name'];
        if ($called_class_name == 'self' || $called_class_name == 'parent' || $called_class_name == 'class' || $called_class_name == 'static') {
            return;
        }
        $fqsen = (string)FullyQualifiedClassName::fromStringInContext($called_class_name, $context);
        DependencyGraphPlugin::$static_calls[] = [$fqsen => ['class' => (string)$class_fqsen,'file' => $context->getFile(),'lineno' => $context->getLineNumberStart()]];
    }
}

return new DependencyGraphPlugin();
