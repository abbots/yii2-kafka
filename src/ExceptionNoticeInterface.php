<?php


namespace yii\kafka;

/**
 * 异常通知接口
 * Interface ExceptionNoticeInterface
 *
 * @package yii\kafka
 */
interface ExceptionNoticeInterface
{
    public function send($message);
}