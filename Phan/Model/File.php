<?php declare(strict_types=1);
namespace Phan\Model;

use \Phan\Database\Column;
use \Phan\Database\ModelOne;
use \Phan\Database\Schema;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\UnionType;
use \Phan\CodeBase\File as CodeBaseFile;

class File extends ModelOne {

    /**
     * @var CodeBaseFile
     */
    private $file;

    /**
     * @param CodeBaseFile $file
     */
    public function __construct(
        CodeBaseFile $file
    ) {
        $this->file = $file;
    }

    public static function createSchema() : Schema {
        return new Schema('File', [
            new Column('file_path', Column::TYPE_STRING, true),
            new Column('modification_time', Column::TYPE_INT)
        ]);
    }

    /**
     * @return string
     * The primary key of this model
     */
    public function primaryKeyValue() : string {
        return $this->file_path;
    }

    /**
     * @return array
     * Get a map from column name to row values for
     * this instance
     */
    public function toRow() : array {
        return [
            'file_path' => $this->file->getFilePath(),
            'modification_time' => $this->file->getModificationTime(),
        ];
    }

    /**
     * @param array
     * A map from column name to value
     *
     * @return File
     * An instance of the model derived from row data
     */
    public static function fromRow(array $row) : CodeBaseFile {
        return new CodeBaseFile(
            $row['file_path'],
            (int)$row['modification_time']
        );
    }

}
