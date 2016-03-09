<?php declare(strict_types=1);
namespace Phan\Model;

use Phan\Database;
use Phan\Database\Column;
use Phan\Database\ModelOne;
use Phan\Database\Schema;
use Phan\Language\FQSEN;
use Phan\Language\FileRef;

class CalledBy extends ModelOne
{

    /**
     * @var string
     * The fully qualified name for the structural element
     * being called
     */
    private $fqsen_string;

    /**
     * @var FileRef
     * The file and line of the caller of the given
     * FQSEN
     */
    private $file_ref;

    /**
     * @param string $fqsen_string
     * The fully qualified name for the structural element
     * being called
     *
     * @param FileRef $file_ref
     * The file and line of the caller of the given
     * FQSEN
     */
    public function __construct(
        string $fqsen_string,
        FileRef $file_ref
    ) {
        $this->fqsen_string = $fqsen_string;
        $this->file_ref = $file_ref;
    }

    /*
    public function getFQSENString() : string {
        return $this->fqsen_string;
    }
    */

    public function getFileRef() : FileRef
    {
        return $this->file_ref;
    }

    /**
     * n.b.: You probably don't want to use this method as this
     *       does lookups based on the int primary key (which is
     *       auto-generated). Consider using `findManyByFQSEN()`
     *       instead.
     */
    public static function read(
        Database $database,
        $primary_key_value
    ) : CalledBy {
        return parent::read($database, $primary_key_value);
    }

    public static function createSchema() : Schema
    {
        $schema = new Schema('CalledBy', [
            new Column('id', Column::TYPE_INT, true, true),
            new Column('fqsen_string', Column::TYPE_STRING),
            new Column('file_path', Column::TYPE_STRING),
            new Column('line_number', Column::TYPE_INT)
        ]);

        // Enforce that we only save one reference per line. Its OK
        // if we overwrite on things like `[C::f(1), C::f(2)]`.
        $schema->addCreateQuery(
            "CREATE UNIQUE INDEX IF NOT EXISTS CalledBy_fqsen_file_line"
            . " ON `CalledBy` "
            . " (fqsen_string, file_path, line_number)"
        );

        // Find all callers by FQSEN
        $schema->addCreateQuery(
            "CREATE INDEX IF NOT EXISTS CalledBy_fqsen"
            . " ON `CalledBy` "
            . " (fqsen_string)"
        );

        // Find all references by File (so we can delete 'em
        // when the file changes).
        $schema->addCreateQuery(
            "CREATE INDEX IF NOT EXISTS CalledBy_file"
            . " ON `CalledBy` "
            . " (file_path)"
        );

        return $schema;
    }

    /**
     * @return CalledBy[]
     * The set of callers for the given FQSEN
     */
    public static function findManyByFQSEN(
        Database $database,
        FQSEN $fqsen
    ) : array {
        // Ensure that we've initialized this model
        static::schema()->initializeOnce($database);

        $query = static::schema()->queryForSelectColumnValue(
            'fqsen_string',
            (string)$fqsen
        );

        $result = $database->query($query);
        if (!$result) {
            return [];
        }

        $called_by_list = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $called_by_list[] = static::fromRow($row);
        }

        return $called_by_list;
    }

    /**
     * @return CalledBy[]
     * The set of calls in the given file
     */
    /*
    public static function findManyByFilePath(
        Database $database,
        string $file_path
    ) : array {
        // Ensure that we've initialized this model
        static::schema()->initializeOnce($database);

        $query = static::schema()->queryForSelectColumnValue(
            'file_path', $file_path
        );

        $result = $database->query($query);
        if (!$result) {
            return [];
        }

        $called_by_list = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $called_by_list[] = static::fromRow($row);
        }

        return $called_by_list;
    }
    */

    /**
     * @return string
     * The primary key of this model
     */
    public function primaryKeyValue() : string
    {
        throw new \Exception("Unimplemented");
        return 'not implemented';
    }

    /**
     * @return array
     * Get a map from column name to row values for
     * this instance
     */
    public function toRow() : array
    {
        return [
            'fqsen_string' => (string)$this->fqsen_string,
            'file_path' => $this->file_ref->getFile(),
            'line_number' => $this->file_ref->getLineNumberStart()
        ];
    }

    /**
     * @param array
     * A map from column name to value
     *
     * @return CalledBy
     * An instance of the model derived from row data
     */
    public static function fromRow(array $row) : CalledBy
    {
        return new CalledBy(
            $row['fqsen_string'],
            (new FileRef)
                ->withFile($row['file_path'])
                ->withLineNumberStart((int)$row['line_number'])
        );
    }
}
