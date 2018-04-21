<?php
namespace Task\Artisan;
/**
 * Zls_Artisan_Mysql
 * @author        影浅-Seekwe
 * @email         seekwe@gmail.com
 * @updatetime    2017-5-31 12:11:36
 */
use z;
class Mysql
{
    private $type, $table, $dbGroup;
    public function creation($type, $table, $dbGroup)
    {
        if (empty($table)) {
            die('table name required, please use : -table <Table Name>');
        }else{
            $this->type = $type;
            $this->table = $table;
            $this->dbGroup = $dbGroup;
            $columns = $this->getTableFieldsInfo($table, $dbGroup);
            return $this->$type($columns, $table);
        }
    }
    private function getTableFieldsInfo($tableName, $db)
    {
        if (!is_object($db)) {
            $db = Z::db($db);
        }
        $type = strtolower($db->getDriverType());
        $info = [];
        if (\method_exists($this, $type)) {
            $info = $this->$type($tableName, $db);
        }
        return $info;
    }
    /**
     * @param                            $tableName
     * @param \Zls_Database_ActiveRecord $db
     * @return array
     */
    public function sqlsrv($tableName, $db)
    {
        $info = [];
        $result = $db->execute('SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME=\'' . $db->getTablePrefix() . $tableName . '\'')->rows();
        $primary = $db->execute('EXEC sp_pkeys @table_name=\'' . $db->getTablePrefix() . $tableName . '\'')->value('COLUMN_NAME');
        if ($result) {
            foreach ($result as $val) {
                $info[$val['COLUMN_NAME']] = [
                    'name'    => $val['COLUMN_NAME'],
                    'type'    => $val['DATA_TYPE'],
                    'comment' => $val['COLUMN_NAME'],//注释
                    'notnull' => $val['IS_NULLABLE'] == 'NO' ? 1 : 0,
                    'default' => $val['COLUMN_DEFAULT'],
                    'primary' => (strtolower($val['COLUMN_NAME']) === $primary),
                    'autoinc' => (strtolower($val['COLUMN_NAME']) === $primary),
                ];
            }
        }
        return $info;
    }
    public function afresh()
    {
        $type = $this->type;
        $columns = $this->getTableFieldsInfo($this->table, $this->dbGroup);
        $result['code'] = '    ' . $this->$type($columns, $this->table) . \PHP_EOL;
        $result['methods'] = [];
        $result['args'] = [];
        if ($type === 'dao') {
            $result['methods'] = [
                'getColumns',
                'getPrimaryKey',
                'getTable',
                'getBean',
            ];
        } else {
            foreach ($columns as $column) {
                $result['methods'][] = 'get' . z::strSnake2Camel($column['name']);
                $result['methods'][] = 'set' . z::strSnake2Camel($column['name']);
            }
        }
        return $result;
    }
    /**
     * @param $tableName
     * @param \Zls_Database_ActiveRecord $db
     * @return array
     */
    private function mysql($tableName, $db)
    {
        $info = [];
        $result = $db->execute('SHOW FULL COLUMNS FROM ' . $db->getTablePrefix() . $tableName)->rows();
        if ($result) {
            foreach ($result as $val) {
                $info[$val['Field']] = [
                    'name'    => $val['Field'],
                    'type'    => $val['Type'],
                    'comment' => $val['Comment'] ? $val['Comment'] : $val['Field'],
                    'notnull' => $val['Null'] == 'NO' ? 1 : 0,
                    'default' => $val['Default'],
                    'primary' => (strtolower($val['Key']) == 'pri'),
                    'autoinc' => (strtolower($val['Extra']) == 'auto_increment'),
                ];
            }
        }
        return $info;
    }
    private function dao($columns, $table)
    {
        $primaryKey = '';
        $_columns = [];
        foreach ($columns as $value) {
            if ($value['primary']) {
                $primaryKey = $value['name'];
            }
            $_columns[] = '\'' . $value['name'] . "'//" . $value['comment'] . PHP_EOL . '               ';
        }
        $columnsString = 'array(' . PHP_EOL . '              ' . implode(',', $_columns) . ')';
        $code = "public function getColumns() {\n        return {columns};\n    }\n\n    public function getPrimaryKey() {\n        return '{primaryKey}';\n    }\n\n    public function getTable() {\n        return '{table}';\n    }\n\n    public function getBean() {\n        return parent::getBean();\n    }\n";
        $code = str_replace(['{columns}', '{primaryKey}', '{table}'], [$columnsString, $primaryKey, $table], $code);
        return $code;
    }
    private function bean($columns)
    {
        $fields = [];
        $fieldTemplate = "    //{comment}\n    protected \${column0};";
        foreach ($columns as $value) {
            $column = str_replace(' ', '', ucwords(str_replace('_', ' ', $value['name'])));
            $column0 = $value['name'];
            $fields[] = str_replace(['{column0}', '{comment}'], [$column0, $value['comment']],
                $fieldTemplate
            );
        }
        $code = "\n{fields}\n\n";
        $code = str_replace(['{fields}'],
            [implode("\n\n", $fields)], $code
        );
        return $code;
    }
}
