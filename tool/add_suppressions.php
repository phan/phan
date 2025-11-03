#!/usr/bin/env php
<?php declare(strict_types=1);

/**
 * Phan Suppression Auto-Fixer
 *
 * Automatically adds @phan-suppress annotations to code based on Phan issue output.
 *
 * Usage:
 *   ./phan --output-mode json | php tool/add_suppressions.php
 *   php tool/add_suppressions.php --from-json issues.json
 *   php tool/add_suppressions.php --dry-run --interactive
 *
 * Features:
 *   - Intelligent suppression type selection (line-level, function-level, file-level)
 *   - Line length constraint handling
 *   - Issue grouping and consolidation
 *   - Dry-run preview mode
 *   - Interactive confirmation
 *   - Configurable behavior
 */

// Bootstrap Phan's autoloader
foreach ([
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
] as $file) {
    if (file_exists($file)) {
        require_once $file;
        break;
    }
}

use Phan\Library\FileCacheEntry;
use Phan\Library\FileCache;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEdit;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEditSet;

/**
 * Configuration for suppression behavior
 */
class SuppressionConfig
{
    /** @var string Default suppression scope */
    public string $default_scope = 'next-line';

    /** @var int Minimum occurrences before using function-level suppression */
    public int $function_threshold = 3;

    /** @var int Minimum occurrences before using file-level suppression */
    public int $file_threshold = 10;

    /** @var int Maximum line length before moving to next line */
    public int $max_line_length = 120;

    /** @var array<string> Issue types to never auto-suppress */
    public array $never_suppress = [];

    /** @var array<string> Issue types to always file-suppress */
    public array $always_file_suppress = [
        'PhanUnreferencedFunction',
        'PhanUnreferencedPublicMethod',
    ];

    /** @var bool Enable verbose output */
    public bool $verbose = false;

    /**
     * Load configuration from file
     */
    public static function load(string $config_file): self
    {
        $config = new self();

        if (file_exists($config_file)) {
            $user_config = require $config_file;
            if (is_array($user_config)) {
                foreach ($user_config as $key => $value) {
                    if (property_exists($config, $key)) {
                        $config->$key = $value;
                    }
                }
            }
        }

        return $config;
    }
}

/**
 * Represents a Phan issue from JSON output
 */
class PhanIssue
{
    public string $type;
    public string $file;
    public int $line;
    public string $description;
    public int $severity;

    /**
     * Parse from Phan JSON output
     */
    public static function fromJson(array $json): self
    {
        $issue = new self();
        $issue->type = $json['check_name'] ?? '';
        $issue->file = $json['location']['path'] ?? '';
        $issue->line = $json['location']['lines']['begin'] ?? 0;
        $issue->description = $json['description'] ?? '';
        $issue->severity = $json['severity'] ?? 0;
        return $issue;
    }
}

/**
 * Represents a suppression to be added
 */
class Suppression
{
    public const TYPE_NEXT_LINE = 'next-line';
    public const TYPE_CURRENT_LINE = 'current-line';
    public const TYPE_FUNCTION = 'function';
    public const TYPE_FILE = 'file';

    public string $file;
    public int $line;
    public string $issue_type;
    public string $suppression_type;
    public ?int $function_line = null;  // For function-level suppressions

    public function __construct(string $file, int $line, string $issue_type, string $type)
    {
        $this->file = $file;
        $this->line = $line;
        $this->issue_type = $issue_type;
        $this->suppression_type = $type;
    }

    /**
     * Get the suppression comment text
     */
    public function getComment(?array $issue_types = null): string
    {
        $types = $issue_types ?? [$this->issue_type];
        $type_list = implode(', ', $types);

        switch ($this->suppression_type) {
            case self::TYPE_NEXT_LINE:
                return "// @phan-suppress-next-line {$type_list}";
            case self::TYPE_CURRENT_LINE:
                return " // @phan-suppress-current-line {$type_list}";
            case self::TYPE_FUNCTION:
                return " * @suppress {$type_list}";
            case self::TYPE_FILE:
                return "// @phan-file-suppress {$type_list}";
            default:
                return "// @phan-suppress-next-line {$type_list}";
        }
    }
}

/**
 * Main suppression tool
 */
class SuppressionTool
{
    private SuppressionConfig $config;
    private bool $dry_run;
    private bool $interactive;

    /** @var array<string,array<int,list<PhanIssue>>> Issues grouped by file and line */
    private array $issues_by_file = [];

    /** @var array<string,list<Suppression>> Suppressions to apply */
    private array $suppressions = [];

    public function __construct(SuppressionConfig $config, bool $dry_run = false, bool $interactive = false)
    {
        $this->config = $config;
        $this->dry_run = $dry_run;
        $this->interactive = $interactive;
    }

    /**
     * Read issues from JSON input
     */
    public function readIssues(string $json_input): void
    {
        // Try to decode as JSON array first
        $json_data = json_decode(trim($json_input), true);

        if (is_array($json_data)) {
            // It's a JSON array
            $issues = $json_data;
        } else {
            // Try line-delimited JSON
            $lines = explode("\n", trim($json_input));
            $issues = [];

            foreach ($lines as $line) {
                if (empty($line)) {
                    continue;
                }

                $data = json_decode($line, true);
                if (is_array($data)) {
                    $issues[] = $data;
                }
            }
        }

        foreach ($issues as $data) {
            if (!isset($data['check_name'])) {
                continue;
            }

            $issue = PhanIssue::fromJson($data);

            // Skip issues we should never suppress
            if (in_array($issue->type, $this->config->never_suppress)) {
                if ($this->config->verbose) {
                    echo "Skipping {$issue->type} (configured to never suppress)\n";
                }
                continue;
            }

            $this->issues_by_file[$issue->file][$issue->line][] = $issue;
        }
    }

    /**
     * Analyze issues and determine appropriate suppressions
     */
    public function analyzeSuppressions(): void
    {
        foreach ($this->issues_by_file as $file => $lines) {
            // Resolve relative paths
            $resolved_file = $file;
            if (!file_exists($resolved_file) && file_exists(getcwd() . '/' . $file)) {
                $resolved_file = getcwd() . '/' . $file;
            }

            if (!file_exists($resolved_file)) {
                fwrite(STDERR, "Warning: File not found: {$file}\n");
                continue;
            }

            // Update file path in the array
            if ($resolved_file !== $file) {
                $lines_data = $this->issues_by_file[$file];
                unset($this->issues_by_file[$file]);
                $this->issues_by_file[$resolved_file] = $lines_data;
                $file = $resolved_file;
            }

            // Count occurrences of each issue type in the file
            $issue_type_counts = [];
            foreach ($this->issues_by_file[$file] as $line_issues) {
                foreach ($line_issues as $issue) {
                    $issue_type_counts[$issue->type] = ($issue_type_counts[$issue->type] ?? 0) + 1;
                }
            }

            // Determine file-level suppressions
            $file_suppressions = [];
            foreach ($issue_type_counts as $type => $count) {
                if ($count >= $this->config->file_threshold ||
                    in_array($type, $this->config->always_file_suppress)) {
                    $file_suppressions[] = $type;
                }
            }

            // Add file-level suppressions if any
            if (!empty($file_suppressions)) {
                $suppression = new Suppression($file, 1, '', Suppression::TYPE_FILE);
                $this->suppressions[$file][] = [
                    'suppression' => $suppression,
                    'types' => $file_suppressions,
                ];
            }

            // Group issues by function for function-level suppression analysis
            $function_groups = $this->groupIssuesByFunction($file, $this->issues_by_file[$file]);

            // Analyze function-level suppressions
            foreach ($function_groups as $function_data) {
                $this->analyzeFunctionSuppressions($file, $function_data, $file_suppressions);
            }

            // Analyze remaining line-level suppressions (use the updated array after function-level handling)
            foreach ($this->issues_by_file[$file] ?? [] as $line_num => $line_issues) {
                $this->analyzeLine($file, $line_num, $line_issues, $file_suppressions);
            }
        }
    }

    /**
     * Group issues by containing function/method
     * @return array<int,array{line:int,issues:array<string,list<PhanIssue>>}>
     */
    private function groupIssuesByFunction(string $file, array $lines): array
    {
        $cache_entry = new FileCacheEntry(file_get_contents($file));
        $function_groups = [];

        // Use AST to find function boundaries
        try {
            $ast = $cache_entry->getAST();
            $functions = $this->findFunctions($ast);

            // Group issues by function
            foreach ($functions as $func_data) {
                $func_line = $func_data['line'];
                $func_end = $func_data['end_line'];
                $issues_by_type = [];

                foreach ($lines as $line_num => $line_issues) {
                    if ($line_num >= $func_line && $line_num <= $func_end) {
                        foreach ($line_issues as $issue) {
                            $issues_by_type[$issue->type][] = $issue;
                        }
                    }
                }

                if (!empty($issues_by_type)) {
                    $function_groups[] = [
                        'line' => $func_line,
                        'end_line' => $func_end,
                        'issues' => $issues_by_type,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // If AST parsing fails, skip function grouping
            if ($this->config->verbose) {
                fwrite(STDERR, "Warning: Could not parse AST for {$file}: {$e->getMessage()}\n");
            }
        }

        return $function_groups;
    }

    /**
     * Find all functions/methods in AST
     */
    private function findFunctions(\Microsoft\PhpParser\Node $ast): array
    {
        $functions = [];
        $this->traverseAST($ast, function($node) use (&$functions, $ast) {
            if ($node instanceof \Microsoft\PhpParser\Node\Statement\FunctionDeclaration ||
                $node instanceof \Microsoft\PhpParser\Node\MethodDeclaration) {

                $file_position_map = new \Microsoft\PhpParser\FilePositionMap($ast->getFileContents());
                $start_line = $file_position_map->getStartLine($node);
                $end_line = $file_position_map->getEndLine($node);

                $functions[] = [
                    'line' => $start_line,
                    'end_line' => $end_line,
                    'node' => $node,
                ];
            }
        });

        return $functions;
    }

    /**
     * Traverse AST and call callback for each node
     */
    private function traverseAST(\Microsoft\PhpParser\Node $node, callable $callback): void
    {
        $callback($node);

        foreach ($node->getChildNodes() as $child) {
            $this->traverseAST($child, $callback);
        }
    }

    /**
     * Analyze function-level suppressions
     */
    private function analyzeFunctionSuppressions(string $file, array $function_data, array $file_suppressions): void
    {
        foreach ($function_data['issues'] as $issue_type => $issues) {
            // Skip if covered by file-level suppression
            if (in_array($issue_type, $file_suppressions)) {
                continue;
            }

            // If 3+ occurrences of same type in function, use function-level suppression
            if (count($issues) >= $this->config->function_threshold) {
                $suppression = new Suppression(
                    $file,
                    $function_data['line'],
                    $issue_type,
                    Suppression::TYPE_FUNCTION
                );
                $this->suppressions[$file][] = [
                    'suppression' => $suppression,
                    'types' => [$issue_type],
                ];

                // Mark these issues as handled by removing them from the lines array
                foreach ($issues as $issue) {
                    $this->markIssueHandled($file, $issue);
                }
            }
        }
    }

    /**
     * Mark an issue as handled (remove from lines array)
     */
    private function markIssueHandled(string $file, PhanIssue $issue): void
    {
        if (!isset($this->issues_by_file[$file][$issue->line])) {
            return;
        }

        $this->issues_by_file[$file][$issue->line] = array_filter(
            $this->issues_by_file[$file][$issue->line],
            fn($i) => $i->type !== $issue->type || $i->line !== $issue->line
        );

        if (empty($this->issues_by_file[$file][$issue->line])) {
            unset($this->issues_by_file[$file][$issue->line]);
        }
    }

    /**
     * Analyze suppressions for a single line
     */
    private function analyzeLine(string $file, int $line, array $issues, array $file_suppressions): void
    {
        $cache_entry = new FileCacheEntry(file_get_contents($file));
        $lines = $cache_entry->getLines();
        $current_line = $lines[$line] ?? '';

        foreach ($issues as $issue) {
            // Skip if already covered by file-level suppression
            if (in_array($issue->type, $file_suppressions)) {
                continue;
            }

            // Determine suppression type
            $type = $this->determineSuppressionType($file, $line, $issue, $current_line);

            $suppression = new Suppression($file, $line, $issue->type, $type);
            $this->suppressions[$file][] = [
                'suppression' => $suppression,
                'types' => [$issue->type],
            ];
        }
    }

    /**
     * Determine the best suppression type for an issue
     */
    private function determineSuppressionType(string $file, int $line, PhanIssue $issue, string $current_line): string
    {
        // Check if current-line would exceed max length
        $comment = " // @phan-suppress-current-line {$issue->type}";
        if (strlen(rtrim($current_line)) + strlen($comment) > $this->config->max_line_length) {
            return Suppression::TYPE_NEXT_LINE;
        }

        // For short lines, use current-line
        if (strlen(rtrim($current_line)) < 80) {
            return Suppression::TYPE_CURRENT_LINE;
        }

        // Default to next-line for readability
        return Suppression::TYPE_NEXT_LINE;
    }

    /**
     * Apply suppressions to files
     */
    public function applySuppressions(): int
    {
        $total_applied = 0;

        foreach ($this->suppressions as $file => $suppressions) {
            if ($this->interactive && !$this->confirmFileChanges($file, $suppressions)) {
                continue;
            }

            $count = $this->applyToFile($file, $suppressions);
            $total_applied += $count;

            if ($this->dry_run || $this->config->verbose) {
                echo "Applied {$count} suppression(s) to {$file}\n";
            }
        }

        return $total_applied;
    }

    /**
     * Apply suppressions to a single file
     */
    private function applyToFile(string $file, array $suppressions): int
    {
        $cache_entry = new FileCacheEntry(file_get_contents($file));
        $lines = $cache_entry->getLines();
        $edits = [];

        // Sort suppressions by line number (reverse order to maintain offsets)
        usort($suppressions, fn($a, $b) => $b['suppression']->line <=> $a['suppression']->line);

        foreach ($suppressions as $supp_data) {
            /** @var Suppression $supp */
            $supp = $supp_data['suppression'];
            $types = $supp_data['types'];

            $edit = $this->createSuppressionEdit($cache_entry, $supp, $types, $lines);
            if ($edit !== null) {
                $edits[] = $edit;
            }
        }

        if (empty($edits) || $this->dry_run) {
            return count($edits);
        }

        // Apply edits
        $contents = $cache_entry->getContents();
        $edit_set = new FileEditSet($edits);
        $new_contents = $this->applyEdits($contents, $edit_set);

        file_put_contents($file, $new_contents);

        return count($edits);
    }

    /**
     * Create a FileEdit for a suppression
     */
    private function createSuppressionEdit(
        FileCacheEntry $cache_entry,
        Suppression $supp,
        array $types,
        array $lines
    ): ?FileEdit {
        $line_offset = $cache_entry->getLineOffset($supp->line);
        if ($line_offset === null) {
            return null;
        }

        switch ($supp->suppression_type) {
            case Suppression::TYPE_FILE:
                // Insert at beginning of file (after <?php)
                $first_line = $lines[1] ?? '';
                if (str_starts_with(trim($first_line), '<?php')) {
                    $offset = strlen($first_line);
                    $comment = "\n" . $supp->getComment($types) . "\n";
                    return new FileEdit($offset, $offset, $comment);
                }
                return null;

            case Suppression::TYPE_FUNCTION:
                // Insert PHPDoc block before function
                return $this->createPHPDocSuppression($cache_entry, $supp, $types, $lines);

            case Suppression::TYPE_NEXT_LINE:
                // Insert before the line
                $indent = $this->getIndentation($lines[$supp->line] ?? '');
                $comment = $indent . $supp->getComment($types) . "\n";
                return new FileEdit($line_offset, $line_offset, $comment);

            case Suppression::TYPE_CURRENT_LINE:
                // Append to end of line
                $current_line = $lines[$supp->line] ?? '';
                $eol_offset = $line_offset + strlen(rtrim($current_line));
                $comment = $supp->getComment($types);
                return new FileEdit($eol_offset, $eol_offset, $comment);

            default:
                return null;
        }
    }

    /**
     * Create PHPDoc block suppression for function/method
     */
    private function createPHPDocSuppression(
        FileCacheEntry $cache_entry,
        Suppression $supp,
        array $types,
        array $lines
    ): ?FileEdit {
        $function_line = $supp->line;
        $line_offset = $cache_entry->getLineOffset($function_line);

        if ($line_offset === null) {
            return null;
        }

        // Check if there's already a PHPDoc block
        $prev_line_num = $function_line - 1;
        $has_phpdoc = false;
        $phpdoc_start_line = $function_line;

        // Look backwards for existing PHPDoc
        while ($prev_line_num > 0) {
            $prev_line = trim($lines[$prev_line_num] ?? '');

            if (str_ends_with($prev_line, '*/')) {
                // Found end of PHPDoc, look for start
                $has_phpdoc = true;
                $phpdoc_start_line = $prev_line_num;

                while ($phpdoc_start_line > 1) {
                    $line = trim($lines[$phpdoc_start_line] ?? '');
                    if (str_starts_with($line, '/**') || str_starts_with($line, '/*')) {
                        break;
                    }
                    $phpdoc_start_line--;
                }
                break;
            } elseif (empty($prev_line)) {
                // Empty line, keep looking
                $prev_line_num--;
            } else {
                // Non-PHPDoc content
                break;
            }
        }

        $indent = $this->getIndentation($lines[$function_line] ?? '');

        if ($has_phpdoc) {
            // Check if it's a single-line PHPDoc comment
            $close_line = $function_line - 1;
            $close_line_content = trim($lines[$close_line] ?? '');
            $is_single_line = preg_match('/^\/\*\*.*\*\/$/', $close_line_content);

            $type_list = implode(', ', $types);

            if ($is_single_line) {
                // Convert single-line to multi-line and add @suppress
                // Extract content between /** and */
                preg_match('/^\/\*\*\s*(.*?)\s*\*\/$/', $close_line_content, $matches);
                $content = $matches[1] ?? '';

                $close_offset = $cache_entry->getLineOffset($close_line);
                if ($close_offset === null) {
                    return null;
                }

                // Build multi-line replacement
                $new_phpdoc = "{$indent}/**\n";
                if (!empty($content)) {
                    $new_phpdoc .= "{$indent} * {$content}\n";
                }
                $new_phpdoc .= "{$indent} * @suppress {$type_list}\n";
                $new_phpdoc .= "{$indent} */\n";

                // Replace the entire single-line comment
                $line_end_offset = $close_offset + strlen($lines[$close_line] ?? '');
                return new FileEdit($close_offset, $line_end_offset, $new_phpdoc);
            } else {
                // Multi-line PHPDoc: Insert @suppress before closing */
                $close_offset = $cache_entry->getLineOffset($close_line);
                if ($close_offset === null) {
                    return null;
                }

                $suppress_line = "{$indent} * @suppress {$type_list}\n";
                return new FileEdit($close_offset, $close_offset, $suppress_line);
            }
        } else {
            // Create new PHPDoc block
            $type_list = implode(', ', $types);
            $phpdoc = "{$indent}/**\n{$indent} * @suppress {$type_list}\n{$indent} */\n";

            return new FileEdit($line_offset, $line_offset, $phpdoc);
        }
    }

    /**
     * Get the indentation of a line
     */
    private function getIndentation(string $line): string
    {
        if (preg_match('/^(\s+)/', $line, $matches)) {
            return $matches[1];
        }
        return '';
    }

    /**
     * Apply a FileEditSet to contents
     */
    private function applyEdits(string $contents, FileEditSet $edit_set): string
    {
        // Sort edits by position (reverse order)
        $edits = $edit_set->edits;
        usort($edits, fn($a, $b) => $b->replace_start <=> $a->replace_start);

        foreach ($edits as $edit) {
            $before = substr($contents, 0, $edit->replace_start);
            $after = substr($contents, $edit->replace_end);
            $contents = $before . $edit->new_text . $after;
        }

        return $contents;
    }

    /**
     * Confirm changes with user in interactive mode
     */
    private function confirmFileChanges(string $file, array $suppressions): bool
    {
        echo "\nFile: {$file}\n";
        echo "Changes:\n";

        foreach ($suppressions as $supp_data) {
            $supp = $supp_data['suppression'];
            $types = $supp_data['types'];
            $type_list = implode(', ', $types);

            echo "  Line {$supp->line}: Add {$supp->suppression_type} suppression for {$type_list}\n";
        }

        echo "Apply these changes? [y/N/q(uit)]: ";
        $response = strtolower(trim(fgets(STDIN)));

        if ($response === 'q') {
            echo "Quitting...\n";
            exit(0);
        }

        return $response === 'y';
    }

    /**
     * Show summary of planned suppressions
     */
    public function showSummary(): void
    {
        $total = 0;
        $by_type = [];

        foreach ($this->suppressions as $file => $suppressions) {
            foreach ($suppressions as $supp_data) {
                $supp = $supp_data['suppression'];
                $type = $supp->suppression_type;
                $by_type[$type] = ($by_type[$type] ?? 0) + 1;
                $total++;
            }
        }

        echo "\nSuppression Summary:\n";
        echo "===================\n";
        echo "Total suppressions: {$total}\n";
        foreach ($by_type as $type => $count) {
            echo "  {$type}: {$count}\n";
        }
        echo "\n";
    }
}

/**
 * Main entry point
 */
function main(): int
{
    $options = getopt('', [
        'dry-run',
        'interactive',
        'from-json:',
        'config:',
        'verbose',
        'help',
    ]);

    if (isset($options['help'])) {
        echo <<<HELP
Phan Suppression Auto-Fixer

Usage:
  ./phan --output-mode json | php tool/add_suppressions.php [options]
  php tool/add_suppressions.php --from-json issues.json [options]

Options:
  --dry-run         Show what would be changed without modifying files
  --interactive     Confirm each file before making changes
  --from-json FILE  Read issues from JSON file instead of stdin
  --config FILE     Load configuration from file (default: .phan/suppress_config.php)
  --verbose         Show detailed output
  --help            Show this help message

Examples:
  # Dry-run to preview changes
  ./phan --output-mode json | php tool/add_suppressions.php --dry-run

  # Interactive mode with confirmation
  ./phan --output-mode json | php tool/add_suppressions.php --interactive

  # Read from file
  ./phan --output-mode json --no-progress-bar > issues.json
  php tool/add_suppressions.php --from-json issues.json

HELP;
        return 0;
    }

    // Load configuration
    $config_file = $options['config'] ?? '.phan/suppress_config.php';
    $config = SuppressionConfig::load($config_file);

    if (isset($options['verbose'])) {
        $config->verbose = true;
    }

    $dry_run = isset($options['dry-run']);
    $interactive = isset($options['interactive']);

    // Read input
    if (isset($options['from-json'])) {
        $json_input = file_get_contents($options['from-json']);
        if ($json_input === false) {
            fwrite(STDERR, "Error: Could not read file: {$options['from-json']}\n");
            return 1;
        }
    } else {
        $json_input = stream_get_contents(STDIN);
    }

    if (empty($json_input)) {
        fwrite(STDERR, "Error: No input provided. Use --from-json or pipe JSON output from Phan.\n");
        return 1;
    }

    // Create tool and process
    $tool = new SuppressionTool($config, $dry_run, $interactive);

    if ($config->verbose) {
        echo "Reading issues...\n";
    }

    $tool->readIssues($json_input);

    if ($config->verbose) {
        echo "Analyzing suppressions...\n";
    }

    $tool->analyzeSuppressions();
    $tool->showSummary();

    if ($dry_run) {
        echo "Dry-run mode: No files will be modified.\n\n";
    }

    $count = $tool->applySuppressions();

    if (!$dry_run) {
        echo "Successfully applied {$count} suppression(s).\n";
    }

    return 0;
}

exit(main());
