<?php

declare(strict_types=1);

use App\Auth;
use App\Config;
use App\Contracts\AuthInterface;
use App\Contracts\RequestValidatorFactoryInterface;
use App\Contracts\SessionInterface;
use App\Contracts\UserProviderServiceInterface;
use App\DataObjects\SessionConfig;
use App\Enum\AppEnvironment;
use App\Enum\SameSite;
use App\RequestValidators\RequestValidatorFactory;
use App\Services\EpisodeService;
use App\Services\ShowService;
use App\Services\TvMazeService;
use App\Services\UpdateService;
use App\Services\UserProviderService;
use App\Session;
use App\TwigDefaultSort;
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
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Csrf\Guard;
use Slim\Factory\AppFactory;
use Slim\Interfaces\RouteParserInterface;
use Slim\Views\Twig;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\JsonManifestVersionStrategy;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookup;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupCollection;
use Symfony\WebpackEncoreBundle\Asset\TagRenderer;
use Symfony\WebpackEncoreBundle\Twig\EntryFilesTwigExtension;

use function DI\create;

return [
    App::class => function (ContainerInterface $container) {
        AppFactory::setContainer($container);
        $router = require CONFIG_PATH . '/routes/web.php';
        $addMiddlewares = require CONFIG_PATH . '/middleware.php';
        $app = AppFactory::create();
        $router($app);
        $addMiddlewares($app);
        return $app;
    },
    Config::class  => create(Config::class)->constructor(require CONFIG_PATH . '/app.php'),
    Twig::class                   => function (Config $config, ContainerInterface $container) {
        $twig = Twig::create(VIEW_PATH, [
            'cache'       => STORAGE_PATH . '/cache/templates',
            'auto_reload' => AppEnvironment::isDevelopment($config->get('app_environment')),
        ]);


        $twig->addExtension(new TwigDefaultSort());
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
        ) use ($config) {
            if ($retries >= $config->get('client.retries', 3)) {
                return false;
            }
            if ($e instanceof ConnectException) {
                // echo "Unable to connect to " .
                //     $request->getUri() .
                //     ". Retrying (" . ($retries + 1) .
                //     "/" .
                //     $config->get('client.retries', 3) .
                //     ")...\n";
                return true;
            }
            return false;
        };

        $retryMiddleware = Middleware::retry($retryDecider, function (int $retries) {
            return 1000 * $retries;
        });
        $stack->push($retryMiddleware);

        return new Client([
            'timeout'  => $config->get('client.timeout', 3.0),
            'handler' => $stack
        ]);
    },
    UpdateService::class => function (ContainerInterface $container) {
        $showService = $container->get(ShowService::class);
        $episodeService = $container->get(EpisodeService::class);
        $tvMazeService = $container->get(TvMazeService::class);
        return new UpdateService($showService, $episodeService, $tvMazeService, $container->get(EntityManager::class));
    },

    'webpack_encore.packages'     => fn () => new Packages(
        new Package(new JsonManifestVersionStrategy(BUILD_PATH . '/manifest.json'))
    ),
    '_default' => fn () => new EntrypointLookup(BUILD_PATH . '/entrypoints.json'),
    'webpack_encore.tag_renderer' => fn (ContainerInterface $container) => new TagRenderer(
        new EntrypointLookupCollection($container),
        $container->get('webpack_encore.packages')
    ),
    ResponseFactoryInterface::class => fn (App $app) => $app->getResponseFactory(),
    AuthInterface::class => fn (ContainerInterface $container) => $container->get(Auth::class),
    UserProviderServiceInterface::class =>
        fn (ContainerInterface $container) => $container->get(UserProviderService::class),
    SessionInterface::class => fn (Config $config) => new Session(
        new SessionConfig(
            $config->get('session.name', ''),
            $config->get('session.secure', true),
            $config->get('session.httponly', true),
            $config->get('session.flash_name', 'flash'),
            SameSite::from($config->get('session.samesite', 'lax'))
        )
    ),
    RequestValidatorFactoryInterface::class =>
        fn (ContainerInterface $container) => $container->get(RequestValidatorFactory::class),
    'csrf' => fn (ResponseFactoryInterface $responseFactory) => new Guard($responseFactory, persistentTokenMode:true),
    MailerInterface::class => function (Config $config) {
        $transport = Transport::fromDsn($config->get('mailer.dsn'));
        return new Mailer($transport);
    },
    BodyRendererInterface::class => function (Twig $twig) {
        return new BodyRenderer($twig->getEnvironment());
    },
    RouteParserInterface::class => fn (App $app) => $app->getRouteCollector()->getRouteParser()
];
