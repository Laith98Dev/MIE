<?php

namespace FaigerSYS\MapImageEngine;

use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Utils;

class TranslateStrings{

	const DEFAULT_LANG = 'eng';

	/** @var string[] */
	private static array $strings = [];

	public static function init() : void{
		$lang = Server::getInstance()->getLanguage()->getLang();
		$owner = MapImageEngine::getInstance();

		$resource = $owner->getResource('strings/' . self::DEFAULT_LANG . '.ini');
		if($resource === null) throw new AssumptionFailedError("Default language file not found");

		$default_strings = Utils::assumeNotFalse(parse_ini_string(Utils::assumeNotFalse(stream_get_contents($resource))));
		if($strings = $owner->getResource('strings/' . $lang . '.ini')){
			$strings = Utils::assumeNotFalse(parse_ini_string(Utils::assumeNotFalse(stream_get_contents(Utils::assumeNotFalse($strings))))) + $default_strings;
		}else{
			$strings = $default_strings;
		}

		self::$strings = $strings;
	}

	public static function translate(string $str, string ...$args) : string{
		return sprintf(self::$strings[$str] ?? $str, ...$args);
	}
}
