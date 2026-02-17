<?php

declare(strict_types=1);


namespace kanakanjiconverter;

final class SystemDictionary{
	public static function apply(PHPKanaKanjiConverter $converter) : void{
		$dict = new UserDictionary();
		//一部はローマ字の変換回避のみおこなわれ、置換は行われない。ここではこの点を考慮せずに辞書に登録しているため、結果が小文字である可能性がある。
		$dict->addAll([
			['reading' => 'test',         'surface' => 'テスト',    'mode' => UserDictionary::MODE_REPLACE],
			['reading' => 'gpt',          'surface' => 'GPT',       'mode' => UserDictionary::MODE_REPLACE],
			['reading' => 'phar',         'surface' => 'Phar',      'mode' => UserDictionary::MODE_REPLACE],
			['reading' => 'php',          'surface' => 'PHP',       'mode' => UserDictionary::MODE_REPLACE],
			['reading' => 'python',       'surface' => 'Python',    'mode' => UserDictionary::MODE_REPLACE],
			['reading' => 'rust',         'surface' => 'Rust',      'mode' => UserDictionary::MODE_REPLACE],
			['reading' => 'then',         'surface' => 'then',      'mode' => UserDictionary::MODE_REPLACE],
			['reading' => 'JavaScript',   'surface' => 'JavaScript','mode' => UserDictionary::MODE_REPLACE],
			['reading' => 'tilyattogpt',  'surface' => 'chatGPT',  'mode' => UserDictionary::MODE_REPLACE],
			['reading' => 'claude',  	'surface' => 'Claude',  'mode' => UserDictionary::MODE_REPLACE],
			['reading' => 'chatgpt',  	'surface' => 'ChatGPT',  'mode' => UserDictionary::MODE_REPLACE],
		]);
		$converter->registerUserDict("system_replace", $dict);


		$NOUN_GENERAL = 1852/* findPosId(id.def, '名詞','一般') 実値 */;//1852 名詞,一般,*,*,*,*,*
		$ADVERB_GENERAL = 12/* '副詞','一般' の id */;//12 副詞,一般,*,*,*,*,*
		$NOUN_PRONOUN = 1900/* '名詞','代名詞' の id */;//1900 名詞,代名詞,一般,*,*,*,*
		$PROPER_NOUN = 1921 /* 固有名詞のid */;//1921 名詞,固有名詞,一般,*,*,*,*
		$CASE_PARTICLE = 368/*助詞の id*/; //368 助詞,格助詞,一般,*,*,*,から
		$VERB_INDEPENDENT = 577;
		// 安全な word_cost に調整した例
		$dict->addAll([
			[
				'reading'   => 'tikakude',
				'surface'   => '近くで',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => -1000,
				'pos'       => '副詞',
				'subpos'    => '一般',
				'pos_label' => '副詞-一般',
				'left_id'   => $ADVERB_GENERAL,
				'right_id'  => $ADVERB_GENERAL,
			],

			[
				'reading'   => 'daare',
				'surface'   => 'だあれ',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => -1200,
				'pos'       => '名詞',
				'subpos'    => '代名詞',
				'pos_label' => '名詞-代名詞',
				'left_id'   => $NOUN_PRONOUN,
				'right_id'  => $NOUN_PRONOUN,
			],

			[
				'reading'   => 'oite',
				'surface'   => 'おいて',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => -800,
				'pos'       => '助詞',
				'subpos'    => '格助詞',
				'pos_label' => '助詞-格助詞',
				'left_id'   => $CASE_PARTICLE,
				'right_id'  => $CASE_PARTICLE,
			],

			[
				'reading'   => 'masumasu',
				'surface'   => 'ますます',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => -1500,
				'pos'       => '副詞',
				'subpos'    => '一般',
				'pos_label' => '副詞-一般',
				'left_id'   => $ADVERB_GENERAL,
				'right_id'  => $ADVERB_GENERAL,
			],

			[
				'reading'   => 'banngohann',
				'surface'   => '晩ご飯',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => -1800,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => $NOUN_GENERAL,
				'right_id'  => $NOUN_GENERAL,
			],

			[
				'reading'   => 'hataite',
				'surface'   => 'はたいて',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => -800,
				'pos'       => '動詞',
				'subpos'    => '自立',
				'pos_label' => '動詞-自立',
				'left_id'   => $VERB_INDEPENDENT,
				'right_id'  => $VERB_INDEPENDENT,
			],
			[
				'reading'   => 'anni',
				'surface'   => 'Annihilation',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 2000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'maaiiya',
				'surface'   => 'まあいいや',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 1000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'maaiiyo',
				'surface'   => 'まあいいよ',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 1000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'ai',
				'surface'   => 'AI',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 2000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'ime',
				'surface'   => 'IME',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 2000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'op',
				'surface'   => 'Operator',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 2000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'unn',
				'surface'   => 'うん',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 2000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'unnsouda',
				'surface'   => 'うんそうだ',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 2000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'ipa',
				'surface'   => 'IPA',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 3000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'c++',
				'surface'   => 'C++',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 3000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'c#',
				'surface'   => 'C#',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 3000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'java',
				'surface'   => 'JAVA',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 3000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'lua',
				'surface'   => 'Lua',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 3000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'perl',
				'surface'   => 'Perl',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 3000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'ruby',
				'surface'   => 'Ruby',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 3000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'da-isuki',
				'surface'   => 'だーいすき',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 3000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'tamuro',
				'surface'   => 'たむろ',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 1000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'tewohana',
				'surface'   => '手を離',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => -1000,
				'pos'       => '動詞',
				'subpos'    => '自立',
				'pos_label' => '動詞-自立',
				'left_id'   => 577,
				'right_id'  => 577,
			],
			[
				'reading'   => 'ひんしつのむら',
				'surface'   => '品質のむら',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => -2000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'もとに',
				'surface'   => 'もとに',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 1000,
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,
				'right_id'  => 1852,
			],
			[
				'reading'   => 'すること',
				'surface'   => 'すること',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => -500,  // むしろボーナスを与えて優先的に選ばせる
				'pos'       => '名詞',
				'subpos'    => '非自立',
				'pos_label' => '名詞-非自立',
				'left_id'   => 2065,  // 名詞-非自立のID（要確認）
				'right_id'  => 2065,
			],
			[
				'reading'   => 'すすめ',
				'surface'   => '勧め',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 2200,  // 2701(進め)より低く設定
				'pos'       => '動詞',
				'subpos'    => '自立',
				'pos_label' => '動詞-自立',
				'left_id'   => 701,   // 動詞,自立,*,*,一段,連用形
				'right_id'  => 701,
			],

			// 2. 「撮る」を優遇
			[
				'reading'   => 'とる',
				'surface'   => '撮る',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 2000,  // 取る(2398)より低く
				'pos'       => '動詞',
				'subpos'    => '自立',
				'pos_label' => '動詞-自立',
				'left_id'   => 837,   // 動詞,自立,*,*,五段動詞,基本形
				'right_id'  => 837,
			],

			// 3. 「印」を優遇
			[
				'reading'   => 'いん',
				'surface'   => '印',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 3500,  // イン(3922)より低く
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,  // 名詞,一般
				'right_id'  => 1852,
			],

			// 4. 「言っ」を優遇
			[
				'reading'   => 'いっ',
				'surface'   => '言っ',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => -250,  // 元の0よりさらに低く、行っ(43)より大幅に低く
				'pos'       => '動詞',
				'subpos'    => '自立',
				'pos_label' => '動詞-自立',
				'left_id'   => 828,   // 動詞,自立,*,*,五段・ワ行促音便,連用タ接続
				'right_id'  => 828,
			],

			// 4. 「が逝った」を優遇
			[
				'reading'   => 'がいった',
				'surface'   => 'が逝った',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => -250,  // 元の0よりさらに低く、行っ(43)より大幅に低く
				'pos'       => '動詞',
				'subpos'    => '自立',
				'pos_label' => '動詞-自立',
				'left_id'   => 828,   // 動詞,自立,*,*,五段・ワ行促音便,連用タ接続
				'right_id'  => 828,
			],

			// 5. 「川」を優遇
			[
				'reading'   => 'かわ',
				'surface'   => '川',
				'mode'      => UserDictionary::MODE_SERVER,
				'word_cost' => 3900,  // 皮(4195)より低く
				'pos'       => '名詞',
				'subpos'    => '一般',
				'pos_label' => '名詞-一般',
				'left_id'   => 1852,  // 名詞,一般
				'right_id'  => 1852,
			],
		]);

		$converter->registerUserDict("system_server", $dict);
	}
}
