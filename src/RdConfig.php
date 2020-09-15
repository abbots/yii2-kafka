<?php


namespace common\extensions\kafka;


use yii\base\Behavior;

class RdConfig extends Behavior
{
    /*------------- 消费端配置 -------------*/
    /**
     * @var string $auto_offset_reset 当没有初始偏移量时，从哪里开始读取 ( smallest : 从最小开始 )
     * --------------------------------------------------------------------------------------------------
     * | none => topic各分区都存在已提交的offset时，从offset后开始消费；只要有一个分区不存在已提交的offset，则抛出异常 |
     * | latest => 当各分区下有已提交的offset时，从提交的offset开始消费；无提交的offset时，消费新产生的该分区下的数据  |
     * | smallest => 当各分区下有已提交的offset时，从提交的offset开始消费；无提交的offset时，从头开始消费           |
     * -------------------------------------------------------------------------------------------------
     */
    public $auto_offset_reset = 'smallest';

    // 消费超时时间
    public $consume_time_out = 120 * 1000;

    // 消息确认，-1 等待所有broker，1：等待当前broker，0：不确认
    public $request_required_acks = -1;
    // 是否自动提交offset
    public $enable_auto_commit = 1;
    // 自动提交offset的时间间隔
    public $auto_commit_interval_ms = 100;
}