<?php

use PureLib\Framework\Application;
require __DIR__.'/../vendor/autoload.php';

$app = new Application(array('time'=>time()));

$app->test = function () { static $i=1; $i++; return $i;};

var_dump($app->test === 2, $app->test === 2);

$app->config->title = 'demo';

var_dump(
	$app->config, 
	$app->config->title, 
	$app->config['title'], 
	count($app->config)
);