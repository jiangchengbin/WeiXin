<?php namespace Jiangchengbin\WeiXin\Facades;

use Illuminate\Support\Facades\Facade;

class WeiXin extends Facade {

	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor()
	{
		return 'WeiXin';
	}
}
