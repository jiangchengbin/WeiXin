<?php namespace Jiangchengbin\WeiXin;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class WeiXinServiceProvider extends ServiceProvider {

	/**
	 * Boot the service provider.
	 *
	 * @return void
	 */
	public function boot()
	{
		
		
	}

	
	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app['WeiXin'] = $this->app->share(
            function ($app) {
                return new \Jiangchengbin\WeiXin\WeiXin();
            }
        );
	}

}
