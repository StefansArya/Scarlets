<?php 
namespace Scarlets\Internal;
use \Scarlets;
use \Scarlets\Console;
use \Scarlets\Extend\Strings;

/*
---------------------------------------------------------------------------
| Scarlets Unit Test
---------------------------------------------------------------------------
|
| Currently no description
|
*/

class UnitTest{
	private static $currentStatus = true;
	private static $statusMessage = null;
	public static function it($desc, $func){
		self::desc($desc);
		self::$currentStatus = true;
		self::$statusMessage = true;
		$error = null;

		ob_start();
		$memory = memory_get_usage();
		$time = microtime(true);

		try{
			$func('\Scarlets\Internal\UnitTest');
		} catch(UnitTestFailed $e){} catch(\Error $e){
			$before = '\Internal\Console';
			$after = "{closure}('\\\\Scarlets\\\\Inter";
			self::$currentStatus = false;
			$error = \Scarlets\Error::simplifyErrorMessage(E_ERROR, $e->__toString(), $e->getFile(), $e->getLine(), $before, $after);
			$error = str_replace('Message: Error: ', 'Message: ', $error);
		} catch(\Exception $e){
			self::$statusMessage = $e->getMessage();
		}

		$time = round((microtime(true) - $time) * 1000);

		$contents = ob_get_contents();
		ob_end_clean();

		if($error !== null){
			$contents .= "\n$error";
		}

		$mem = Strings::formatBytes(memory_get_peak_usage()-$memory);

		if(self::$currentStatus === true)
			$status = Console::chalk("✔ Success", 'green', true);
		elseif(self::$currentStatus === false)
			$status = Console::chalk("✘ Failed", 'red', true);
		else
			$status = Console::chalk('⣿ '.self::$currentStatus, 'yellow', true);

		echo "\n    $status    ~$time ms    Mem:$mem";

		if(self::$statusMessage !== null)
			echo "   ➜ ".Console::chalk(self::$statusMessage, self::$currentStatus === false ? 'red' : 'yellow', true);

		if($contents !== ''){
			echo Console::chalk("\n  ░▒▓█►  There are some outputted message for the above test:", 'cyan');
			$contents = explode("\n", $contents);
			foreach ($contents as &$line) {
				echo "\n      | $line";
			}
			echo "\n";
		}
	}

	public static function describe($text){
		echo "\n".Console::chalk($text, 'cyan');
	}

	private static function finish($type, $msg = null){
		$args = func_get_args();
		if(count($args) > 2)
			self::$statusMessage = vsprintf($msg, array_slice($args, 2));
		else self::$statusMessage = &$msg;

		if($type === false){
			self::$currentStatus = false;
			throw new UnitTestFailed();
		}
		elseif($type === true)
			self::$currentStatus = true;
		else self::$currentStatus = $type;
	}

	private static function desc(&$text){
		echo "\n • $text";
	}

	public static function equal($what, $with){
		if($what === $with)
			self::finish(true);
		elseif($what == $with)
			self::finish("Partially", "The data type was different");
		else
			self::finish(false, '%s != %s', $what, $with);
	}

	public static function true($what){
		if($what === true)
			self::finish(true);
		elseif($what == true)
			self::finish("Partially", '%s !== true', $what);
		else
			self::finish(false, '%s != true', $what);
	}

	public static function false($what){
		if($what === false)
			self::finish(true);
		elseif($what == false)
			self::finish("Partially", '%s !== false', $what);
		else
			self::finish(false, '%s != false', $what);
	}
}

// For throwing an failed event on the middle of execution
class UnitTestFailed extends \Exception{}