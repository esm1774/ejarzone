<?php

class Logger {
    private $logFile;
    private $logLevel;
    private static $instance = null;

    // مستويات السجلات
    const ERROR = 'ERROR';
    const WARNING = 'WARNING';
    const INFO = 'INFO';
    const DEBUG = 'DEBUG';

    private function __construct() {
        // إنشاء مجلد logs إذا لم يكن موجوداً
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        // تحديد ملف السجل اليومي
        $this->logFile = $logDir . '/app_' . date('Y-m-d') . '.log';
        
        // تعيين مستوى السجل الافتراضي
        $this->logLevel = self::INFO;
    }

    // الحصول على نسخة وحيدة من الكلاس
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // تعيين مستوى السجل
    public function setLogLevel($level) {
        $this->logLevel = $level;
    }

    // كتابة سجل
    private function writeLog($level, $message, array $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($backtrace[1]) ? basename($backtrace[1]['file']) . ':' . $backtrace[1]['line'] : 'unknown';
        
        // تنسيق الرسالة
        $logMessage = sprintf(
            "[%s] [%s] [%s] %s %s\n",
            $timestamp,
            $level,
            $caller,
            $this->interpolate($message, $context),
            !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
        );

        // كتابة السجل إلى الملف
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    // استبدال المتغيرات في الرسالة
    private function interpolate($message, array $context = []) {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        return strtr($message, $replace);
    }

    // دوال السجل العامة
    public function error($message, array $context = []) {
        $this->writeLog(self::ERROR, $message, $context);
    }

    public function warning($message, array $context = []) {
        $this->writeLog(self::WARNING, $message, $context);
    }

    public function info($message, array $context = []) {
        $this->writeLog(self::INFO, $message, $context);
    }

    public function debug($message, array $context = []) {
        $this->writeLog(self::DEBUG, $message, $context);
    }

    // منع نسخ الكائن
    private function __clone() {}

    // منع إعادة إنشاء الكائن من خلال unserialization
    public function __wakeup() {}
}
