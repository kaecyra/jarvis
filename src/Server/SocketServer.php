<?php

/**
 * @license MIT
 * @copyright 2017 Tim Gunter
 */

namespace Kaecyra\Jarvis\Core\Server;

use Kaecyra\Jarvis\Core\Log\JarvisLogTrait;
use Kaecyra\Jarvis\Core\Log\JarvisLogInterface;

use Kaecyra\AppCommon\Log\LoggerBoilerTrait;
use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

/**
 * Socket server
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package jarvis
 */
class SocketServer implements MessageComponentInterface, EventAwareInterface, LoggerAwareInterface, JarvisLogInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;
    use JarvisLogTrait;
    use EventAwareTrait;

    /**
     * Array mapping of ID > SocketClient objects
     * @var \SplObjectStorage
     */
    protected $clientManager;

    public function __construct(
        ClientManager $clientManager
    ) {
        $this->clientManager = $clientManager;
    }

    /**
     * Get a SocketClient instance
     *
     * @param ConnectionInterface $connection
     * @return SocketClient|false
     */
    public function getClient(ConnectionInterface $connection) {
        return $this->clientManager->getClient($connection);
    }

    /**
     * Unregister a client
     *
     * @param ClientInterface $client
     */
    public function unregisterClient(ClientInterface $client) {
        $connection = $client->getConnection();
        if ($this->clients->contains($connection)) {
            $this->clients->detach($connection);
        }
       unset($client);
    }

    /**
     * Handle new connections from socket clients
     *
     * @param ConnectionInterface $connection
     */
    public function onOpen(ConnectionInterface $connection) {
        $this->jLog(LogLevel::NOTICE, "Socket connected");
        $this->jLog(LogLevel::INFO, " address: {$connection->remoteAddress}");

        $this->clientManager->registerClient($connection);
    }

    /**
     * Handle messages from socket clients
     *
     * @param ConnectionInterface $connection
     * @param string $msg
     * @return type
     */
    public function onMessage(ConnectionInterface $connection, $msg) {
        $client = $this->getClient($connection);
        if (!$client) {
            $this->jLog(LogLevel::ERROR, "message from unknown socket client, discarded");
            $this->jLog(LogLevel::INFO, $msg);
            return;
        }

        try {
            $message = SocketMessage::parse($msg);
            $client->onMessage($message);
        } catch (\Exception $ex) {
            $this->jLog(LogLevel::ERROR, "Socket message handling error: ".$ex->getMessage());
            return false;
        }
    }

    /**
     * Handle connections being closed by socket clients
     *
     * @param ConnectionInterface $connection
     */
    public function onClose(ConnectionInterface $connection) {
        $this->jLog(LogLevel::NOTICE, "Socket disconnected");
        $this->jLog(LogLevel::INFO, " address: {$connection->remoteAddress}");

        // Gracefully shut down client
        $client = $this->getClient($connection);
        if ($client) {
            $client->onClose();
        }

        // Discard connection
        $this->clientManager->unregisterClient($client);
    }

    /**
     * Handle errors from socket clients
     *
     * @param ConnectionInterface $connection
     * @param \Exception $ex
     */
    public function onError(ConnectionInterface $connection, \Exception $ex) {
        $this->jLog(LogLevel::ERROR, "socket client error: ".$ex->getMessage());
        $connection->close();
    }

}