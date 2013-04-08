<?php // image serving controllers (ghouse.co)
namespace GHouse\Controller\Provider;

use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use GHouse\Controller\Provider\Base\BaseControllerProvider as Base_Controller_Provider;

class ImageControllerProvider extends Base_Controller_Provider
{
	public function setControllers(Application $app, Array $mw)
	{
		$controller = $app['controllers_factory'];


		/* <!-- Start Controller Definitions -- */

		// Artist Images
		$controller->match('/artist/{artist_slug}.{file_ext}', function (Request $request, $artist_slug, $file_ext) use ($app) {

			$filePath = $app['media_dir'] . '/uploads/artist/' . $artist_slug . '/' . $artist_slug . '.' . $file_ext;

			if (!file_exists($filePath))
				$app->abort(404, 'No such image.');

			return $app->sendFile($filePath);


		})->method('GET');


		// Label Images
		$controller->match('/label/{label_slug}.{file_ext}', function (Request $request, $label_slug, $file_ext) use ($app) {

			$filePath = $app['media_dir'] . '/uploads/label/' . $label_slug . '.' . $file_ext;

			if (!file_exists($filePath))
				$app->abort(404, 'No such image.');

			return $app->sendFile($filePath);


		})->method('GET');


		// Release Images
		$controller->match('/artist/{artist_slug}/releases/{release_slug}.{file_ext}', function (Request $request, $release_slug, $file_ext, $artist_slug) use ($app) {

			$filePath = $app['media_dir'] . '/uploads/artist/' . $artist_slug  . '/releases/' . $release_slug . '.' . $file_ext;

			if (!file_exists($filePath))
				$app->abort(404, 'No such image.');

			return $app->sendFile($filePath);


		})->method('GET');

		/* -- End Controller Definitions //--> */


		return $controller;
	}

	public function setMiddlewares(Application $app)
	{

		$middleware = array();


		/* <!-- Start Middleware Definitions -- */


		/* -- End Middleware Definitions //--> */


		return $middleware;
	}
}