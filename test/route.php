<?php

use PureLib\Framework\Application;
use Symfony\Component\HttpFoundation\Request;

require __DIR__.'/../vendor/autoload.php';

$app = new Application(array('time'=>time()),null, null, Request::create('/hello/test'));

$app->route('/', function () { echo 'home'; } );
$app->route('/hello/{id}', function ($id) { echo 'hello'; }, array('id'=>'default'));

$app->matchRoute();

$app->onunmatchroute();

$app->run();