<?php


namespace yii\kafka;


use RdKafka\Message;

interface ConsumerInterface
{
    /**
     * 消费统一处理方法
     * 所有消费业务处理类必须实现此方法
     * @param $message
     * @return mixed
     */
    public function execute(Message $message);
}