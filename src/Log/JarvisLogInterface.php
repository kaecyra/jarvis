<?php

/**
 * @license MIT
 * @copyright 2017 Tim Gunter
 */

namespace Kaecyra\Jarvis\Core\Log;

/**
 * Jarvis log interface
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package jarvis
 */
interface JarvisLogInterface {

    public function jLog(string $level, string $message, array $context);

    public function setLogTag($logTag);
    
    public function setDefaultLogCallback();

}