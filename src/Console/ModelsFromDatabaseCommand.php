<?php
/**
* 用法： php artisan db:models
*
*
*
*
*
*
*/

namespace Yousheng\LaravelHelper\Console;

use Illuminate\Console\GeneratorCommand;

use Illuminate\Support\Str;
use DB;
use PDO;

// todo 支持nova，添加--nova，一键生成与model对应的 resource
class ModelsFromDatabaseCommand extends GeneratorCommand
{
    use SchemaGeneratorTrait;
    protected $signature = 'db:models {--overwrite} {--ignore=}  {--only=}';
    protected $description = 'create models files from an existing MySQL database';
    protected static $ignoreTabels = ['migrations'];
    public function handle()
    {
        $this->setDoctrineConnection();

        $this->info("\nFetching tables...");

        // 处理表
        // @todo 加上only和except
        $tables = collect($this->getTables())->map(function($table){
            return $table->getName();
        })->all();


        $onlyTables=[];
        $optionIgnore = $this->option('ignore');
        $optionOnly = $this->option('only');
        if($optionOnly=trim($optionOnly)){
            $onlyTables =  explode(',', $optionOnly);
        }

        if(!empty($onlyTables)){

            $tables = array_intersect($tables,$onlyTables);
            $optionIgnore=[];
        }
        if(!empty($optionIgnore)){
            $tables = array_diff($tables,$onlyTables);
        }

        // 从所有表中读取 column,主键，外健信息
        $this->info('Fetching table columns, primary keys, foreign keys');
        $prep = $this->getColumnsPrimaryAndForeignKeysPerTable($tables);
        /**
         *   "user_channel" => array:3 [
                "foreign" => array:1 [
                  0 => array:6 [
                    "name" => "user_channel_ibfk_1"
                    "field" => "user_id"
                    "references" => "id"
                    "on" => "users"
                    "onUpdate" => "NO ACTION"
                    "onDelete" => "CASCADE"
                  ]
                ]
                "primary" => array:1 [
                  0 => "id"
                ]
                "columns" => array:11 [
                  0 => "id"
                  1 => "user_id"
                  2 => "username"
                  3 => "title"
                  4 => "description"
                  5 => "formal"
                  6 => "image"
                  7 => "avatar"
                  8 => "attach"
                  9 => "mode"
                  10 => "view"
                ]
              ]
         */

        //3. create an array of rules, holding the info for our Eloquent models to be
        $this->info('Generating Eloquent rules');
        $eloquentRules = $this->getEloquentRules($tables, $prep);
        /**
         *   "users_meta" => array:5 [
            "hasMany" => []
            "hasOne" => []
            "belongsTo" => array:1 [
              0 => array:3 [
                0 => "users"
                1 => "user_id"
                2 => "id"
              ]
            ]
            "belongsToMany" => []
            "fillable" => array:5 [
              0 => "'id'"
              1 => "'user_id'"
              2 => "'option'"
              3 => "'value'"
              4 => "'mode'"
            ]
          ]
         */

        //4. Generate our Eloquent Models
        $this->info("Generating Eloquent models\n");
        $this->generateEloquentModels( $eloquentRules);

        $this->info("\nAll done!");
    }


    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return $this->option('pivot')
            ? $this->resolveStubPath('/stubs/model.pivot.stub')
            : $this->resolveStubPath('/stubs/model.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param  string  $stub
     * @return string
     */
    protected function resolveStubPath($stub)
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.$stub;
    }




    // todo: 增加only和excep选项
    public function getTables() {
        return $this->doctrineSchemaManager->listTables();
    }

    private function generateEloquentModels( $eloquentRules)
    {
        //0. set namespace
        //self::$namespace = $this->getNamespace();

        foreach ($eloquentRules as $table => $rules) {
            try {
                $this->generateEloquentModel( $table, $rules);
            } catch(Exception $e) {
                $this->error("\nFailed to generate model for table $table");
                return;
            }
        }
    }

    private function generateEloquentModel( $table, $rules) {

        /**
         *  "users_meta" => array:5 [
                "hasMany" => []
                "hasOne" => []
                "belongsTo" => array:1 [
                  0 => array:3 [
                    0 => "users"
                    1 => "user_id"
                    2 => "id"
                  ]
                ]
                "belongsToMany" => []
                "fillable" => array:5 [
                  0 => "'id'"
                  1 => "'user_id'"
                  2 => "'option'"
                  3 => "'value'"
                  4 => "'mode'"
                ]
              ]
         */
        //1. Determine path where the file should be generated
        //$destinationFolder = $this->getPath();
        $destinationFolder = app_path('Models');
        $modelName = $this->generateModelNameFromTableName($table);
        $filePathToGenerate = $destinationFolder . '/'.$modelName.'.php';

        $canContinue = $this->canGenerateEloquentModel($filePathToGenerate, $table);
        if(!$canContinue) {
            return;
        }

        //2.  generate relationship functions and fillable array
        $hasMany = $rules['hasMany'];
        $hasOne = $rules['hasOne'];
        $belongsTo = $rules['belongsTo'];
        $belongsToMany = $rules['belongsToMany'];


        $fillable = implode(', ', $rules['fillable']);

        $belongsToFunctions = $this->generateBelongsToFunctions($belongsTo);
        $belongsToManyFunctions = $this->generateBelongsToManyFunctions($belongsToMany);
        $hasManyFunctions = $this->generateHasManyFunctions($hasMany);
        $hasOneFunctions = $this->generateHasOneFunctions($hasOne);

        $functions = $this->generateFunctions([
            $belongsToFunctions,
            $belongsToManyFunctions,
            $hasManyFunctions,
            $hasOneFunctions,
        ]);

        //3. prepare template data
        $templateData = array(
           // 'NAMESPACE' => self::$namespace,
            '{{NAMESPACE}}' => 'App\\Models',
            '{{NAME}}' => $modelName,
            '{{TABLENAME}}' => $table,
            '{{FILLABLE}}' => $fillable,
            '{{FUNCTIONS}}' => $functions
        );

        $templatePath = $this->getTemplatePath();


        /**
         * array:3 [
                  0 => "/www/common/source_php/yousheng/laravel-helper/src/Console/templates/model.txt"
                  1 => array:5 [
                    "NAMESPACE" => "App\"
                    "NAME" => "AdsBox"
                    "TABLENAME" => "ads_box"
                    "FILLABLE" => "'id', 'title', 'size', 'position', 'image', 'url', 'mode', 'sort'"
                    "FUNCTIONS" => ""
                  ]
                  2 => "/www/server/tencent_hongkong/0he1.com/v2_web/app/Models/AdsBox.php"
                ]
         */

        $this->info("table ".$table."'s model path is: ".$filePathToGenerate);
        // 文件的owner是root
        file_put_contents($filePathToGenerate,str_replace(array_keys($templateData),array_values($templateData),file_get_contents($templatePath)));
        //run Jeffrey's generator
       /* $this->generator->make(
            $templatePath,
            $templateData,
            $filePathToGenerate
        );*/
        $this->info("Generated model for table $table");
    }

    private function canGenerateEloquentModel($filePathToGenerate, $table) {
        $canOverWrite = $this->option('overwrite');
        if(file_exists($filePathToGenerate)) {
            $this->error(str_replace(base_path(),'',$filePathToGenerate)."已存在，为了安全暂不支持覆盖功能，请先手工删除后再执行db:models");
            return false;

            if($canOverWrite) {
                $deleted = unlink($filePathToGenerate);
                if(!$deleted) {
                    $this->warn("Failed to delete existing model $filePathToGenerate");
                    return false;
                }
            } else {
                $this->warn("Skipped model generation, file already exists. (force using --overwrite) $table -> $filePathToGenerate");
                return false;
            }
        }

        return true;
    }


    protected function getNamespace_del() {
        $ns = $this->option('namespace');
        if(empty($ns)) {
            $ns = env('APP_NAME','App\Models');
        }

        //convert forward slashes in the namespace to backslashes
        $ns = str_replace('/', '\\', $ns);
        return $ns;

    }

    private function generateFunctions($functionsContainer)
    {
        $f = '';
        foreach ($functionsContainer as $functions) {
            $f .= $functions;
        }

        return $f;
    }

    private function generateHasManyFunctions($rulesContainer)
    {
        $functions = '';
        foreach ($rulesContainer as $rules) {
            $hasManyModel = $this->generateModelNameFromTableName($rules[0]);
            $key1 = $rules[1];
            $key2 = $rules[2];

            $hasManyFunctionName = $this->getPluralFunctionName($hasManyModel);

            $function = "
    public function $hasManyFunctionName() {".'
        return $this->hasMany'."(\\App\\Models\\$hasManyModel::class, '$key1', '$key2');
    }
";
            $functions .= $function;
        }

        return $functions;
    }

    private function generateHasOneFunctions($rulesContainer)
    {
        $functions = '';
        foreach ($rulesContainer as $rules) {
            $hasOneModel = $this->generateModelNameFromTableName($rules[0]);
            $key1 = $rules[1];
            $key2 = $rules[2];

            $hasOneFunctionName = $this->getSingularFunctionName($hasOneModel);

            $function = "
    public function $hasOneFunctionName() {".'
        return $this->hasOne'."(\\App\\Models\\$hasOneModel::class, '$key1', '$key2');
    }
";
            $functions .= $function;
        }

        return $functions;
    }

    private function generateBelongsToFunctions($rulesContainer)
    {
        $functions = '';
        foreach ($rulesContainer as $rules) {
            $belongsToModel = $this->generateModelNameFromTableName($rules[0]);
            $key1 = $rules[1];
            $key2 = $rules[2];

            $belongsToFunctionName = $this->getSingularFunctionName($belongsToModel);

            $function = "
    public function $belongsToFunctionName() {".'
        return $this->belongsTo'."(\\App\\Models\\$belongsToModel::class, '$key1', '$key2');
    }
";
            $functions .= $function;
        }

        return $functions;
    }

    private function generateBelongsToManyFunctions($rulesContainer)
    {
        $functions = '';
        foreach ($rulesContainer as $rules) {
            $belongsToManyModel = $this->generateModelNameFromTableName($rules[0]);
            $through = $rules[1];
            $key1 = $rules[2];
            $key2 = $rules[3];

            $belongsToManyFunctionName = $this->getPluralFunctionName($belongsToManyModel);

            $function = "
    public function $belongsToManyFunctionName() {".'
        return $this->belongsToMany'."(\\App\\Models\\$belongsToManyModel::class, '$through', '$key1', '$key2');
    }
";
            $functions .= $function;
        }

        return $functions;
    }

    private function getPluralFunctionName($modelName)
    {
        $modelName = lcfirst($modelName);
        return Str::plural($modelName);
    }

    private function getSingularFunctionName($modelName)
    {
        $modelName = lcfirst($modelName);
        return Str::singular($modelName);
    }

    private function generateModelNameFromTableName($table)
    {
        return ucfirst( Str::camel(Str::singular($table)));
    }


    private function getColumnsPrimaryAndForeignKeysPerTable($tables)
    {
        return collect(array_flip($tables))->map(function ($key,$table){
           $a = $this->doctrineSchemaManager->listTableForeignKeys($table);
           if(empty($a)){
               $_foreignKeys=[];
           }else{
               $_foreignKeys=collect($this->doctrineSchemaManager->listTableForeignKeys($table))
                   ->map(function($ForeignKeyConstraint) use ($table){
                       return [
                           'name'       => $ForeignKeyConstraint->getName(),               // 外键名字，如 user_meta_fk
                           'field'      => $ForeignKeyConstraint->getLocalColumns()[0],   // user_id
                           'references' => $ForeignKeyConstraint->getForeignColumns()[0], // id
                           'on'         => $ForeignKeyConstraint->getForeignTableName(), // users
                           'onUpdate'   => $ForeignKeyConstraint->hasOption('onUpdate') ? $ForeignKeyConstraint->getOption('onUpdate') : 'RESTRICT',
                           'onDelete'   => $ForeignKeyConstraint->hasOption('onDelete') ? $ForeignKeyConstraint->getOption('onDelete') : 'RESTRICT',
                       ];
                   });
           }


            $pks=$this->doctrineSchemaManager->listTableDetails($table)->getPrimaryKey();
            $_primaryKeys=$pks?$pks->getColumns():[];

           // get columns lists
           $_columns = array_keys($this->doctrineSchemaManager->listTableColumns($table));
            return [
                'foreign' => $_foreignKeys,
                'primary' => $_primaryKeys,
                'columns' => $_columns
            ];
        })->all();


        //------------无误后删除下面的代码
        $prep = [];
        foreach ($tables as $table) {
            $foreignKeys = [];
            $fks = $this->doctrineSchemaManager->listTableForeignKeys($table);
                foreach ($fks as $foreignKey) {
                    $foreignKeys[] = [
                        'name'       => $foreignKey->getName(),               // 外键名字，如 user_meta_fk
                        'field'      => $foreignKey->getLocalColumns()[0],   // user_id
                        'references' => $foreignKey->getForeignColumns()[0], // id
                        'on'         => $foreignKey->getForeignTableName(), // users
                        'onUpdate'   => $foreignKey->hasOption('onUpdate') ? $foreignKey->getOption('onUpdate') : 'RESTRICT',
                        'onDelete'   => $foreignKey->hasOption('onDelete') ? $foreignKey->getOption('onDelete') : 'RESTRICT',
                    ];
                }

            //get primary keys 获取主键
            //$primaryKeys = $this->schemaGenerator->getPrimaryKeys($table);

            $primary_key_index = $this->doctrineSchemaManager->listTableDetails($table)->getPrimaryKey();
            $primaryKeys= $primary_key_index ? $primary_key_index->getColumns() : [];


            // get columns lists
            $__columns = $this->doctrineSchemaManager->listTableColumns($table);
            $columns = [];
            foreach($__columns as $col) {
                $columns[] = $col->toArray()['name'];
            }

            $prep[$table] = [
                'foreign' => $foreignKeys,
                'primary' => $primaryKeys,
                'columns' => $columns,
            ];
        }

        dd($prep);
        return $prep;
    }

    // todo 加上 morph
    private function getEloquentRules($tables, $prep)
    {
        $rules = [];

        //first create empty ruleset for each table
        foreach ($prep as $table => $properties) {
            $rules[$table] = [
                'hasMany' => [],
                'hasOne' => [],
                'belongsTo' => [],
                'belongsToMany' => [],
                'fillable' => [],
            ];
        }

        foreach ($prep as $table => $properties) {
            $foreign = $properties['foreign'];
            $primary = $properties['primary'];
            $columns = $properties['columns'];

            // 所有都设为 fillable
            $this->setFillableProperties($table, $rules, $columns);

            $isManyToMany = $this->detectManyToMany($prep, $table);

            if ($isManyToMany === true) {
                $this->addManyToManyRules($tables, $table, $prep, $rules);
            }

            //the below used to be in an ELSE clause but we should be as verbose as possible
            //when we detect a many-to-many table, we still want to set relations on it
            //else
            {
                foreach ($foreign as $fk) {
                    $isOneToOne = $this->detectOneToOne($fk, $primary);

                    if ($isOneToOne) {
                        $this->addOneToOneRules($tables, $table, $rules, $fk);
                    } else {
                        $this->addOneToManyRules($tables, $table, $rules, $fk);
                    }
                }
            }
        }

        return $rules;
    }

    private function setFillableProperties($table, &$rules, $columns)
    {
        $fillable = [];
        foreach ($columns as $column_name) {
            if ($column_name !== 'created_at' && $column_name !== 'updated_at') {
                $fillable[] = "'$column_name'";
            }
        }
        $rules[$table]['fillable'] = $fillable;
    }

    private function addOneToManyRules($tables, $table, &$rules, $fk)
    {
        //$table belongs to $FK
        //FK hasMany $table

        $fkTable = $fk['on'];
        $field = $fk['field'];
        $references = $fk['references'];
        if(in_array($fkTable, $tables)) {
            $rules[$fkTable]['hasMany'][] = [$table, $field, $references];
        }
        if(in_array($table, $tables)) {
            $rules[$table]['belongsTo'][] = [$fkTable, $field, $references];
        }
    }

    private function addOneToOneRules($tables, $table, &$rules, $fk)
    {
        //$table belongsTo $FK
        //$FK hasOne $table

        $fkTable = $fk['on'];
        $field = $fk['field'];
        $references = $fk['references'];
        if(in_array($fkTable, $tables)) {
            $rules[$fkTable]['hasOne'][] = [$table, $field, $references];
        }
        if(in_array($table, $tables)) {
            $rules[$table]['belongsTo'][] = [$fkTable, $field, $references];
        }
    }

    private function addManyToManyRules($tables, $table, $prep, &$rules)
    {

        //$FK1 belongsToMany $FK2
        //$FK2 belongsToMany $FK1

        $foreign = $prep[$table]['foreign'];

        $fk1 = $foreign[0];
        $fk1Table = $fk1['on'];
        $fk1Field = $fk1['field'];
        //$fk1References = $fk1['references'];

        $fk2 = $foreign[1];
        $fk2Table = $fk2['on'];
        $fk2Field = $fk2['field'];
        //$fk2References = $fk2['references'];

        //User belongstomany groups user_group, user_id, group_id
        if(in_array($fk1Table, $tables)) {
            $rules[$fk1Table]['belongsToMany'][] = [$fk2Table, $table, $fk1Field, $fk2Field];
        }
        if(in_array($fk2Table, $tables)) {
            $rules[$fk2Table]['belongsToMany'][] = [$fk1Table, $table, $fk2Field, $fk1Field];
        }
    }

    //if FK is also a primary key, and there is only one primary key, we know this will be a one to one relationship
    private function detectOneToOne($fk, $primary)
    {
        if (count($primary) === 1) {
            foreach ($primary as $prim) {
                if ($prim === $fk['field']) {
                    return true;
                }
            }
        }

        return false;
    }

    //does this table have exactly two foreign keys that are also NOT primary,
    //and no tables in the database refer to this table?
    private function detectManyToMany($prep, $table)
    {
        $properties = $prep[$table];
        $foreignKeys = $properties['foreign'];
        $primaryKeys = $properties['primary'];

        //ensure we only have two foreign keys
        // 只有两个外键
        if (count($foreignKeys) === 2) {
            // certificates
            // contents_comment
            // user_channel_video

            //ensure our foreign keys are not also defined as primary keys
            // 确保一个外键同时不是主键
            $primaryKeyCountThatAreAlsoForeignKeys = 0;
            foreach ($foreignKeys as $foreign) {
                foreach ($primaryKeys as $primary) {
                    if ($primary === $foreign['name']) {
                        ++$primaryKeyCountThatAreAlsoForeignKeys;
                    }
                }
            }

            if ($primaryKeyCountThatAreAlsoForeignKeys === 1) {
                //one of the keys foreign keys was also a primary key
                //this is not a many to many. (many to many is only possible when both or none of the foreign keys are also primary)
                return false;
            }

            //ensure no other tables refer to this one
            foreach ($prep as $compareTable => $properties) {
                if ($table !== $compareTable) {
                    foreach ($properties['foreign'] as $prop) {
                        if ($prop['on'] === $table) {
                            return false;
                        }
                    }
                }
            }
            //this is a many to many table! 我理解为中间件
            return true;
        }

        return false;
    }

    private function initializeSchemaGenerator()
    {
        $this->schemaGenerator = new SchemaGenerator(
            $this->option('connection'),
            null,
            null
        );

        return $this->schemaGenerator;
    }

    /**
     * Fetch the template data.
     *
     * @return array
     */
    protected function getTemplateData()
    {
        return [
            'NAME' => ucwords($this->argument('modelName')),
            'NAMESPACE' => env('APP_NAME','App\Models'),
        ];
    }

    /**
     * The path to where the file will be created.
     *
     * @return mixed
     */
    protected function getFileGenerationPath()
    {
       // $path = $this->getPathByOptionOrConfig('path', 'model_target_path');
        $path = app_path('Models');

        if(!is_dir($path)) {
            $this->warn('Path is not a directory, creating ' . $path);
            mkdir($path);
        }

        return $path;
    }

    /**
     * Get the path to the generator template.
     *
     * @return mixed
     */
    protected function getTemplatePath()
    {
        $tp = __DIR__.'/stubs/model.txt';

        return $tp;
    }
}
