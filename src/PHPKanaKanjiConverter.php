<?php

declare(strict_types=1);


namespace kanakanjiconverter;

final class PHPKanaKanjiConverter{

	private ConvertibleRomaji $romaji;
	private KanaKanjiConverter $kannziconverter;


	/**
	 * Constructor for initializing the ConvertibleRomaji instance and the KanaKanjiConverter with a dictionary path.
	 *
	 * @return void
	 */
	public function __construct(){
		$this->romaji = new ConvertibleRomaji();
		//$hiragana = $romaji->toHiragana(true);

		$dictDir = realpath(__DIR__ . '/dictionary_oss') ?: (__DIR__ . '/dictionary_oss');
		$this->kannziconverter = new KanaKanjiConverter($dictDir);
	}


	/**
	 * Converts the provided input string into Hiragana and then performs a Kana-Kanji conversion.
	 *
	 * @param string $input The input string to be converted.
	 * @param bool $removeIllegalFlag Optional flag to remove illegal characters during conversion. Default is false.
	 * @param int $numofbest The number of best conversion results to return. Default is 3.
	 *
	 * @return array An array of conversion results after performing Kana-Kanji conversion.
	 */
	public function convert(string $input, bool $removeIllegalFlag = false, int $numofbest = 3) : array{
		$input = $this->romaji->toHiragana($input, $removeIllegalFlag);
		return $this->kannziconverter->convert($input, $numofbest);
	}
}