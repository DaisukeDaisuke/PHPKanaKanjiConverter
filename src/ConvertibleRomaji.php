<?php

declare(strict_types=1);


namespace app;

/**
 * ローマ字変換＋簡易かな→漢字変換の実装例
 *
 * 注意:
 * - 本実装は簡易実演用です。実用の精度を上げるには大辞書や形態素解析器（MeCab 等）との連携を推奨します。
 */
class ConvertibleRomaji{
	private $originText;
	private $lowerText;

	// 変換辞書（長い候補を優先して扱うことを前提）
	private static $map = [
		// 拗音・長めのもの（3文字）
		'kya' => 'きゃ', 'kyu' => 'きゅ', 'kyo' => 'きょ',
		'gya' => 'ぎゃ', 'gyu' => 'ぎゅ', 'gyo' => 'ぎょ',
		'sha' => 'しゃ', 'shu' => 'しゅ', 'sho' => 'しょ',
		'cha' => 'ちゃ', 'chu' => 'ちゅ', 'cho' => 'ちょ',
		'nya' => 'にゃ', 'nyu' => 'にゅ', 'nyo' => 'にょ',
		'hya' => 'ひゃ', 'hyu' => 'ひゅ', 'hyo' => 'ひょ',
		'mya' => 'みゃ', 'myu' => 'みゅ', 'myo' => 'みょ',
		'rya' => 'りゃ', 'ryu' => 'りゅ', 'ryo' => 'りょ',
		'bya' => 'びゃ', 'byu' => 'びゅ', 'byo' => 'びょ',
		'pya' => 'ぴゃ', 'pyu' => 'ぴゅ', 'pyo' => 'ぴょ',
		'ja' => 'じゃ', 'ju' => 'じゅ', 'jo' => 'じょ', // 2-3文字混在するが最長一致で扱う

		// 2文字（子音+母音等）
		'ka' => 'か', 'ki' => 'き', 'ku' => 'く', 'ke' => 'け', 'ko' => 'こ',
		'sa' => 'さ', 'shi' => 'し', 'si' => 'し', 'su' => 'す', 'se' => 'せ', 'so' => 'そ',
		'ta' => 'た', 'chi' => 'ち', 'ti' => 'ち', 'tsu' => 'つ', 'tu' => 'つ', 'te' => 'て', 'to' => 'と',
		'na' => 'な', 'ni' => 'に', 'nu' => 'ぬ', 'ne' => 'ね', 'no' => 'の',
		'ha' => 'は', 'hi' => 'ひ', 'fu' => 'ふ', 'hu' => 'ふ', 'he' => 'へ', 'ho' => 'ほ',
		'ma' => 'ま', 'mi' => 'み', 'mu' => 'む', 'me' => 'め', 'mo' => 'も',
		'ya' => 'や', 'yu' => 'ゆ', 'yo' => 'よ',
		'ra' => 'ら', 'ri' => 'り', 'ru' => 'る', 're' => 'れ', 'ro' => 'ろ',
		'wa' => 'わ', 'wo' => 'を',
		'ga' => 'が', 'gi' => 'ぎ', 'gu' => 'ぐ', 'ge' => 'げ', 'go' => 'ご',
		'za' => 'ざ', 'ji' => 'じ', 'zu' => 'ず', 'ze' => 'ぜ', 'zo' => 'ぞ',
		'da' => 'だ', 'de' => 'で', 'do' => 'ど',
		'ba' => 'ば', 'bi' => 'び', 'bu' => 'ぶ', 'be' => 'べ', 'bo' => 'ぼ',
		'pa' => 'ぱ', 'pi' => 'ぴ', 'pu' => 'ぷ', 'pe' => 'ぺ', 'po' => 'ぽ',

		// 母音と単独子音
		'a' => 'あ', 'i' => 'い', 'u' => 'う', 'e' => 'え', 'o' => 'お',
		'n' => 'ん', 'm' => 'ん',

		// --- 小文字系（x / l 系） ---
		'xa' => 'ぁ', 'la' => 'ぁ',
		'xi' => 'ぃ', 'li' => 'ぃ',
		'xu' => 'ぅ', 'lu' => 'ぅ',
		'xe' => 'ぇ', 'le' => 'ぇ',
		'xo' => 'ぉ', 'lo' => 'ぉ',

		'xtu' => 'っ', 'ltu' => 'っ',

		'xya' => 'ゃ', 'lya' => 'ゃ',
		'xyu' => 'ゅ', 'lyu' => 'ゅ',
		'xyo' => 'ょ', 'lyo' => 'ょ',

// --- zye 系 ---
		'zya' => 'じゃ',
		'zyu' => 'じゅ',
		'zyo' => 'じょ',
		'zye' => 'じぇ',
		'zyi' => 'じぃ',

// --- 拡張拗音 ---
		'kye' => 'きぇ',
		'kyi' => 'きぃ',
		'gye' => 'ぎぇ',
		'gyi' => 'ぎぃ',

		'sye' => 'しぇ',
		'syi' => 'しぃ',

		'jye' => 'じぇ',
		'jyi' => 'じぃ',

		'tye' => 'ちぇ',
		'tyi' => 'ちぃ',

		'dye' => 'ぢぇ',
		'dyi' => 'ぢぃ',

		'nye' => 'にぇ',
		'nyi' => 'にぃ',

		'hye' => 'ひぇ',
		'hyi' => 'ひぃ',

		'bye' => 'びぇ',
		'byi' => 'びぃ',

		'pye' => 'ぴぇ',
		'pyi' => 'ぴぃ',

		'mye' => 'みぇ',
		'myi' => 'みぃ',

		'rye' => 'りぇ',
		'ryi' => 'りぃ',

// --- gwa / kwa 系 ---
		'gwa' => 'ぐぁ',
		'gwi' => 'ぐぃ',
		'gwu' => 'ぐぅ',
		'gwe' => 'ぐぇ',
		'gwo' => 'ぐぉ',

		'kwa' => 'くぁ',
		'kwi' => 'くぃ',
		'kwu' => 'くぅ',
		'kwe' => 'くぇ',
		'kwo' => 'くぉ',

// --- q 系 ---
		'qa' => 'くぁ',
		'qi' => 'くぃ',
		'qwu' => 'くぅ',
		'qe' => 'くぇ',
		'qo' => 'くぉ',

// --- th / dh 系 ---
		'thi' => 'てぃ',
		'the' => 'てぇ',
		'thu' => 'てゅ',
		'tha' => 'てゃ',
		'tho' => 'てょ',

		'dhi' => 'でぃ',
		'dhe' => 'でぇ',
		'dhu' => 'でゅ',
		'dha' => 'でゃ',
		'dho' => 'でょ',

// --- tw / dw 系 ---
		'twa' => 'とぁ',
		'twi' => 'とぃ',
		'twu' => 'とぅ',
		'twe' => 'とぇ',
		'two' => 'とぉ',

		'dwa' => 'どぁ',
		'dwi' => 'どぃ',
		'dwu' => 'どぅ',
		'dwe' => 'どぇ',
		'dwo' => 'どぉ',

// --- f 拡張 ---
		'fa' => 'ふぁ',
		'fi' => 'ふぃ',
		'fe' => 'ふぇ',
		'fo' => 'ふぉ',
		'fwu' => 'ふぅ',

// --- w 拡張 ---
		'wha' => 'うぁ',
		'who' => 'うぉ',
		'wi' => 'うぃ',
		'we' => 'うぇ',

// --- ぢ / づ ---
		'di' => 'ぢ',
		'du' => 'づ',
	];

	public function __construct($text = ''){
		$this->originText = $text;
		$this->lowerText = mb_strtolower($text, 'UTF-8');
	}

	/**
	 * ローマ字をひらがなに変換する（簡易ルール）
	 *
	 * $removeIllegalFlag: true の場合、変換できなかった文字は削る
	 */
	public function toHiragana($removeIllegalFlag = true){
		// 正規化: マクロン（ō 等） -> ou / uu のように簡易変換
		$norm = str_replace(
			['ā', 'ī', 'ū', 'ē', 'ō', 'Ā', 'Ī', 'Ū', 'Ē', 'Ō'],
			['aa', 'ii', 'uu', 'ee', 'ou', 'aa', 'ii', 'uu', 'ee', 'ou'],
			$this->lowerText
		);

		// 半角スペースや記号はそのままにするためトークン化（簡易）
		$tokens = preg_split('/(\s+)/u', $norm, -1, PREG_SPLIT_DELIM_CAPTURE);

		$out = '';
		foreach($tokens as $token){
			// 空白トークンそのまま
			if(preg_match('/^\s+$/u', $token)){
				$out .= $token;
				continue;
			}
			$out .= $this->convertTokenToHiragana($token, $removeIllegalFlag);
		}

		$out = str_replace("んん", "ん", $out);

		return $out;
	}

	private function convertTokenToHiragana($text, $removeIllegalFlag){
		$pos = 0;
		$len = mb_strlen($text, 'UTF-8');
		$res = '';

		// キャッシュ用に最大キー長を計算（ここは 3 で十分）
		$maxKeyLen = 4;

		while($pos < $len){
			// 促音（っ）の処理: 同一子音が連続する場合
			$cur = mb_substr($text, $pos, 1, 'UTF-8');
			$next = ($pos + 1 < $len) ? mb_substr($text, $pos + 1, 1, 'UTF-8') : '';
			if($next !== '' && $cur === $next && preg_match('/[bcdfghjklmnpqrstvwxyz]/i', $cur)){
				// ただし n の場合は促音ではない（ん の可能性）
				if($cur !== 'n'){
					$res .= 'っ';
					$pos += 1; // 同一子音を一文字分スキップ（次のループで残りを処理）
					continue;
				}
			}

			// 省略可能: 各長さ（長い順）でキー探索（最大3文字）
			$matched = false;
			for($l = min($maxKeyLen, $len - $pos); $l >= 1; $l--){
				$substr = mb_substr($text, $pos, $l, 'UTF-8');
				// ローマ字辞書は小文字前提
				$substrLower = mb_strtolower($substr, 'UTF-8');

				// 特殊なケース: "n'" のような撥音明示
				if($substrLower === "n'"){
					$res .= 'ん';
					$pos += $l;
					$matched = true;
					break;
				}

				if(isset(self::$map[$substrLower])){
					$res .= self::$map[$substrLower];
					$pos += $l;
					$matched = true;
					break;
				}
			}

			if(!$matched){
				// マッチしない場合:
				// - 英数字や記号はそのまま（必要に応じて除去）
				$ch = mb_substr($text, $pos, 1, 'UTF-8');
				if(preg_match('/[a-z0-9]/i', $ch)){
					if($removeIllegalFlag){
						// 何もしない（削る）
					}else{
						$res .= $ch;
					}
				}else{
					// 日本語やその他の文字はそのまま残す
					$res .= $ch;
				}
				$pos += 1;
			}
		}

		return $res;
	}

	public function toKatakana($removeIllegalFlag = true){
		$hiragana = $this->toHiragana($removeIllegalFlag);
		// ひらがなをカタカナに（全角のひらがな範囲→カタカナ範囲へ）
		// mb_convert_kana の 'C' は半角カナ→全角カナなどの変換用なので使わない
		// ここでは Unicode の変換（ひらがな→カタカナ）を文字ごとに行う
		$out = '';
		$len = mb_strlen($hiragana, 'UTF-8');
		for($i = 0; $i < $len; $i++){
			$ch = mb_substr($hiragana, $i, 1, 'UTF-8');
			$code = mb_ord($ch, 'UTF-8');
			// ひらがな範囲: U+3041 - U+3096
			if($code >= 0x3041 && $code <= 0x3096){
				$out .= mb_chr($code + 0x60, 'UTF-8'); // カタカナはひらがなより0x60進んでいる
			}else{
				$out .= $ch;
			}
		}
		return $out;
	}
}