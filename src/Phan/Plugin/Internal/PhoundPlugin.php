<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\ContextNode;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\CodeBase;
use Phan\Language\Element\ClassElement;
use Phan\Config;

/**
 * Populates a SQLite3 database (version >= 3.32.0 required) with callsites of class elements, as well as class,
 * trait, and interface hierarchies. Class elements include methods, static methods, properties, static properties,
 * and constants. Class hierarchies include classes, interfaces, traits, and their associated relationships.
 *
 * The database can be queried to find callsites of a given class element as well as class, trait,
 * and interface relationships, including transitive relationships of all three.
 *
 * Examples:
 *
 * 1) Search for callsites of the \Foo::bar method:
 *     select * from callsites where element = '\Foo::bar' and type = 'method' order by callsite
 *
 * 2) Search for callsites of the \Foo::bar method in a specific directory 'my_directory':
 *     select * from callsites where element = '\Foo::bar' and type = 'method' and callsite like 'my_directory%' order by callsite
 *
 * 3) Search for callsites of the \Foo::baz property:
 *     select * from callsites where element = '\Foo::baz' and type = 'prop' order by callsite
 *
 * 4) Search for callsites of the \Foo::BANG constant:
 *     select * from callsites where element = '\Foo::BANG' and type = 'const' order by callsite
 *
 * Relationship tables are pre-flattened at finalization time using recursive CTEs, so
 * transitive relationships are materialized directly. This means queries don't need
 * recursive CTEs -- simple JOINs suffice:
 *
 * 5) Find all classes implementing a given interface (including via sub-interfaces and class inheritance):
 *     SELECT c.name, c.filepath
 *     FROM classes c
 *     JOIN class_interfaces ci ON c.name = ci.class
 *     WHERE ci.interface = '\My_Interface'
 *     ORDER BY c.name;
 *
 * 6) Find all classes extending from a base class (direct and transitive):
 *     SELECT c.name, c.filepath
 *     FROM classes c
 *     JOIN class_relationships cr ON c.name = cr.child
 *     WHERE cr.parent = '\My_Base_Class'
 *     ORDER BY c.name;
 *
 * 7) Find all classes using a given trait (direct and transitive):
 *     SELECT c.name, c.filepath
 *     FROM classes c
 *     JOIN class_traits ct ON c.name = ct.class
 *     WHERE ct.trait = '\My_Trait'
 *     ORDER BY c.name;
 *
 * 8) Find all traits that use a given trait:
 *     SELECT t.name, t.filepath
 *     FROM traits t
 *     JOIN trait_traits tt ON t.name = tt.trait
 *     WHERE tt.uses_trait = '\My_Trait'
 *     ORDER BY t.name;
 */
final class PhoundVisitor extends PluginAwarePostAnalysisVisitor
{
    // Avoid `SQLite3::prepare(): Unable to prepare statement: 1, too many SQL variables`
    // See: https://sqlite.org/limits.html#max_variable_number
    // SQLite version 3.32.0 required - which increased max variables from 999 to 32766
    private const MAX_SQLITE_VARIABLES = 32766;
    // callsites has three columns (element, type, callsite)
    private const CALLSITES_BULK_INSERT_SIZE = self::MAX_SQLITE_VARIABLES / 3;
    // hierarchy tables have 2 columns (parent, child), (class, interface), (class, trait)
    private const HIERARCHY_BULK_INSERT_SIZE = self::MAX_SQLITE_VARIABLES / 2;

    /** @var SQLite3 */
    private static $db;
    /** @var SQLite3Stmt */
    private static $callsites_prepared_insert;
    /** @var SQLite3Stmt */
    private static $classes_prepared_insert;
    /** @var SQLite3Stmt */
    private static $interfaces_prepared_insert;
    /** @var SQLite3Stmt */
    private static $traits_prepared_insert;
    /** @var SQLite3Stmt */
    private static $trait_traits_prepared_insert;
    /** @var SQLite3Stmt */
    private static $interface_relationships_prepared_insert;
    /** @var SQLite3Stmt */
    private static $class_relationships_prepared_insert;
    /** @var SQLite3Stmt */
    private static $class_interfaces_prepared_insert;
    /** @var SQLite3Stmt */
    private static $class_traits_prepared_insert;

    /** @var list<array{string,string,string}> */
    private static $callsites = [];
    /** @var list<array{string,string}> */
    private static $classes = [];
    /** @var list<array{string,string}> */
    private static $interfaces = [];
    /** @var list<array{string,string}> */
    private static $traits = [];
    /** @var list<array{string,string}> */
    private static $trait_traits = [];
    /** @var list<array{string,string}> */
    private static $interface_relationships = [];
    /** @var list<array{string,string}> */
    private static $class_relationships = [];
    /** @var list<array{string,string}> */
    private static $class_interfaces = [];
    /** @var list<array{string,string}> */
    private static $class_traits = [];

    private const TABLES = [
        'callsites' => [
            'columns' => [
                'element TEXT NOT NULL',
                'type TEXT NOT NULL',
                'callsite TEXT NOT NULL',
            ],
            'constraints' => [
                'PRIMARY KEY (element, type, callsite)',
            ]
        ],
        'classes' => [
            'columns' => [
                'name TEXT NOT NULL PRIMARY KEY',
                'filepath TEXT NOT NULL',
            ]
        ],
        'interfaces' => [
            'columns' => [
                'name TEXT NOT NULL PRIMARY KEY',
                'filepath TEXT NOT NULL',
            ]
        ],
        'traits' => [
            'columns' => [
                'name TEXT NOT NULL PRIMARY KEY',
                'filepath TEXT NOT NULL',
            ]
        ],
        'trait_traits' => [
            'columns' => [
                'trait TEXT NOT NULL',
                'uses_trait TEXT NOT NULL',
            ],
            'constraints' => [
                'PRIMARY KEY (trait, uses_trait)',
            ]
        ],
        'interface_relationships' => [
            'columns' => [
                'parent TEXT NOT NULL',
                'child TEXT NOT NULL',
            ],
            'constraints' => [
                'PRIMARY KEY (parent, child)',
            ]
        ],
        'class_relationships' => [
            'columns' => [
                'parent TEXT NOT NULL',
                'child TEXT NOT NULL',
            ],
            'constraints' => [
                'PRIMARY KEY (parent, child)',
            ]
        ],
        'class_interfaces' => [
            'columns' => [
                'class TEXT NOT NULL',
                'interface TEXT NOT NULL',
            ],
            'constraints' => [
                'PRIMARY KEY (class, interface)',
            ]
        ],
        'class_traits' => [
            'columns' => [
                'class TEXT NOT NULL',
                'trait TEXT NOT NULL',
            ],
            'constraints' => [
                'PRIMARY KEY (class, trait)',
            ]
        ],
        'signatures' => [
            'columns' => [
                'fqsen TEXT NOT NULL',
                'kind TEXT NOT NULL',
                'class_fqsen TEXT',
                'name TEXT NOT NULL',
                'type TEXT NOT NULL',
                'is_static INTEGER NOT NULL DEFAULT 0',
                'visibility TEXT',
                'filepath TEXT NOT NULL',
                'lineno INTEGER NOT NULL',
            ],
            'constraints' => [
                'PRIMARY KEY (fqsen, kind)',
            ],
        ],
        'parameters' => [
            'columns' => [
                'fqsen TEXT NOT NULL',
                'idx INTEGER NOT NULL',
                'name TEXT NOT NULL',
                'type TEXT NOT NULL',
                'is_variadic INTEGER NOT NULL DEFAULT 0',
                'is_reference INTEGER NOT NULL DEFAULT 0',
                'is_optional INTEGER NOT NULL DEFAULT 0',
                'default_repr TEXT',
            ],
            'constraints' => [
                'PRIMARY KEY (fqsen, idx)',
            ],
        ],
    ];

    /**
     * @param CodeBase $code_base
     * @param Context  $context
     * @throws Exception
     */
    public function __construct(CodeBase $code_base, Context $context) {
        parent::__construct($code_base, $context);

        if (self::$db) {
            return;
        }

        $db_path = (string) (Config::getValue('plugin_config')['phound_sqlite_path'] ?? '');
        if ($db_path === '') {
            throw new Exception("You must specify a `plugin_config.phound_sqlite_path` in your phan configuration.");
        }
        self::$db = new SQLite3($db_path);

        foreach (array_keys(self::TABLES) as $table) {
            if (!self::$db->exec("DROP TABLE IF EXISTS $table")) {
                throw new Exception("Failed to drop table: $table");
            }
        }

        // build tables in natural order
        foreach (self::TABLES as $table => $table_meta) {
            $table_stmt = implode(', ', $table_meta['columns']);

            if (isset($table_meta['constraints'])) {
                $table_stmt .= ', ' . implode(', ', $table_meta['constraints']);
            }

            if (!self::$db->exec("create table $table($table_stmt)")) {
                throw new Exception("Failed to create table: $table");
            }
        }
        // Indexes are created after bulk loading in finalizeProcess for better performance
        if (!self::$db->exec("PRAGMA synchronous = OFF")) {
            throw new Exception("Failed to set PRAGMA synchronous");
        }
        if (!self::$db->exec("PRAGMA journal_mode = OFF")) {
            throw new Exception("Failed to set PRAGMA journal_mode");
        }
        self::$callsites_prepared_insert  = self::createCallsitesBulkInsertPreparedStatement(self::CALLSITES_BULK_INSERT_SIZE);
        self::$classes_prepared_insert    = self::createHierarchyBulkInsertPreparedStmt("classes", self::HIERARCHY_BULK_INSERT_SIZE);
        self::$interfaces_prepared_insert = self::createHierarchyBulkInsertPreparedStmt("interfaces", self::HIERARCHY_BULK_INSERT_SIZE);
        self::$traits_prepared_insert     = self::createHierarchyBulkInsertPreparedStmt("traits", self::HIERARCHY_BULK_INSERT_SIZE);
        self::$trait_traits_prepared_insert = self::createHierarchyBulkInsertPreparedStmt("trait_traits", self::HIERARCHY_BULK_INSERT_SIZE);
        self::$interface_relationships_prepared_insert = self::createHierarchyBulkInsertPreparedStmt("interface_relationships", self::HIERARCHY_BULK_INSERT_SIZE);
        self::$class_relationships_prepared_insert = self::createHierarchyBulkInsertPreparedStmt("class_relationships", self::HIERARCHY_BULK_INSERT_SIZE);
        self::$class_interfaces_prepared_insert = self::createHierarchyBulkInsertPreparedStmt("class_interfaces", self::HIERARCHY_BULK_INSERT_SIZE);
        self::$class_traits_prepared_insert = self::createHierarchyBulkInsertPreparedStmt("class_traits", self::HIERARCHY_BULK_INSERT_SIZE);
    }

    /**
     * @param  int    $bulk_insert_size
     * @throws Exception
     */
    private static function createCallsitesBulkInsertPreparedStatement(int $bulk_insert_size): SQLite3Stmt {
        $bulk_insert_sql = "INSERT OR IGNORE INTO callsites ('element', 'type', 'callsite') VALUES ";
        $bulk_insert_sql .= str_repeat("(?, ?, ?), ", $bulk_insert_size);
        $bulk_insert_sql = rtrim($bulk_insert_sql, ', ');
        $stmt = self::$db->prepare($bulk_insert_sql);
        if ($stmt === false) {
            throw new Exception("Failed to prepare callsites bulk insert statement");
        }
        return $stmt;
    }

    /**
     * Creates a prepared statement for inserting into hierarchy tables, which always have two columns
     * @param string    $table_name from self::TABLES
     * @param int       $bulk_insert_size the number of rows to insert
     * @throws Exception on preparation failure
     */
    private static function createHierarchyBulkInsertPreparedStmt(
        string $table_name,
        int $bulk_insert_size
    ): SQLite3Stmt {
        $table_meta = self::TABLES[$table_name];
        $col_names = [];
        foreach ($table_meta['columns'] as $column) {
            $col_names[] = "'" . explode(' ', $column)[0] . "'";
        }
        $insert_columns = implode(', ', $col_names);
        $bulk_insert_sql = "INSERT or IGNORE INTO $table_name ($insert_columns) VALUES ";
        $bulk_insert_sql .= str_repeat("(?, ?), ", $bulk_insert_size);
        $bulk_insert_sql = rtrim($bulk_insert_sql, ', ');
        if (!$stmt = self::$db->prepare($bulk_insert_sql)) {
            throw new Exception("Failed to prepare bulk insert statement for: $table_name");
        }
        return $stmt;
    }

    /**
     * @throws Exception
     */
    public function visitNew(Node $node)
    {
        try {
            $elements = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getMethodList('__construct', false, false, true);
        } catch (Exception) {
            return;
        }
        $this->genericVisitClassElements($elements, 'method');
    }

    /**
     * @throws Exception
     */
    public function visitMethodCall(Node $node)
    {
        try {
            $elements = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getMethodList($node->children['method'], false, false); // @phan-suppress-current-line PhanPartialTypeMismatchArgument
        } catch (Exception) {
            return;
        }
        $this->genericVisitClassElements($elements, 'method');
    }


    /**
     * @param Node $node a node of type AST_NULLSAFE_METHOD_CALL
     * @override
     * @throws Exception
     */
    public function visitNullsafeMethodCall(Node $node): void
    {
        $this->visitMethodCall($node);
    }

    /**
     * @throws Exception
     */
    public function visitStaticCall(Node $node)
    {
        try {
            $elements = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getMethodList($node->children['method'], true, false); // @phan-suppress-current-line PhanPartialTypeMismatchArgument
        } catch (Exception) {
            return;
        }
        $this->genericVisitClassElements($elements, 'method');
    }

    /**
     * Visit a node with kind `\ast\AST_CLASS_CONST`
     * @throws Exception
     */
    public function visitClassConst(Node $node) {
        try {
            $elements = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getClassConstList();
        } catch (Exception) {
            return;
        }
        $this->genericVisitClassElements($elements, 'const');
    }

    /**
     * Visit a node with kind `\ast\AST_STATIC_PROP`
     * @throws Exception
     */
    public function visitStaticProp(Node $node) {
        try {
            $elements = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getPropertyList(true);
        } catch (Exception) {
            return;
        }
        $this->genericVisitClassElements($elements, 'prop');
    }

    /**
     * Visit a node with kind `\ast\AST_PROP`
     * @throws Exception
     */
    public function visitProp(Node $node) {
        try {
            $elements = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getPropertyList(false);
        } catch (Exception) {
            return;
        }
        $this->genericVisitClassElements($elements, 'prop');
    }

    /**
     * Visit a node with kind `\ast\AST_NULLSAFE_PROP`
     * @throws Exception
     */
    public function visitNullsafeProp(Node $node) {
        $this->visitProp($node);
    }

    /**
     * Helper function to add class elements to the DB
     * @param  list<ClassElement> $elements
     * @param  string       $type
     * @throws Exception
     */
    public function genericVisitClassElements(array $elements, string $type): void {
        foreach ($elements as $element) {
            $element_name = $element->getFQSEN()->__toString();
            $callsite = $this->context->__toString();
            self::$callsites[] = [$element_name, $type, $callsite];

            if (count(self::$callsites) === self::CALLSITES_BULK_INSERT_SIZE) {
                self::doCallsitesBulkWrite(self::$callsites_prepared_insert);
            }
        }
    }

    /**
     * @param  SQLite3Stmt $stmt
     * @throws Exception
     */
    private static function doCallsitesBulkWrite(SQLite3Stmt $stmt): void {
        sort(self::$callsites);
        $bind_index = 1;
        foreach (self::$callsites as $callsite) {
            $stmt->bindValue($bind_index, $callsite[0], SQLITE3_TEXT);
            $bind_index++;
            $stmt->bindValue($bind_index, $callsite[1], SQLITE3_TEXT);
            $bind_index++;
            $stmt->bindValue($bind_index, $callsite[2], SQLITE3_TEXT);
            $bind_index++;
        }
        self::execStatement($stmt);
        self::$callsites = [];
    }

    /**
     * Called when visiting classes, interfaces, and traits, including anonymous
     * versions of the same. Phan generates FQSENs consistently for anonymous classes
     * using file modification time, i.e. if a file contains anonymous_class_83cba571,
     * it will always contain anonymous_class_83cba571 unless/until that file is modified.
     * @param Node  $node - the class AST node to evaluate
     * @throws Exception
     */
    public function visitClass(Node $node): void { // @phan-suppress-current-line PhanUnusedPublicFinalMethodParameter
        $clazz = $this->context->getClassInScope($this->code_base);
        $filepath = $this->context->getProjectRelativePath();

        if ($clazz->isClass()) {
            self::handleClass($clazz, $filepath);

        } elseif ($clazz->isInterface()) {
            self::handleInterface($clazz, $filepath);

        } elseif ($clazz->isTrait()) {
            self::handleTrait($clazz, $filepath);

        } else {
            throw new Exception("Unknown class type: $clazz");
        }
    }

    /**
     * Processes a class to obtain its name, filepath, relationships, interfaces, and traits.
     * @param Clazz     $clazz the Phan class model to evaluate
     * @param string    $filepath the project-relative path to the file containing the class
     * @throws Exception
     */
    private static function handleClass(Clazz $clazz, string $filepath): void {
        // collect basic info
        $name = $clazz->getFQSEN()->__toString();
        self::$classes[] = [$name, $filepath];
        // store
        if (count(self::$classes) === self::HIERARCHY_BULK_INSERT_SIZE) {
            self::doHierarchyBulkWrite(self::$classes, self::$classes_prepared_insert);
        }

        // collect parent class
        if ($clazz->hasParentType()) {
            $parent_name = $clazz->getParentClassFQSEN()->__toString();
            self::$class_relationships[] = [$parent_name, $name];
        }
        // store
        if (count(self::$class_relationships) === self::HIERARCHY_BULK_INSERT_SIZE) {
            self::doHierarchyBulkWrite(self::$class_relationships, self::$class_relationships_prepared_insert);
        }

        // collect implemented interfaces
        $impl_interfaces = $clazz->getInterfaceFQSENList();
        foreach ($impl_interfaces as $iface) {
            self::$class_interfaces[] = [$name, $iface->__toString()];
            // store
            if (count(self::$class_interfaces) === self::HIERARCHY_BULK_INSERT_SIZE) {
                self::doHierarchyBulkWrite(self::$class_interfaces, self::$class_interfaces_prepared_insert);
            }
        }

        // collect used traits
        $used_traits = $clazz->getTraitFQSENList();
        foreach ($used_traits as $trait) {
            self::$class_traits[] = [$name, $trait->__toString()];
            // store
            if (count(self::$class_traits) === self::HIERARCHY_BULK_INSERT_SIZE) {
                self::doHierarchyBulkWrite(self::$class_traits, self::$class_traits_prepared_insert);
            }
        }
    }

    /**
     * Processes an interface to obtain its name, filepath, relationships, and extended interfaces.
     * @param Clazz     $clazz the Phan class model representing the interface
     * @param string    $filepath the project-relative path to the file containing the interface
     * @throws Exception
     */
    private static function handleInterface(Clazz $clazz, string $filepath): void {
        // collect basic info
        $name = $clazz->getFQSEN()->__toString();
        self::$interfaces[] = [$name, $filepath];
        // store
        if (count(self::$interfaces) === self::HIERARCHY_BULK_INSERT_SIZE) {
            self::doHierarchyBulkWrite(self::$interfaces, self::$interfaces_prepared_insert);
        }

        // collect extensions of other interfaces
        $parent_ifaces = $clazz->getInterfaceFQSENList();
        foreach ($parent_ifaces as $iface) {
            self::$interface_relationships[] = [$iface->__toString(), $name]; // (parent, child)
            // store
            if (count(self::$interface_relationships) === self::HIERARCHY_BULK_INSERT_SIZE) {
                self::doHierarchyBulkWrite(self::$interface_relationships, self::$interface_relationships_prepared_insert);
            }
        }
    }

    /**
     * Processes a trait to obtain its name, filepath, and used traits.
     * @param Clazz     $clazz the Phan class model representing the trait
     * @param string    $filepath the project-relative path to the file containing the trait
     * @throws Exception
     */
    private static function handleTrait(Clazz $clazz, string $filepath): void {
        // collect basic info
        $name = $clazz->getFQSEN()->__toString();
        self::$traits[] = [$name, $filepath];
        // store
        if (count(self::$traits) === self::HIERARCHY_BULK_INSERT_SIZE) {
            self::doHierarchyBulkWrite(self::$traits, self::$traits_prepared_insert);
        }

        // collect usages of other traits
        $uses_traits = $clazz->getTraitFQSENList();
        foreach ($uses_traits as $trait) {
            self::$trait_traits[] = [$name, $trait->__toString()];
            // store
            if (count(self::$trait_traits) === self::HIERARCHY_BULK_INSERT_SIZE) {
                self::doHierarchyBulkWrite(self::$trait_traits, self::$trait_traits_prepared_insert);
            }
        }
    }

    /**
     * Bind any 2 values to a row for both tables and relationship tables, just because they have the same
     * # of columns. if base tables diverge in # of columns from relationship tables, this breaks.
     * @param list<array{string,string}> &$nodes - cleared after inserting successfully
     * @param SQLite3Stmt $stmt
     * @throws Exception
     */
    private static function doHierarchyBulkWrite(array &$nodes, SQLite3Stmt $stmt): void {
        sort($nodes);
        $bind_index = 1;
        foreach ($nodes as $node) {
            $stmt->bindValue($bind_index, $node[0], SQLITE3_TEXT);
            $bind_index++;
            $stmt->bindValue($bind_index, $node[1], SQLITE3_TEXT);
            $bind_index++;
        }
        self::execStatement($stmt);
        $nodes = [];
    }

    /**
     * @param SQLite3Stmt $stmt
     * @throws Exception
     */
    private static function execStatement(SQLite3Stmt $stmt): void {
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute prepared statement");
        }

        if (!$stmt->reset()) {
            throw new Exception("Failed to reset prepared statement");
        }

        if (!$stmt->clear()) {
            throw new Exception("Failed to clear prepared statement bindings");
        }
    }

    /**
     * Finish pending bulk writes and write signature data.
     * @throws Exception
     */
    public static function finalizeProcess(CodeBase $code_base): void {
        if (count(self::$callsites) > 0) {
            $stmt = self::createCallsitesBulkInsertPreparedStatement(count(self::$callsites));
            self::doCallsitesBulkWrite($stmt);
        }
        if (count(self::$classes) > 0) {
            $stmt = self::createHierarchyBulkInsertPreparedStmt(
                "classes",
                count(self::$classes)
            );
            self::doHierarchyBulkWrite(self::$classes, $stmt);
        }
        if (count(self::$interfaces) > 0) {
            $stmt = self::createHierarchyBulkInsertPreparedStmt(
                "interfaces",
                count(self::$interfaces)
            );
            self::doHierarchyBulkWrite(self::$interfaces, $stmt);
        }
        if (count(self::$traits) > 0) {
            $stmt = self::createHierarchyBulkInsertPreparedStmt(
                "traits",
                count(self::$traits)
            );
            self::doHierarchyBulkWrite(self::$traits, $stmt);
        }
        if (count(self::$class_relationships) > 0) {
            $stmt = self::createHierarchyBulkInsertPreparedStmt(
                "class_relationships",
                count(self::$class_relationships)
            );
            self::doHierarchyBulkWrite(self::$class_relationships, $stmt);
        }
        if (count(self::$class_interfaces) > 0) {
            $stmt = self::createHierarchyBulkInsertPreparedStmt(
                "class_interfaces",
                count(self::$class_interfaces)
            );
            self::doHierarchyBulkWrite(self::$class_interfaces, $stmt);
        }
        if (count(self::$class_traits) > 0) {
            $stmt = self::createHierarchyBulkInsertPreparedStmt(
                "class_traits",
                count(self::$class_traits)
            );
            self::doHierarchyBulkWrite(self::$class_traits, $stmt);
        }
        if (count(self::$interface_relationships) > 0) {
            $stmt = self::createHierarchyBulkInsertPreparedStmt(
                "interface_relationships",
                count(self::$interface_relationships)
            );
            self::doHierarchyBulkWrite(self::$interface_relationships, $stmt);
        }
        if (count(self::$trait_traits) > 0) {
            $stmt = self::createHierarchyBulkInsertPreparedStmt(
                "trait_traits",
                count(self::$trait_traits)
            );
            self::doHierarchyBulkWrite(self::$trait_traits, $stmt);
        }

        // To simplify the queries so that they don't need to use recursive CTEs,
        // flatten all relationship tables with recursive CTEs ahead of time.
        $flatten_class_relationships = "
            WITH RECURSIVE ancestor_descendant(parent, child) AS (
                SELECT parent, child
                FROM class_relationships
                UNION
                SELECT cr.parent, ad.child
                FROM class_relationships cr
                JOIN ancestor_descendant ad
                ON cr.child = ad.parent
            )
            INSERT OR IGNORE INTO class_relationships (parent, child)
            SELECT parent, child FROM ancestor_descendant
            WHERE parent != child;
        ";
        if (!self::$db->exec($flatten_class_relationships)) {
            throw new Exception("Failed to flatten class relationships");
        }

        $flatten_interface_relationships = "
            WITH RECURSIVE ancestor_descendant(parent, child) AS (
                SELECT parent, child
                FROM interface_relationships
                UNION
                SELECT ir.parent, ad.child
                FROM interface_relationships ir
                JOIN ancestor_descendant ad
                ON ir.child = ad.parent
            )
            INSERT OR IGNORE INTO interface_relationships (parent, child)
            SELECT parent, child FROM ancestor_descendant
            WHERE parent != child;
        ";
        if (!self::$db->exec($flatten_interface_relationships)) {
            throw new Exception("Failed to flatten interface relationships");
        }

        $flatten_trait_relationships = "
            WITH RECURSIVE ancestor_descendant(trait, uses_trait) AS (
                SELECT trait, uses_trait
                FROM trait_traits
                UNION
                SELECT tt.trait, ad.uses_trait
                FROM trait_traits tt
                JOIN ancestor_descendant ad
                ON tt.uses_trait = ad.trait
            )
            INSERT OR IGNORE INTO trait_traits (trait, uses_trait)
            SELECT trait, uses_trait FROM ancestor_descendant
            WHERE trait != uses_trait;
        ";
        if (!self::$db->exec($flatten_trait_relationships)) {
            throw new Exception("Failed to flatten trait relationships");
        }

        // Propagate interfaces down the class hierarchy: if a parent class
        // implements an interface, all child classes should too.
        // class_relationships is already fully flattened, so a single
        // join covers all transitive descendants without recursion.
        $flatten_class_interfaces = "
            INSERT OR IGNORE INTO class_interfaces (class, interface)
            SELECT cr.child, ci.interface
            FROM class_interfaces ci
            JOIN class_relationships cr
            ON cr.parent = ci.class;
        ";
        if (!self::$db->exec($flatten_class_interfaces)) {
            throw new Exception("Failed to flatten class interfaces");
        }

        // Propagate ancestor interfaces into class_interfaces: if a class
        // implements an interface, it also implements all ancestor interfaces.
        // interface_relationships is already fully flattened, so a single
        // join covers all transitive ancestors without recursion.
        $propagate_interface_ancestors = "
            INSERT OR IGNORE INTO class_interfaces (class, interface)
            SELECT ci.class, ir.parent
            FROM class_interfaces ci
            JOIN interface_relationships ir
            ON ci.interface = ir.child;
        ";
        if (!self::$db->exec($propagate_interface_ancestors)) {
            throw new Exception("Failed to propagate interface ancestors into class interfaces");
        }

        // Propagate traits down the class hierarchy: if a parent class
        // uses a trait, all child classes should too.
        // class_relationships is already fully flattened, so a single
        // join covers all transitive descendants without recursion.
        $flatten_class_traits = "
            INSERT OR IGNORE INTO class_traits (class, trait)
            SELECT cr.child, ct.trait
            FROM class_traits ct
            JOIN class_relationships cr
            ON cr.parent = ct.class;
        ";
        if (!self::$db->exec($flatten_class_traits)) {
            throw new Exception("Failed to flatten class traits");
        }

        // Propagate ancestor traits into class_traits: if a class uses a
        // trait, it also uses all traits that trait transitively depends on.
        // trait_traits is already fully flattened, so a single join covers
        // all transitive ancestors without recursion.
        $propagate_trait_ancestors = "
            INSERT OR IGNORE INTO class_traits (class, trait)
            SELECT ct.class, tt.uses_trait
            FROM class_traits ct
            JOIN trait_traits tt
            ON ct.trait = tt.trait;
        ";
        if (!self::$db->exec($propagate_trait_ancestors)) {
            throw new Exception("Failed to propagate trait ancestors into class traits");
        }

        self::writeSignatures($code_base);

        // Create indexes needed by propagation queries (which join on the child column)
        $propagation_indexes = [
            'CREATE INDEX IF NOT EXISTS class_relationships_child ON class_relationships (child)',
            'CREATE INDEX IF NOT EXISTS interface_relationships_child ON interface_relationships (child)',
        ];
        foreach ($propagation_indexes as $index_sql) {
            if (!self::$db->exec($index_sql)) {
                throw new Exception("Failed to create index: $index_sql");
            }
        }

        self::propagateCallsitesToAncestors(self::$db);

        // Create remaining indexes after bulk loading for better performance
        $indexes = [
            'CREATE INDEX IF NOT EXISTS element_and_callsite ON callsites (element, callsite)',
            'CREATE INDEX IF NOT EXISTS class_interfaces_interface ON class_interfaces (interface)',
            'CREATE INDEX IF NOT EXISTS signatures_name ON signatures (name)',
            'CREATE INDEX IF NOT EXISTS signatures_class ON signatures (class_fqsen)',
            'CREATE INDEX IF NOT EXISTS signatures_filepath ON signatures (filepath)',
        ];
        foreach ($indexes as $index_sql) {
            if (!self::$db->exec($index_sql)) {
                throw new Exception("Failed to create index: $index_sql");
            }
        }
    }

    /**
     * Propagate callsites up the class/interface/trait hierarchy.
     * After this, a call to \Child::method also appears as a callsite
     * of \Parent::method (and interface/trait methods), provided the
     * ancestor actually declares that member (verified via signatures).
     *
     * Requires that hierarchy tables are already flattened and signatures
     * are already written. Uses INSERT OR IGNORE to avoid duplicates.
     *
     * @throws Exception
     */
    public static function propagateCallsitesToAncestors(SQLite3 $db): void
    {
        // All five propagation paths are combined into a single INSERT ... UNION ALL
        // so SQLite evaluates the entire SELECT as one snapshot before inserting.
        // This avoids later subqueries scanning rows inserted by earlier ones
        // (which would only produce duplicates discarded by INSERT OR IGNORE).
        $propagate = "
            INSERT OR IGNORE INTO callsites (element, type, callsite)

            -- Class hierarchy (uses already-flattened class_relationships)
            SELECT
                cr.parent || '::' || SUBSTR(c.element, INSTR(c.element, '::') + 2),
                c.type,
                c.callsite
            FROM callsites c
            JOIN class_relationships cr
                ON cr.child = SUBSTR(c.element, 1, INSTR(c.element, '::') - 1)
            JOIN signatures s
                ON s.fqsen = cr.parent || '::' || SUBSTR(c.element, INSTR(c.element, '::') + 2)
                AND s.kind = c.type
                AND s.visibility != 'private'

            UNION ALL

            -- Interfaces (uses already-flattened class_interfaces)
            SELECT
                ci.interface || '::' || SUBSTR(c.element, INSTR(c.element, '::') + 2),
                c.type,
                c.callsite
            FROM callsites c
            JOIN class_interfaces ci
                ON ci.class = SUBSTR(c.element, 1, INSTR(c.element, '::') - 1)
            JOIN signatures s
                ON s.fqsen = ci.interface || '::' || SUBSTR(c.element, INSTR(c.element, '::') + 2)
                AND s.kind = c.type
                AND s.visibility != 'private'

            UNION ALL

            -- Traits (uses already-flattened class_traits)
            SELECT
                ct.trait || '::' || SUBSTR(c.element, INSTR(c.element, '::') + 2),
                c.type,
                c.callsite
            FROM callsites c
            JOIN class_traits ct
                ON ct.class = SUBSTR(c.element, 1, INSTR(c.element, '::') - 1)
            JOIN signatures s
                ON s.fqsen = ct.trait || '::' || SUBSTR(c.element, INSTR(c.element, '::') + 2)
                AND s.kind = c.type
                AND s.visibility != 'private'

            UNION ALL

            -- Interface hierarchy (uses already-flattened interface_relationships)
            SELECT
                ir.parent || '::' || SUBSTR(c.element, INSTR(c.element, '::') + 2),
                c.type,
                c.callsite
            FROM callsites c
            JOIN interface_relationships ir
                ON ir.child = SUBSTR(c.element, 1, INSTR(c.element, '::') - 1)
            JOIN signatures s
                ON s.fqsen = ir.parent || '::' || SUBSTR(c.element, INSTR(c.element, '::') + 2)
                AND s.kind = c.type
                AND s.visibility != 'private'

            UNION ALL

            -- Trait hierarchy (uses already-flattened trait_traits)
            SELECT
                tt.uses_trait || '::' || SUBSTR(c.element, INSTR(c.element, '::') + 2),
                c.type,
                c.callsite
            FROM callsites c
            JOIN trait_traits tt
                ON tt.trait = SUBSTR(c.element, 1, INSTR(c.element, '::') - 1)
            JOIN signatures s
                ON s.fqsen = tt.uses_trait || '::' || SUBSTR(c.element, INSTR(c.element, '::') + 2)
                AND s.kind = c.type
                AND s.visibility != 'private'

            -- Insert in PK order (element, type, callsite) for better B-tree performance
            ORDER BY 1, 2, 3
        ";
        if (!$db->exec($propagate)) {
            throw new Exception("Failed to propagate callsites to ancestors");
        }
    }

    /**
     * Write function/method signatures and parameters to the database.
     * Collects all rows first, sorts by PK, then bulk-inserts for performance.
     * Includes inherited/trait members so FQSENs for user-defined elements in callsites generally have a matching signature row.
     * @throws Exception
     */
    private static function writeSignatures(CodeBase $code_base): void
    {
        /** @var list<array{string,string,?string,string,string,int,?string,string,int}> */
        $sig_rows = [];
        /** @var list<array{string,int,string,string,int,int,int,?string}> */
        $param_rows = [];

        // Standalone functions
        foreach ($code_base->getFunctionMap() as $func) {
            if ($func->isPHPInternal()) {
                continue;
            }
            $fqsen = $func->getFQSEN()->__toString();
            $file_ref = $func->getFileRef();
            $sig_rows[] = [$fqsen, 'function', null, $func->getName(), $func->getUnionType()->__toString(), $func->isStatic() ? 1 : 0, null, $file_ref->getProjectRelativePath(), $file_ref->getLineNumberStart()];
            self::collectParameters($param_rows, $fqsen, $func->getParameterList());
        }

        // Class methods, properties, constants — includes inherited/trait members
        foreach ($code_base->getUserDefinedClassMap() as $clazz) {
            if ($clazz->isPHPInternal()) {
                continue;
            }
            $class_fqsen = $clazz->getFQSEN();
            $class_fqsen_str = $class_fqsen->__toString();

            // Ensure the implicit constructor is materialized for non-trait,
            // non-interface classes. Phan lazily creates default constructors
            // only when getMethodByName('__construct') is called (e.g., from
            // a `new` expression). Classes that are never directly instantiated
            // won't have a __construct in the method map, which means
            // propagated callsites can't find the ancestor's constructor.
            if (!$clazz->isTrait() && !$clazz->isInterface()) {
                $clazz->getMethodByName($code_base, '__construct');
            }

            foreach ($code_base->getMethodMapByFullyQualifiedClassName($class_fqsen) as $method) {
                if ($method->isPHPInternal()) {
                    continue;
                }
                $fqsen = $method->getFQSEN()->__toString();
                $file_ref = $method->getFileRef();
                $sig_rows[] = [$fqsen, 'method', $class_fqsen_str, $method->getName(), $method->getUnionType()->__toString(), $method->isStatic() ? 1 : 0, $method->getVisibilityName(), $file_ref->getProjectRelativePath(), $file_ref->getLineNumberStart()];
                self::collectParameters($param_rows, $fqsen, $method->getParameterList());
            }

            foreach ($code_base->getPropertyMapByFullyQualifiedClassName($class_fqsen) as $prop) {
                if ($prop->isPHPInternal()) {
                    continue;
                }
                $fqsen = $prop->getFQSEN()->__toString();
                $file_ref = $prop->getFileRef();
                $sig_rows[] = [$fqsen, 'prop', $class_fqsen_str, $prop->getName(), $prop->getUnionType()->__toString(), $prop->isStatic() ? 1 : 0, $prop->getVisibilityName(), $file_ref->getProjectRelativePath(), $file_ref->getLineNumberStart()];
            }

            foreach ($code_base->getClassConstantMapByFullyQualifiedClassName($class_fqsen) as $const) {
                if ($const->isPHPInternal()) {
                    continue;
                }
                $fqsen = $const->getFQSEN()->__toString();
                $file_ref = $const->getFileRef();
                $sig_rows[] = [$fqsen, 'const', $class_fqsen_str, $const->getName(), $const->getUnionType()->__toString(), 0, $const->getVisibilityName(), $file_ref->getProjectRelativePath(), $file_ref->getLineNumberStart()];
            }
        }

        // Sort by PK (fqsen, kind) and (fqsen, idx) for optimal insert performance.
        // sort() compares arrays element-by-element, so element [0] (fqsen) then [1] (kind/idx).
        sort($sig_rows);
        sort($param_rows);

        if (!self::$db->exec('BEGIN')) {
            throw new Exception("Failed to begin transaction for signatures");
        }

        try {
            self::bulkInsertSignatures($sig_rows);
            self::bulkInsertParameters($param_rows);

            if (!self::$db->exec('COMMIT')) {
                throw new Exception("Failed to commit signatures transaction");
            }
        } catch (\Throwable $e) {
            self::$db->exec('ROLLBACK');
            throw $e;
        }
    }

    // signatures has 9 columns
    private const SIGNATURES_BULK_INSERT_SIZE = self::MAX_SQLITE_VARIABLES / 9;
    // parameters has 8 columns
    private const PARAMETERS_BULK_INSERT_SIZE = self::MAX_SQLITE_VARIABLES / 8;

    /**
     * @param list<array{string,string,?string,string,string,int,?string,string,int}> $rows
     * @throws Exception
     */
    private static function bulkInsertSignatures(array $rows): void
    {
        $total = count($rows);
        if ($total === 0) {
            return;
        }
        $batch_size = (int) min(self::SIGNATURES_BULK_INSERT_SIZE, $total);
        $offset = 0;
        $stmt = self::prepareSignaturesBulkInsert($batch_size);

        while ($offset < $total) {
            $remaining = $total - $offset;
            if ($remaining < $batch_size) {
                $stmt = self::prepareSignaturesBulkInsert($remaining);
                $batch_size = $remaining;
            }

            $bind_index = 1;
            for ($i = $offset; $i < $offset + $batch_size; $i++) {
                $row = $rows[$i];
                $stmt->bindValue($bind_index++, $row[0], SQLITE3_TEXT); // fqsen
                $stmt->bindValue($bind_index++, $row[1], SQLITE3_TEXT); // kind
                $stmt->bindValue($bind_index++, $row[2], $row[2] !== null ? SQLITE3_TEXT : SQLITE3_NULL); // class_fqsen
                $stmt->bindValue($bind_index++, $row[3], SQLITE3_TEXT); // name
                $stmt->bindValue($bind_index++, $row[4], SQLITE3_TEXT); // type
                $stmt->bindValue($bind_index++, $row[5], SQLITE3_INTEGER); // is_static
                $stmt->bindValue($bind_index++, $row[6], $row[6] !== null ? SQLITE3_TEXT : SQLITE3_NULL); // visibility
                $stmt->bindValue($bind_index++, $row[7], SQLITE3_TEXT); // filepath
                $stmt->bindValue($bind_index++, $row[8], SQLITE3_INTEGER); // lineno
            }
            self::execStatement($stmt);
            $offset += $batch_size;
        }
    }

    /**
     * @throws Exception
     */
    private static function prepareSignaturesBulkInsert(int $count): SQLite3Stmt
    {
        $placeholders = str_repeat('(?, ?, ?, ?, ?, ?, ?, ?, ?), ', $count);
        $sql = "INSERT OR IGNORE INTO signatures (fqsen, kind, class_fqsen, name, type, is_static, visibility, filepath, lineno) VALUES " .
            rtrim($placeholders, ', ');
        $stmt = self::$db->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Failed to prepare signatures bulk insert statement");
        }
        return $stmt;
    }

    /**
     * @param list<array{string,int,string,string,int,int,int,?string}> $rows
     * @throws Exception
     */
    private static function bulkInsertParameters(array $rows): void
    {
        $total = count($rows);
        if ($total === 0) {
            return;
        }
        $batch_size = (int) min(self::PARAMETERS_BULK_INSERT_SIZE, $total);
        $offset = 0;
        $stmt = self::prepareParametersBulkInsert($batch_size);

        while ($offset < $total) {
            $remaining = $total - $offset;
            if ($remaining < $batch_size) {
                $stmt = self::prepareParametersBulkInsert($remaining);
                $batch_size = $remaining;
            }

            $bind_index = 1;
            for ($i = $offset; $i < $offset + $batch_size; $i++) {
                $row = $rows[$i];
                $stmt->bindValue($bind_index++, $row[0], SQLITE3_TEXT); // fqsen
                $stmt->bindValue($bind_index++, $row[1], SQLITE3_INTEGER); // idx
                $stmt->bindValue($bind_index++, $row[2], SQLITE3_TEXT); // name
                $stmt->bindValue($bind_index++, $row[3], SQLITE3_TEXT); // type
                $stmt->bindValue($bind_index++, $row[4], SQLITE3_INTEGER); // is_variadic
                $stmt->bindValue($bind_index++, $row[5], SQLITE3_INTEGER); // is_reference
                $stmt->bindValue($bind_index++, $row[6], SQLITE3_INTEGER); // is_optional
                $stmt->bindValue($bind_index++, $row[7], $row[7] !== null ? SQLITE3_TEXT : SQLITE3_NULL); // default_repr
            }
            self::execStatement($stmt);
            $offset += $batch_size;
        }
    }

    /**
     * @throws Exception
     */
    private static function prepareParametersBulkInsert(int $count): SQLite3Stmt
    {
        $placeholders = str_repeat('(?, ?, ?, ?, ?, ?, ?, ?), ', $count);
        $sql = "INSERT OR IGNORE INTO parameters (fqsen, idx, name, type, is_variadic, is_reference, is_optional, default_repr) VALUES " .
            rtrim($placeholders, ', ');
        $stmt = self::$db->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Failed to prepare parameters bulk insert statement");
        }
        return $stmt;
    }

    /**
     * Collect parameter data into rows array for bulk insertion.
     * @param list<array{string,int,string,string,int,int,int,?string}> &$rows
     * @param string $fqsen
     * @param list<\Phan\Language\Element\Parameter> $parameters
     */
    private static function collectParameters(array &$rows, string $fqsen, array $parameters): void
    {
        foreach ($parameters as $idx => $param) {
            $default = null;
            if ($param->hasDefaultValue() && !$param->isVariadic()) {
                $default_value = $param->getDefaultValue();
                if ($default_value instanceof \ast\Node) {
                    $default = \Phan\AST\ASTReverter::toShortString($default_value);
                    if (\strlen($default) > 50) {
                        $default = \substr($default, 0, 47) . '...';
                    }
                } else {
                    $default = \Phan\Library\StringUtil::varExportPretty($default_value);
                    if (\strlen($default) > 50) {
                        $default = \substr($default, 0, 47) . '...';
                    }
                }
            }
            $rows[] = [
                $fqsen,
                $idx,
                $param->getName(),
                $param->getUnionType()->__toString(),
                $param->isVariadic() ? 1 : 0,
                $param->isPassByReference() ? 1 : 0,
                $param->isOptional() ? 1 : 0,
                $default,
            ];
        }
    }

}

use Phan\PluginV3;
use Phan\PluginV3\PostAnalyzeNodeCapability;
use Phan\PluginV3\FinalizeProcessCapability;
use Phan\PluginV3\AnalyzeCallableArgumentCapability;
use Phan\AST\UnionTypeVisitor;
use Phan\Language\Element\FunctionInterface;

/**
 * Plugin to go with PhoundVisitor.
 */
final class PhoundPlugin extends PluginV3 implements PostAnalyzeNodeCapability, AnalyzeCallableArgumentCapability, FinalizeProcessCapability
{

    /**
     * Returns the name of the visitor class to be instantiated and invoked to analyze a node in the analysis phase.
     * (To post-analyze a node)
     * (PostAnalyzeNodeCapability is run after PreAnalyzeNodeCapability and after analysis of child nodes)
     *
     * The class should be created by the plugin visitor, and must extend PluginAwarePostAnalysisVisitor.
     *
     * If state needs to be shared with a visitor and a plugin, a plugin author may use static variables of that plugin.
     *
     * @return string - The name of a class extending PluginAwarePostAnalysisVisitor
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return PhoundVisitor::class;
    }

    /**
     * @return Closure(CodeBase,Context,FunctionInterface,int,Node|int|string|float):void
     * @unused-param $code_base
     */
    public function getAnalyzeCallableArgumentClosure(CodeBase $code_base): Closure
    {
        /**
         * @param Node|int|string|float $arg_node
         * @throws \Exception
         */
        return static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $unused_callee,
            int $unused_param_index,
            Node|int|string|float $arg_node
        ): void {
            $function_like_list = UnionTypeVisitor::functionLikeListFromNodeAndContext($code_base, $context, $arg_node, true);
            if (\count($function_like_list) === 0) {
                return;
            }

            $elements = [];
            foreach ($function_like_list as $function) {
                if ($function instanceof ClassElement) {
                    $elements[] = $function;
                }
            }

            if ($elements) {
                $phound_visitor = new PhoundVisitor($code_base, $context);
                $phound_visitor->genericVisitClassElements($elements, 'method');
            }
        };
    }

    /**
     * This is called after the other forms of analysis are finished running.
     * Useful if a PluginV3 needs to aggregate results of analysis.
     * This may be used to emit additional issues.
     *
     * This is run once per forked analysis process.
     * Some plugins using this, such as UnusedSuppressionPlugin,
     * will not work as expected with more than one process.
     * If possible, write plugins to emit issues immediately.
     * @throws Exception
     */
    public function finalizeProcess(CodeBase $code_base): void
    {
        PhoundVisitor::finalizeProcess($code_base);
    }

}

return new PhoundPlugin();
