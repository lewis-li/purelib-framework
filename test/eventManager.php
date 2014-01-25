<?php

use PureLib\Framework\Application;
require __DIR__.'/../vendor/autoload.php';

$app = new Application(array('time'=>time()));

$app->test = function () { static $i=1; $i++; return $i;};

$app->config->title = 'demo';

$app->on('start', function () use ($app) { echo $app->test; } );
$app->on('start', function () use ($app) { echo $app->config->title; } );

$app->triggerEvent('start');