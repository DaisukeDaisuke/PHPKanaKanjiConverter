<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use kanakanjiconverter\KanaKanjiConverter;

function formatDuration(int $seconds): string
{
	if ($seconds < 0) {
		$seconds = 0;
	}

	$hours = intdiv($seconds, 3600);
	$minutes = intdiv($seconds % 3600, 60);

	return sprintf('%02d時間%02d分', $hours, $minutes);
}

function formatMiB(int $bytes): string
{
	return sprintf('%.2fMB', $bytes / 1024 / 1024);
}

$baseDir = realpath(__DIR__ . '/../src/dictionary_oss');
if ($baseDir === false) {
	fwrite(STDERR, "error: dictionary_oss not found\n");
	exit(1);
}

$inputPath = $baseDir . DIRECTORY_SEPARATOR . 'dictionary10.txt';
$outputPath = $baseDir . DIRECTORY_SEPARATOR . 'newdictionary10.txt';

if (!is_file($inputPath)) {
	fwrite(STDERR, "error: dictionary10.txt not found\n");
	exit(1);
}

$converter = new KanaKanjiConverter($baseDir);

$in = fopen($inputPath, 'rb');
if ($in === false) {
	fwrite(STDERR, "error: cannot open input\n");
	exit(1);
}

$total = 0;
while (fgets($in) !== false) {
	$total++;
}

if (!rewind($in)) {
	fclose($in);
	fwrite(STDERR, "error: cannot rewind input\n");
	exit(1);
}

$out = fopen($outputPath, 'wb');
if ($out === false) {
	fclose($in);
	fwrite(STDERR, "error: cannot open output\n");
	exit(1);
}

$processed = 0;
$kept = 0;
$dropped = 0;
$outputBytes = 0;
$progressEvery = 1000;
$startedAt = hrtime(true);

while (($line = fgets($in)) !== false) {
	$processed++;

	$trimmed = rtrim($line, "\r\n");
	if ($trimmed === '') {
		continue;
	}

	$parts = explode("\t", $trimmed, 5);
	if (count($parts) < 5) {
		$written = fwrite($out, $line);
		if ($written !== false) {
			$outputBytes += $written;
		}
		$kept++;
		continue;
	}

	$reading = $parts[0];
	$surface = $parts[4];
	$result = $converter->convert($reading, 1);
	$best = $result['best']['text'] ?? '';

	if ($best !== $surface) {
		$written = fwrite($out, $line);
		if ($written !== false) {
			$outputBytes += $written;
		}
		$kept++;
	} else {
		$dropped++;
	}

	if (($processed % $progressEvery) === 0 || $processed === $total) {
		$elapsedSeconds = (int) floor((hrtime(true) - $startedAt) / 1_000_000_000);

		$progressRate = $total > 0 ? ($processed / $total) : 1.0;
		$keepRate = $processed > 0 ? ($kept / $processed) : 0.0;
		$dropRate = $processed > 0 ? ($dropped / $processed) : 0.0;

		$remainingSeconds = 0;
		if ($processed > 0 && $total > 0 && $progressRate > 0.0) {
			$estimatedTotalSeconds = (int) round($elapsedSeconds / $progressRate);
			$remainingSeconds = max(0, $estimatedTotalSeconds - $elapsedSeconds);
		}

		$estimatedOutputBytes = 0;
		if ($processed > 0 && $total > 0) {
			$estimatedOutputBytes = (int) round($outputBytes / $processed * $total);
		}

		$currentMemoryBytes = memory_get_usage(true);
		$peakMemoryBytes = memory_get_peak_usage(true);

		fwrite(
			STDOUT,
			sprintf(
				"\r進捗: %6.2f%%  残り: %s  経過: %s  処理: %d/%d  ドロップ: %d (%.2f%%)  出力: %s / 推定 %s  メモリ: %s  peak: %s",
				$processed > 0 ? (($processed / $total) * 100.0) : 100.0,
				formatDuration($remainingSeconds),
				formatDuration($elapsedSeconds),
				$processed,
				$total,
				$dropped,
				$dropRate * 100.0,
				formatMiB($outputBytes),
				formatMiB($estimatedOutputBytes),
				formatMiB($currentMemoryBytes),
				formatMiB($peakMemoryBytes)
			)
		);
	}
}

fclose($in);
fclose($out);

$dropRate = $processed > 0 ? ($dropped / $processed) * 100.0 : 0.0;
$keepRate = $processed > 0 ? ($kept / $processed) * 100.0 : 0.0;

fwrite(STDOUT, "\n");
fwrite(STDOUT, "processed={$processed}\n");
fwrite(STDOUT, "kept={$kept}\n");
fwrite(STDOUT, "dropped={$dropped}\n");
fwrite(STDOUT, sprintf("drop_rate=%.2f%%\n", $dropRate));
fwrite(STDOUT, sprintf("keep_rate=%.2f%%\n", $keepRate));
fwrite(STDOUT, "output_bytes={$outputBytes}\n");
fwrite(STDOUT, "output={$outputPath}\n");