<?php

namespace FilippoToso\MigrationsGenerator;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Blade;
use Illuminate\Database\Schema\Builder;

use Doctrine\DBAL\Types\TimeType;
use Doctrine\DBAL\Types\DateType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\DateTimeTzType;
use Doctrine\DBAL\Types\VarDateTimeType;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\JsonArrayType;
use Doctrine\DBAL\Types\ObjectType;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\ArrayType;
use Doctrine\DBAL\Types\Type;

class GenerateMigrations extends Command
{
    protected const OPEN_ROW = '<' . '?php' . "\n\n";

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:migrations
                            {--overwrite : Overwrite already generated migrations}
                            {--connection=default : Which connection use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate migrations from the database';

    /**
     * Specify the database connection to be used
     *
     * @var string|null
     */
    protected $connection = null;

    /**
     * Specify if overwrite existing generated models
     *
     * @var boolean
     */
    protected $overwrite = false;

    /**
     * Database tables
     * @var array
     */
    protected $tables = [];

    /**
     * Database columns
     * @var array
     */
    protected $columns = [];

    /**
     * Database indexes
     * @var array
     */
    protected $indexes = [];

    /**
     * Database foreign keys
     * @var array
     */
    protected $foreignKeys = [];

    /**
     * Database primary keys
     * @var array
     */
    protected $primaryKeys = [];

    /**
     * Tables required by current table
     * @var array
     */
    protected $requiredTables = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Normalize columns
     * @method normalizeColumns
     * @param  array $columns
     * @return array
     */
    protected function normalizeColumns($columns)
    {
        $results = [];

        foreach ($columns as $column => $data) {
            $column = str_replace('`', '', $column);
            $results[$column] = $data;
        }

        return $results;
    }


    /**
     * Load the database information in local arrays
     * @method buildInternalData
     * @return void
     */
    protected function buildInternalData()
    {
        $this->tables = array_diff(
            DB::connection($this->connection)->getDoctrineSchemaManager()->listTableNames(),
            config('migrations-generator.exclude')
        );

        $this->tables = array_diff($this->tables, ['migrations']);

        $this->columns = [];
        $this->indexes = [];
        $this->foreignKeys = [];
        $this->primaryKeys = [];
        $this->requiredTables = [];

        foreach ($this->tables as $table) {

            $this->columns[$table] = $this->normalizeColumns(DB::connection($this->connection)->getDoctrineSchemaManager()->listTableColumns($table));
            $this->indexes[$table] = DB::connection($this->connection)->getDoctrineSchemaManager()->listTableIndexes($table);
            $this->foreignKeys[$table] = DB::connection($this->connection)->getDoctrineSchemaManager()->listTableForeignKeys($table);
            $this->primaryKeys[$table] = isset($this->indexes[$table]['primary']) ? head($this->indexes[$table]['primary']->getColumns()) : null;
        }

        foreach ($this->foreignKeys as $table => $foreignKeys) {
            $this->requiredTables[$table] = [];
            foreach ($foreignKeys as $foreignKeyName => $foreignKey) {
                $this->requiredTables[$table][] = $foreignKey->getForeignTableName();
            }
        }

    }

    /**
     * Get the table columns list of different types
     * @method getTableColumns
     * @param  string          $table
     * @return array
     */
    protected function getTableColumns($table)
    {
        $results = [];

        $columns = $this->columns[$table];
        $columnsNames = array_keys($columns);

        $indexes = $this->indexes[$table];
        $primary = isset($indexes['primary']) ? head($indexes['primary']->getColumns()) : null;

        $defaultResult = [
            'type' => null,
            'name' => null,
            'length' => null,
            'precision' => null,
            'scale' => null,
            'unsigned' => null,
            'notnull' => null,
            'default' => null,
            'autoincrement' => null,
            'comment' => null,
            'usecurrent' => null,
            'command' => null,
        ];

        foreach ($columns as $columnName => $column) {

            $current = [
                'type' => null,
                'name' => $column->getName(),
                'length' => $column->getLength(),
                'precision' => $column->getPrecision(),
                'scale' => $column->getScale(),
                'unsigned' => $column->getUnsigned(),
                'nullable' => !$column->getNotNull(),
                'default' => $column->getDefault(),
                'autoincrement' => $column->getAutoincrement(),
                'comment' => $column->getComment(),
                'usecurrent' => false,
                'command' => null,
            ];

            $autoIncrement = $column->getAutoincrement();
            $precision = $column->getPrecision();
            $typeName = $column->getType()->getName();
            $unsigned = $column->getUnsigned();
            $length = $column->getLength();
            $name = $column->getName();
            $default = $column->getDefault();
            $nullable = !$column->getNotNull();

            if (preg_match('#^(.*)_type$#si', $columnName, $matches)) {
                $morphName = $matches[1];
                $morphKey = $morphName . '_id';
                if (isset($columns[$morphKey])) {
                    $results[$morphName] = array_merge($defaultResult, [
                        'type' => $nullable ? 'nullableMorphs' : 'morphs',
                        'name' => $morphName
                    ]);
                    continue;
                }
            } elseif (preg_match('#^(.*)_id#si', $columnName, $matches)) {
                $morphName = $matches[1];
                $morphType = $morphName . '_type';
                if (isset($columns[$morphType])) {
                    continue;
                }
            }

            if (($columnName == 'created_at') && isset($columns['updated_at'])) {
                $current['type'] = $nullable ? 'nullableTimestamps' : 'timestamps';
            } elseif (($columnName == 'updated_at') && isset($columns['created_at'])) {
                continue;
            } elseif ($name == 'remember_token') {
                $current['type'] = 'rememberToken';
            } elseif ($name == 'deleted_at') {
                $current['type'] = 'softDeletes';
            } elseif ($typeName == Type::BIGINT) {
                if ($autoIncrement) {
                    $current['type'] = 'bigIncrements';
                    $current['autoincrement'] = false;
                } else {
                    $current['type'] = $unsigned ? 'unsignedBigInteger' : 'bigInteger';
                }
                $current['unsigned'] = false;
            } elseif ($typeName == Type::BLOB) {
                $current['type'] = 'binary';
            } elseif ($typeName == Type::BOOLEAN) {
                $current['type'] = $unsigned ? 'unsignedTinyInteger' : 'boolean';
                $current['unsigned'] = false;
            } elseif ($typeName == Type::STRING) {
                if ($column->getFixed()) {
                    $current['type'] = 'char';
                } else {
                    if ($length == 0) {
                        $current['type'] = 'longText';
                        $current['length'] = null;
                    } elseif (Builder::$defaultStringLength == $length) {
                        $current['type'] = 'string';
                        $current['length'] = null;
                    } elseif ($length < 255) {
                        $current['type'] = 'string';
                        $current['length'] = $length;
                    } elseif ($length == 255) {
                        $current['type'] = 'string';
                        $current['length'] = null;
                    } elseif (($length > 255) && ($length <= 65535)) {
                        $current['type'] = 'string';
                        $current['length'] = $length;
                    } else {
                        $current['type'] = 'longText';
                        $current['length'] = null;
                    }
                }
            } elseif ($typeName == Type::DATE) {
                $current['type'] = 'date';
            } elseif ($typeName == Type::DATETIME) {
                if ($default == 'CURRENT_TIMESTAMP') {
                    $current['type'] = 'timestamp';
                    $current['usecurrent'] = true;
                } else {
                    $current['type'] = 'dateTime';
                }
            } elseif ($typeName == Type::DECIMAL) {
                $current['type'] = $unsigned ? 'unsignedDecimal' : 'decimal';
                $current['unsigned'] = false;
            } elseif ($typeName == Type::FLOAT) {
                $current['type'] = 'float';
            } elseif ($typeName == Type::INTEGER) {
                if ($autoIncrement) {
                    $current['type'] = 'increments';
                    $current['autoincrement'] = false;
                } else {
                    $current['type'] = $unsigned ? 'unsignedInteger' : 'integer';
                }
                $current['unsigned'] = false;
            } elseif ($typeName == Type::TEXT) {
                if ($length == 65535) {
                    $current['type'] = 'text';
                    $current['length'] = null;
                } elseif ($length == 16777215) {
                    $current['type'] = 'mediumText';
                    $current['length'] = null;
                } else {
                    $current['type'] = 'longText';
                    $current['length'] = null;
                }
            } elseif ($typeName == Type::SMALLINT) {
                if ($autoIncrement) {
                    $current['type'] = 'smallIncrements';
                    $current['autoincrement'] = false;
                } else {
                    $current['type'] = $unsigned ? 'unsignedSmallInteger' : 'smallInteger';
                }
                $current['unsigned'] = false;
            } elseif ($typeName == Type::TIME) {
                $current['type'] = 'time';
            }

            $results[$columnName] = $current;
        }

        $timestampsTables = config('migrations-generator.timestamps');
        $timestampsTables = is_array($timestampsTables) ? $timestampsTables : (($timestampsTables == '*') ? [$table] : []);
        if (!isset($results['timestamps']) && !isset($results['nullableTimestamps']) && (in_array($table, $timestampsTables))) {
            $results['timestamps'] = array_merge($defaultResult, [
                'type' => 'timestamps',
            ]);
        }

        $softDeleteTables = config('migrations-generator.soft_deletes');
        $softDeleteTables = is_array($softDeleteTables) ? $softDeleteTables : (($softDeleteTables == '*') ? [$table] : []);
        if (!isset($results['softDeletes']) && (in_array($table, $softDeleteTables))) {
            $results['softDeletes'] = array_merge($defaultResult, [
                'type' => 'softDeletes',
            ]);
        }

        foreach ($results as $columnName => &$columnAttr) {

            if (in_array($columnAttr['type'], [
                'timestamps', 'rememberToken', 'softDeletes'
            ])) {
                $columnAttr['command'] = sprintf('$table->%s();', $columnAttr['type']);
            } elseif (in_array($columnAttr['type'], [
                'morphs', 'nullableMorphs', 'nullableTimestamps',
                'bigIncrements', 'increments', 'smallIncrements',
            ])) {
                $columnAttr['command'] = sprintf('$table->%s(\'%s\');', $columnAttr['type'], $columnAttr['name']);
            } elseif (in_array($columnAttr['type'], [
                'bigInteger', 'binary', 'boolean', 'date',
                'dateTime', 'integer', 'longText', 'longText',
                'mediumText', 'smallInteger', 'tinyInteger', 'text', 'time',
                'unsignedBigInteger', 'unsignedInteger', 'unsignedSmallInteger', 'unsignedTinyInteger',
            ])) {
                $columnAttr['command'] = sprintf('$table->%s(\'%s\')', $columnAttr['type'], $columnAttr['name']);
                $columnAttr['command'] .= ($columnAttr['nullable']) ? '->nullable()' : '';
                $columnAttr['command'] .= ';';
            } elseif (in_array($columnAttr['type'], [
                'timestamp', 'string', 'char',
                'decimal', 'unsignedDecimal', 'float',
            ])) {

                if ($columnAttr['type'] == 'timestamp') {
                    $columnAttr['command'] = sprintf('$table->%s(\'%s\')', $columnAttr['type'], $columnAttr['name']);
                    $columnAttr['command'] .= ($columnAttr['usecurrent']) ? '->useCurrent()' : '';
                } elseif (in_array($columnAttr['type'], ['decimal', 'unsignedDecimal', 'float'])) {
                    $columnAttr['command'] = sprintf('$table->%s(\'%s\', %d, %d)', $columnAttr['type'], $columnAttr['name'], $columnAttr['precision'], $columnAttr['scale']);
                } elseif (in_array($columnAttr['type'], ['string', 'char'])) {
                    if ($columnAttr['length']) {
                        $columnAttr['command'] = sprintf('$table->%s(\'%s\', %d)', $columnAttr['type'], $columnAttr['name'], $columnAttr['length']);
                    } else {
                        $columnAttr['command'] = sprintf('$table->%s(\'%s\')', $columnAttr['type'], $columnAttr['name']);
                    }
                }
                $columnAttr['command'] .= ($columnAttr['nullable']) ? '->nullable()' : '';
                $columnAttr['command'] .= ';';
            }

        }

        return $results;

    }

    /**
     * Get the table timestamp status
     * @method getTimestamps
     * @param  string        $table
     * @return boolean
     */
    protected function getTimestamps($table)
    {
        return isset($this->columns[$table]['created_at']) && isset($this->columns[$table]['updated_at']);
    }

    /**
     * Get the table soft delete status
     * @method getSoftDeletes
     * @param  string        $table
     * @return boolean
     */
    protected function getSoftDeletes($table)
    {
        return isset($this->columns[$table]['deleted_at']);
    }

    /**
     * Get the table primary key
     * @method getSoftDeletes
     * @param  string        $table
     * @return string|null
     */
    protected function getPrimaryKey($table)
    {
        return $this->primaryKeys[$table];
    }

    /**
     * Generate the generated migratuibs
     * @method generateMigration
     * @param  string $table The origin table
     * @return void
     */
    protected function generateMigration($table, $id)
    {
        $migration = $this->getMigrationName($table);

        $filename = database_path(sprintf('migrations/%s_%s.php', date('Y_m_d_His', time() + $id), $migration));

        $search = database_path(sprintf('migrations/*%s*.php', $migration));
        $files = glob($search);

        if (count($files) > 0) {
            if ($this->overwrite) {
                array_map('unlink', $files);
            } else {
                $this->error(sprintf('Migration "%s" already exists (and no "overwrite" parameter), skipping.', $migration));
                return false;
            }
        }

        $this->comment(sprintf('Generating migration for "%s" table.', $table));

        $params = [
            'table' => $table,
            'class' => sprintf('Create%sTable', ucwords(camel_case(strtolower($table)))),
            'primaryKey' => $this->getPrimaryKey($table),
            'columns' => $this->getTableColumns($table),
            'indexes' => $this->getIndexes($table),
            'foreignKeys' => $this->getForeignKeys($table),
        ];

        $content = self::OPEN_ROW . View::make('migrations-generator::generated-migration', $params)->render();

        $directory = dirname($filename);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($filename, $content);

        $this->info(sprintf('Migration "%s" successfully generated!', $migration));
        return true;
    }

    protected function getMigrationName($table)
    {
        return sprintf('create_%s_table', $table);
    }

    protected function getIndexes($table)
    {

        $indexes = $this->indexes[$table];

        $results = [];

        foreach ($indexes as $indexName => $index) {

            // Skip primary keys until find a way to handle them
            if ($index->isPrimary()) {
                continue;
            }

            // Skip morphs
            $columns = $index->getColumns();
            if ((count($columns) == 2) && ((ends_with($columns[0], '_type') && ends_with($columns[1], '_id')) || (ends_with($columns[1], '_type') && ends_with($columns[0], '_id')))) {
                continue;
            }

            $name = $index->getName();

            $pattern = sprintf('#^(%s_.*_index|.*_FKIndex\d+)$#si', $table);
            if (config('migrations-generator.automatic_index_names') || preg_match($pattern, $name)) {
                $name = null;
            }

            if ($index->isUnique()) {
                $type = 'unique';
            } else {
                $type = 'index';
            }

            if ($name) {
                $command = sprintf('$table->%s(%s, \'%s\');', $type, (count($columns) == 1) ? "'" . current($columns) . "'" : "['" . implode("', '", $columns) . "']", $name);
            } else {
                $command = sprintf('$table->%s(%s);', $type, (count($columns) == 1) ? "'" . current($columns) . "'" : "['" . implode("', '", $columns) . "']");
            }

            $results[$indexName] = [
                'name' => $name,
                'type' => $type,
                'columns' => $columns,
                'command' => $command,
            ];
        }

        return $results;

    }

    protected function getForeignKeys($table)
    {
        $foreignKeys = $this->foreignKeys[$table];

        $results = [];

        foreach ($foreignKeys as $foreignKeyName => $foreignKey) {

            $localColumn = current($foreignKey->getLocalColumns());
            $referenceColumn = current($foreignKey->getForeignColumns());
            $foreignTable = $foreignKey->getForeignTableName();
            $onDelete = trim(strtolower($foreignKey->getOption('onDelete')));

            $command = sprintf('$table->foreign(\'%s\')->references(\'%s\')->on(\'%s\')', $localColumn, $referenceColumn, $foreignTable);
            $command .= ($onDelete && ($onDelete != 'no action')) ? sprintf('->onDelete(\'%s\')', $onDelete) : '';
            $command .= ';';

            $results[$foreignKeyName] = [
                'name' => $foreignKeyName,
                'local' => $localColumn,
                'reference' => $referenceColumn,
                'table' => $foreignTable,
                'command' => $command,
            ];
        }

        return $results;
    }

    protected function sortTables($tables)
    {
        $allTables = DB::connection($this->connection)->getDoctrineSchemaManager()->listTableNames();

        $limit = 25 * count($allTables);

        $sorted = [];

        while (($limit > 0) && count($allTables) > 0) {

            $current = array_shift($allTables);

            $requiredTables = isset($this->requiredTables[$current]) ? $this->requiredTables[$current] : [];

            $diff = array_diff($requiredTables, $sorted);
            if (empty($diff)) {
                $sorted[] = $current;
            } else {
                $allTables[] = $current;
            }

            $limit--;
        }

        $sorted = array_merge($sorted, $allTables);

        $results = array_intersect($sorted, $tables);

        return $results;

    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->overwrite = $this->option('overwrite');

        $this->connection = $this->option('connection') == 'default' ? null : $this->option('connection');

        DB::connection($this->connection)->getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');

        $this->buildInternalData();

        $this->info('Migrations generation started.');

        $tables = $this->sortTables($this->tables);

        $id = 0;
        foreach ($tables as $table) {
            $this->generateMigration($table, $id);
            $id++;
        }

        $this->info('Migrations successfully generated!');
    }

}
