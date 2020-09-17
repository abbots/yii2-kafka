# Yii2集成kafka扩展

Requirements
------------

1、 Yii2框架

2、 RdKafka扩展，扩展安装如下

```
1、安装librdkafka

git clone https://github.com/edenhill/librdkafka.git
cd librdkafka
./configure
make && make install

2、安装php-rdkafka扩展

git clone https://github.com/arnaud-lb/php-rdkafka.git
cd php-rdkafka
phpize
./configure --with-php-config=/usr/local/php7.0/bin/php-config
make && make install

3、php.ini配置

extension = rdkafka.so
```

Installation
------------
The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
composer require abbots/yii2-kafka
```

DIRECTORY STRUCTURE
-------------------
```
src
    Kafka
    ConsumerInterface
    Consumer
    RdConfig
    Logger
```

Config
------
```
components => [
    'example' => [
        'class' => yii\kafka\Consumer::class,
        'group_id' => 'group_example',       //消费组
        'topics'   => ['topic_example'],     //消费主题

        /*---------------RdKafka配置------------------------*/
        'enable_auto_commit' => 0,          //是否自动提交[1打开、0关闭]，默认关闭
        'auto_offset_reset' => 'smallest',  //数据下标模式
        /*---------------RdKafka配置------------------------*/

        //消费回调类配置
        'callback' => [
            'class' => \console\service\login\LoginLogService::class,
            'businessMethod' => 'business',
        ],

        //broker节点配置
        //kafkaBrokerList:192.168.0.36:9092,192.168.0.37:9092,192.168.0.38:9092
        'broker_list' => function(){
            return Yii::$app->params['kafkaBrokerList'];
        },
    ],
]
```

Example
-------

业务逻辑类封装(必须实现yii\kafka\ConsumerInterface接口)

```php
<?php
namespace console\services;

use Yii;
use RdKafka\Message;
use yii\base\Exception;
use yii\kafka\ConsumerInterface;
use common\extensions\helpers\StringHelper;

/**
 * kafka公共逻辑基础类
 * Class Service
 *
 * @package console\base
 */
class KafkaService implements ConsumerInterface
{
    /**
     * 业务方法配置（适配多业务处理）
     * 必须在消费启动前配置
     *
     * @var string $businessMethod
     */
    public $businessMethod='business';

    /** @var array 消费信息（主题、分区...） */
    public $consumerInfo = [];

    /**
     * 队列消费方法统一封装
     *
     * @param Message $message
     * @return mixed
     */
    public function execute(Message $message)
    {
        //TODO message数据处理
        $data = $this->getData($message);

        //调用实际业务处理
        $this->{$this->businessMethod}($data);
    }

    /**
     * 数据处理
     *
     * @param Message $message
     */
    public function getData(Message $message)
    {
        // TODO message数据处理

        //return $data;
    }

    /**
     * 实际业务处理方法
     * @param Message $message
     */
    public function business(Message $message)
    {
        // TODO
    }
}
```

控制器基础类封装
```php
<?php

namespace console\base;

use Yii;
use yii\kafka\Consumer;
use yii\base\Exception;

/**
 * 控制台kafka消费控制器基础类
 * Class KafkaController
 *
 * @package common\console\base
 */
class KafkaController extends \yii\console\Controller
{
    /**
     * 消费者对象
     *
     * @var Consumer $kafkaConsumer
     */
    protected $kafkaConsumer = null;

    /**
     * @var array $consumer 消费组件配置
     *
     * 格式：[控制器方法名=>kafka消费组件名]
     */
    protected $consumer = [];

    public function beforeAction($action)
    {
        $result = parent::beforeAction($action);

        $this->setKafkaConsumer();

        return $result;
    }

    /**
     * @throws Exception
     * 消费对象创建
     */
    protected function setKafkaConsumer()
    {
        $action = $this->action->id;

        if (!isset($this->consumer[$action])) {
            return false;
        }

        $this->kafkaConsumer = Yii::$app->{$this->consumer[$action]};
        if (!($this->kafkaConsumer instanceof Consumer)) {
            throw new Exception('消费组件配置错误');
        }
        //设置客户端ID
        $params = Yii::$app->request->params;
        if(isset($params[1]) && is_numeric($params[1])) {
            $this->kafkaConsumer->client_id = $params[1];
        }
    }

    /**
     * 启动消费队列
     */
    protected function kafkaStart()
    {
        $this->kafkaConsumer->start();
    }
}
```

控制器
```php
<?php
namespace console\modules\example\controllers;

use Yii;
use console\base\KafkaController;

class ExampleController extends KafkaController
{
    public $consumer = ['index' => 'example'];
    
    public function actionIndex()
    {
        $this->kafkaStart();
    }
}
```
