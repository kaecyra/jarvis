<?php

/**
 * @license MIT
 * @copyright 2017 Tim Gunter
 */

namespace Kaecyra\Jarvis\Core\Client;

use Kaecyra\Jarvis\Core\Socket\SocketMessage;

use Kaecyra\Jarvis\Core\Log\JarvisLogTrait;
use Kaecyra\Jarvis\Core\Log\JarvisLogInterface;

use Kaecyra\AppCommon\Log\LoggerBoilerTrait;
use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

use Ratchet\ConnectionInterface;

/**
 * Client
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package jarvis
 */
class Client implements LoggerAwareInterface, EventAwareInterface, JarvisLogInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;
    use JarvisLogTrait;
    use EventAwareTrait;

    /**
     * Ratchet connection
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * Array of known hooks
     * @var array
     */
    protected $bindings;

    /**
     * Unique Client ID
     * @var string
     */
    protected $uid = null;

    /**
     * Client name
     * @var string
     */
    protected $name;

    /**
     * Client ID
     * @var string
     */
    protected $id;

    /**
     * Client type
     * @var string
     */
    protected $type;

    /**
     * Create new client
     *
     * @param ConnectionInterface $connection
     */
    public function __construct(ConnectionInterface $connection) {
        $this->uid = uniqid('client-');
        $this->id = $this->uid;
        $this->name = 'unknown client';
        $this->connection = $connection;
        $this->hooks = [];

        $this->setLogTag(function(){
            return "client: {$this->id}";
        });
    }

    /**
     * Register socket client
     *
     * @param string $name
     * @param string $id
     */
    public function registerClientID(string $name, string $id) {
        $this->name = $name;
        $this->id = $id;
    }

    /**
     * Handle incoming message from client
     *
     * @param SocketMessage $message
     */
    public function onMessage(SocketMessage $message) {
        // Route to message handler
        $call = 'message_'.$message->getMethod();

        if (is_callable([$this, $call])) {
            $this->$call($message);
        } else {
            $this->jLog(LogLevel::INFO, "received message: ".$message->getMethod());
            $this->jLog(LogLevel::INFO, sprintf("  could not handle message: unknown type '{%s}'", $message->getMethod()));
        }
    }

    /**
     * Shutdown client
     *
     * This method is called when the client disconnects from the server. Here we
     * need to unbind any binds that were registered by this client.
     */
    public function onClose() {
        $bindings = $this->getBindings();

        // Remove bindings for this client
        foreach ($bindings as $event => $signature) {
            $this->getEventManager()->unbind($event, $signature);
        }
    }

    /**
     * Send a message to the client
     *
     * @param string $method
     * @param mixed $data
     */
    public function sendMessage(string $method, $data = null) {
        $message = SocketMessage::compile($method, $data);
        $this->connection->send($message);
    }

    /**
     * Send error
     *
     * Send an error message to the client and optionally disconnect.
     *
     * @param string $reason optional.
     * @param boolean $fatal optional. disconnect. default true.
     */
    public function sendError($reason = null, $fatal = true) {
        if ($reason) {
            $this->jLog(LogLevel::INFO, "client error: {$reason}");
            $this->sendMessage('error', [
                'reason' => $reason
            ]);
        } else {
            $this->jLog(LogLevel::INFO, "client error");
        }

        if ($fatal) {
            $this->connection->close();
        }
    }

    /**
     * Register a binding
     *
     * @param string $event
     * @param \Callable $callback
     */
    public function hook(string $event, \Callable $callback) {
        $signature = $this->bind($event, $callback);
        $this->registerBinding($event, $signature);
    }

    /**
     * Register a client binding
     *
     * @param string $event
     * @param string $signature
     */
    public function registerBinding(string $event, string $signature) {
        $this->jLog(LogLevel::INFO, "  registered hook for '{$event}' -> {$signature}");
        $this->bindings[$event] = $signature;
    }

    /**
     * Get list of events bound by this client
     *
     * @return array
     */
    public function getBindings() {
        return $this->bindings;
    }

}