<?php

/**
 * @license MIT
 * @copyright 2017 Tim Gunter
 */

namespace Kaecyra\Jarvis\Core;

use Garden\Container\Container;
use Garden\Container\Reference;
use Garden\Daemon\Daemon;
use Garden\Daemon\AppInterface;
use Garden\Daemon\ErrorHandler;
use Garden\Cli\Cli;
use Garden\Cli\Args;

use Kaecyra\AppCommon\AbstractConfig;
use Kaecyra\AppCommon\ConfigInterface;
use Kaecyra\AppCommon\ConfigCollection;
use Kaecyra\AppCommon\Event\EventAwareInterface;
use Kaecyra\AppCommon\Event\EventAwareTrait;
use Kaecyra\AppCommon\Log\LoggerBoilerTrait;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;

/**
 * JARVIS home automation central core.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package jarvis
 * @version 1.0
 */
class Core implements AppInterface, LoggerAwareInterface, EventAwareInterface {

    use LoggerAwareTrait;
    use LoggerBoilerTrait;
    use EventAwareTrait;

    /**
     * Dependency Injection Container
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Configuration
     * @var ConfigInterface
     */
    protected $config;

    /**
     * Addon manager
     * @var AddonManager
     */
    protected $addons;

    /**
     * Command Line Interface
     * @var Cli
     */
    protected $cli;

    /**
     * Bootstrap
     *
     * @param ContainerInterface $container
     */
    public static function bootstrap(ContainerInterface $container) {

        // Reflect on ourselves for the version
        $matched = preg_match('`@version ([\w\d\.-]+)$`im', file_get_contents(__FILE__), $matches);
        if (!$matched) {
            echo "Unable to read version\n";
            exit;
        }
        $version = $matches[1];
        define('APP_VERSION', $version);

        define('APP', 'queue-worker');
        define('PATH_ROOT', getcwd());
        date_default_timezone_set('UTC');

        // Check environment

        if (PHP_VERSION_ID < 70000) {
            die(APP." requires PHP 7.0 or greater.");
        }

        if (posix_getuid() != 0) {
            echo "Must be root.\n";
            exit;
        }

        // Report and track all errors

        error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);
        ini_set('display_errors', 0);
        ini_set('track_errors', 1);

        define('PATH_CONFIG', PATH_ROOT.'/conf');

        // Prepare Dependency Injection

        $container
            ->rule(ContainerInterface::class)
            ->setClass(Container::class)
            ->addAlias(Container::class)

            ->setInstance(Container::class, $container)

            ->defaultRule()
            ->setShared(true)

            ->rule(ConfigInterface::class)
            ->setClass(ConfigCollection::class)
            ->addAlias(AbstractConfig::class)
            ->addCall('addFile', [paths(PATH_ROOT, 'conf/config.json'), false])
            ->addCall('addFolder', [paths(PATH_ROOT, 'conf/conf.d'), 'json'])

            ->rule(LoggerAwareInterface::class)
            ->addCall('setLogger')

            ->rule(EventAwareInterface::class)
            ->addCall('setEventManager')

            ->rule(AddonManager::class)
            ->setConstructorArgs([new Reference([ConfigInterface::class, 'addons.scan'])])

            ->rule(Daemon::class)
            ->setConstructorArgs([
                [
                    'appversion'        => APP_VERSION,
                    'appdir'            => PATH_ROOT,
                    'appdescription'    => 'Vanilla Product Queue',
                    'appnamespace'      => 'Vanilla\\QueueWorker',
                    'appname'           => 'QueueWorker',
                    'authorname'        => 'Tim Gunter',
                    'authoremail'       => 'tim@vanillaforums.com'
                ],
                new Reference([ConfigInterface::class, 'daemon'])
            ])
            ->addCall('configure', [new Reference([AbstractConfig::class, "daemon"])]);

        // Set up loggers

        $logger = new \Kaecyra\AppCommon\Log\AggregateLogger;
        $logLevel = $container->get(AbstractConfig::class)->get('log.level');
        $loggers = $container->get(AbstractConfig::class)->get('log.loggers');
        foreach ($loggers as $logConfig) {
            $loggerClass = "Kaecyra\\AppCommon\\Log\\".ucfirst($logConfig['destination']).'Logger';
            if ($container->has($loggerClass)) {
                $subLogger = $container->getArgs($loggerClass, [PATH_ROOT, $logConfig]);
                $logger->addLogger($subLogger, $logConfig['level'] ?? $logLevel, $logConfig['key'] ?? null);
            }
        }

        $logger->containersableLogger('persist');
        $container->setInstance(LoggerInterface::class, $logger);
    }

    /**
     * Construct app
     *
     * @param Container $container
     * @param Cli $cli
     * @param AbstractConfig $config
     * @param AddonManager $addons
     * @param ErrorHandler $errorHandler
     */
    public function __construct(
        Container $container,
        Cli $cli,
        AbstractConfig $config,
        AddonManager $addons,
        ErrorHandler $errorHandler
    ) {
        $this->container = $container;
        $this->cli = $cli;
        $this->config = $config;
        $this->addons = $addons;

        // Set worker allocation oversight strategy
        $strategyClass = $this->config->get('queue.oversight.strategy');
        $this->container->rule(AllocationStrategyInterface::class);
        $this->container->setClass($strategyClass);

        // Set job parser
        $parserClass = $this->config->get('queue.message.parser');
        $this->container->rule(ParserInterface::class);
        $this->container->setClass($parserClass);

        // Add logging error handler
        $logHandler = $this->container->get(LogErrorHandler::class);
        $errorHandler->addHandler([$logHandler, 'error'], E_ALL);

        // Add fatal error handler
        $fatalHandler = $this->container->get(FatalErrorHandler::class);
        $errorHandler->addHandler([$fatalHandler, 'error']);
    }

    /**
     * Check environment for app runtime compatibility
     *
     * Provide any custom CLI configuration, and check validity of configuration.
     */
    public function preflight() {
        $this->log(LogLevel::INFO, " preflight checking");
    }

    /**
     * Initialize app and disable console logging
     *
     * This occurs in the main daemon process, prior to worker forking. No
     * connections should be established here, since this method's actions are
     * pre-worker forking, and will be shared to child processes.
     *
     * @param Args $args
     */
    public function initialize(Args $args) {
        $this->log(LogLevel::INFO, " initializing");

        // Remove echo logger

        $this->log(LogLevel::INFO, " transitioning logger");
        $this->getLogger()->removeLogger('echo', false);
        $this->getLogger()->enableLogger('persist');

        // Start enabled addons

        $this->addons->startAddons($this->config->get('addons.active'));

        // Allow attaching to initialize

        $this->fire('initialize');
    }

    /**
     * Run payload
     *
     * This method is the main program scope for the payload. Forking has already
     * been handled at this point, so this scope is confined to a single process.
     *
     * Returning from this function ends the process.
     *
     * @param array $workerConfig
     */
    public function run($workerConfig) {

        // RUN

    }

}