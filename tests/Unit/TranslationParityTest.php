<?php

use Tests\TestCase;

uses(TestCase::class);

/**
 * Every locale must ship the exact same key set as the English source, with
 * non-empty values. Missing keys fall back to English at runtime, but the
 * parity test keeps translators honest before that fallback is needed.
 */
test('every locale shares the english dictionary keys', function () {
    $english = appDictionary('en');

    expect($english)->not->toBeEmpty();

    $englishKeys = array_keys($english);
    sort($englishKeys);

    foreach (['bn', 'fr', 'de', 'es'] as $locale) {
        $dictionary = appDictionary($locale);
        $keys = array_keys($dictionary);
        sort($keys);

        expect($keys)->toBe($englishKeys)
            ->and(array_values($dictionary))->each->toBeString();
    }
});

/**
 * @return array<string, string>
 */
function appDictionary(string $locale): array
{
    $decoded = json_decode(
        (string) file_get_contents(lang_path("app/{$locale}.json")),
        true,
    );

    expect($decoded)->toBeArray();

    return $decoded;
}
