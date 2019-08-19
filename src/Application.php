<?php

namespace Mix\Console;

use Mix\Bean\ApplicationContext;
use Mix\Bean\BeanInjector;
use Mix\Console\CommandLine\Argument;
use Mix\Console\CommandLine\Flag;
use Mix\Concurrent\Coroutine;
use Mix\Console\Exception\ConfigException;
use Mix\Console\Exception\NotFoundException;

/**
 * Class Application
 * @package Mix\Console
 * @author liu,jian <coder.keda@gmail.com>
 */
class Application
{

    /**
     * 应用名称
     * @var string
     */
    public $appName = 'app-console';

    /**
     * 应用版本
     * @var string
     */
    public $appVersion = '0.0.0';

    /**
     * 应用调试
     * @var bool
     */
    public $appDebug = true;

    /**
     * 基础路径
     * @var string
     */
    public $basePath = '';

    /**
     * 开启默认协程
     * @var bool
     */
    public $enableCoroutine = true;

    /**
     * 协程设置
     * @var array
     */
    public $coroutineSetting = [
        'max_coroutine' => 300000,
        'hook_flags'    => SWOOLE_HOOK_ALL,
    ];

    /**
     * Context
     * @var ApplicationContext
     */
    public $context;

    /**
     * 命令
     * @var array
     */
    public $commands = [];

    /**
     * 依赖配置
     * @var array
     */
    public $beans = [];

    /**
     * 是否为单命令
     * @var bool
     */
    protected $isSingleCommand;

    /**
     * Application constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        // 注入
        BeanInjector::inject($this, $config);
        // 实例化ApplicationContext
        $this->context = new ApplicationContext($this->beans);
        // 保存引用
        \Mix::$app = $this;
        // 错误注册
        /** @var Error $error */
        $error = $this->context->get('error');
        $error->register();
        // 是否为单命令
        $commands              = $this->commands;
        $frist                 = array_shift($commands);
        $this->isSingleCommand = is_string($frist);
    }

    /**
     * 获取配置
     * @param $name
     * @return mixed
     */
    public function getConfig($name)
    {
        $message   = "Configuration does not exist: {$name}.";
        $fragments = explode('.', $name);
        // 判断一级配置是否存在
        $first = array_shift($fragments);
        if (!isset($this->$first)) {
            throw new ConfigException($message);
        }
        // 判断其他配置是否存在
        $current = $this->$first;
        foreach ($fragments as $key) {
            if (!isset($current[$key])) {
                throw new ConfigException($message);
            }
            $current = $current[$key];
        }
        return $current;
    }

    /**
     * 执行功能 (CLI模式)
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
            } elseif ($this->isSingleCommand) {
                // 单命令执行
                $this->callCommand(Argument::command());
                return;
            }
            $keys   = array_keys($options);
            $flag   = array_shift($keys);
            $script = Argument::script();
            throw new NotFoundException("flag provided but not defined: '{$flag}', see '{$script} --help'."); // 这里只是全局flag效验
        }
        if (Argument::command() !== '' && Flag::bool(['h', 'help'], false)) {
            $this->commandHelp();
            return;
        }
        // 非单命令执行
        $this->callCommand(Argument::command());
    }

    /**
     * 帮助
     */
    protected function help()
    {
        $script = Argument::script();
        println("Usage: {$script}" . ($this->isSingleCommand ? '' : ' [OPTIONS] COMMAND') . " [opt...]");
        $this->printOptions();
        if (!$this->isSingleCommand) {
            $this->printCommands();
        } else {
            $this->printCommandOptions();
        }
        println('');
        println("Run '{$script}" . ($this->isSingleCommand ? '' : ' COMMAND') . " --help' for more information on a command.");
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
        if (!$this->isSingleCommand) {
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
     * 调用命令
     * @param $command
     */
    public function callCommand($command)
    {
        // 生成类名，方法名
        $class = '';
        if (!$this->isSingleCommand) {
            if (!isset($this->commands[$command])) {
                $script = Argument::script();
                throw new NotFoundException("'{$command}' is not command, see '{$script} --help'.");
            }
            $class = $this->commands[$command];
            if (is_array($class)) {
                $class = array_shift($class);
            }
        } else {
            $tmp   = $this->commands;
            $class = array_shift($tmp);
        }
        $action = 'main';
        // 命令行选项效验
        $this->validateOptions($command);
        // 执行功能
        if ($this->enableCoroutine) { // 协程执行
            $scheduler = new \Swoole\Coroutine\Scheduler;
            $scheduler->set($this->coroutineSetting);
            $scheduler->add(function () use ($class, $action) {
                xgo([$this, 'runAction'], $class, $action);
            });
            $scheduler->start();
            return;
        }
        $this->runAction($class, $action); // 普通执行
    }

    /**
     * 执行功能
     * @param $class
     * @param $action
     */
    public function runAction($class, $action)
    {
        // 判断类是否存在
        if (!class_exists($class)) {
            throw new NotFoundException("'{$class}' class not found.");
        }
        // 实例化
        $instance = new $class();
        // 判断方法是否存在
        if (!method_exists($instance, $action)) {
            throw new NotFoundException("'{$class}::main' method not found.");
        }
        // 执行方法
        call_user_func([$instance, $action]);
    }

    /**
     * 命令行选项效验
     * @param $command
     */
    protected function validateOptions($command)
    {
        $options = [];
        if (!$this->isSingleCommand) {
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
                throw new NotFoundException("flag provided but not defined: '{$flag}', see '{$script}{$command} --help'.");
            }
        }
    }

}
