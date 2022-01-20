<?php
/**
 * 生成数据库命令
 * 还在完善中
 */
namespace Yousheng\LaravelHelper\Console;

use Illuminate\Console\Command;
use PDO;
use PDOException;

class DatabaseCreateCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'db:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command creates a new database';

    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'db:create';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $database = env('DB_DATABASE', false);
        $host = env('DB_HOST', false);
        $port = env('DB_PORT', false);
        $username = env('DB_USERNAME', false);
        $password = env('DB_PASSWORD', false);

        if (! $database ) {
            $this->info('Skipping creation of database as env(DB_DATABASE) is empty');
            return;
        }
        if(!($host && $username && $password)){
            $this->error('please alter file .env for database configuation');
            return;
        }

        $this->info("please enter mysql root name and password for create database ".$database);
        $rootUser = $this->ask('mysql root name');
        $rootPassword = $this->ask('root password');

        try {
            $pdo = $this->getPDOConnection($host, $port,$rootUser, $rootPassword);

            $stmt = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$database'");
            $databaseExists =  (bool) $stmt->fetchColumn();

            if(!$databaseExists){
                $pdo->exec(sprintf(
                    // 'CREATE DATABASE IF NOT EXISTS %s CHARACTER SET %s COLLATE %s;',
                    'CREATE DATABASE %s CHARACTER SET %s COLLATE %s;',
                    $database,
                    env('DB_CHARSET')??'utf8mb4',
                    env('DB_COLLATION')??'utf8mb4_unicode_ci'
                ));
                $this->info(sprintf('Successfully created %s database', $database));
            }else{
                $this->info(sprintf('database %s exists', $database));
            }

            /*  @todo mysql 版本检测
                $version = $pdo->query('select version()')->fetchColumn();
                (float)$version = mb_substr($version, 0, 6);
                if ($version < '5.6.10') {
                    $this->info('不支持5.6.10下的版本');
                    return ;
                } 
            */
            if($rootUser !== $username /* && $this->option('user')*/){
                $pdo->exec("CREATE USER IF NOT EXISTS '$username'@'%' IDENTIFIED BY '$password'");
                $pdo->exec("GRANT ALL ON `$database`.* TO '$username'@'%'");
                $this->info(sprintf('Successfully created  user %s', $username));
            }
        } catch (PDOException $exception) {
            $this->error(sprintf('Failed to create %s database, %s', $database, $exception->getMessage()));
        }
    }

    /**
     * @param  string $host
     * @param  integer $port
     * @param  string $username
     * @param  string $password
     * @return PDO
     */
    private function getPDOConnection($host, $port, $username, $password)
    {
        return new PDO(sprintf('mysql:host=%s;port=%d;', $host, $port), $username, $password);
    }

}