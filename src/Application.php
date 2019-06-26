<?php

namespace Mix\Console;

use Mix\Console\CommandLine\Argument;
use Mix\Console\CommandLine\Flag;
use Mix\Concurrent\Coroutine;

/**
 * Class Application
 * @package Mix\Console
 * @author liu,jian <coder.keda@gmail.com>
 */
class Application extends \Mix\Core\Application
{

    /**
     * 命令命名空间
     * @var string
     */
    public $commandNamespace = '';

    /**
     * 命令
     * @var array
     */
    public $commands = [];

    /**
     * 是否为单命令
     * @var bool
     */
    public $isSingle;

    /**
     * Application constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        // 保存引用
        \Mix::$app = $this;
        // 错误注册
        \Mix\Core\Error::register();
        // 是否为单命令
        $commands       = $this->commands;
        $frist          = array_shift($commands);
        $this->isSingle = is_string($frist);
    }

    /**
     * 执行功能 (CLI模式)
     * @return mixed
     */
    public function run()
    {
        if (PHP_SAPI != 'cli') {
            throw new \RuntimeException('Please run in CLI mode.');
        }
        Flag::init();
        if (Argument::command() == '') {
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
            } elseif ($this->isSingle) {
                // 单命令执行
                return $this->runAction(Argument::command());
            }
            $keys   = array_keys($options);
            $flag   = array_shift($keys);
            $script = Argument::script();
            throw new \Mix\Exception\NotFoundException("flag provided but not defined: '{$flag}', see '{$script} --help'."); // 这里只是全局flag效验
        }
        if (Argument::command() !== '' && Flag::bool(['h', 'help'], false)) {
            $this->commandHelp();
            return;
        }
        // 非单命令执行
        return $this->runAction(Argument::command());
    }

    /**
     * 帮助
     */
    protected function help()
    {
        $script = Argument::script();
        println("Usage: {$script} [OPTIONS] COMMAND [opt...]");
        $this->printOptions();
        if (!$this->isSingle) {
            $this->printCommands();
        } else {
            $this->printCommandOptions();
        }
        println('');
        println("Run '{$script} COMMAND --help' for more information on a command.");
        println('');
        println("Developed with Mix PHP framework. (mixphp.cn)");
    }

    /**
     * 命令帮助
     */
    protected function commandHelp()
    {
        $script  = Argument::script();
        $command = Argument::command();
        println("Usage: {$script} {$command} [opt...]");
        $this->printCommandOptions();
        println('');
        println("Developed with Mix PHP framework. (mixphp.cn)");
    }

    /**
     * 版本
     */
    protected function version()
    {
        $appName          = \Mix::$app->appName;
        $appVersion       = \Mix::$app->appVersion;
        $frameworkVersion = \Mix::$version;
        println("{$appName} version {$appVersion}, framework version {$frameworkVersion}");
    }

    /**
     * 打印选项列表
     */
    protected function printOptions()
    {
        $tabs = "\t";
        println('');
        println('Global Options:');
        println("  -h, --help{$tabs}Print usage");
        println("  -v, --version{$tabs}Print version information");
    }

    /**
     * 打印命令列表
     */
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
                println("  {$command}\t{$description}");
            } else {
                println("  {$command} {$subCommand}\t{$description}");
            }
        }
    }

    /**
     * 打印命令选项列表
     */
    protected function printCommandOptions()
    {
        $command = Argument::command();
        $options = [];
        if (!$this->isSingle) {
            if (isset($this->commands[$command]['options'])) {
                $options = $this->commands[$command]['options'];
            }
        } else {
            if (isset($this->commands['options'])) {
                $options = $this->commands['options'];
            }
        }
        if (empty($options)) {
            return;
        }
        println('');
        println('Command Options:');
        foreach ($options as $option) {
            $names = array_shift($option);
            if (is_string($names)) {
                $names = [$names];
            }
            $flags = [];
            foreach ($names as $name) {
                if (strlen($name) == 1) {
                    $flags[] = "-{$name}";
                } else {
                    $flags[] = "--{$name}";
                }
            }
            $flag        = implode(', ', $flags);
            $description = $option['description'] ?? '';
            println("  {$flag}\t{$description}");
        }
    }

    /**
     * 执行功能并返回
     * @param $command
     * @return mixed
     */
    public function runAction($command)
    {
        // 提取类前缀
        $shortClass = '';
        if (!$this->isSingle) {
            if (!isset($this->commands[$command])) {
                $script = Argument::script();
                throw new \Mix\Exception\NotFoundException("'{$command}' is not command, see '{$script} --help'.");
            }
            $shortClass = $this->commands[$command];
            if (is_array($shortClass)) {
                $shortClass = array_shift($shortClass);
            }
        } else {
            $tmp        = $this->commands;
            $shortClass = array_shift($tmp);
        }
        // 生成类名，方法名
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
        // 实例化
        $commandInstance = new $commandClass();
        // 判断方法是否存在
        if (!method_exists($commandInstance, $commandAction)) {
            throw new \Mix\Exception\CommandException("'{$commandClass}::main' method not found.");
        }
        // 命令行选项效验
        $this->validateOptions($command);
        // 执行方法
        return call_user_func([$commandInstance, $commandAction]);
    }

    /**
     * 命令行选项效验
     * @param $command
     */
    protected function validateOptions($command)
    {
        $options = [];
        if (!$this->isSingle) {
            $options = $this->commands[$command]['options'] ?? [];
        } else {
            $options = $this->commands['options'] ?? [];
        }
        $regflags = [];
        foreach ($options as $option) {
            $names = array_shift($option);
            if (is_string($names)) {
                $names = [$names];
            }
            foreach ($names as $name) {
                if (strlen($name) == 1) {
                    $regflags[] = "-{$name}";
                } else {
                    $regflags[] = "--{$name}";
                }
            }
        }
        foreach (array_keys(Flag::options()) as $flag) {
            if (!in_array($flag, $regflags)) {
                $script  = Argument::script();
                $command = Argument::command();
                $command = $command ? " {$command}" : $command;
                throw new \Mix\Exception\NotFoundException("flag provided but not defined: '{$flag}', see '{$script}{$command} --help'.");
            }
        }
    }

}
