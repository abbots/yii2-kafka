<?php
/**
 * Created by PhpStorm.
 * User: wxmai7
 * Date: 2019/3/1
 * Time: 11:35
 */

namespace common\extensions\kafka;

use Yii;
use RdKafka\Message;
use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Exception;
use ReflectionClass;

/**
 * Class Consumer
 *
 * @package common\extensions\kafka
 *
 * @property string $group_id                消费组
 * @property mixed  $client_id               客户端ID，默认为消费进程ID
 * @property array  $topics                  主题
 * @property string $auto_offset_reset       topic各分区都存在已提交的offset时，从offset后开始消费；只要有一个分区不存在已提交的offset，则抛出异常
 * @property int    $consume_time_out        消费超时时间
 * @property int    $request_time_out_ms     客户端请求超时时间
 * @property int    $request_required_acks   消息确认，-1 等待所有broker，1：等待当前broker，0：不确认
 * @property int    $enable_auto_commit      是否自动提交offset
 * @property int    $auto_commit_interval_ms 自动提交offset的时间间隔
 * @property Logger $logHandle               日志处理句柄
 *
 */
class Consumer extends Kafka
{
    /**
     * 数据消费处理回调
     *
     * @var \Closure callable
     */
    public $callback = null;

    /**
     * 日志类配置
     *
     * @var array $log
     */
    public $log;

    /**
     * 消费主题
     *
     * @var array $topics
     */
    public $topics = [];

    /**
     * 消费组
     *
     * @var string $group_id
     */
    public $group_id;

    /**
     * 客户端ID
     *
     * @var int $client_id
     */
    public $client_id;

    /**
     * 分区个数
     *
     * @var int $partition
     */
    public $partition;

    /**
     * 通过行为注入kafka消费配置属性
     *
     * @return array|string[]
     */
    public function behaviors()
    {
        return [
            RdConfig::class,
        ];
    }

    /**
     * 初始化消费配置
     */
    public function init()
    {
        $this->setClientId();
    }

    /**
     * @return object
     */
    public function getLogHandle()
    {
        if (!empty($this->log)) {
            return Yii::createObject($this->logHandle);
        } else {
            return Yii::createObject([
                'class'       => Logger::class,
                'basePath'    => Yii::getAlias('@runtime') . "/kafka/consumer/{$this->group_id}/",
                'dataLogFile' => "[date]/{$this->client_id}.log",
            ]);
        }
    }

    /**
     * 设置客户端ID
     * 默认使用进程号作为客户端ID
     *
     * @param int|bool $clientId
     */
    protected function setClientId($clientId = false)
    {
        if ($clientId === false) {
            $clientId = posix_getpid();
        }

        $this->client_id = $clientId;
    }

    /**
     * 获取节点配置
     * @return mixed
     */
    protected function getBrobers()
    {
        if ($this->broker_list instanceof \Closure) {
            $brokers = call_user_func($this->broker_list);
        } else {
            $brokers = $this->broker_list;
        }

        return $brokers;
    }

    /**
     * 开始消费
     *
     * @throws Exception
     */
    public function start()
    {
        $this->chkConsumerConf();

        //设置消费处理类
        /** @var ConsumerInterface $object */
        $object = Yii::createObject($this->callback);
        if (!($object instanceof ConsumerInterface)) {
            throw new Exception('ConsumerInterface::execute must implements');
        }

        $conf = new Conf();
        $conf->set('metadata.broker.list', $this->getBrobers());
        $conf->set('group.id', $this->group_id);
        $conf->set('client.id', $this->client_id);
        $conf->setRebalanceCb([$this, 'rebalanceCb']);
        $conf->set('auto.offset.reset', $this->auto_offset_reset);
        // 在interval.ms的时间内自动提交确认(默认开启)，建议不要启动, 1是启动，0是未启动
        $conf->set('enable.auto.commit', $this->enable_auto_commit);
        // $conf->set('auto.commit.interval.ms', $this->auto_commit_interval_ms);

        $consumer = new KafkaConsumer($conf);
        $consumer->subscribe($this->topics);

        // 写日志
        $this->logHandle->debug("Consume Process[$this->client_id] Started!");

        while (true) {
            $message = $consumer->consume($this->consume_time_out);

            if ($this->chkMessage($message)) {
                continue;
            }

            //记录消费日志
            $this->messageLog($message);

            try {
                $object->execute($message);
                $consumer->commit();
            } catch (\Throwable $e) {
                //捕获一切错误异常
                $this->logHandle->error($e->getMessage());
            }
        }
    }

    /**
     * 当有新的消费进程加入或者退出消费组时，kafka 会自动重新分配分区给消费者进程，这里注册了一个回调函数，当分区被重新分配时触发
     *
     * @param KafkaConsumer          $kafka
     * @param                        $err
     * @param array|null             $partitions
     * @throws Exception
     */
    public function rebalanceCb(KafkaConsumer $kafka, $err, array $partitions = null)
    {
        switch ($err) {
            case RD_KAFKA_RESP_ERR__ASSIGN_PARTITIONS:
                // 消费进程分配分区时
                $str = count($partitions) > 0 ? '成功' : '失败';
                $this->logHandle->debug("Assign:消费进程分配分区 {$str}");
                $kafka->assign($partitions);
                break;

            case RD_KAFKA_RESP_ERR__REVOKE_PARTITIONS:
                // 消费进程退出分区时
                $this->logHandle->debug("Revoke:消费进程退出分区");
                $kafka->assign(null);
                break;

            default:
                // 错误
                $this->logHandle->debug("Error:消费进程分配分区错误，信息：{$err}");
                throw new Exception($err);
        }
    }

    /**
     * @param Message $message
     * @return bool|string
     * @throws Exception
     */
    protected static function chkMessage(Message $message)
    {
        switch ($message->err) {
            case RD_KAFKA_RESP_ERR_NO_ERROR:
                return false;
            case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                return "没有更多消息，请等待";
            case RD_KAFKA_RESP_ERR__TIMED_OUT:
                return "请求超时";
            default:
                throw new Exception($message->errstr(), $message->err);
        }
    }

    /**
     * @throws Exception
     */
    private function chkConsumerConf()
    {
        if (!$this->group_id) {
            throw new Exception("消费者配置消费组 group_id 为 空", 0);
        }
        if (!$this->client_id) {
            throw new Exception("消费者配置客户端 client_id 为 空", 0);
        }
    }

    /**
     * 消费返回记录日志
     *
     * @param Message $message
     * @param bool    $error 数据格式错误
     * @return bool
     */
    protected function messageLog(Message $message, $error = false)
    {
        $errorMsg = $message->err;
        if ($message->err !== 0) {
            $errorMsg = "[$message->err] " . $message->errstr();
        }

        $content    = json_decode($message->payload, true);
        $logContent = "Topic：{$message->topic_name} , Partition：{$message->partition} , Offset：{$message->offset}, Error：{$errorMsg} , Data：{$content['message']}";

        if ($errorMsg == '-185') {
            return true;
        }

        if ($error || $errorMsg) {
            $this->logHandle->error("消费返回记录错误：" . $logContent);
        } else {
            $this->logHandle->info($logContent);
        }
    }
}