<?php 
namespace Scarlets\Library;
use \Scarlets;

/*
---------------------------------------------------------------------------
| Scarlets Cache
---------------------------------------------------------------------------
|
| With cache you can save any data to the volatile memory or keep it
| even the process was shutdown. 
|
| It's very recommended to have msgpack on your system
| https://github.com/msgpack/msgpack-php
|
*/

class Cache{
	public static $path = '';
	public static $expiration = [];
	public static $expirationPath = '';
	public static $lastCheck = 0;

	public static function init(){
		$config = Scarlets\Config::load('filesystem');
		$settings = &$config['filesystem.storage'][$config['filesystem.cache_storage']];

		if($settings['driver'] === 'localfile'){
			self::$path = &$settings['path'];
			self::$expirationPath = $settings['path'].'/__expiration.srz';
			self::$lastCheck = filemtime(self::$expirationPath);
			self::$expiration = unserialize(file_get_contents(self::$expirationPath));
		}
	}

	private static function reloadExpiration(){
		$temp = filemtime(self::$expirationPath);
		if(self::$lastCheck < $temp){
			self::$lastCheck = $temp;
			self::$expiration = unserialize(file_get_contents(self::$expirationPath));
		}
	}

	public static function get($key){
		if(!isset(self::$expiration[$key]))
			return false;

		if(self::$expiration[$key] !== 0){
			self::reloadExpiration();
			if(self::$expiration[$key] > time()){
				unset(self::$expiration[$key]);
				unlink($path."/$key.cache");
				file_put_contents(self::$expirationPath, serialize(self::$expiration[$key]));
				return false;
			}
		}

		return file_get_contents($path."/$key.cache");
	}

	public static function set($key, $value, $seconds=0){
		if($seconds !== 0){
			self::$expiration[$key] = $seconds + time();
			file_put_contents(self::$expirationPath, serialize(self::$expiration[$key]));
		}

		if(!isset(self::$expiration[$key])){
			self::$expiration[$key] = 0;
			file_put_contents(self::$expirationPath, serialize(self::$expiration[$key]));
		}

		file_put_contents($path."/$key.cache", $value);
	}

	public static function has($key){
		if(!isset(self::$expiration[$key]))
			return false;

		if(self::$expiration[$key] !== 0){
			self::reloadExpiration();
			if(self::$expiration[$key] > time()){
				unset(self::$expiration[$key]);
				unlink($path."/$key.cache");
				file_put_contents(self::$expirationPath, serialize(self::$expiration[$key]));
				return false;
			}
		}

		return true;
	}

	// Get and forget
	public static function &pull($key, $value){
		if(!isset(self::$expiration[$key]))
			return false;

		$data = file_get_contents($path."/$key.cache");
		unlink($path."/$key.cache");
		unset(self::$expiration[$key]);
		file_put_contents(self::$expirationPath, serialize(self::$expiration[$key]));
		return $data;
	}

	public static function forget($key){
		unlink($path."/$key.cache");
		if(!isset(self::$expiration[$key]))
			return false;

		unset(self::$expiration[$key]);
		file_put_contents(self::$expirationPath, serialize(self::$expiration[$key]));
	}
	
	public static function flush($key, $value){
		self::$expiration = [];
		$list = glob($path.'/*.*');
		foreach($list as &$value){
			unlink($value);
		}
		return true;
	}
	
	public static function extendTime($key, $seconds){
		if(!isset(self::$expiration[$key]))
			return false;

		self::reloadExpiration();
		if(self::$expiration[$key] >= time()){
			unset(self::$expiration[$key]);
			unlink($path."/$key.cache");
			file_put_contents(self::$expirationPath, serialize(self::$expiration[$key]));
			return false;
		}

		self::$expiration[$key] = $seconds + time();
		file_put_contents(self::$expirationPath, serialize(self::$expiration[$key]));
		return true;
	}
}
Cache::init();
/*
---------------------------------------------------------------------------
| Micro-optimization
---------------------------------------------------------------------------
|
|  - Don't use JSON for serialize cache because it's slow on large data
|  - Don't use native unserialize for user input because it's exploitable
|  - If you use native serialize, make sure you save it to file and load
|	 from that file only
|
| PHP 7.0
|  - use native serialize when you serialize often and unserialize rarely
|  - use igbinary when serialize rarely and unserialize often
|  - use msgpack if you don't know which is the best
| source: https://blobfolio.com/2017/03/benchmark-php7-serialization/
|
|  Priority: msgpack -> native -> igbinary -> JSON
|
*/