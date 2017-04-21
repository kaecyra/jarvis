<?php

/**
 * @license MIT
 * @copyright 2017 Tim Gunter
 */

namespace Kaecyra\Jarvis\Core\Client;

use Kaecyra\Jarvis\Core\Log\JarvisLogTrait;
use Kaecyra\Jarvis\Core\Log\JarvisLogInterface;

use Kaecyra\AppCommon\Log\LoggerBoilerTrait;
use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

use Psr\Container\ContainerInterface;

use Ratchet\ConnectionInterface;

/**
 * Abstract client
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package jarvis
 */
class ClientManager implements EventAwareInterface, LoggerAwareInterface, JarvisLogInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;
    use JarvisLogTrait;
    use EventAwareTrait;

    /**
     * Array mapping of ID > SocketClient objects
     * @var \SplObjectStorage
     */
    protected $clients;

    /**
     * Client ID associations
     * @var array
     */
    protected $lookup;

    public function __construct(
        ContainerInterface $container
    ) {
        $this->container = $container;
        $this->clients = new \SplObjectStorage;
    }

    /**
     * Learn about authentication methods
     *
     * @param array $methods
     */
    public function addAuthMethods(array $methods = []) {
        foreach ($methods as $method) {

        }
    }

    /**
     * Get a client
     *
     * @param ConnectionInterface $connection
     */
    public function registerClient(ConnectionInterface $connection) {
        $client = $this->container->getArgs(Client::class, [$connection]);
        $this->clients->attach($connection, $client);
        return $client;
    }

    /**
     * Get a SocketClient instance
     *
     * @param ConnectionInterface $connection
     * @return SocketClient|false
     */
    public function getClient(ConnectionInterface $connection) {
        if ($this->clients->contains($connection)) {
            return $this->clients[$connection];
        }
        return false;
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
     * Garbage-collect client info links
     *
     * Some clients will disconnect. Their links need to be cleaned up.
     */
    public function cleanup() {

    }

}