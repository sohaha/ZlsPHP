<?php
namespace Task\Artisan;
/**
 * Zls_Artisan_Common
 * @author        影浅-Seekwe
 * @email       seekwe@gmail.com
 * @updatetime    2017-2-27 16:52:51
 */
use z;
class Common
{
    private $hmvc;
    public function creation($name, $type, $table, $hmvc, $dbGroup, $force, $style = 'PSR4')
    {
        //$name = $args->get('name');
        //$type = strtolower($args->get('type'));
        //$force = $args->get('force');
        //$style = $args->get('style');
        //$table = $args->get('table');
        //$dbGroup = $args->get('db');
        $afresh = false;
        if (!in_array($style, ['PSR4', 'PSR0'])) {
            $style = 'PSR4';
        }
        $this->hmvc = $hmvc;
        $config = Z::config();
        $classesDir = $config->getPrimaryApplicationDir() . $config->getClassesDirName() . '/';
        $getHmvcModules = $config->getHmvcModules();
        if ($this->hmvc && $HmvcModules = Z::arrayGet($getHmvcModules, $this->hmvc)) {
            $classesDir = $config->getPrimaryApplicationDir() . $config->getHmvcDirName() . '/' . $HmvcModules . '/' . $config->getClassesDirName() . '/';
        }
        switch ($type) {
            case 'controller' :
                $info = [
                    'dir'         => $config->getControllerDirName(),
                    'parentClass' => 'Zls_Controller',
                    'method'      => "public function " . Z::config()->getMethodPrefix() . 'index()' . "\n    {\n\n    }",
                    'nameTip'     => 'Controller',
                ];
                break;
            case 'business'   :
                $info = [
                    'dir'         => $config->getBusinessDirName(),
                    'parentClass' => 'Zls_Business',
                    'method'      => "public function business()\n    {\n\n    }",
                    'nameTip'     => 'Business',
                ];
                break;
            case 'model'      :
                $info = [
                    'dir'         => $config->getModelDirName(),
                    'parentClass' => 'Zls_Model',
                    'method'      => "public function model()\n    {\n\n    }",
                    'nameTip'     => 'Model',
                ];
                break;
            case 'task':
                $info = [
                    'dir'         => $config->getTaskDirName(),
                    'parentClass' => 'Zls_Task',
                    'method'      => "public function execute(\\Zls_CliArgs \$args)\n    {\n\n    }",
                    'nameTip'     => 'Task',
                ];
                break;
            case 'dao':
                $afresh = true;
                $info = [
                    'dir'         => $config->getDaoDirName(),
                    'parentClass' => 'Zls_Dao',
                    'method'      => z::factory('Task\Artisan\Mysql', true)->creation($type, $table, $dbGroup),
                    'nameTip'     => 'Dao',
                ];
                break;
            case 'bean':
                //$afresh = true;
                $info = [
                    'dir'         => $config->getBeanDirName(),
                    'parentClass' => 'Zls_Bean',
                    'method'      => z::factory('Task\Artisan\Mysql', true)->creation($type, $table, $dbGroup),
                    'nameTip'     => 'Bean',
                ];
                break;
            default:
                Z::finish("Unknown type : {$type}\n Please use : -type [controller,business,model,task,dao,bean]");
        }
        $classname = $name;
        $typename = $info['dir'];
        $file = $classesDir . str_replace('_', '/', $typename . '_' . $classname) . '.php';
        $method = $info['method'];
        $parentClass = $info['parentClass'];
        $tip = $info['nameTip'];
        if (file_exists($file)) {
            if ($afresh) {
                $this->afreshFile($file, $typename, $classname, $tip, $type, $table, $dbGroup);
            } elseif ($force) {
                $this->writeFile($typename, $classname, $method, $parentClass, $file, $tip, $style);
            } else {
                echo "[ Error ]\n{$tip} [ {$classname} ] already exists , {$file}\nyou can use -force to force the file.\n";
            }
        } else {
            $this->writeFile($typename, $classname, $method, $parentClass, $file, $tip, $style);
        }
    }
    /**
     * @param $file
     * @param $typename
     * @param $classname
     * @param $type
     * @param $table
     * @param $dbGroup
     */
    private function afreshFile($file, $typename, $classname, $tip, $type, $table, $dbGroup)
    {
        $content = '';
        $typename = ($this->hmvc ? 'Hmvc_' . $typename : '' . $typename) . '_';
        z::includeOnce($file);
        $obj = z::factory($typename . $classname);
        try {
            $ref = new \ReflectionClass($obj);
            $factory = z::factory('Task\Artisan\Mysql', true)->afresh();
            if ($factory) {
                $content = file($file);
                $place = 0;
                foreach ($factory['methods'] as $v) {
                    try {
                        if ($method = $ref->getMethod($v)) {
                            $start = $method->getStartLine() - 1;
                            $end = $method->getEndLine() - 1;
                            do {
                                if (!$place) {
                                    $place = true;
                                    $content[$start] = $factory['code'];
                                } else {
                                    unset($content[$start]);
                                }
                                $start++;
                            } while ($start <= $end);
                        }
                    } catch (\ReflectionException $e) {
                    }
                }
                if (!$place) {
                    $endLine = $ref->getEndLine() - 1;
                    $_content = \trim(z::arrayGet($content, $endLine));
                    if (!$_content) {
                        $endLine = $endLine - 1;
                        $_content = \trim(z::arrayGet($content, $endLine));
                    }
                    $content[$endLine] = $factory['code'] . \PHP_EOL . $_content;
                }
                $content = implode($content);
            }
            if ($content && file_put_contents($file, $content)) {
                echo "[ Successfull ]\n{$tip} [ {$classname} ] created successfully \n{$file}\n";
            }
        } catch (\ReflectionException $e) {
            echo $e->getMessage();
        }
    }
    private function writeFile($typename, $classname, $method, $parentClass, $file, $tip, $style)
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $content = $this->$style($typename, $classname, $parentClass, $method);
        if (file_put_contents($file, $content)) {
            echo "[ Successfull ]\n{$tip} [ {$classname} ] created successfully \n{$file}\n";
        }
    }
    private function PSR0($typename, $classname, $parentClass, $method)
    {
        $classname = $this->hmvc ? 'Hmvc_' : '';
        return vsprintf("<?php\n\nclass {$classname}%s_%s extends %s \n{\n    %s\n}", [$typename, $classname, $parentClass, $method]);
    }
    private function PSR4($typename, $classname, $parentClass, $method)
    {
        $classname = str_replace('\\', '/', $classname);
        $classname = str_replace('_', '/', $classname);
        $classnameArg = explode('/', $classname);
        $classname = array_pop($classnameArg);
        $classnameArg = implode('\\', $classnameArg);
        if ($classnameArg) {
            $typename = $typename . '\\' . $classnameArg;
        }
        $namespace = $this->hmvc ? 'namespace Hmvc\\' : 'namespace ';
        return vsprintf("<?php\n\n{$namespace}%s;\nuse Z;\n\nclass %s extends \%s \n{\n    %s\n}", [$typename, $classname, $parentClass, $method]);
    }
}
