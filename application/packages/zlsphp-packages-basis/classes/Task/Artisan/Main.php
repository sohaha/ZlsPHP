<?php
namespace Task\Artisan;
/**
 * Zls_Artisan_Main
 * @author        影浅-Seekwe
 * @email       seekwe@gmail.com
 * @updatetime    2017-2-27 16:52:51
 */
use z;
class Main extends \Zls_Task
{
    private $args = [];
    public function execute(\Zls_CliArgs $args)
    {
        list($name, $type, $table, $hmvc, $dbGroup, $force, $style) = $this->getArgs($args);
        z::factory('Task\Artisan\Common')->creation($name, $type, $table, $hmvc, $dbGroup, $force, $style);
    }
    private function getArgs(\Zls_CliArgs $args, $type = '')
    {
        $name = $args->get('name');
        $type = $type ?: strtolower($args->get('type'));
        if (empty($name)) {
            Z::finish('name required , please use : -name <Name>');
        }
        if (empty($type)) {
            Z::finish('type required, please use : -type [controller,business,model,task,dao,bean]');
        }
        $force = $args->get('force');
        $style = $args->get('style');
        $table = $args->get('table');
        $dbGroup = $args->get('db');
        $hmvc = $args->get('hmvc');
        $argc = [$name, $type, $table, $hmvc, $dbGroup, $force, $style];
        return $argc;
    }
    public function model(\Zls_CliArgs $args)
    {
        list($name, $type, $table, $hmvc, $dbGroup, $force, $style) = $this->getArgs($args, 'model');
        z::factory('Task\Artisan\Common')->creation($name, $type, $table, $hmvc, $dbGroup, $force, $style);
    }
    public function task(\Zls_CliArgs $args)
    {
        list($name, $type, $table, $hmvc, $dbGroup, $force, $style) = $this->getArgs($args, 'task');
        z::factory('Task\Artisan\Common')->creation($name, $type, $table, $hmvc, $dbGroup, $force, $style);
    }
    public function business(\Zls_CliArgs $args)
    {
        list($name, $type, $table, $hmvc, $dbGroup, $force, $style) = $this->getArgs($args, 'business');
        z::factory('Task\Artisan\Common')->creation($name, $type, $table, $hmvc, $dbGroup, $force, $style);
    }
    public function bean(\Zls_CliArgs $args)
    {
        list($name, $type, $table, $hmvc, $dbGroup, $force, $style) = $this->getArgs($args, 'bean');
        z::factory('Task\Artisan\Common')->creation($name, $type, $table, $hmvc, $dbGroup, $force, $style);
    }
    public function dao(\Zls_CliArgs $args)
    {
        list($name, $type, $table, $hmvc, $dbGroup, $force, $style) = $this->getArgs($args, 'dao');
        z::factory('Task\Artisan\Common')->creation($name, $type, $table, $hmvc, $dbGroup, $force, $style);
    }
    public function controller(\Zls_CliArgs $args)
    {
        list($name, $type, $table, $hmvc, $dbGroup, $force, $style) = $this->getArgs($args, 'controller');
        z::factory('Task\Artisan\Common')->creation($name, $type, $table, $hmvc, $dbGroup, $force, $style);
    }
    public function help()
    {
        echo <<<EC
Quick Start :
php zls artisan start
    Options:
      -host  Listening IP
      -port  Listening Port
Code Factory:
php zls artisan create xxx
    Options:
      -name  FileName
      -type  Create type [controller,business,model,task,dao,bean]
      -table Database Table Name, -type = dao or bean
      -db    Database Config Name
      -env   Environment
      -force Overwrite old files
    create controller: php zls artisan create:controller -name controllerName
    create business: php zls artisan create:business -name businessName
    create task: php zls artisan create:task -name taskName
    create dao: php zls artisan create:dao -name Zls -table tableName
    ...
EC;
    }
}
