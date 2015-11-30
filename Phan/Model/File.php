<?php declare(strict_types=1);
namespace Phan\Model;

use \Phan\CodeBase\File as CodeBaseFile;
use \Phan\Database;
use \Phan\Database\Column;
use \Phan\Database\ListAssociation;
use \Phan\Database\ModelOne;
use \Phan\Database\Schema;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;
use \Phan\Language\UnionType;

class File extends ModelOne {

    /**
     * @var CodeBaseFile
     */
    private $file;

    /**
     * @param CodeBaseFile $file
     */
    public function __construct(CodeBaseFile $file) {
        $this->file = $file;
    }

    public function getFile() : CodeBaseFile {
        return $this->file;
    }

    public static function read(Database $database, $primary_key_value) : File {
        return parent::read($database, $primary_key_value);
    }

    public static function createSchema() : Schema {
        $schema = new Schema('File', [
            new Column('file_path', Column::TYPE_STRING, true),
            new Column('modification_time', Column::TYPE_INT)
        ]);

        $schema->addAssociation(
            new ListAssociation(
                'FileClassFQSEN', Column::TYPE_STRING,
                function (File $file, array $class_fqsen_string_list) {
                    $file->getFile()->setClassFQSENList(
                        array_map(function (string $fqsen_string) {
                            return FullyQualifiedClassName::fromFullyQualifiedString(
                                $fqsen_string
                            );
                        }, $class_fqsen_string_list)
                    );
                },
                function (File $file) {
                    return array_map(function (FullyQualifiedClassName $fqsen) {
                        return (string)$fqsen;
                    }, $file->getFile()->getClassFQSENList());
                }
            )
        );

        $schema->addAssociation(
            new ListAssociation(
                'FileMethodFQSEN', Column::TYPE_STRING,
                function (File $file, array $method_fqsen_string_list) {
                    $file->getFile()->setMethodFQSENList(
                        array_map(function (string $fqsen_string) {
                            if (false !== strpos($fqsen_string, '::')) {
                                return FullyQualifiedMethodName::fromFullyQualifiedString(
                                    $fqsen_string
                                );
                            } else {
                                return FullyQualifiedFunctionName::fromFullyQualifiedString(
                                    $fqsen_string
                                );
                            }
                        }, $method_fqsen_string_list)
                    );
                },
                function (File $file) {
                    return array_map(function (FQSEN $fqsen) {
                        return (string)$fqsen;
                    }, $file->getFile()->getMethodFQSENList());
                }
            )
        );


        return $schema;
    }

    /**
     * @return string
     * The primary key of this model
     */
    public function primaryKeyValue() : string {
        return $this->file->getFilePath();
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
    public static function fromRow(array $row) : File {
        return new File(new CodeBaseFile(
            $row['file_path'],
            (int)$row['modification_time']
        ));
    }

}
