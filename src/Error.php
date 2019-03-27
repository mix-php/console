<?php

namespace Mix\Console;

use Mix\Console\CommandLine\Color;
use Mix\Core\Component\AbstractComponent;
use Mix\Helper\PhpHelper;

/**
 * Class Error
 * @package Mix\Console
 * @author liu,jian <coder.keda@gmail.com>
 */
class Error extends AbstractComponent
{

    /**
     * 错误级别
     * @var int
     */
    public $level = E_ALL;

    /**
     * 异常处理
     * @param $e
     */
    public function handleException($e)
    {
        // 错误参数定义
        $errors = [
            'code'    => $e->getCode(),
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'type'    => get_class($e),
            'trace'   => $e->getTraceAsString(),
        ];
        // 日志处理
        if (!($e instanceof \Mix\Exception\NotFoundException)) {
            self::log($errors);
        }
        // 打印到屏幕
        self::print($errors);
    }

    /**
     * 写入日志
     * @param $errors
     */
    protected static function log($errors)
    {
        if (!\Mix::$app->isRegistered('log')) {
            return;
        }
        // 构造消息
        $message = "{$errors['message']}" . PHP_EOL;
        $message .= "[type] {$errors['type']} [code] {$errors['code']}" . PHP_EOL;
        $message .= "[file] {$errors['file']} [line] {$errors['line']}" . PHP_EOL;
        $message .= "[trace] {$errors['trace']}" . PHP_EOL;
        $message .= '$_SERVER' . substr(print_r($_SERVER, true), 5, -1);
        // 写入
        $errorType = \Mix\Core\Error::getType($errors['code']);
        switch ($errorType) {
            case 'error':
                \Mix::$app->log->error($message);
                break;
            case 'warning':
                \Mix::$app->log->warning($message);
                break;
            case 'notice':
                \Mix::$app->log->notice($message);
                break;
        }
    }

    /**
     * 打印到屏幕
     * @param $errors
     */
    protected static function print($errors)
    {
        // 只输出消息
        if ($errors['type'] == 'Mix\Exception\NotFoundException' || !\Mix::$app->appDebug) {
            println($errors['message']);
            return;
        }
        // 无格式打印
        if (PhpHelper::isWin()) {
            self::plainPrint($errors);
            return;
        }
        // 带颜色打印
        self::colorPrint($errors);
    }

    /**
     * 无格式打印
     * @param $errors
     */
    protected static function plainPrint($errors)
    {
        println($errors['message']);
        println("{$errors['type']} code {$errors['code']}");
        echo $errors['file'];
        echo ' line ';
        println($errors['line']);
        println(str_replace("\n", PHP_EOL, $errors['trace']));
    }

    /**
     * 带颜色打印
     * @param $errors
     */
    protected static function colorPrint($errors)
    {
        Color::new(Color::BG_RED)->println($errors['message']);
        Color::new()->println("{$errors['type']} code {$errors['code']}");
        Color::new(Color::BG_RED)->print($errors['file']);
        Color::new()->print(' line ');
        Color::new(Color::BG_RED)->println($errors['line']);
        Color::new()->println(str_replace("\n", PHP_EOL, $errors['trace']));
    }

}
