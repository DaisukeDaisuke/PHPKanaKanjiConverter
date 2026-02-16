<?php

declare(strict_types=1);


namespace kanakanjiconverter;

final class SystemDictionary{
	public static function apply(PHPKanaKanjiConverter $converter) : void{
		$dict = new UserDictionary();
		$dict->addAll([
			['reading' => 'tikakude',       'surface' => '近くで',  'mode' => UserDictionary::MODE_SERVER, 'word_cost' => -1000, 'pos' => "名詞"],
			['reading' => 'daare',     'surface' => 'だあれ',  'mode' => UserDictionary::MODE_SERVER,'word_cost' => -6000, 'pos' => "名詞"],
			['reading' => 'test',       'surface' => 'テスト',  'mode' => UserDictionary::MODE_REPLACE, 'word_cost' => -5000, 'pos' => "名詞"],
			['reading' => 'oite',       'surface' => 'おいて',  'mode' => UserDictionary::MODE_SERVER, 'word_cost' => -5000, 'pos' => "名詞"],
			['reading' => 'masumasu',       'surface' => 'ますます',  'mode' => UserDictionary::MODE_SERVER, 'word_cost' => -5000, 'pos' => "名詞"],
			['reading' => 'banngohann',       'surface' => '晩ご飯',  'mode' => UserDictionary::MODE_SERVER, 'word_cost' => -5000, 'pos' => "名詞"],
			['reading' => 'hataite',       'surface' => 'はたいて',  'mode' => UserDictionary::MODE_SERVER, 'word_cost' => -5000, 'pos' => "名詞"],
		]);
		$converter->registerUserDict("system", $dict);
	}
}
