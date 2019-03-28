<?php

namespace Mix\Console;

use Mix\Core\Component\AbstractComponent;

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
            return;
        }
        // 打印到屏幕
        println($errors['message']);
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
        $message = <<<EOL
{message}
[type] {type} [code] {code}
[file] {file} [line] {line}
[trace] {trace}
EOL;
        // 写入
        $errorType = \Mix\Core\Error::getType($errors['code']);
        switch ($errorType) {
            case 'error':
                \Mix::$app->log->error($message, $errors);
                break;
            case 'warning':
                \Mix::$app->log->warning($message, $errors);
                break;
            case 'notice':
                \Mix::$app->log->notice($message, $errors);
                break;
        }
    }

}
