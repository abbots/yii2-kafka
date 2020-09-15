<?php
/**
 * Created by PhpStorm.
 * Author: Ltj
 * Date: 2019/4/24
 * Time: 16:16
 */

namespace common\extensions\kafka;

use Yii;
use Psr\Log\LogLevel;
use yii\base\BaseObject;

class Logger extends BaseObject
{
    //日志分类
    public $categories = 'kafka';
    //日志文件夹路径
    public $basePath;
    //数据日志文件名([date]动态日期格式)
    public $dataLogFile;

    public function init()
    {
        if(empty($this->basePath)) {
            $this->basePath = Yii::getAlias('@runtime')."/kafka";
        }
    }

    /**
     * 日志文件路径设置
     * @param string $level
     */
    public function logFile($level=LogLevel::INFO)
    {
        if($level == LogLevel::INFO) {
            //数据日志动态增加日期文件夹
            $this->dataLogFile = str_replace("[date]", date('Ymd'), $this->dataLogFile);
            $logFile = $this->dataLogFile;
        }else{
            $logFile = $level.".log";
        }

        $fullName = str_replace("/", DIRECTORY_SEPARATOR,$this->basePath.$logFile);

        if(isset(Yii::$app->log->targets[$this->categories])) {
            Yii::$app->log->targets[$this->categories]->logFile = $fullName;
        }
    }

    public function info($message)
    {
        $this->logFile(LogLevel::INFO);
        Yii::info($message, $this->categories);
    }

    public function debug($message)
    {
        $this->logFile(LogLevel::DEBUG);
        Yii::getLogger()->log($message, \yii\log\Logger::LEVEL_TRACE, $this->categories);
    }

    public function error($message)
    {
        $this->logFile(LogLevel::ERROR);
        Yii::error($message, $this->categories);
    }
}