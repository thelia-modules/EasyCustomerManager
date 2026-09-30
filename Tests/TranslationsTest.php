<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EasyCustomerManager\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A key missing from a catalogue falls back to the English text with no error: every key used in the
 * module has to be translated in each language it ships.
 */
final class TranslationsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function locales(): iterable
    {
        yield 'fr_FR' => ['fr_FR'];
        yield 'de_DE' => ['de_DE'];
    }

    #[DataProvider('locales')]
    public function testEveryKeyUsedByTheModuleIsTranslated(string $locale): void
    {
        $catalogue = require \dirname(__DIR__).'/I18n/'.$locale.'.php';
        self::assertIsArray($catalogue);

        $missing = array_values(array_filter(
            self::usedKeys(),
            static fn (string $key): bool => !isset($catalogue[$key]) || '' === $catalogue[$key] || $catalogue[$key] === $key,
        ));

        self::assertSame([], $missing);
    }

    /**
     * @return list<string>
     */
    private static function usedKeys(): array
    {
        $root = \dirname(__DIR__);
        $keys = [];
        $sources = [
            ...glob($root.'/templates/backOffice/default-twig/EasyCustomerManager/{,*/}*.twig', \GLOB_BRACE) ?: [],
            ...glob($root.'/{Controller,Form}/*.php', \GLOB_BRACE) ?: [],
        ];

        foreach ($sources as $source) {
            $code = (string) file_get_contents($source);

            // {{ 'key'|trans({}, domain) }} and trans('key', [...], EasyCustomerManager::DOMAIN_NAME)
            preg_match_all("/'((?:[^'\\\\]|\\\\.)+)'\\s*\\|\\s*trans\\(\\{[^}]*\\},\\s*(?:domain|'easycustomermanager')\\)/", $code, $twig);
            preg_match_all("/trans\\(\\s*'((?:[^'\\\\]|\\\\.)+)',\\s*\\[[^\\]]*\\],\\s*EasyCustomerManager::DOMAIN_NAME/", $code, $php);

            foreach ([...$twig[1], ...$php[1]] as $key) {
                $keys[] = stripslashes($key);
            }
        }

        $keys = array_values(array_unique($keys));
        self::assertNotEmpty($keys);

        return $keys;
    }
}
