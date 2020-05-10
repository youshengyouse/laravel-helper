<?php

namespace Yousheng\LaravelHelper\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use DB;
use PDO;

class MigrationsFromDatabaseCommand extends Command
{

    /**
     * The console command name.
     * example: php artisan convert:migrations --ignore="table1, table2"
     *
     * @var string
     */
    protected $signature = 'migrate:database {database} {--ignore=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create migrations files from an existing MySQL database';

    protected static $ignoreTabels = ['migrations'];
    protected static $selects = array('column_name as Field', 'column_type as Type', 'is_nullable as Null', 'column_key as Key', 'column_default as Default', 'extra as Extra', 'data_type as Data_Type');
    protected static $schema = [];
    protected static $type2method = [
        'PRI' => 'increments',
        "int:length" => "interger",
    ];
    protected static $sortedTables = [];
    protected static $templateGetTables = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_TYPE='BASE TABLE' AND TABLE_SCHEMA='%s'";
    protected static $templateGetForeignTables = "SELECT TABLE_NAME,REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA='%s' AND REFERENCED_TABLE_SCHEMA='%s'";
    protected static $templateGetForeignConstraint = <<<EOT
SELECT 
	a_.CONSTRAINT_NAME, 
	a_.TABLE_NAME, 
	a_.COLUMN_NAME,
	a_.REFERENCED_TABLE_NAME, 
	a_.REFERENCED_COLUMN_NAME, 
	b_.UPDATE_RULE, 
	b_.DELETE_RULE
FROM 
	information_schema.`KEY_COLUMN_USAGE` AS a_
INNER JOIN 
	information_schema.REFERENTIAL_CONSTRAINTS AS b_ 
ON a_.CONSTRAINT_NAME = b_.CONSTRAINT_NAME AND a_.CONSTRAINT_SCHEMA=b_.CONSTRAINT_SCHEMA AND a_.TABLE_NAME = b_.TABLE_NAME
WHERE a_.CONSTRAINT_SCHEMA='%s' AND a_.TABLE_NAME = '%s' ;
EOT;

    protected static $templateSchema = <<<EOT
<?php

/**
 * ----------------------------------------------------------------------
 * 数据库迁移助手.功能升级中，bug请直接在加qq                                                                                  |
 * ----------------------------------------------------------------------
 * Author: daqi <2922800186@qq.com>
 * ----------------------------------------------------------------------
 * Date: 数据库迁移文件生成时间 %s
 * ----------------------------------------------------------------------
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class %s extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        %s
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        %s
    }
}

EOT;


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
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // 数据库名和排除的表
        $databaseName = $this->argument('database');
        $optionIgnore = $this->option('ignore');

        // 提取哪些表需要生成迁移文件
        $tables = collect(DB::select(sprintf(static::$templateGetTables, $databaseName)))->pluck('TABLE_NAME')->all();

        $ignoreTables = array_merge(static::$ignoreTabels, explode(',', str_replace(' ', '', $optionIgnore)));
        $migrateTables = array_diff($tables, $ignoreTables);
        if ($countMigrate = count($migrateTables)) {
            $this->info(count($migrateTables) . ' tables will be writed into migration file,include ' . $migrateTables[0] . ' ... ' . $migrateTables[count($migrateTables) - 1] . ' etc.');
        }
        // 外键表
        $tableForeigns = collect(DB::select(sprintf(static::$templateGetForeignTables, $databaseName, $databaseName)))->mapWithKeys(function ($item) {
            return [$item->TABLE_NAME => $item->REFERENCED_TABLE_NAME];
        })->all();

        // 所有表排序
        $migrateTables = $this->sortTable($migrateTables, $tableForeigns);

        if ($countForeign = count($tableForeigns)) {
            $this->info($countForeign . ' FOREIGN TABLES  will be writed into migration file');
        }


        foreach ($migrateTables as $table) {
            $downStack[] = $table;
            $down = "        Schema::dropIfExists('{$table}');";
            $up = "        Schema::create('{$table}', function(Blueprint \$table) {\n";
            $tableDescribes = DB::table('information_schema.columns')->where('table_schema', $databaseName)->where('table_name', $table)->get(static::$selects)->all();
            $tableDescribes = array_map(function ($value) {
                return (array)$value;
            }, $tableDescribes);

            foreach ($tableDescribes as $values) {
                extract($values);
                // $Field $Type $Null $Key $Extra $Data_Type
                $method = "";
                $type = Str::before($Type, '('); // int(10)
                $numbers = "";
                $nullable = $Null == "NO" ? "" : "->nullable()";
                $default = empty($Default) ? "" : "->default(\"{$Default}\")";
                $unsigned = strpos($Type, "unsigned") === false ? '' : '->unsigned()';
                $unique = $Key == 'UNI' ? "->unique()" : "";
                $choices = '';
                switch ($type) {
                    case 'enum':
                        $method = 'enum';
                        $choices = preg_replace('/enum/', 'array', $Type);
                        $choices = ", $choices";
                        break;
                    case 'int' :
                        $method = 'unsignedInteger';
                        break;
                    case 'bigint' :
                        $method = 'bigInteger';
                        break;
                    case 'samllint' :
                        $method = 'smallInteger';
                        break;
                    case 'char' :
                    case 'varchar' :
                        $numbers = ", " . substr($Type, strpos($Type, '(') + 1, -1);
                        $method = 'string';
                        break;
                    case 'float' :
                        $method = 'float';
                        break;
                    case 'decimal' :
                        $numbers = ", " . substr($Type, strpos($Type, '(') + 1, -1);
                        $method = 'decimal';
                        break;
                    case 'tinyint' :
                        if ($Type == 'tinyint(1)') {
                            $method = 'boolean';
                        } else {
                            $method = 'tinyInteger';
                        }
                        break;
                    case 'date':
                        $method = 'date';
                        break;
                    case 'timestamp' :
                        $method = 'timestamp';
                        break;
                    case 'datetime' :
                        $method = 'dateTime';
                        break;
                    case 'mediumtext' :
                        $method = 'mediumtext';
                        break;
                    case 'text' :
                        $method = 'text';
                        break;
                }
                if ($Key == 'PRI') {
                    $method = 'increments';
                }
                $up .= "            $" . "table->{$method}('{$Field}'{$choices}{$numbers}){$nullable}{$default}{$unsigned}{$unique};\n";

            }

            // 如果当前表有外键，获取相应外键信息
            if (array_key_exists($table, $tableForeigns)) {
                $foreignInfo = collect(DB::select(sprintf(static::$templateGetForeignConstraint, $databaseName, $table)))->all();
                foreach ($foreignInfo as $v) {
                    $foreign = $v->COLUMN_NAME;
                    $references = $v->REFERENCED_COLUMN_NAME;
                    $on = $v->REFERENCED_TABLE_NAME;
                    $onDelete = $v->DELETE_RULE;
                    $onUpdate = $v->UPDATE_RULE;
                    $up .= "            $" . "table->foreign('{$foreign}')->references('{$references}')->on('{$on}')";
                    $up .= $onDelete ? "->onDelete('" . $onDelete . "')" : '';
                    $up .= $onUpdate ? "->onUpdate('" . $onUpdate . "');" : ';';
                }

            }

            $up .= "        });\n\n";

            self::$schema[$table] = array(
                'up' => $up,
                'down' => $down
            );


        }

        $schema = static::compileSchema($databaseName);
        $filename = date('Y_m_d_His') . "_create_" . str_replace(['_', '.'], '', Str::snake($databaseName)) . "_database.php";
        $path = app()->databasePath() . '/migrations/';
        file_put_contents($path . $filename, $schema);
        $this->comment('Migration Created Successfully');
    }


    private static function compileSchema($databaseName)
    {
        static $number = 1;
        $upSchema = "";
        $downSchema = "";
        $newSchema = "";
        foreach (self::$schema as $name => $values) {
            $upSchema .= "        // --------------------------------{$number}------------------{$name}\n{$values['up']}";
            if ($values['down'] !== "") {
                $downSchema .= "\n{$values['down']}";
            }
            $number++;
        }
        $_className = "Create" . str_replace(['_', '.'], '', Str::title($databaseName)) . "Database";
        $_createDate = date("Y-m-d H_i_s");
        $_up = $upSchema;
        $_down = $downSchema;
        return sprintf(static::$templateSchema, $_createDate, $_className, $_up, $_down);
    }

    /**
     * @param $original 原始数组
     * @param $associate 关联数组，键依赖于值，所以键得放在值后面
     * @return array
     */
    private function sortTable($original, $associate)
    {
        $one=array_diff($original,array_keys($associate));
        $two =[];
        foreach ($associate as $key=>$value){
            if(in_array($value,$one)) continue;
            if(in_array($value,$two)) {
                $two[]=$key;
            }else{
                $two[]=$value;
                $two[]=$key;
            }
        }
        return array_merge($one,$two);
    }
}
