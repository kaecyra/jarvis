<?php

/**
 * @license MIT
 * @copyright 2017 Tim Gunter
 */

namespace Kaecyra\Jarvis\Core\Log;

/**
 * Jarvis log trait
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package jarvis
 */
trait JarvisLogTrait {

    /**
     * Log tag
     * @var string|Callable
     */
    protected $logTag = null;

    /**
     * Jarvis tagged log message
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function jLog(string $level, string $message, array $context) {
        $logtag = $this->getLogTag();
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        $this->log($level, "[{$logtag}] ".$message, $context);
    }

    /**
     * Get log tag
     *
     * @return string
     */
    protected function getLogTag() {
        return is_callable($this->logTag) ? $this->logtag() : $this->logTag;
    }

    /**
     * Set log tag
     *
     * @param string|Callable $logTag
     */
    protected function setLogTag($logTag) {
        $this->logTag = $logTag;
    }

    /**
     * Set default logtag callback
     *
     *
     */
    public function setDefaultLogCallback() {
        $this->setLogTag(function(){
            return (new \ReflectionClass($this))->getShortName();
        });
    }

}