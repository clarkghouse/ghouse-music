<?php // dobis master controllers (ghouse.co)
namespace GHouse\Controller\Provider;

use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;
use GHouse\Controller\Provider\Base\BaseControllerProvider as Base_Controller_Provider;

class DobisMasterControllerProvider extends Base_Controller_Provider
{
	public function setControllers(Application $app, Array $mw)
	{
		$controller = $app['controllers_factory'];
		$controller->before($mw['dobis_version']);


		/* <!-- Start Controller Definitions -- */

		// Index page
		$controller->match('/', function (Request $request) use ($app) {

			return $app->render('dobis/index.html.twig');

		})
		->method('GET')
		->before($mw['must_be_logged_in'])
		->before($mw['get_artists']);


		// Login page
		$controller->match('/login', function (Request $request) use ($app) {
			
			$data = array('username' => '', 'password' => '');
			$message = null;


			$form = $app['form.factory']->createNamedBuilder('login', 'form', $data)
				->add('username', 'text', array('label' => false))
				->add('password', 'password', array('label' => false))
				->getForm();

			if ($request->getMethod() == 'POST')
			{
				$form->bind($request);

				if ($form->isValid())
				{
					$data = $form->getData();

					$dobis_user = $app['db']->fetchAssoc('SELECT * FROM `dobis_user` WHERE `username` = ? and `password` = ?', array($data['username'], md5($data['password'])));

					if (!$dobis_user)
					{
						$message = array('error', 'Invalid dengus or paynuss. Please try again.');
					}
					else
					{
						$app['db']->update('dobis_user', array('last_login' => time()), array('id' => $dobis_user['id']));
						$app['session']->start();
						$user['authenticated'] = 'yes';
						$app['session']->set('dobis_user', $dobis_user);
						
						return $app->redirect('/dobismaster/');
					}
				}
				else
				{
					$message = array('error', '<strong>Error:</strong> Tengus.');
				}

			}

			return $app->render('dobis/login.html.twig', array('form' => $form->createView(), 'message' => $message));

		})->method('GET|POST')->before($mw['must_be_logged_out']);

		
		// Logout
		$controller->match('/logout', function (Request $request) use ($app) {
			$app['session']->set('dobis_user', null);
			$app['session']->clear();

			$app['session']->getFlashBag()->set('notice', 'Great Job!');

			return $app->redirect('/dobismaster/login');

		})->method('GET');


		// Edit Artist / New Artist
		$controller->match('/artists/{artist_slug}', function (Request $request, $artist_slug) use ($app) {

			$message = null;

			if ($artist_slug == 'new')
			{
				$artist = array();
				$releases = array();
			}
			else
			{
				$artist = $app['db']->fetchAssoc('SELECT * FROM `artist` WHERE `slug` = ?', array($artist_slug));
				
				if (!$artist)
					return $app->abort(404, 'No such artist.');

				$releases = $app['db']->fetchAll('SELECT * FROM `release` WHERE `artist_id` = ? ORDER BY `release_date` DESC', array($artist['id']));
				// Set the artist's picture URL for use in the template
				$artist['picture_url'] = $app['base_url'] . '/img/artist/' . $artist['slug'];
				// If one isn't set for the artist, use the latest release
				if ($releases)
					$artist['picture_url'] .= $artist['picture_ext'] == '' ? '/releases/' . $releases[0]['slug'] . '.' . $releases[0]['picture_ext'] : '.' . $artist['picture_ext'];
				else
					$artist['picture_url'] = $artist['picture_ext'] == '' ? 'none.jpg' : $artist['picture_url'].'.'.$artist['picture_ext'];
			}

			// Labels Dropdown
			$labels = $app['db']->fetchAll('SELECT * FROM `label`');
			$labels_dropdown = array();
			foreach ($labels as $label)
				$labels_dropdown[$label['id']] = $label['name'];
			
			// Artist Form
			$artist_form = $app['form.factory']->createNamedBuilder('artist', 'form', $artist)
				->add('id', 'hidden')
				->add('name', 'text', array('constraints' => array(new Assert\NotBlank())))
				->add('location', 'text', array('constraints' => array(new Assert\NotBlank())))
				->add('new_picture', 'file')
				->add('twitter_handle', 'text', array('constraints' => array(new Assert\NotBlank())))
				->add('twitter_card_description', 'textarea', array('constraints' => array(new Assert\Length(array('max' => 200)))))
				->add('songkick_artist_id')
				->add('smart_url');

			// Set up the releases form...
			$releases_form = $app['form.factory']->createNamedBuilder('releases', 'form');


			// ...and construct it accordingly
			if(!empty($releases))
			{
				$existing_releases_form = $app['form.factory']->createNamedBuilder('existing_releases', 'form');

				foreach($releases as $release)
				{
					$release['release_date'] = new \DateTime($release['release_date']);

					$release_form = $app['form.factory']->createNamedBuilder($release['id'], 'form', $release)
						->add('id', 'hidden')
						->add('name', 'text', array('constraints' => array(new Assert\NotBlank())))
						->add('slug', 'hidden')
						->add('label_id', 'choice', array('choices' => $labels_dropdown))
						->add('spotify_uri')
						->add('itunes_url', 'text', array('constraints' => array(new Assert\NotBlank())))
						->add('release_date', 'date', array('constraints' => array(new Assert\NotBlank())))
						->add('new_picture', 'file')
						->add('twitter_card_description', 'textarea', array('constraints' => array(new Assert\Length(array('max' => 200)))))
						->add('smart_url')
						->add('remove', 'hidden');

					$existing_releases_form->add($release_form);
				}

				// Releases Form
				$releases_form->add($existing_releases_form);
			}

			// Parent form
			$parent_form = $app['form.factory']->createNamedBuilder('dobis_form', 'form')
				->add($artist_form)
				->add($releases_form)
				->getForm();

			// New Releases Form -- Leave blank; will be filled in if need be
			$new_releases_form = false;

			// Process submission
			if ($request->getMethod() == 'POST')
			{
				$parent_form->bind($request);

				$new_releases = $request->get('new_releases');
				if ($new_releases)
				{
					$new_releases_form = $app['form.factory']->createNamedBuilder('new_releases', 'form', null, array('csrf_protection' => false));
					
					foreach($new_releases as $key => $release)
					{
						$release_form = $app['form.factory']->createNamedBuilder($key, 'form', null, array('csrf_protection' => false))
							->add('name', 'text', array('constraints' => array(new Assert\NotBlank())))
							->add('label_id', 'choice', array('choices' => $labels_dropdown))
							->add('twitter_card_description', 'textarea', array('constraints' => array(new Assert\Length(array('max' => 200)))))
							->add('spotify_uri')
							->add('itunes_url', 'text', array('constraints' => array(new Assert\NotBlank())))
							->add('release_date', 'date', array('constraints' => array(new Assert\NotBlank())))
							->add('new_picture', 'file', array('constraints' => array(new Assert\NotBlank())))							
							->add('smart_url');

						$new_releases_form->add($release_form);
					}

					$new_releases_form = $new_releases_form->getForm()->bind($request);
					$new_releases_valid = $new_releases_form->isValid();
				}
				else
				{					
					$new_releases_valid = true;
				}

				// If everything is valid...
				if ($parent_form->isValid() && $new_releases_valid)
				{
					$dobisData = $parent_form->getData();


					// get the artist info ready for the database
					$artistData = $dobisData['artist'];
					$artistData['slug'] = $app['slugify']($artistData['name']);


					// make sure the artist's media folders are set up
					$artist_media_dir = $app['media_dir'].'/uploads/artist/'.$artistData['slug'];
					$release_media_dir = $artist_media_dir.'/releases';

					if (!is_dir($artist_media_dir))
					{
						mkdir($artist_media_dir);
						chmod($artist_media_dir, 0777);
						mkdir($release_media_dir);
						chmod($release_media_dir, 0777);
					}

					// upload picture if necessary
					if (!is_null($artistData['new_picture']))
					{
						$artistData['picture_ext'] = $artistData['new_picture']->guessExtension();
						$filename = $artistData['slug'].'.'.$artistData['picture_ext'];

						$artistData['new_picture']->move($artist_media_dir, $filename);
					}
					else
					{
						if (isset($artist['id']))
							$artistData['picture_ext'] = $artist['picture_ext'];
						else
							$artistData['picture_ext'] = null;
					}


					if ($artist_slug == 'new')
					{
						$app['db']->insert('artist', array(
							'name' => $artistData['name'],
							'slug' => $artistData['slug'],
							'location' => $artistData['location'],
							'twitter_handle' => $artistData['twitter_handle'],
							'twitter_card_description' => $artistData['twitter_card_description'],
							'songkick_artist_id' => $artistData['songkick_artist_id'],
							'picture_ext' => $artistData['picture_ext'],
							'smart_url' => $artistData['smart_url']
						));

						$artist_id = $app['db']->lastInsertId();
						$artist = $app['db']->fetchAssoc('SELECT * FROM `artist` WHERE `id` = ?', array($artist_id));
					}
					else
					{
						$app['db']->update('artist', array(
							'name' => $artistData['name'],
							'slug' => $artistData['slug'],
							'location' => $artistData['location'],
							'twitter_handle' => $artistData['twitter_handle'],
							'twitter_card_description' => $artistData['twitter_card_description'],
							'songkick_artist_id' => $artistData['songkick_artist_id'],
							'picture_ext' => $artistData['picture_ext'],
							'smart_url' => $artistData['smart_url']),
							array('id' => $artistData['id']));

						$artist = $app['db']->fetchAssoc('SELECT * FROM `artist` WHERE `id` = ?', array($artistData['id']));
					}

					// Existing Releases?
					$existingReleasesDobis = isset($dobisData['releases']['existing_releases']) ? $dobisData['releases']['existing_releases'] : false;

					if ($existingReleasesDobis)
					{
						foreach($existingReleasesDobis as $existing_release)
						{
							// should we delete it?
							if (!is_null($existing_release['remove']))
							{
								$app['db']->delete('`release`', array('id' => $existing_release['id']));
							}
							else
							{
								$old_slug = $existing_release['slug'];
								$existing_release['slug'] = $app['slugify']($existing_release['name']);

								// upload a new picture if necessary
								if ($existing_release['new_picture'] != null)
								{
									$existing_release['picture_ext'] = $existing_release['new_picture']->guessExtension();
									$filename = $existing_release['slug'].'.'.$existing_release['picture_ext'];
									$existing_release['new_picture']->move($release_media_dir, $filename);
								}
								// or rename the picture if need be
								else
								{
									if ($old_slug != $existing_release['slug'])
									{
										$old_file = $release_media_dir.'/'.$old_slug.'.'.$existing_release['picture_ext'];
										$new_file = $release_media_dir.'/'.$existing_release['slug'].'.'.$existing_release['picture_ext'];
										rename($old_file, $new_file);
									}

								}

								$release_update = array(
									'label_id' => $existing_release['label_id'],
									'name' => $existing_release['name'],
									'slug' => $existing_release['slug'],
									'release_date' => $existing_release['release_date']->format('Y-m-d'),
									'spotify_uri' => $existing_release['spotify_uri'],
									'itunes_url' => $existing_release['itunes_url'],
									'smart_url' => $existing_release['smart_url'],								
									'picture_ext' => $existing_release['picture_ext'],
									'twitter_card_description' => $existing_release['twitter_card_description']
								);

								$app['db']->update('`release`', $release_update, array('id' => $existing_release['id']));								
							}
						}
					}
					

					// New Releases?
					if ($new_releases_form)
					{
						$newReleasesDobis = $new_releases_form->getData();

						foreach($newReleasesDobis as $new_release)
						{
							$new_release['artist_id'] = $artist['id'];
							$new_release['slug'] = $app['slugify']($new_release['name']);
							$new_release['picture_ext'] = $new_release['new_picture']->guessExtension();

							// upload picture
							$filename = $new_release['slug'].'.'.$new_release['picture_ext'];
							$new_release['new_picture']->move($release_media_dir, $filename);

							$release_insert = array(
								'artist_id' => intval($new_release['artist_id']),
								'label_id' => $new_release['label_id'],
								'name' => $new_release['name'],
								'slug' => $new_release['slug'],
								'release_date' => $new_release['release_date']->format('Y-m-d'),
								'spotify_uri' => $new_release['spotify_uri'],
								'itunes_url' => $new_release['itunes_url'],
								'smart_url' => $new_release['smart_url'],								
								'picture_ext' => $new_release['picture_ext'],
								'twitter_card_description' => $new_release['twitter_card_description']
							);

							// save to db
							$app['db']->insert('`release`', $release_insert);

						}
					}
					

					$app['session']->getFlashBag()->set('notice', 'Great Job!');

					return $app->redirect('/dobismaster/artists/'.$artist['slug']);
					
				}

				$message = "There were errors with your entry. Please scroll down and correct the listed errors.";
			}

			// If we've got new releases added with errors, create the view now...
			if ($new_releases_form)
				$new_releases_form = $new_releases_form->createView();

			return $app->render('dobis/artist.html.twig', array('page_script' => 'dobis-artist', 'new_releases_form' => $new_releases_form, 'parent_form' => $parent_form->createView(), 'artist' => $artist, 'releases' => $releases, 'message' => $message));

		})->method('GET|POST')->before($mw['must_be_logged_in']);

		
		// AJAX - Slugify
		$controller->post('/ajax/slugify', function (Request $request) use ($app) {
			if (!$request->isXmlHttpRequest())
				return $app->abort(404, 'No such page.');

			return $app['slugify']($request->get('text'));
		});


		// AJAX - New release form slot
		$controller->post('/ajax/new-release/{release_no}', function (Request $request, $release_no) use ($app) {
			if (!$request->isXmlHttpRequest())
				return $app->abort(404, 'No such page.');

			// Labels Dropdown
			$labels = $app['db']->fetchAll('SELECT * FROM `label`');
			$labels_dropdown = array();
			foreach ($labels as $label)
				$labels_dropdown[$label['id']] = $label['name'];

			$release_form = $app['form.factory']->createNamedBuilder($release_no, 'form', null, array('csrf_protection' => false))
				->add('name', 'text', array('constraints' => array(new Assert\NotBlank())))
				->add('label_id', 'choice', array('choices' => $labels_dropdown))
				->add('spotify_uri')
				->add('itunes_url', 'text', array('constraints' => array(new Assert\NotBlank())))
				->add('release_date', 'date', array('constraints' => array(new Assert\NotBlank())))
				->add('new_picture', 'file', array('constraints' => array(new Assert\NotBlank())))
				->add('twitter_card_description', 'textarea', array('constraints' => array(new Assert\Length(array('max' => 200)))))
				->add('smart_url');

			$new_releases_form = $app['form.factory']->createNamedBuilder('new_releases', 'form', null, array('csrf_protection' => false))
				->add($release_form)
				->getForm();

			return $app->render('dobis/ajax-new-release.html.twig', array('release_no' => $release_no, 'new_releases_form' => $new_releases_form->createView()));
		})->before($mw['must_be_logged_in']);

		/* -- End Controller Definitions //--> */


		return $controller;
	}

	public function setMiddlewares(Application $app)
	{

		$middleware = array();


		/* <!-- Start Middleware Definitions -- */

		$middleware['dobis_version'] = function (Request $request) use ($app) {
			$app['dobis_version'] = "5000";
		};

		$middleware['must_be_logged_in'] = function (Request $request) use ($app) {
			$dobis_user = $app['session']->get('dobis_user');

			if (!isset($dobis_user['id']))
				return $app->redirect('/dobismaster/login');

			$app['dobis_user'] = $dobis_user;
		};


		$middleware['must_be_logged_out'] = function (Request $request) use ($app) {
			$dobis_user = $app['session']->get('dobis_user');

			if (isset($dobis_user['id']))
				return $app->redirect('/dobismaster/');
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

		/* -- End Middleware Definitions //--> */


		return $middleware;
	}
}