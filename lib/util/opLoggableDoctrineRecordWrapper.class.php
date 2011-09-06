<?php

// License: MIT License
// author: Kousuke Ebihara <ebihara@php.net>

if (!defined('DEBUG_BACKTRACE_IGNORE_ARGS')) {
    define(DEBUG_BACKTRACE_IGNORE_ARGS, false);
}

class opLoggableDoctrineRecordWrapper extends opDoctrineRecord
{
    protected $record, $traces, $readCount = 0, $writeCount = 0, $unknownMethodCount = 0;

    public function __construct($record)
    {
        $this->record = $record;

        // NOTE: we recommended use PHP 5.3.6 + for DEBUG_BACKTRACE_IGNORE_ARGS options
        // (see: http://php.net/manual/en/function.debug-backtrace.php)
        $this->traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        // parse all propeties in opDoctrineRecord and assign values from $this->record
        $refObj = new ReflectionObject($record);
        $refClass = new ReflectionClass('opDoctrineRecord');
        foreach ($refClass->getProperties() as $refProp) {
            $name = $refProp->getName();
            if (!$refObj->hasProperty($name)) {
                continue;
            }

            $refProp->setAccessible(true);

            if (!$refProp->isStatic()) {
                $this->$name = $this->record->$name;
            }
        }
    }

    public function __get($name)
    {
        $this->readCount++;

        return $this->record->$name;
    }

    public function __set($name, $value)
    {
        $this->writeCount++;

        $this->record->$name = $value;
    }

    public function __call($name, $arguments = array())
    {
        if (0 === strpos($name, 'set')) {
            $this->writeCount++;
        } elseif (0 === strpos($name, 'get')) {
            $this->readCount++;
        } else {
            $this->unknownMethodCount++;
        }

        return call_user_func_array(array($this->record, $name), $arguments);
    }

    public function getLogString()
    {
        $traceString = '';
        foreach ($this->traces as $trace) {
            $traceString .= @$trace['file'].':'.@$trace['line'].PHP_EOL;
        }

        return sprintf('---------------------------------------'.PHP_EOL
            .'[%s] (%s) Write:%d, Read:%d, Unknown: %d'.PHP_EOL
            .'Trace: %s'.PHP_EOL, date('Y-m-d H:i:s'), get_class($this->record), $this->writeCount, $this->readCount, $this->unknownMethodCount, $traceString);
    }

    public function __destruct()
    {
        // NOTE: tune this testing
        if ($this->writeCount) {
            return null;
        }

        if (2 >= $this->readCount) {
            $path = '/tmp/doctrine_access_log';
            file_put_contents($path, $this->getLogString(), FILE_APPEND);
            chmod($path, 0777);
        }
    }
}
