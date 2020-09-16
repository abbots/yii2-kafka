<?php
/**
 * Created by PhpStorm.
 * User: wxmai7
 * Date: 2019/3/28
 * Time: 14:57
 */

namespace yii\kafka;

use yii\base\Component;

class Kafka extends Component
{
    //Kafka 节点配置
    public $broker_list;
}