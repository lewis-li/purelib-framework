<?php
namespace PureLib\Framework;

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\ClassLoader\UniversalClassLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\Dumper\PhpMatcherDumper;
use Symfony\Component\Routing\Route;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;

class Application
{
	protected $container;

	public function __construct(array $config=array(), \PureLib\Di\DiInterface $container=null, $eventManager=null, $request=null, $response=null)
	{
		if ($container === null) {
			$container = new \PureLib\Di\Adapter\Pimple(new \Pimple());
		}

		$this->container = $container;

		$this->set('config', function () use ($config) {
			return new \ArrayObject($config,\ArrayObject::ARRAY_AS_PROPS);
		} );

			if ($eventManager === null) {
				$eventManager = function () {
					return new \Zend\EventManager\EventManager;
				};
			}

			if ($request === null) {
				$request = function () {
					return Request::createFromGlobals();
				};
			}

			if ($response === null) {
				$response = function () {
					return new Response();
				};
			}

			$this->set('eventManager',  $eventManager);
			$this->set('request', $request);
			$this->set('response', $response);
			$this->set('router', function (){
				return new RouteCollection();
			});

			$this->on('bootstrap', function ($e) {
				$callback = $this->get('request')->attributes->get('_callback');
				$args = $this->get('request')->attributes->get('_args');
				ob_start();
				call_user_func_array($callback, $args);
				$this->get('response')->setContent(ob_get_clean());
			});
			
			$this->on('bootstrapAfter', function ($e) {
				$e->getTarget()->get('response')->send();
			} );
			
			$this->on('route', function ($e) {
				$this->matchRoute();
			});
	
			$this->on('unmatchroute', function($e) {
				$this->get('response')->setStatusCode(404, 'not found page');
			});
	}

	public function set($name, $value)
	{
		return $this->container->set($name, $value);
	}

	public function get($name)
	{
		return $this->container->get($name);
	}

	public function __get($name)
	{
		return $this->get($name);
	}

	public function __set($name, $value)
	{
		return $this->set($name, $value);
	}

	public function attachEventListener($event, $listener=null, $priority=1)
	{
		$this->get('eventManager')->attach( strtolower($event), $listener, $priority);
	}

	public function on($event, $listener=null, $priority=1)
	{
		$this->attachEventListener($event, $listener, $priority);
	}

	public function detachEventListener($listener)
	{
		$this->get('eventManager')->detach($listener);
	}

	public function triggerEvent($event, $context=null, $argv=array(), $callback=null)
	{
		$this->get('eventManager')->trigger( strtolower($event), $context, $argv, $callback);
	}
	
	public function __call($method, $args)
	{
		if (strtolower(substr($method,0,2)) === 'on') {
			$data = isset($args[0]) && is_array($args[0]) ? $args[0] : array();
			$eventType = substr($method, 2);
			$this->triggerEvent($eventType, $this, $data, null);
		}
	}

	public function route($pattern, $callback=null, $defaults=array())
	{
		$name = $pattern;
		$defaults['_callback'] = $callback;
		$defaults['_controller'] = $callback;
		$route = new Route($pattern, $defaults);
		$this->get('router')->add($name, $route);
	}

	public function matchRoute()
	{
		try {
			$request = $this->get('request');
			$context = new RequestContext();
			$context->fromRequest($this->get('request'));
			$matcher = new UrlMatcher($this->get('router'), $context);
			$path = $this->get('request')->getPathInfo();

			if (strlen($path)>1) {
				$path = rtrim($path,'\/');
			}
			$match = $matcher->match($path);
			$this->get('request')->attributes->add($match);

			$resolver = new ControllerResolver();
			$controller = $resolver->getController($request);
			$args = $resolver->getArguments($request, $controller);
			$match['_args'] = $args;
			$this->get('request')->attributes->add($match) ;
			return $match;
		} catch (ResourceNotFoundException $e) {
			$this->triggerEvent('unmatchroute', $e->getTarget());
		} catch (Exception $e) {
			$this->triggerEvent('unmatchroute', $e->getTarget());
		}
	}

	public function run()
	{
		$this->triggerEvent('bootstrapBefore', $this);
		$this->triggerEvent('bootstrap', $this);
		$this->triggerEvent('bootstrapAfter', $this);
	}
}
