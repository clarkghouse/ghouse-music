<?php // main site controllers (ghouse.co)
namespace GHouse\Controller\Provider;

use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use GHouse\Controller\Provider\Base\BaseControllerProvider as Base_Controller_Provider;

class MainControllerProvider extends Base_Controller_Provider
{
	public function setControllers(Application $app, Array $mw)
	{
		$controller = $app['controllers_factory'];


		/* <!-- Start Controller Definitions -- */

		// Index page
		$controller->match('/', function (Request $request) use ($app) {

			return $app->render('front/index.html.twig',
				array('page_title' => 'Music Label(s) + Booking Agency', 'home' => true, 'page_script' => 'home'));

		})
		->method('GET|POST')
		->before($mw['get_spotlight_features'])
		->before($mw['get_labels'])
		->before($mw['get_artists']);


		// AJAX release portion
		$controller->match('/ajax/{artist_slug}/releases/{release_slug}', function (Request $request) use ($app) {
			if (!$request->isXmlHttpRequest())
				return $app->abort(404, 'Wrong request method.');

			return $app->render('partials/release.html.twig', array('featured_release' => $app['release']));

		})
		->method('POST')
		->before($mw['get_artist'])
		->before($mw['get_release']);

		// Artist page
		$controller->match('/{artist_slug}', function (Request $request) use ($app) {
			
			return $app->render('front/artist.html.twig',
				array('page_script' => 'artist', 'page_title' => $app['artist']['name'] . ' (' . $app['artist']['location'] . ')', 'featured_release' => $app['artist']['releases'][0]));

		})
		->method('GET')
		->before($mw['get_artist']);


		// Artist release page
		$controller->match('/{artist_slug}/releases/{release_slug}', function (Request $request) use ($app) {

			return $app->render('front/artist.html.twig',
				array('page_script' => 'artist', 'page_title' => $app['artist']['name'] . ' &ndash; ' . $app['artist']['location'], 'featured_release' => $app['release']));

		})
		->method('GET')
		->before($mw['get_artist'])
		->before($mw['get_release']);

		/* -- End Controller Definitions //--> */


		return $controller;
	}

	public function setMiddlewares(Application $app)
	{

		$middleware = array();


		/* <!-- Start Middleware Definitions -- */

		$middleware['get_spotlight_features'] = function (Request $request) use ($app) {
			$spotlight_main = $app['db']->fetchAssoc('SELECT * FROM `spotlight_feature` WHERE `stack_order` = 0');
			$spotlight_sub = $app['db']->fetchAll('SELECT * FROM `spotlight_feature` WHERE `stack_order` > 0 ORDER BY `stack_order` ASC');

			$app['spotlight_features'] = array('main' => $spotlight_main, 'sub_features' => $spotlight_sub);
		};


		$middleware['get_labels'] = function (Request $request) use ($app) {
			$labels = $app['db']->fetchAll('SELECT * FROM `label` ORDER BY `stack_order` ASC');

			foreach ($labels as $key => $label)
			{
				$labels[$key]['picture_url'] = $app['base_url'] . '/img/label/' . $label['slug'] . '.' . $label['picture_ext'];
			}

			$app['labels'] = $labels;
		};


		$middleware['get_artists'] = function (Request $request) use ($app) {
			$artists = $app['db']->fetchAll('SELECT * from `artist` ORDER BY `name` ASC');

			foreach($artists as $artist_key => $artist)
			{
				// Taken out temporarily for performance
				//$artist['shows']	= $app['songkick.get_artist_concerts']($artist['songkick_artist_id']);
				$artist['releases'] = $app['db']->fetchAll('SELECT * FROM `release` WHERE `artist_id` = ? ORDER BY `release_date` DESC', array($artist['id']));

				foreach($artist['releases'] as $key => $release)
				{
					$label = $app['db']->fetchAssoc('SELECT * FROM `label` WHERE `id` = ?', array($release['label_id']));

					$artist['labels'][$label['slug']] = $label;
					$artist['releases'][$key]['label'] = $label;
				}

				// Set the artist's picture URL for use in the template
				$artist['picture_url'] = $app['base_url'] . '/img/artist/' . $artist['slug'];
				// If one isn't set for the artist, use the latest release
				$artist['picture_url'] .= $artist['picture_ext'] == '' ? '/releases/' . $artist['releases'][0]['slug'] . '.' . $artist['releases'][0]['picture_ext'] : '.' . $artist['picture_ext'];

				$artists[$artist_key] = $artist;
			}
			

			$app['artists'] = $artists;
		};


		$middleware['get_artist'] = function (Request $request) use ($app) {
			$artist = $app['db']->fetchAssoc('SELECT * from `artist` WHERE `slug` = ?', array($request->get('artist_slug')));

			if (!$artist)
				$app->abort(404, 'No such artist.');

			
			$page_url = $app['base_url'].'/'.$artist['slug'];

			$tweet_link = "https://twitter.com/share?";

			if ($artist['smart_url'] != null) 	// if there's a smart url, set that as the main url and the page url as the count
				$tweet_link .= "url=".urlencode($artist['smart_url'])."&counturl=".urlencode($page_url);
			else 								// otherwise, just use the page url
				$tweet_link .= "url=".urlencode($page_url);

			$tweet_link .= "&via=ghouse&related=".$artist['twitter_handle']."&text=".urlencode("@".$artist['twitter_handle']);


			$artist['shows']		= $app['songkick.get_artist_concerts']($artist['songkick_artist_id']);
			$artist['tweet_link']	= $tweet_link;
			$artist['releases']		= $app['db']->fetchAll('SELECT * FROM `release` WHERE `artist_id` = ? ORDER BY `release_date` DESC', array($artist['id']));

			if (empty($artist['releases']))
			{
				return $app->redirect('/');
			}

			foreach($artist['releases'] as $key => $release)
			{
				$label = $app['db']->fetchAssoc('SELECT * FROM `label` WHERE `id` = ?', array($release['label_id']));
				
				$page_url = $app['base_url'].'/'.$artist['slug'].'/releases/'.$release['slug'];

				$tweet_link = "https://twitter.com/share?";

				if ($release['smart_url'] != null) 	// if there's a smart url, set that as the main url and the page url as the count
					$tweet_link .= "url=".urlencode($release['smart_url'])."&counturl=".urlencode($page_url);
				else 								// otherwise, just use the page url
					$tweet_link .= "url=".urlencode($page_url);

				$tweet_link .= "&via=ghouse&related=".$artist['twitter_handle']."&text=".urlencode($release['name']." by @".$artist['twitter_handle']);

				$artist['labels'][$label['slug']] = $label;
				$artist['releases'][$key]['label'] = $label;
				$artist['releases'][$key]['tweet_link'] = $tweet_link;
			}

			// Set the artist's picture URL for use in the template
			$artist['picture_url'] = $app['base_url'] . '/img/artist/' . $artist['slug'];
			// If one isn't set for the artist, use the latest release
			$artist['picture_url'] .= $artist['picture_ext'] == '' ? '/releases/' . $artist['releases'][0]['slug'] . '.' . $artist['releases'][0]['picture_ext'] : '.' . $artist['picture_ext'];

			$app['artist'] = $artist;
		};


		$middleware['get_release'] = function (Request $request) use ($app) {

			$artist = $app['artist'] ? $app['artist'] : $app['db']->fetchAssoc('SELECT * FROM `artist` WHERE `slug` = ?', array($request->get('artist_slug')));

			if (!$artist)
				$app->abort(404, 'No such artist.');

			$release = $app['db']->fetchAssoc('SELECT * FROM `release` WHERE `artist_id` = ? AND `slug` = ?', array($artist['id'], $request->get('release_slug')));

			if (!$release)
				$app->abort(404, 'No such release.');

				
			$label = $app['db']->fetchAssoc('SELECT * FROM `label` WHERE `id` = ?', array($release['label_id']));
			
			$page_url = $app['base_url'].'/'.$artist['slug'].'/releases/'.$release['slug'];

			$tweet_link = "https://twitter.com/share?";

			if ($release['smart_url'] != null) 	// if there's a smart url, set that as the main url and the page url as the count
				$tweet_link .= "url=".urlencode($release['smart_url'])."&counturl=".urlencode($page_url);
			else 								// otherwise, just use the page url
				$tweet_link .= "url=".urlencode($page_url);

			$tweet_link .= "&via=ghouse&related=".$artist['twitter_handle']."&text=".urlencode($release['name']." by @".$artist['twitter_handle']);

			$release['tweet_link'] = $tweet_link;
			$release['label'] = $label;
			$app['release'] = $release;
		};

		/* -- End Middleware Definitions //--> */


		return $middleware;
	}
}