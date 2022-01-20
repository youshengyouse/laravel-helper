<?php


namespace Yousheng\LaravelHelper\Console;
use Illuminate\Support\Facades\DB;


trait SchemaGeneratorTrait
{
    private $doctrineConnection;
    private $doctrineSchemaManager;

    public function setDoctrineConnection($database=null)
    {
        $conn = DB::connection();
        if(is_string($database)){
            $conn->setDatabaseName($database);
        }
        $this->doctrineConnection =$doctrineConnection= $conn->getDoctrineConnection();
        $this->doctrineSchemaManager=$doctrineConnection->getSchemaManager();
        $doctrineConnection->getDatabasePlatform()->registerDoctrineTypeMapping('json', 'text');
        $doctrineConnection->getDatabasePlatform()->registerDoctrineTypeMapping('jsonb', 'text');
        $doctrineConnection->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
        $doctrineConnection->getDatabasePlatform()->registerDoctrineTypeMapping('bit', 'boolean');
        return $this;
    }




}