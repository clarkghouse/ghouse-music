<?php // app service configuration
use Symfony\Component\Yaml\Yaml;


// Set PHP's timezone jussincase
date_default_timezone_set('America/New_York');


// 
//  APP CONFIG:
//  In the region below you'll find settings for app-wide variables,
//  service configuration options, and other base-level project settings.
//  
//  If you can't find what you're looking for, check the config/ dir.
//


// Base URL and DIR for application -- please set explicitly here
$app['base_url']	= "http://music.ghouse.co";
$app['base_dir']	= "/home/ghouseco/music.ghouse.co/";


// swiftmailer
$app['swiftmailer.options'] = array(
	'host'	 	=> 'smtp.gmail.com',
	'port' 		=> 465,
	'username' 	=> 'clark@ghouse.co',
	'password'	=>'whelpad0n321',
	'auth_mode'	=> 'ssl'
);


// monolog app title
$app['monolog.name']	= 'ghouse';


// songkick API key
$app['songkick.options.api_key'] = 'fL8GRfZV8WDvsRds';



// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
//  please do not edit what is below unless it is absolutely necessary.
//  when at all possible, defer configuration to the region above or to
//  the config/app.yml file.
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!



// load config from app.yml
$application_config = Yaml::parse($app['base_dir'].'/config/app.yml');


// general app settings
$app['env']						= $application_config['env'];
$app['debug'] 					= ($app['env'] == 'local' || $app['env'] == 'dev') ? true : false;
$app['uploads_url']				= $app['base_url'] . '/' . 'uploads';
$app['assets_url']				= $app['base_url'] . '/' . 'assets';
$app['css_url']					= $app['assets_url'] . '/' . 'css';
$app['js_url']					= $app['assets_url'] . '/' . 'js';
$app['img_url']					= $app['assets_url'] . '/' . 'img';
$app['media_dir']				= $app['base_dir'] . '/' . 'media';


// monolog log file
$app['monolog.logfile'] = $app['base_dir'].'/log/app.log';

// doctrine dbal
$app['db.options'] = array(
	'driver' 	=> $application_config[$app['env']]['db']['driver'],
	'dbname' 	=> $application_config[$app['env']]['db']['dbname'],
	'host' 		=> $application_config[$app['env']]['db']['host'],
	'user' 		=> $application_config[$app['env']]['db']['user'],
	'password' 	=> $application_config[$app['env']]['db']['password']
);



// twig
$app['twig.path'] = $app['base_dir'] . '/view/template';