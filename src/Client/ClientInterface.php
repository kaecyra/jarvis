<?php

/**
 * @license MIT
 * @copyright 2017 Tim Gunter
 */

namespace Kaecyra\Jarvis\Core\Client;

use Kaecyra\Jarvis\Core\Socket\SocketMessage;

/**
 * Socket client interface
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package jarvis
 */
interface ClientInterface {

    public function onMessage(SocketMessage $message);
    public function onClose();

}