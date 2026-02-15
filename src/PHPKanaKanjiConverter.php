<?php

declare(strict_types=1);


namespace kanakanjiconverter;

final class PHPKanaKanjiConverter{

	private ConvertibleRomaji $romaji;
	private KanaKanjiConverter $kannziconverter;


	/**
	 * Constructor for initializing the ConvertibleRomaji instance and the KanaKanjiConverter with a dictionary path.
	 *
	 * @param bool $warmUP Determines whether to execute a warm-up operation during initialization.
	 * @return void
	 */
	public function __construct(){
		$this->romaji = new ConvertibleRomaji();
		//$hiragana = $romaji->toHiragana(true);

		$dictDir = realpath(__DIR__ . '/dictionary_oss') ?: (__DIR__ . '/dictionary_oss');
		$this->kannziconverter = new KanaKanjiConverter($dictDir);
	}

	/**
	 * Converts a given Romaji input string into Hiragana and performs a KanaKanji conversion to return the best matching results.
	 *
	 * @param string $input The input string in Romaji to be converted.
	 * @param int $numofbest Specifies the number of best matching results to return.
	 * @return array{best: array{text: string, tokens: array<int, array{surface: string, reading: string, word_cost: string, penalty: string}>}, cost: int, candidates: list<array{surface: string, reading: string, word_cost: string, penalty: string}>} An array of the best matching conversion results.
	 */
	public function convert(string $input, int $numofbest = 3) : array{
		$input = $this->romaji->toHiragana($input);
		return $this->kannziconverter->convert($input, $numofbest);
	}
}