<?php

/**
 * @license MIT
 * @copyright 2017 Tim Gunter
 */

namespace Kaecyra\Jarvis\Core\Socket;

/**
 * Jarvis socket message
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package jarvis
 */
class SocketMessage {

    /**
     *
     * @var string
     */
    protected $method;

    /**
     *
     * @var array
     */
    protected $data;

    private function __construct() {

    }

    /**
     * Get message method
     *
     * @return string
     */
    public function getMethod() {
        return $this->method;
    }

    /**
     * Set message method
     *
     * @param string $method
     * @return \Alice\Server\Message
     */
    public function setMethod($method) {
        $this->method = $method;
        return $this;
    }

    /**
     * Get message data
     *
     * @return mixed
     */
    public function getData() {
        return $this->data;
    }

    /**
     * Set message data
     *
     * @param mixed $data
     * @return \Alice\Server\Message
     */
    public function setData($data) {
        $this->data = $data;
        return $this;
    }

    /**
     * Populate message data
     *
     * @param array $messageData
     */
    public function populate($messageData) {
        $this->method = val('method', $messageData);
        $this->data = val('data', $messageData);
    }

    /**
     * Parse a string message
     *
     * @param string $msg
     * @return Message
     */
    public static function parse($msg) {
        $messageData = json_decode($msg, true);
        if ($messageData === false) {
            throw new \Exception('Unable to decode incoming message');
        }

        $message = new SocketMessage();
        $message->populate($messageData);
        return $message;
    }

    /**
     * Create a new message
     *
     * @param string $method
     * @param array $data
     * @return Message
     */
    public static function compile($method, $data) {
        $message = new SocketMessage();
        $message->populate([
            'method' => $method,
            'data' => $data
        ]);
        return $message;
    }

    /**
     * Encode message as JSON
     *
     * @return string
     */
    public function __toString() {
        return json_encode([
            'method' => $this->method,
            'data' => $this->data
        ]);
    }

}