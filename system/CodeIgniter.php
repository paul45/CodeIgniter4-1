<?php namespace CodeIgniter;

	/**
	 * CodeIgniter
	 *
	 * An open source application development framework for PHP
	 *
	 * This content is released under the MIT License (MIT)
	 *
	 * Copyright (c) 2014 - 2016, British Columbia Institute of Technology
	 *
	 * Permission is hereby granted, free of charge, to any person obtaining a copy
	 * of this software and associated documentation files (the "Software"), to deal
	 * in the Software without restriction, including without limitation the rights
	 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	 * copies of the Software, and to permit persons to whom the Software is
	 * furnished to do so, subject to the following conditions:
	 *
	 * The above copyright notice and this permission notice shall be included in
	 * all copies or substantial portions of the Software.
	 *
	 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	 * THE SOFTWARE.
	 *
	 * @package      CodeIgniter
	 * @author       CodeIgniter Dev Team
	 * @copyright    Copyright (c) 2014 - 2016, British Columbia Institute of Technology (http://bcit.ca/)
	 * @license      http://opensource.org/licenses/MIT	MIT License
	 * @link         http://codeigniter.com
	 * @since        Version 3.0.0
	 * @filesource
	 */


use Config\App;
use Config\Services;
use Config\Autoload;
use CodeIgniter\Hooks\Hooks;
use CodeIgniter\Config\DotEnv;

/**
 * System Initialization Class
 *
 * Loads the base classes and executes the request.
 */
class CodeIgniter
{
	/**
	 * The current version of CodeIgniter Framework
	 *
	 * @var string
	 */
	protected $CIVersion = '4.0-dev';

	/**
	 * UNIX timestamp for the start of script execution
	 * in seconds with microseconds.
	 *
	 * @var float
	 */
	protected $startMemory;

	/**
	 * App start time
	 *
	 * @var float
	 */
	protected $startTime;

	/**
	 * The application configuration object.
	 *
	 * @var \Config\App
	 */
	protected $config;

	/**
	 * @var \CodeIgniter\HTTP\Request
	 */
	protected $request;

	/**
	 * @var \CodeIgniter\HTTP\Response
	 */
	protected $response;

	/**
	 * @var \CodeIgniter\Router\Router
	 */
	protected $router;

	/**
	 * @var string
	 */
	protected $controller;

	/**
	 * @var string
	 */
	protected $method;

	/**
	 * @var string
	 */
	protected $output;

	//--------------------------------------------------------------------

	/**
	 * CodeIgniter constructor.
	 *
	 * @param int $startMemory
	 */
	public function __construct(int $startMemory, float $startTime)
	{
		$this->startMemory = $startMemory;
		$this->startTime   = $startTime;
	}

	//--------------------------------------------------------------------

	/**
	 * The class entry point. This is where the magic happens and all
	 * of the framework pieces are pulled together and shown how to
	 * make beautiful music together. Or something like that. :)
	 */
	public function run()
	{
		define('CI_VERSION', $this->CIVersion);

		require_once BASEPATH.'Common.php';
		require_once APPPATH.'Config/Services.php';

		$this->loadFrameworkConstants();
		$this->setupAutoloader();
		$this->setExceptionHandling();
		$this->startBenchmark();
		$this->loadComposerAutoloader();

		//--------------------------------------------------------------------
		// Is there a "pre-system" hook?
		//--------------------------------------------------------------------
		Hooks::trigger('pre_system');

		$this->getRequestObject();
		$this->getResponseObject();
		$this->forceSecureAccess();
		$this->tryToRouteIt();

		//--------------------------------------------------------------------
		// Are there any "pre-controller" hooks?
		//--------------------------------------------------------------------
		Hooks::trigger('pre_controller');

		$this->startController();

		//--------------------------------------------------------------------
		// Is there a "post_controller" hook?
		//--------------------------------------------------------------------
		Hooks::trigger('post_controller');

		$this->gatherOutput();
		$this->sendResponse();

		//--------------------------------------------------------------------
		// Is there a post-system hook?
		//--------------------------------------------------------------------
		Hooks::trigger('post_system');
	}

	//--------------------------------------------------------------------

	/**
	 * Load the framework constants
	 */
	protected function loadFrameworkConstants()
	{
		if (file_exists(APPPATH.'Config/'.ENVIRONMENT.'/constants.php'))
		{
			require_once APPPATH.'Config/'.ENVIRONMENT.'/constants.php';
		}

		require_once(APPPATH.'Config/Constants.php');
	}

	//--------------------------------------------------------------------

	/**
	 * Load any environment-specific settings from .env file
	 */
	protected function loadDotEnv()
	{
		// Load environment settings from .env files
		// into $_SERVER and $_ENV
		require BASEPATH.'Config/DotEnv.php';
		$env = new DotEnv(APPPATH);
		$env->load();
		unset($env);
	}

	//--------------------------------------------------------------------

	/**
	 * Setup the autoloader
	 */
	protected function setupAutoloader()
	{
		// The autoloader isn't initialized yet, so load the file manually.
		require BASEPATH.'Autoloader/Autoloader.php';
		require APPPATH.'Config/Autoload.php';

		// The Autoloader class only handles namespaces
		// and "legacy" support.
		$loader = Services::autoloader();
		$loader->initialize(new Autoload());

		// The register function will prepend
		// the psr4 loader.
		$loader->register();
	}

	//--------------------------------------------------------------------

	/**
	 * Set custom exception handling
	 */
	protected function setExceptionHandling()
	{
		$this->config = new App();

		Services::exceptions($this->config, true)
		        ->initialize();
	}

	//--------------------------------------------------------------------

	/**
	 * Start the Benchmark
	 * 
	 * The timer is used to display total script execution both in the
	 * debug toolbar, and potentially on the displayed page.
	 */
	protected function startBenchmark()
	{
		$this->benchmark = Services::timer(true);
		$this->benchmark->start('total_execution', $this->startTime);
		$this->benchmark->start('bootstrap');
	}

	//--------------------------------------------------------------------

	/**
	 * Should we use a Composer autoloader?
	 */
	protected function loadComposerAutoloader()
	{
		$composer_autoload = $this->config->composerAutoload;

		if (empty($composer_autoload))
		{
			return;
		}

		if ($composer_autoload === true)
		{
			file_exists(APPPATH.'vendor/autoload.php')
				? require_once(APPPATH.'vendor/autoload.php')
				: log_message('error', '$this->config->\'composerAutoload\' is set to TRUE but '.APPPATH.
				                       'vendor/autoload.php was not found.');
		}
		elseif (file_exists($composer_autoload))
		{
			require_once($composer_autoload);
		}
		else
		{
			log_message('error',
				'Could not find the specified $this->config->\'composerAutoload\' path: '.$composer_autoload);
		}
	}

	//--------------------------------------------------------------------

	/**
	 * Get our Request object
	 */
	protected function getRequestObject()
	{
		$this->request = is_cli()
			? Services::clirequest($this->config, true)
			: Services::request($this->config, true);

		$this->request->setProtocolVersion($_SERVER['SERVER_PROTOCOL']);
	}

	//--------------------------------------------------------------------

	/**
	 * Get our Response object
	 */
	protected function getResponseObject()
	{
		$this->response = Services::response($this->config, true);
		$this->response->setProtocolVersion($this->request->getProtocolVersion());

		// Assume success until proven otherwise.
		$this->response->setStatusCode(200);
	}

	//--------------------------------------------------------------------

	/**
	 * Force Secure Site Access?
	 */
	protected function forceSecureAccess($duration = 31536000)
	{
		if ($this->config->forceGlobalSecureRequests !== true)
		{
			return;
		}

		force_https($duration, $this->request, $this->response);
	}

	//--------------------------------------------------------------------

	/**
	 * CSRF Protection
	 */
	protected function CsrfProtection()
	{
		if ($this->config->CSRFProtection !== true || is_cli())
		{
			return;
		}

		$security = Services::security($this->config);

		$security->CSRFVerify($this->request);
	}

	//--------------------------------------------------------------------

	/**
	 * Try to Route It
	 */
	protected function tryToRouteIt()
	{
		require APPPATH.'Config/Routes.php';

		$this->router = Services::router($routes, true);

		$path = is_cli() ? $this->request->getPath() : $this->request->uri->getPath();

		$this->benchmark->stop('bootstrap');
		$this->benchmark->start('routing');

		try
		{
			$this->controller = $this->router->handle($path);
		}
		catch (\CodeIgniter\Router\RedirectException $e)
		{
			$logger = Services::logger();
			$logger->info('REDIRECTED ROUTE at '.$e->getMessage());

			// If the route is a 'redirect' route, it throws
			// the exception with the $to as the message
			$this->response->redirect($e->getMessage(), 'auto', $e->getCode());
			exit(EXIT_SUCCESS);
		}

		$this->method = $this->router->methodName();

		$this->benchmark->stop('routing');
	}

	//--------------------------------------------------------------------

	protected function startController()
	{
		ob_start();

		$this->benchmark->start('controller');
		$this->benchmark->start('controller_constructor');

		$e404 = false;

		// Is it routed to a Closure?
		if (is_callable($this->controller))
		{
			echo $this->controller(...$this->router->params());
		}
		else
		{
			if (empty($this->controller))
			{
				$e404 = true;
			}
			else
			{
				// Try to autoload the class
				if ( ! class_exists($this->controller, true) || $this->method[0] === '_')
				{
					$e404 = true;
				}
				else if ( ! method_exists($this->controller, '_remap') &&
					! is_callable([$this->controller, $this->method], false)
				)
				{
					$e404 = true;
				}
			}

			// Is there a 404 Override available?
			if ($e404 && $override = $this->router->get404Override())
			{
				if ($override instanceof \Closure)
				{
					echo $override();
				}
				else if (is_array($override))
				{
					$this->controller = $override[0];
					$this->method = $override[1];

					unset($override);
				}

				$e404 = false;
			}

			// Display 404 Errors
			if ($e404)
			{
				$this->response->setStatusCode(404);

				if (ob_get_level() > 0)
				{
					ob_end_flush();
				}
				ob_start();

				// Show the 404 error page
				if (is_cli())
				{
					require APPPATH.'Views/errors/cli/error_404.php';
				}
				else
				{
					require APPPATH.'Views/errors/html/error_404.php';
				}

				$buffer = ob_get_contents();
				ob_end_clean();

				echo $buffer;
				exit(EXIT_UNKNOWN_FILE);    // Unknown file
			}

			if ( ! $e404 && ! isset($override))
			{
				$class = new $this->controller($this->request, $this->response);

				$this->benchmark->stop('controller_constructor');

				//--------------------------------------------------------------------
				// Is there a "post_controller_constructor" hook?
				//--------------------------------------------------------------------
				Hooks::trigger('post_controller_constructor');

				if (method_exists($class, '_remap'))
				{
					$class->_remap($this->method, ...$this->router->params());
				}
				else
				{
					$class->{$this->method}(...$this->router->params());
				}
			}
		}

		$this->benchmark->stop('controller');
	}

	//--------------------------------------------------------------------

	/**
	 * Output gathering and cleanup
	 */
	protected function gatherOutput()
	{
		$this->output = ob_get_contents();
		ob_end_clean();

		$totalTime    = $this->benchmark->stop('total_execution')
		                                ->getElapsedTime('total_execution');

		$this->output = str_replace('{elapsed_time}', $totalTime, $this->output);

		//--------------------------------------------------------------------
		// Display the Debug Toolbar?
		//--------------------------------------------------------------------
		if (ENVIRONMENT != 'production' && $this->config->toolbarEnabled)
		{
			$toolbar = Services::toolbar($this->config);
			$this->output .= $toolbar->run($this->startTime, $totalTime,
				$this->startMemory, $this->request,
				$this->response);
		}
	}

	//--------------------------------------------------------------------

	/**
	 * Sends the output of this request back to the client.
	 */
	protected function sendResponse()
	{
		$this->response->setBody($this->output);

		$this->response->send();
	}

	//--------------------------------------------------------------------

}
