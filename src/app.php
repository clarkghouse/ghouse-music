<?php
require_once __DIR__.'/../vendor/autoload.php';

use Silex\Application;
use Silex\Provider\HttpCacheServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\SymfonyBridgesServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Silex\Provider\FormServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Silex\Provider\DoctrineServiceProvider;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use GHouse\Songkick\Provider\SongkickServiceProvider;


class ghouseApplication extends Application
{
    use Application\TwigTrait;
    use Application\SecurityTrait;
    use Application\FormTrait;
    use Application\UrlGeneratorTrait;
    use Application\SwiftmailerTrait;
    use Application\MonologTrait;
    use Application\TranslationTrait;
}


$app = new ghouseApplication();

require __DIR__.'/config.php';


// $app->register(new HttpCacheServiceProvider());
$app->register(new SessionServiceProvider());
$app->register(new ValidatorServiceProvider());
$app->register(new FormServiceProvider());
$app->register(new UrlGeneratorServiceProvider());
$app->register(new TranslationServiceProvider());
$app->register(new MonologServiceProvider(), array('monolog.name' => 'ghouse'));
$app->register(new TwigServiceProvider(), array('twig.path' => $app['twig.path']));
$app->register(new DoctrineServiceProvider());
$app->register(new SongkickServiceProvider());

// global middlewares
// require __DIR__.'/middlewares.php';


// mount controllers
$app->mount('/img', new GHouse\Controller\Provider\ImageControllerProvider());
$app->mount('/', new GHouse\Controller\Provider\MainControllerProvider());


return $app;