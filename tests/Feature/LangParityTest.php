<?php

/**
 * Every English text must exist in Arabic (and the other way round), with the same :placeholders.
 * This stops a half-translated page from ever shipping.
 */
function flattenLang(array $lines, string $prefix = ''): array
{
    $flat = [];

    foreach ($lines as $key => $value) {
        $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

        is_array($value) ? $flat += flattenLang($value, $path) : $flat[$path] = (string) $value;
    }

    return $flat;
}

function placeholders(string $text): array
{
    preg_match_all('/:[a-zA-Z_]+/', $text, $found);
    $names = array_unique($found[0]);
    sort($names);

    return $names;
}

$files = ['landing', 'auth_ui', 'portal', 'payment', 'exam', 'report', 'settings'];

it('has an Arabic file for every English file', function (string $file): void {
    expect(file_exists(lang_path("en/{$file}.php")))->toBeTrue()
        ->and(file_exists(lang_path("ar/{$file}.php")))->toBeTrue();
})->with($files);

it('has exactly the same keys in English and Arabic', function (string $file): void {
    $en = array_keys(flattenLang(require lang_path("en/{$file}.php")));
    $ar = array_keys(flattenLang(require lang_path("ar/{$file}.php")));

    expect(array_values(array_diff($en, $ar)))->toBe([], "Missing in Arabic ({$file})")
        ->and(array_values(array_diff($ar, $en)))->toBe([], "Missing in English ({$file})");
})->with($files);

it('uses the same placeholders in both languages', function (string $file): void {
    $en = flattenLang(require lang_path("en/{$file}.php"));
    $ar = flattenLang(require lang_path("ar/{$file}.php"));
    $mismatches = [];

    foreach ($en as $key => $text) {
        if (isset($ar[$key]) && placeholders($text) !== placeholders($ar[$key])) {
            $mismatches[] = $key;
        }
    }

    expect($mismatches)->toBe([]);
})->with($files);

it('has no empty translations', function (string $file): void {
    $empty = [];

    foreach (['en', 'ar'] as $lang) {
        foreach (flattenLang(require lang_path("{$lang}/{$file}.php")) as $key => $text) {
            if (trim($text) === '') {
                $empty[] = "{$lang}.{$file}.{$key}";
            }
        }
    }

    expect($empty)->toBe([]);
})->with($files);

it('keeps lists the same length in both languages', function (string $file): void {
    $lists = function (array $lines, string $prefix = '') use (&$lists): array {
        $found = [];
        foreach ($lines as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $found[$path] = count($value);
                $found += $lists($value, $path);
            }
        }

        return $found;
    };

    expect($lists(require lang_path("ar/{$file}.php")))->toBe($lists(require lang_path("en/{$file}.php")));
})->with($files);
