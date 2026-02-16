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
 * Populates a sqlite database with callsites of class elements, as well as class, trait, and interface
 * hierarchies. Class elements include methods, static methods, properties, static properties,
 * and constants. Class heirarchies include classes and their parent-child relationships, interfaces, and traits.
 *
 * The database can be queried to find callsites of a given class element as well as class, trait,
 * and interface hierarchy.
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
 * 5) Using Common Table Expressions and the class hierarchy tables, we can find all clases implementing any interface:
 *     WITH RECURSIVE
 *       sub_interfaces (name) AS (
 *         SELECT '\My_Interface'
 *         UNION ALL
 *         SELECT ir.child
 *         FROM interface_relationships ir
 *         JOIN sub_interfaces si ON ir.parent = si.name
 *       ),
 *       direct_implementers (class_name) AS (
 *         SELECT DISTINCT ci.class
 *         FROM class_interfaces ci
 *         JOIN sub_interfaces si ON ci.interface = si.name
 *       ),
 *       all_implementing_classes (class_name) AS (
 *         SELECT class_name FROM direct_implementers
 *         UNION ALL
 *         SELECT cr.child
 *         FROM class_relationships cr
 *         JOIN all_implementing_classes aic ON cr.parent = aic.class_name
 *       )
 *     SELECT DISTINCT c.name, c.filepath
 *     FROM classes c
 *     JOIN all_implementing_classes aic ON c.name = aic.class_name
 *     ORDER BY c.name;
 *
 * 6) Find similar relationships between traits, like all classes using a trait,
 *    all traits using a trait.
 *
 * 7) Find all classes extending from a base or abstract class, considering the full hierarchy
 *
 * 7) Combine the results of these queries to find all classes implementinng an interface
 *    through the use of a specific trait, a useful mmigration and refactoring seam.
 */
final class PhoundVisitor extends PluginAwarePostAnalysisVisitor
{
    // Avoid `SQLite3::prepare(): Unable to prepare statement: 1, too many SQL variables`
    // See #9: https://www.sqlite.org/limits.html
    // Calculated by table with the most columns (callsites): element, type, callsite / 999 = 333
    // 999 is the max variables until SQLite version 3.32.0, which increased max to 32766
    private const BULK_INSERT_SIZE = 333;

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
    private static $class_relationships_prepared_insert;

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
                'trait TEXT',
                'uses_trait TEXT',
            ],
            'constraints' => [
                'unique (trait, uses_trait)',
            ]
        ],
        'interface_relationships' => [
            'columns' => [
                'parent TEXT',
                'child TEXT',
            ],
            'constraints' => [
                'unique (parent, child)',
            ]
        ],
        'class_relationships' => [
            'columns' => [
                'parent TEXT',
                'child TEXT',
            ],
            'constraints' => [
                'unique (parent, child)',
            ]
        ],
        'class_interfaces' => [
            'columns' => [
                'class TEXT',
                'interface TEXT',
            ],
            'constraints' => [
                'unique (class, interface)',
            ]
        ],
        'class_traits' => [
            'columns' => [
                'class TEXT',
                'trait TEXT',
            ],
            'constraints' => [
                'unique (class, trait)',
            ]
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

        // must be set before table creation to take effect
        if (!self::$db->exec("PRAGMA page_size = 4096")) {
            throw new Exception("Failed to set PRAGMA page_size");
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

        if (!self::$db->exec('CREATE INDEX element_and_callsite ON callsites (element, callsite)')) {
            throw new Exception("Failed to create index on callsites");
        }

        if (!self::$db->exec("PRAGMA synchronous = OFF")) {
            throw new Exception("Failed to set PRAGMA synchronous");
        }
        if (!self::$db->exec("PRAGMA journal_mode = OFF")) {
            throw new Exception("Failed to set PRAGMA journal_mode");
        }
        self::$callsites_prepared_insert  = self::createCallsitesBulkInsertPreparedStatement(self::BULK_INSERT_SIZE);
        self::$classes_prepared_insert    = self::createHierarchyBulkInsertPreparedStmt("classes", self::BULK_INSERT_SIZE);
        self::$interfaces_prepared_insert = self::createHierarchyBulkInsertPreparedStmt("interfaces", self::BULK_INSERT_SIZE);
        self::$traits_prepared_insert     = self::createHierarchyBulkInsertPreparedStmt("traits", self::BULK_INSERT_SIZE);
        self::$class_relationships_prepared_insert = self::createHierarchyBulkInsertPreparedStmt("class_relationships", self::BULK_INSERT_SIZE);
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
     * Creates a prepaired statement for inserting
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

            if (count(self::$callsites) >= self::BULK_INSERT_SIZE) {
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
    public function visitClass(Node $node): void {
        if (
            !$this->context->isInClassScope() ||
            !($node->kind  === \ast\AST_CLASS)
        ) {
            return;
        }

        $clazz = $this->context->getClassInScope($this->code_base);
        $filepath = $this->context->getProjectRelativePath();

        if ($clazz->isClass()) {
            self::handleClass($clazz, $filepath);

        } else if ($clazz->isInterface()) {
            self::handleInterface($clazz, $filepath);

        } else if ($clazz->isTrait()) {
            self::handleTrait($clazz, $filepath);

        } else {
            throw new Exception("Unknown class type: $clazz");
        }
    }

    /**
     * Processes a class to obtain its name, filepath, relationships, interfaces, and traits.
     * @param Clazz     $clazz the class AST node to evaluate
     * @param string    $filepath The absolute filepath to the file containing the class
     * @throws Exception
     */
    private static function handleClass(Clazz $clazz, string $filepath): void {
        // collect basic info
        $name = $clazz->getFQSEN()->__toString();
        self::$classes[] = [$name, $filepath];
        // store
        if (count(self::$classes) >= self::BULK_INSERT_SIZE) {
            self::doHierarchyBulkWrite(self::$classes, self::$classes_prepared_insert);
            self::$classes = [];
        }

        // collect parent class
        if ($clazz->hasParentType()) {
            $parent_name = $clazz->getParentClassFQSEN()->__toString();
            self::$class_relationships[] = [$parent_name, $name];
        }
        // store
        if (count(self::$class_relationships) >= self::BULK_INSERT_SIZE) {
            self::doHierarchyBulkWrite(self::$class_relationships, self::$class_relationships_prepared_insert);
            self::$class_relationships = [];
        }

        // collect implemented interfaces
        $impl_interfaces = $clazz->getInterfaceFQSENList();
        foreach ($impl_interfaces as $iface) {
            self::$class_interfaces[] = [$name, $iface->__toString()];
        }
        // store
        if (count(self::$class_interfaces) >= self::BULK_INSERT_SIZE) {
            $stmt = self::createHierarchyBulkInsertPreparedStmt(
                "class_interfaces",
                count(self::$class_interfaces)
            );
            self::doHierarchyBulkWrite(self::$class_interfaces, $stmt);
            self::$class_interfaces = [];
        }

        // collect used traits
        $used_traits = $clazz->getTraitFQSENList();
        foreach ($used_traits as $trait) {
            self::$class_traits[] = [$name, $trait->__toString()];
        }
        // store
        if (count(self::$class_traits) >= self::BULK_INSERT_SIZE) {
            $stmt = self::createHierarchyBulkInsertPreparedStmt(
                "class_traits",
                count(self::$class_traits)
            );
            self::doHierarchyBulkWrite(self::$class_traits, $stmt);
            self::$class_traits = [];
        }
    }

    /**
     * Processes an interface to obtain its name, filepath, relationships, and implemented interfaces.
     * @param Clazz     $clazz the interface AST node to evaluate
     * @param string    $filepath The absolute filepath to the file containing the interface
     * @throws Exception
     */
    private static function handleInterface(Clazz $clazz, string $filepath): void {
        // collect basic info
        $name = $clazz->getFQSEN()->__toString();
        self::$interfaces[] = [$name, $filepath];
        // store
        if (count(self::$interfaces) >= self::BULK_INSERT_SIZE) {
            self::doHierarchyBulkWrite(self::$interfaces, self::$interfaces_prepared_insert);
            self::$interfaces = [];
        }

        // collect extensions of other interfaces
        $parent_ifaces = $clazz->getInterfaceFQSENList();
        if (count($parent_ifaces) > 0) {
            foreach ($parent_ifaces as $iface) {
                self::$interface_relationships[] = [
                    $iface->__toString(),
                    $name
                ];
            }
        }

        // store
        if (count(self::$interface_relationships) >= self::BULK_INSERT_SIZE) {
            $stmt = self::createHierarchyBulkInsertPreparedStmt(
                "interface_relationships",
                count(self::$interface_relationships)
            );
            self::doHierarchyBulkWrite(self::$interface_relationships, $stmt);
            self::$interface_relationships = [];
        }
    }

    /**
     * Processes a trait to obtain its name, filepath, and used traits.
     * @param Clazz     $clazz the trait AST node to evaluate
     * @param string    $filepath The absolute filepath to the file containing the trait
     * @throws Exception
     */
    private static function handleTrait(Clazz $clazz, string $filepath): void {
        // collect basic info
        $name = $clazz->getFQSEN()->__toString();
        self::$traits[] = [$name, $filepath];
        // store
        if (count(self::$traits) >= self::BULK_INSERT_SIZE) {
            self::doHierarchyBulkWrite(self::$traits, self::$traits_prepared_insert);
            self::$traits = [];
        }

        // collect usages of other traits
        $uses_traits = $clazz->getTraitFQSENList();
        foreach ($uses_traits as $trait) {
            self::$trait_traits[] = [$name, $trait->__toString()];
        }
        // store
        if (count(self::$trait_traits) >= self::BULK_INSERT_SIZE) {
            $stmt = self::createHierarchyBulkInsertPreparedStmt(
                "trait_traits",
                count(self::$trait_traits)
            );
            self::doHierarchyBulkWrite(self::$trait_traits, $stmt);
            self::$trait_traits = [];
        }
    }

    /**
     * Bind any 2 values to a row for both tables and relationship tables, just because they have the same
     * # of columns. if base tables diverge in # of columns from relationship tables, this breaks.
     * @param list<array{string,string}> $nodes
     * @param SQLite3Stmt $stmt
     * @throws Exception
     */
    private static function doHierarchyBulkWrite(array $nodes, SQLite3Stmt $stmt): void {
        $bind_index = 1;
        foreach ($nodes as $node) {
            $stmt->bindValue($bind_index, $node[0], SQLITE3_TEXT);
            $bind_index++;
            $stmt->bindValue($bind_index, $node[1], SQLITE3_TEXT);
            $bind_index++;
        }
        self::execStatement($stmt);
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
     * Finish pending bulk writes.
     * @throws Exception
     */
    public static function finalizeProcess(): void {
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
                UNION ALL
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
                UNION ALL
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
                UNION ALL
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
        $flatten_class_interfaces = "
            WITH RECURSIVE inherited(class, interface) AS (
                SELECT class, interface
                FROM class_interfaces
                UNION ALL
                SELECT cr.child, ih.interface
                FROM class_relationships cr
                JOIN inherited ih
                ON cr.parent = ih.class
            )
            INSERT OR IGNORE INTO class_interfaces (class, interface)
            SELECT class, interface FROM inherited;
        ";
        if (!self::$db->exec($flatten_class_interfaces)) {
            throw new Exception("Failed to flatten class interfaces");
        }

        // Propagate traits down the class hierarchy: if a parent class
        // uses a trait, all child classes should too.
        $flatten_class_traits = "
            WITH RECURSIVE inherited(class, trait) AS (
                SELECT class, trait
                FROM class_traits
                UNION ALL
                SELECT cr.child, ih.trait
                FROM class_relationships cr
                JOIN inherited ih
                ON cr.parent = ih.class
            )
            INSERT OR IGNORE INTO class_traits (class, trait)
            SELECT class, trait FROM inherited;
        ";
        if (!self::$db->exec($flatten_class_traits)) {
            throw new Exception("Failed to flatten class traits");
        }
    }

}

use Phan\PluginV3;
use Phan\PluginV3\PostAnalyzeNodeCapability;
use Phan\PluginV3\FinalizeProcessCapability;
use Phan\PluginV3\AnalyzeFunctionCallCapability;
use Phan\AST\UnionTypeVisitor;
use Phan\Language\Element\FunctionInterface;

/**
 * Plugin to go with PhoundVisitor.
 */
final class PhoundPlugin extends PluginV3 implements PostAnalyzeNodeCapability, AnalyzeFunctionCallCapability, FinalizeProcessCapability
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
     * @param CodeBase $code_base @phan-unused-param
     * @return array<string,Closure(CodeBase,Context,FunctionInterface,list<mixed>,?Node)>
     * maps FQSEN of function or method to a closure used to analyze the function in question.
     * '\A::foo' or 'A::foo' as a key will override a method, and '\foo' or 'foo' as a key will override a function.
     * Closure Type: function(CodeBase $code_base, Context $context, Func|Method $function, array $args, ?Node $node) : void {...}
     *
     * If compatibility with older Phan versions is needed, make the param for $node optional.
     *
     * Note that $function->getMostRecentParentNodeListForCall() can be used to get the parent node list of the current call (will be the empty array if fetching it failed).
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base): array
    {
        // Unit tests invoke this repeatedly. Cache it.
        static $analyzers = null;
        if ($analyzers === null) {
            $analyzers = self::getAnalyzeFunctionCallClosuresStatic();
        }
        return $analyzers;
    }

    /**
     * Ensure that we track callsites in callables passed to call_user_func,
     * forward_static_call, call_user_func_array, forward_static_call_array,
     * Closure::fromCallable, etc.
     *
     * Much of the logic in here was cribbed from https://github.com/phan/phan/blob/0fd8121798fa1c77d7f7608cf36d71f0b8325880/src/Phan/Plugin/Internal/ClosureReturnTypeOverridePlugin.php#L199
     *
     * @return array<string,\Closure>
     */
    private static function getAnalyzeFunctionCallClosuresStatic(): array
    {
        /**
         * @param list<Node|int|string|float> $args
         * @throws Exception
         */
        $generic_callback = static function(
            CodeBase $code_base,
            Context $context,
            array $args
        ): void {
            $function_like_list = UnionTypeVisitor::functionLikeListFromNodeAndContext($code_base, $context, $args[0], true);
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

        /**
         * @param list<Node|int|string|float> $args
         * @throws Exception
         */
        $call_user_func_callback = static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $unused_function,
            array $args,
            ?Node $_
        ) use ($generic_callback) : void {
            if (\count($args) < 1) {
                return;
            }
            $generic_callback($code_base, $context, $args);
        };

        /**
         * @param list<Node|int|string|float> $args
         * @throws Exception
         */
        $call_user_func_array_callback = static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $unused_function,
            array $args,
            ?Node $_
        ) use ($generic_callback) : void {
            if (\count($args) < 2) {
                return;
            }
            $generic_callback($code_base, $context, $args);
        };

        /**
         * @param list<Node|int|string|float> $args
         * @throws Exception
         */
        $from_callable_callback = static function (
            CodeBase $code_base,
            Context $context,
            FunctionInterface $unused_function,
            array $args,
            ?Node $_
        ) use ($generic_callback) : void {
            if (\count($args) !== 1) {
                return;
            }

            $generic_callback($code_base, $context, $args);
        };

        return [
            'call_user_func'            => $call_user_func_callback,
            'forward_static_call'       => $call_user_func_callback,
            'call_user_func_array'      => $call_user_func_array_callback,
            'forward_static_call_array' => $call_user_func_array_callback,
            'Closure::fromCallable'     => $from_callable_callback,
        ];
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
     * @unused-param $code_base
     * @throws Exception
     */
    public function finalizeProcess(CodeBase $code_base): void
    {
        PhoundVisitor::finalizeProcess();
    }

}

return new PhoundPlugin();
