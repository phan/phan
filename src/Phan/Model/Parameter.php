<?php declare(strict_types=1);
namespace Phan\Model;

use \Phan\Database;
use \Phan\Database\Column;
use \Phan\Database\ModelOne;
use \Phan\Database\Schema;
use \Phan\Language\Element\Parameter as ParameterElement;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;
use \Phan\Language\UnionType;

class Parameter extends ModelOne
{

    /**
     * @var ParameterElement
     */
    private $parameter;

    /**
     * @var FullyQualifiedMethodName|FullyQualifiedFunctionName
     */
    private $method_fqsen;

    /** @var int|null */
    private $primary_key_value = null;

    /**
     * @param ParameterElement $parameter
     */
    public function __construct(
        ParameterElement $parameter,
        FQSEN $method_fqsen,
        int $primary_key_value = null
    ) {
        $this->parameter = $parameter;
        $this->method_fqsen = $method_fqsen;
        $this->primary_key_value = $primary_key_value;
    }

    public static function createSchema() : Schema
    {
        $schema = new Schema('Parameter', [
            new Column('id', Column::TYPE_INT, true, true),
            new Column('method_fqsen', Column::TYPE_STRING),
            new Column('name', Column::TYPE_STRING),
            new Column('type', Column::TYPE_STRING),
            new Column('flags', Column::TYPE_INT),
            new Column('context', Column::TYPE_STRING),
            new Column('is_deprecated', Column::TYPE_BOOL),
        ]);

        // Enforce that we only save one reference per line. Its OK
        // if we overwrite on things like `[C::f(1), C::f(2)]`.
        $schema->addCreateQuery(
            "CREATE INDEX IF NOT EXISTS Parameter_Method_FQSEN"
            . " ON `Parameter` "
            . " (method_fqsen)"
        );

        return $schema;
    }

    /**
     * @return ParameterElement
     */
    public function getParameter() : ParameterElement
    {
        return $this->parameter;
    }

    /**
     * We include this method in order to narrow the return
     * type
     */
    public static function read(
        Database $database,
        $primary_key_value
    ) : Parameter {
        return parent::read($database, $primary_key_value);
    }


    /**
     * @param Database $database
     * A database to write this model to
     *
     * @return void
     */
    public function write(Database $database)
    {
        parent::write($database);
        $this->primary_key_value =
            $database->lastInsertRowID();
    }

    /**
     * @return Parameter[]
     * The set of parameters for a given method
     */
    public static function findManyByFQSEN(
        Database $database,
        FQSEN $fqsen
    ) : array {
        // Ensure that we've initialized this model
        static::schema()->initializeOnce($database);

        $query = static::schema()->queryForSelectColumnValue(
            'method_fqsen',
            (string)$fqsen
        );

        $result = $database->query($query);
        if (!$result) {
            return [];
        }

        $list = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $list[] = static::fromRow($row);
        }

        return $list;
    }


    /**
     * @return string
     * The value of the primary key for this model
     */
    public function primaryKeyValue()
    {
        return (string)$this->primary_key_value;
    }

    /**
     * @return array
     * Get a map from column name to row values for
     * this instance
     */
    public function toRow() : array
    {
        return [
            'method_fqsen' => (string)$this->method_fqsen,
            'name' => (string)$this->parameter->getName(),
            'type' => (string)$this->parameter->getUnionType(),
            'flags' => $this->parameter->getFlags(),
            'context' => base64_encode(serialize($this->parameter->getContext())),
            'is_deprecated' => $this->parameter->isDeprecated(),
        ];
    }

    /**
     * @param array
     * A map from column name to value
     *
     * @return Model
     * An instance of the model derived from row data
     */
    public static function fromRow(array $row) : Parameter
    {
        if (false !== strpos($row['method_fqsen'], '::')) {
            $method_fqsen = FullyQualifiedMethodName::fromFullyQualifiedString(
                $row['method_fqsen']
            );
        } else {
            $method_fqsen = FullyQualifiedFunctionName::fromFullyQualifiedString(
                $row['method_fqsen']
            );
        }

        $primary_key_value = $row['id'] ?? null;

        $parameter = new Parameter(new ParameterElement(
            unserialize(base64_decode($row['context'])),
            $row['name'],
            UnionType::fromFullyQualifiedString($row['type']),
            (int)$row['flags']
        ), $method_fqsen, $primary_key_value);

        return $parameter;
    }
}
