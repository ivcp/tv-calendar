<?php

declare(strict_types=1);

use App\Config;
use App\Enum\AppEnvironment;
use App\Services\EpisodeService;
use App\Services\ShowService;
use App\Services\TvMazeService;
use App\Services\UpdateService;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Views\Twig;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\JsonManifestVersionStrategy;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookup;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupCollection;
use Symfony\WebpackEncoreBundle\Asset\TagRenderer;
use Symfony\WebpackEncoreBundle\Twig\EntryFilesTwigExtension;

use function DI\create;

return [
    Config::class  => create(Config::class)->constructor(require CONFIG_PATH . '/app.php'),
    Twig::class                   => function (Config $config, ContainerInterface $container) {
        $twig = Twig::create(VIEW_PATH, [
            'cache'       => STORAGE_PATH . '/cache/templates',
            'auto_reload' => AppEnvironment::isDevelopment($config->get('app_environment')),
        ]);

        $twig->addExtension(new EntryFilesTwigExtension($container));
        $twig->addExtension(new AssetExtension($container->get('webpack_encore.packages')));
        return $twig;
    },
    EntityManager::class          => function (Config $config) {
        $ormConfig = ORMSetup::createAttributeMetadataConfiguration(
            $config->get('doctrine.entity_dir'),
            $config->get('doctrine.dev_mode')
        );

        return new EntityManager(
            DriverManager::getConnection($config->get('doctrine.connection'), $ormConfig),
            $ormConfig
        );
    },
    Client::class => function (Config $config) {

        $stack = new HandlerStack();
        $stack->setHandler(new CurlHandler());
        $retryDecider = function (
            int $retries,
            RequestInterface $request,
            ?ResponseInterface $response,
            ?\RuntimeException $e
        ) {
            if ($retries >= 3) {
                return false;
            }
            if ($e instanceof ConnectException) {
                echo "Unable to connect to " . $request->getUri() . ". Retrying (" . ($retries + 1) . "/" . 3 . ")...\n";
                return true;
            }
            return false;
        };

        $retryMiddleware = Middleware::retry($retryDecider, function (int $retries) {
            return 1000 * $retries;
        });
        $stack->push($retryMiddleware);

        return new Client([
            'timeout'  => 3.0,
            'handler' => $stack
        ]);
    },
    UpdateService::class => function (EntityManager $entityManager, Client $client) {
        $showService = new ShowService($entityManager);
        $episodeService = new EpisodeService($entityManager);
        $tvMazeService = new TvMazeService($client);
        return new UpdateService($showService, $episodeService, $tvMazeService);
    },

    'webpack_encore.packages'     => fn() => new Packages(
        new Package(new JsonManifestVersionStrategy(BUILD_PATH . '/manifest.json'))
    ),
    '_default' => fn() => new EntrypointLookup(BUILD_PATH . '/entrypoints.json'),
    'webpack_encore.tag_renderer' => fn(ContainerInterface $container) => new TagRenderer(
        new EntrypointLookupCollection($container),
        $container->get('webpack_encore.packages')
    ),
];
