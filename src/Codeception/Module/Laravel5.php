<?php
namespace Codeception\Module;

use Codeception\Exception\ModuleConfig;
use Codeception\Lib\Connector\Laravel5 as LaravelConnector;
use Codeception\Lib\Framework;
use Codeception\Lib\Interfaces\ActiveRecord;
use Codeception\Subscriber\ErrorHandler;

/**
 *
 * This module allows you to run functional tests for Laravel 5.
 * Module is very fresh and should be improved with Laravel testing capabilities.
 * Please try it and leave your feedbacks. If you want to maintain it - connect Codeception team.
 *
 * ## Status
 *
 * * Maintainer: **Davert**
 * * Stability: **stable**
 * * Contact: davert.codeception@mailican.com
 *
 * ## Config
 *
 * * cleanup: `boolean`, default `true` - all db queries will be run in transaction, which will be rolled back at the end of test.
 * * environment: `string`, default `testing` - When running in unit testing mode, we will set a different environment.
 * * bootstrap: `string`, default `bootstrap/app.php` - Relative path to app.php config file.
 * * root: `string`, default `` - Root path of our application.
 *
 * ## API
 *
 * * app - `Illuminate\Foundation\Application` instance
 * * client - `BrowserKit` client
 *
 */
class Laravel5 extends Framework implements ActiveRecord
{

    /**
     * @var \Illuminate\Foundation\Application
     */
    public $app;

    /**
     * @var array
     */
    protected $config = [];

    /**
     * Constructor.
     *
     * @param $config
     */
    public function __construct($config = null)
    {
        $this->config = array_merge(
            array(
                'cleanup' => true,
                'environment' => 'testing',
                'bootstrap' => 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php',
                'root' => '',
            ),
            (array) $config
        );

        parent::__construct();
    }

    /**
     * Initialize hook.
     */
    public function _initialize()
    {
        $this->setTestingEnvironment();
        $this->revertErrorHandler();
    }

    /**
     * Before hook.
     *
     * @param \Codeception\TestCase $test
     * @throws ModuleConfig
     */
    public function _before(\Codeception\TestCase $test)
    {
        $this->app = $this->getApplication();

        $httpKernel = $this->app->make('Illuminate\Contracts\Http\Kernel');
        $httpKernel->bootstrap();
        $this->app->boot();

        $this->client = new LaravelConnector($httpKernel);
        $this->client->followRedirects(true);

        if ($this->config['cleanup']) {
            $this->app['db']->beginTransaction();
        }
    }

    /**
     * After hook.
     *
     * @param \Codeception\TestCase $test
     */
    public function _after(\Codeception\TestCase $test)
    {
        if ($this->config['cleanup']) {
            $this->app['db']->rollback();
        }

        if ($this->app['auth']) {
            $this->app['auth']->logout();
        }

        if ($this->app['cache']) {
            $this->app['cache']->flush();
        }

        if ($this->app['session']) {
            $this->app['session']->flush();
        }
    }

    /**
     * Set testing environment, the Laravel framework initializes this
     * in Illuminate\Foundation\Bootstrap\DetectEnvironment
     */
    protected function setTestingEnvironment() {
        putenv('APP_ENV=' . $this->config['environment']);
    }

    /**
     * Revert back to the Codeception error handler,
     * becauses Laravel registers it's own error handler.
     */
    protected function revertErrorHandler()
    {
        $handler = new ErrorHandler();
        set_error_handler(array($handler, 'errorHandler'));
    }

    /**
     * Get the Laravel application object.
     *
     * @return \Illuminate\Foundation\Application
     * @throws \Codeception\Exception\ModuleConfig
     */
    protected function getApplication()
    {
        $projectDir = explode('workbench', \Codeception\Configuration::projectDir())[0];
        $projectDir .= $this->config['root'];
        require $projectDir . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

        \Illuminate\Support\ClassLoader::register();

        if (is_dir($workbench = $projectDir . 'workbench')) {
            \Illuminate\Workbench\Starter::start($workbench);
        }

        $bootstrapFile = $projectDir . $this->config['bootstrap'];

        if (!file_exists($bootstrapFile)) {
            throw new ModuleConfig(
                $this, "Laravel bootstrap file not found in $bootstrapFile.\nPlease provide a valid path to it using 'bootstrap' config param. "
            );
        }

        $app = require $bootstrapFile;

        return $app;
    }

    /**
     * Opens web page using route name and parameters.
     *
     * ```php
     * <?php
     * $I->amOnRoute('posts.create');
     * ?>
     * ```
     *
     * @param $route
     * @param array $params
     */
    public function amOnRoute($route, $params = [])
    {
        $url = $this->app['url']->route($route, $params);
        codecept_debug($url);
        $this->amOnPage($url);
    }

    /**
     * Opens web page by action name
     *
     * ```php
     * <?php
     * $I->amOnAction('PostsController@index');
     * ?>
     * ```
     *
     * @param $action
     * @param array $params
     */
    public function amOnAction($action, $params = [])
    {
        $url = $this->app['url']->action($action, $params);
        $this->amOnPage($url);
    }

    /**
     * Checks that current url matches route
     *
     * ```php
     * <?php
     * $I->seeCurrentRouteIs('posts.index');
     * ?>
     * ```
     * @param $route
     * @param array $params
     */
    public function seeCurrentRouteIs($route, $params = array())
    {
        $this->seeCurrentUrlEquals($this->app['url']->route($route, $params, false));
    }

    /**
     * Checks that current url matches action
     *
     * ```php
     * <?php
     * $I->seeCurrentActionIs('PostsController@index');
     * ?>
     * ```
     *
     * @param $action
     * @param array $params
     */
    public function seeCurrentActionIs($action, $params = array())
    {
        $this->seeCurrentUrlEquals($this->app['url']->action($action, $params, false));
    }

    /**
     * Assert that the session has a given list of values.
     *
     * @param  string|array $key
     * @param  mixed $value
     * @return void
     */
    public function seeInSession($key, $value = null)
    {
        if (is_array($key)) {
            $this->seeSessionHasValues($key);
            return;
        }

        if (is_null($value)) {
            $this->assertTrue($this->app['session']->has($key));
        } else {
            $this->assertEquals($value, $this->app['session']->get($key));
        }
    }

    /**
     * Assert that the session has a given list of values.
     *
     * @param  array $bindings
     * @return void
     */
    public function seeSessionHasValues(array $bindings)
    {
        foreach ($bindings as $key => $value) {
            if (is_int($key)) {
                $this->seeInSession($value);
            } else {
                $this->seeInSession($key, $value);
            }
        }
    }

    /**
     * Assert that Session has error messages
     * The seeSessionHasValues cannot be used, as Message bag Object is returned by Laravel4
     *
     * Useful for validation messages and generally messages array
     *  e.g.
     *  return `Redirect::to('register')->withErrors($validator);`
     *
     * Example of Usage
     *
     * ``` php
     * <?php
     * $I->seeSessionErrorMessage(array('username'=>'Invalid Username'));
     * ?>
     * ```
     * @param array $bindings
     */
    public function seeSessionErrorMessage(array $bindings)
    {
        $this->seeSessionHasErrors(); //check if  has errors at all
        $errorMessageBag = $this->app['session']->get('errors');
        foreach ($bindings as $key => $value) {
            $this->assertEquals($value, $errorMessageBag->first($key));
        }
    }

    /**
     * Assert that the session has errors bound.
     *
     * @return bool
     */
    public function seeSessionHasErrors()
    {
        $this->seeInSession('errors');
    }

    /**
     * Set the currently logged in user for the application.
     * Takes either `UserInterface` instance or array of credentials.
     *
     * @param  \Illuminate\Auth\UserInterface|array $user
     * @param  string $driver
     * @return void
     */
    public function amLoggedAs($user, $driver = null)
    {
        if ($user instanceof \Illuminate\Auth\UserInterface) {
            $this->app['auth']->driver($driver)->setUser($user);
        } else {
            $this->app['auth']->driver($driver)->attempt($user);
        }
    }

    /**
     * Logs user out
     */
    public function logout()
    {
        $this->app['auth']->logout();
    }

    /**
     * Checks that user is authenticated
     */
    public function seeAuthentication()
    {
        $this->assertTrue($this->app['auth']->check(), 'User is not logged in');
    }

    /**
     * Check that user is not authenticated
     */
    public function dontSeeAuthentication()
    {
        $this->assertFalse($this->app['auth']->check(), 'User is logged in');
    }

    /**
     * Return an instance of a class from the IoC Container.
     * (http://laravel.com/docs/ioc)
     *
     * Example
     * ``` php
     * <?php
     * // In Laravel
     * App::bind('foo', function($app)
     * {
     *     return new FooBar;
     * });
     *
     * // Then in test
     * $service = $I->grabService('foo');
     *
     * // Will return an instance of FooBar, also works for singletons.
     * ?>
     * ```
     *
     * @param  string $class
     * @return mixed
     */
    public function grabService($class)
    {
        return $this->app[$class];
    }

    /**
     * Inserts record into the database.
     *
     * ``` php
     * <?php
     * $user_id = $I->haveRecord('users', array('name' => 'Davert'));
     * ?>
     * ```
     *
     * @param $model
     * @param array $attributes
     * @return mixed
     */
    public function haveRecord($model, $attributes = array())
    {
        $id = $this->app['db']->table($model)->insertGetId($attributes);
        if (!$id) {
            $this->fail("Couldnt insert record into table $model");
        }
        return $id;
    }

    /**
     * Checks that record exists in database.
     *
     * ``` php
     * $I->seeRecord('users', array('name' => 'davert'));
     * ```
     *
     * @param $model
     * @param array $attributes
     */
    public function seeRecord($model, $attributes = array())
    {
        $record = $this->findRecord($model, $attributes);
        if (!$record) {
            $this->fail("Couldn't find $model with " . json_encode($attributes));
        }
        $this->debugSection($model, json_encode($record));
    }

    /**
     * Checks that record does not exist in database.
     *
     * ``` php
     * <?php
     * $I->dontSeeRecord('users', array('name' => 'davert'));
     * ?>
     * ```
     *
     * @param $model
     * @param array $attributes
     */
    public function dontSeeRecord($model, $attributes = array())
    {
        $record = $this->findRecord($model, $attributes);
        $this->debugSection($model, json_encode($record));
        if ($record) {
            $this->fail("Unexpectedly managed to find $model with " . json_encode($attributes));
        }
    }

    /**
     * Retrieves record from database
     *
     * ``` php
     * <?php
     * $category = $I->grabRecord('users', array('name' => 'davert'));
     * ?>
     * ```
     *
     * @param $model
     * @param array $attributes
     * @return mixed
     */
    public function grabRecord($model, $attributes = array())
    {
        return $this->findRecord($model, $attributes);
    }

    /**
     * @param $model
     * @param array $attributes
     * @return mixed
     */
    protected function findRecord($model, $attributes = array())
    {
        $query = $this->app['db']->table($model);
        foreach ($attributes as $key => $value) {
            $query->where($key, $value);
        }
        return $query->first();
    }

}
