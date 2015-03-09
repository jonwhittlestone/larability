<?php namespace Jonwhittlestone\Larability;

use Illuminate\Support\ServiceProvider;

class LarabilityServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('jonwhittlestone/larability');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{

		$this->app->booting(function()
		{
  		$loader = \Illuminate\Foundation\AliasLoader::getInstance();
  		$loader->alias('Larability', 'Jonwhittlestone\Larability\Facades\Larability');
		});

		$this->app['larability'] = $this->app->share(function($app){
			return new Larability;
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('larability');
	}

}
