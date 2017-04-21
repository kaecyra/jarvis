<?php

/**
 * @license MIT
 * @copyright 2017 Tim Gunter
 */

namespace Kaecyra\Jarvis\Core\Server;

use Kaecyra\Jarvis\Core\Client\ClientManager;
use Kaecyra\Jarvis\Core\Client\ClientInterface;

use Kaecyra\AppCommon\Log\LoggerBoilerTrait;
use Kaecyra\Jarvis\Core\Log\JarvisLogTrait;
use Kaecyra\Jarvis\Core\Log\JarvisLogInterface;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

use Psr\Container\ContainerInterface;

use React\EventLoop\LoopInterface;
use Ratchet\App as RatchetApp;

/**
 * Client Manager
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package jarvis
 */
class SocketApp extends RatchetApp implements LoggerAwareInterface, JarvisLogInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;
    use JarvisLogTrait;

    /**
     * Start socket app
     *
     * @param ClientManager $clientManager
     * @param LoopInterface $loop
     * @param string $httpHost
     * @param int $port
     * @param string $address
     */
    public function __construct(
        LoopInterface $loop,
        $httpHost = 'localhost', $port = 8080, $address = '127.0.0.1'
    ) {
        $this->log(LogLevel::NOTICE, "Creating socket listener: {$httpHost}:{$port} ({$address})", [
            'host'      => $httpHost,
            'port'      => $port,
            'address'   => $address
        ]);

        parent::__construct($httpHost, $port, $address, $loop);
    }

    /**
     * Add routes to socket app
     *
     * @param ContainerInterface $container
     * @param array $routes
     */
    public function addRoutes(ContainerInterface $container, array $routes = []) {
        $this->log(LogLevel::NOTICE, "  adding routes");
        foreach ($routes as $route) {
            $server = $route['server'] ?? 'DefaultServer';
            $this->log(LogLevel::INFO, "    route: {$route['path']} -> {$server}");
            $this->route($route['path'], $container->get($server), $route['origins'] ?? ['*']);
        }
    }

}