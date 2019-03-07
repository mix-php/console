<?php

namespace Mix\Console;

use Mix\Console\CommandLine\Arguments;
use Mix\Console\CommandLine\Flag;

/**
 * App类
 * @author LIUJIAN <coder.keda@gmail.com>
 */
class Application extends \Mix\Core\Application
{

    // 应用名称
    public $appName = 'app-console';

    // 应用版本
    public $appVersion = '0.0.0';

    // 命令命名空间
    public $commandNamespace = '';

    // 命令
    public $commands = [];

    // 执行功能 (CLI模式)
    public function run()
    {
        if (PHP_SAPI != 'cli') {
            throw new \RuntimeException('Please run in CLI mode.');
        }
        Flag::initialize();
        if (Arguments::subCommand() == '' && Arguments::command() == '') {
            if (Flag::bool(['h', 'help'], false)) {
                $this->help();
                return;
            }
            if (Flag::bool(['v', 'version'], false)) {
                $this->version();
                return;
            }
            $options = Flag::options();
            if (empty($options)) {
                $this->help();
                return;
            }
            $keys = array_keys($options);
            $flag = array_shift($keys);
            throw new \Mix\Exception\NotFoundException("flag provided but not defined: '{$flag}', see '-h/--help'.");
        }
        if ((Arguments::command() !== '' || Arguments::subCommand() !== '') && Flag::bool(['h', 'help'], false)) {
            $this->commandHelp();
            return;
        }
        $command = trim(implode(' ', [Arguments::command(), Arguments::subCommand()]));
        $this->runAction($command);
    }

    // 帮助
    protected function help()
    {
        $script = Arguments::script();
        println("Usage: {$script} [OPTIONS] COMMAND [SUBCOMMAND] [arg...]");
        $this->printOptions();
        $this->printCommands();
        println('');
        println("Run '{$script} COMMAND [SUBCOMMAND] --help' for more information on a command.");
        println('');
        println("Developed with Mix PHP framework. (mixphp.cn)");
    }

    // 命令帮助
    protected function commandHelp()
    {
        $script  = Arguments::script();
        $command = trim(implode(' ', [Arguments::command(), Arguments::subCommand()]));
        println("Usage: {$script} {$command} [arg...]");
        $this->printCommandOptions();
        println("Developed with Mix PHP framework. (mixphp.cn)");
    }

    // 版本
    protected function version()
    {
        $appName          = \Mix::$app->appName;
        $appVersion       = \Mix::$app->appVersion;
        $frameworkVersion = \Mix::$version;
        println("{$appName} version {$appVersion}, framework version {$frameworkVersion}");
    }

    // 打印选项列表
    protected function printOptions()
    {
        println('');
        println('Options:');
        println("  -h/--help\tPrint usage.");
        println("  -v/--version\tPrint version information.");
    }

    // 打印命令列表
    protected function printCommands()
    {
        println('');
        println('Commands:');
        foreach ($this->commands as $key => $item) {
            $command     = $key;
            $subCommand  = '';
            $description = $item['description'] ?? '';
            if (strpos($key, ' ') !== false) {
                list($command, $subCommand) = explode(' ', $key);
            }
            if ($subCommand == '') {
                println("    {$command}\t{$description}");
            } else {
                println("    {$command} {$subCommand}\t{$description}");
            }
        }
    }

    // 打印命令选项列表
    protected function printCommandOptions()
    {
        $command = trim(implode(' ', [Arguments::command(), Arguments::subCommand()]));
        if (!isset($this->commands[$command]['options'])) {
            return;
        }
        $options = $this->commands[$command]['options'];
        println('');
        println('Options:');
        foreach ($options as $option => $description) {
            println("  {$option}\t{$description}");
        }
        println('');
    }

    // 执行功能并返回
    public function runAction($command)
    {
        if (!isset($this->commands[$command])) {
            throw new \Mix\Exception\NotFoundException("'{$command}' is not command, see '-h/--help'.");
        }
        // 实例化控制器
        $shortClass = $this->commands[$command];
        if (is_array($shortClass)) {
            $shortClass = array_shift($shortClass);
        }
        $shortClass    = str_replace('/', "\\", $shortClass);
        $commandDir    = \Mix\Helper\FileSystemHelper::dirname($shortClass);
        $commandDir    = $commandDir == '.' ? '' : "$commandDir\\";
        $commandName   = \Mix\Helper\FileSystemHelper::basename($shortClass);
        $commandClass  = "{$this->commandNamespace}\\{$commandDir}{$commandName}Command";
        $commandAction = 'main';
        // 判断类是否存在
        if (!class_exists($commandClass)) {
            throw new \Mix\Exception\CommandException("'{$commandClass}' class not found.");
        }
        $commandInstance = new $commandClass();
        // 判断方法是否存在
        if (!method_exists($commandInstance, $commandAction)) {
            throw new \Mix\Exception\CommandException("'{$commandClass}::main' method not found.");
        }
        // 执行方法
        return call_user_func([$commandInstance, $commandAction]);
    }

    // 获取组件
    public function __get($name)
    {
        // 从容器返回组件
        return $this->container->get($name);
    }

}
