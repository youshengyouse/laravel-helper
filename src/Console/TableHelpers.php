<?php


namespace Yousheng\LaravelHelper\Console;


trait TableHelpers
{
    protected static $templateGetTables = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_TYPE='BASE TABLE' AND TABLE_SCHEMA='%s'";


    /**
     * 从数据库中读取所有的表
     *
     * @return mixed
     */
    public function tables($databaseName)
    {
        return DB::select(sprintf(static::$templateGetTables, $databaseName));
    }


    /**
     * @return mixed
     */
    public function getTables()
    {
        return $this->schema->listTableNames();
    }

    public function getFields($table)
    {
        return $this->fieldGenerator->generate($table, $this->schema, $this->database, $this->ignoreIndexNames);
    }

    public function getForeignKeyConstraints($table)
    {
        return $this->foreignKeyGenerator->generate($table, $this->schema, $this->ignoreForeignKeyNames);
    }
}