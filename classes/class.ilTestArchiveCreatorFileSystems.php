<?php

declare(strict_types=1);

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/


use ILIAS\Filesystem\Filesystem;
use ILIAS\Filesystem\Provider\DelegatingFilesystemFactory;
use ILIAS\Filesystem\Provider\Configuration\LocalConfig;
use ILIAS\Filesystem\Util\LegacyPathHelper;
use ILIAS\Filesystem\Provider\FlySystem\FlySystemFilesystemFactory;

/**
 * Class ilTestArchiveCreatorFileSystems
 *
 * This class extends the functionality of LegacyPathHelper with additional file systems for:
 * ./Modules    some css code is taken from there
 * ./templates
 *
 * Instead of throwing an exception this class will return null if no file system fits
 *
 * @see \ILIAS\Filesystem\Util\LegacyPathHelper
 */
class ilTestArchiveCreatorFileSystems
{
    protected Filesystem $modules;
    protected Filesystem $services;
    protected Filesystem $templates;

    public function __construct()
    {
        global $DIC;

        /** @var DelegatingFilesystemFactory $factory */
        $factory = $DIC['filesystem.factory'];

        $this->modules = $factory->getLocal(new LocalConfig(ILIAS_ABSOLUTE_PATH . '/Modules'), true);
        $this->services = $factory->getLocal(new LocalConfig(ILIAS_ABSOLUTE_PATH . '/Services'), true);
        $this->templates = $factory->getLocal(new LocalConfig(ILIAS_ABSOLUTE_PATH . '/templates'), true);
    }

    /**
     * Get the temporary filesystem without whitelist decorator
     * Used to prevent .sec files written when fonts are copied to the archive
     */
    public function getPureTemp(): Filesystem
    {
        $factory = new FlySystemFilesystemFactory();
        return $factory->getLocal(new LocalConfig(CLIENT_DATA_DIR . '/temp'));
    }


    /**
     * Get the plugin specific relation from paths to filesystems
     * @return array path => file system
     */
    private function systemsByPath(): array
    {
        return [
            './Modules' => $this->modules,
            './Services' => $this->services,
            './templates' => $this->templates,
            ILIAS_ABSOLUTE_PATH . '/Modules' => $this->modules,
            ILIAS_ABSOLUTE_PATH . '/Services' => $this->services,
            ILIAS_ABSOLUTE_PATH . '/templates' => $this->templates,
        ];
    }


    /**
     * Tries to fetch the filesystem responsible for the absolute path.
     * Please note that the function is case-sensitive.
     *
     * Relative paths are also detected for the ILIAS web storage like './data/default'
     * @param string $absolute_path The absolute used for the filesystem search.
     * @return ?Filesystem                   The responsible filesystem for the given path.
     */
    public function deriveFilesystemFrom(string $absolute_path): ?Filesystem
    {
        try {
            // first try additional filesystems for the plugin
            foreach ($this->systemsByPath() as $path => $system) {
                if (self::checkPossiblePath($path, $absolute_path)) {
                    return $system;
                }
            }
            // then try the standard filesystems
            return LegacyPathHelper::deriveFilesystemFrom($absolute_path);
        } catch (Exception $e) {
            return null;
        }
    }


    /**
     * Creates a relative path from an absolute path which starts with a valid storage location.
     * The primary use case for this method is to trim the path after the filesystem was fetch via the deriveFilesystemFrom method.
     *
     * @param string $absolute_path         The path which should be trimmed.
     * @return ?string                      The trimmed relative path.
     * @see LegacyPathHelper::deriveFilesystemFrom()
     */
    public function createRelativePath(string $absolute_path): ?string
    {
        try {
            // first try additional filesystems for the plugin
            foreach ($this->systemsByPath() as $path => $system) {
                if (self::checkPossiblePath($path, $absolute_path)) {
                    return self::resolveRelativePath($path, $absolute_path);
                }
            }
            // then try the standard filesystems
            return LegacyPathHelper::createRelativePath($absolute_path);

        } catch (Exception $e) {
            return null;
        }
    }

    private static function resolveRelativePath(string $possible_path, string $absolute_path): string
    {
        $real_possible_path = realpath($possible_path);

        switch (true) {
            case $possible_path === $absolute_path:
            case $real_possible_path === $absolute_path:
                return "";
            case strpos($absolute_path, $possible_path) === 0:
                return substr(
                    $absolute_path,
                    strlen($possible_path) + 1
                );                             //also remove the trailing slash
            case strpos($absolute_path, $real_possible_path) === 0:
                return substr(
                    $absolute_path,
                    strlen($real_possible_path) + 1
                );                             //also remove the trailing slash
            default:
                throw new \InvalidArgumentException("Invalid path supplied. Path must start with the web, storage, temp, customizing or libs storage location. Path given: '{$absolute_path}'");
        }
    }


    /**
     * @param string $possible_path
     * @param string $absolute_path
     *
     * @return bool
     */
    private static function checkPossiblePath(string $possible_path, string $absolute_path): bool
    {
        $real_possible_path = realpath($possible_path);

        switch (true) {
            case $possible_path === $absolute_path:
            case $real_possible_path === $absolute_path:
            case strpos($absolute_path, $possible_path) === 0:
            case is_string($real_possible_path) && strpos($absolute_path, $real_possible_path) === 0:
                return true;
            default:
                return false;
        }
    }

    /**
     * Sanitize a file name
     * a combination of various methods
     * we don't want to convert html entities, or do any url encoding
     * we want to retain the "essence" of the original file name, if possible
     *
     * @see http://www.house6.com/blog/?p=83
     * @param string $f
     * @param string $a_space_replace
     * @return mixed|string
     */
    public function sanitizeFilename($f, $a_space_replace = '_')
    {
        // Transliteration of letter characters from Unicode blocks
        // Latin-1 Supplement through Cyrillic Supplement to ASCII letters (a-z, A-Z).
        // Multi-character replacements follow common German/scientific transcription
        // (e.g. ae, zh, shch, th). Soft/hard signs map to empty string.
        $replace_chars = [
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'Ae', 'Å' => 'Aa',
            'Æ' => 'Ae', 'Ç' => 'C', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ð' => 'D', 'Ñ' => 'N',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'Oe', 'Ø' => 'Oe',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'Ue', 'Ý' => 'Y', 'Þ' => 'Th',
            'ß' => 'ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'ae',
            'å' => 'aa', 'æ' => 'ae', 'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e',
            'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'd',
            'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'oe',
            'ø' => 'oe', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'ue', 'ý' => 'y',
            'þ' => 'th', 'ÿ' => 'y', 'Ā' => 'A', 'ā' => 'a', 'Ă' => 'A', 'ă' => 'a',
            'Ą' => 'A', 'ą' => 'a', 'Ć' => 'C', 'ć' => 'c', 'Ĉ' => 'C', 'ĉ' => 'c',
            'Ċ' => 'C', 'ċ' => 'c', 'Č' => 'C', 'č' => 'c', 'Ď' => 'D', 'ď' => 'd',
            'Đ' => 'Dj', 'đ' => 'dj', 'Ē' => 'E', 'ē' => 'e', 'Ĕ' => 'E', 'ĕ' => 'e',
            'Ė' => 'E', 'ė' => 'e', 'Ę' => 'E', 'ę' => 'e', 'Ě' => 'E', 'ě' => 'e',
            'Ĝ' => 'G', 'ĝ' => 'g', 'Ğ' => 'G', 'ğ' => 'g', 'Ġ' => 'G', 'ġ' => 'g',
            'Ģ' => 'G', 'ģ' => 'g', 'Ĥ' => 'H', 'ĥ' => 'h', 'Ħ' => 'H', 'ħ' => 'h',
            'Ĩ' => 'I', 'ĩ' => 'i', 'Ī' => 'I', 'ī' => 'i', 'Ĭ' => 'I', 'ĭ' => 'i',
            'Į' => 'I', 'į' => 'i', 'İ' => 'I', 'ı' => 'i', 'Ĳ' => 'IJ', 'ĳ' => 'ij',
            'Ĵ' => 'J', 'ĵ' => 'j', 'Ķ' => 'K', 'ķ' => 'k', 'ĸ' => 'k', 'Ĺ' => 'L',
            'ĺ' => 'l', 'Ļ' => 'L', 'ļ' => 'l', 'Ľ' => 'L', 'ľ' => 'l', 'Ŀ' => 'L',
            'ŀ' => 'l', 'Ł' => 'L', 'ł' => 'l', 'Ń' => 'N', 'ń' => 'n', 'Ņ' => 'N',
            'ņ' => 'n', 'Ň' => 'N', 'ň' => 'n', 'ŉ' => 'n', 'Ŋ' => 'N', 'ŋ' => 'n',
            'Ō' => 'O', 'ō' => 'o', 'Ŏ' => 'O', 'ŏ' => 'o', 'Ő' => 'O', 'ő' => 'o',
            'Œ' => 'OE', 'œ' => 'oe', 'Ŕ' => 'R', 'ŕ' => 'r', 'Ŗ' => 'R', 'ŗ' => 'r',
            'Ř' => 'R', 'ř' => 'r', 'Ś' => 'S', 'ś' => 's', 'Ŝ' => 'S', 'ŝ' => 's',
            'Ş' => 'S', 'ş' => 's', 'Š' => 'S', 'š' => 's', 'Ţ' => 'T', 'ţ' => 't',
            'Ť' => 'T', 'ť' => 't', 'Ŧ' => 'T', 'ŧ' => 't', 'Ũ' => 'U', 'ũ' => 'u',
            'Ū' => 'U', 'ū' => 'u', 'Ŭ' => 'U', 'ŭ' => 'u', 'Ů' => 'U', 'ů' => 'u',
            'Ű' => 'U', 'ű' => 'u', 'Ų' => 'U', 'ų' => 'u', 'Ŵ' => 'W', 'ŵ' => 'w',
            'Ŷ' => 'Y', 'ŷ' => 'y', 'Ÿ' => 'Y', 'Ź' => 'Z', 'ź' => 'z', 'Ż' => 'Z',
            'ż' => 'z', 'Ž' => 'Z', 'ž' => 'z', 'ſ' => 's', 'ƀ' => 'b', 'Ɓ' => 'B',
            'Ƃ' => 'B', 'ƃ' => 'b', 'Ƈ' => 'C', 'ƈ' => 'c', 'Ɖ' => 'D', 'Ɗ' => 'D',
            'Ƌ' => 'D', 'ƌ' => 'd', 'Ɛ' => 'E', 'Ƒ' => 'F', 'ƒ' => 'f', 'Ɠ' => 'G',
            'ƕ' => 'hv', 'Ɩ' => 'I', 'Ɨ' => 'I', 'Ƙ' => 'K', 'ƙ' => 'k', 'ƚ' => 'l',
            'Ɲ' => 'N', 'ƞ' => 'n', 'Ơ' => 'O', 'ơ' => 'o', 'Ƣ' => 'OI', 'ƣ' => 'oi',
            'Ƥ' => 'P', 'ƥ' => 'p', 'ƫ' => 't', 'Ƭ' => 'T', 'ƭ' => 't', 'Ʈ' => 'T',
            'Ư' => 'U', 'ư' => 'u', 'Ʋ' => 'V', 'Ƴ' => 'Y', 'ƴ' => 'y', 'Ƶ' => 'Z',
            'ƶ' => 'z', 'Ǆ' => 'DZ', 'ǅ' => 'Dz', 'ǆ' => 'dz', 'Ǉ' => 'LJ', 'ǈ' => 'Lj',
            'ǉ' => 'lj', 'Ǌ' => 'NJ', 'ǋ' => 'Nj', 'ǌ' => 'nj', 'Ǎ' => 'A', 'ǎ' => 'a',
            'Ǐ' => 'I', 'ǐ' => 'i', 'Ǒ' => 'O', 'ǒ' => 'o', 'Ǔ' => 'U', 'ǔ' => 'u',
            'Ǖ' => 'U', 'ǖ' => 'u', 'Ǘ' => 'U', 'ǘ' => 'u', 'Ǚ' => 'U', 'ǚ' => 'u',
            'Ǜ' => 'U', 'ǜ' => 'u', 'Ǟ' => 'A', 'ǟ' => 'a', 'Ǡ' => 'A', 'ǡ' => 'a',
            'Ǣ' => 'AE', 'ǣ' => 'ae', 'Ǥ' => 'G', 'ǥ' => 'g', 'Ǧ' => 'G', 'ǧ' => 'g',
            'Ǩ' => 'K', 'ǩ' => 'k', 'Ǫ' => 'O', 'ǫ' => 'o', 'Ǭ' => 'O', 'ǭ' => 'o',
            'ǰ' => 'j', 'Ǳ' => 'DZ', 'ǲ' => 'Dz', 'ǳ' => 'dz', 'Ǵ' => 'G', 'ǵ' => 'g',
            'Ǹ' => 'N', 'ǹ' => 'n', 'Ǻ' => 'A', 'ǻ' => 'a', 'Ǽ' => 'AE', 'ǽ' => 'ae',
            'Ǿ' => 'O', 'ǿ' => 'o', 'Ȁ' => 'A', 'ȁ' => 'a', 'Ȃ' => 'A', 'ȃ' => 'a',
            'Ȅ' => 'E', 'ȅ' => 'e', 'Ȇ' => 'E', 'ȇ' => 'e', 'Ȉ' => 'I', 'ȉ' => 'i',
            'Ȋ' => 'I', 'ȋ' => 'i', 'Ȍ' => 'O', 'ȍ' => 'o', 'Ȏ' => 'O', 'ȏ' => 'o',
            'Ȑ' => 'R', 'ȑ' => 'r', 'Ȓ' => 'R', 'ȓ' => 'r', 'Ȕ' => 'U', 'ȕ' => 'u',
            'Ȗ' => 'U', 'ȗ' => 'u', 'Ș' => 'S', 'ș' => 's', 'Ț' => 'T', 'ț' => 't',
            'Ȟ' => 'H', 'ȟ' => 'h', 'ȡ' => 'd', 'Ȥ' => 'Z', 'ȥ' => 'z', 'Ȧ' => 'A',
            'ȧ' => 'a', 'Ȩ' => 'E', 'ȩ' => 'e', 'Ȫ' => 'O', 'ȫ' => 'o', 'Ȭ' => 'O',
            'ȭ' => 'o', 'Ȯ' => 'O', 'ȯ' => 'o', 'Ȱ' => 'O', 'ȱ' => 'o', 'Ȳ' => 'Y',
            'ȳ' => 'y', 'ȴ' => 'l', 'ȵ' => 'n', 'ȶ' => 't', 'ȷ' => 'j', 'ȸ' => 'db',
            'ȹ' => 'qp', 'Ⱥ' => 'A', 'Ȼ' => 'C', 'ȼ' => 'c', 'Ƚ' => 'L', 'Ⱦ' => 'T',
            'ȿ' => 's', 'ɀ' => 'z', 'Ƀ' => 'B', 'Ʉ' => 'U', 'Ɇ' => 'E', 'ɇ' => 'e',
            'Ɉ' => 'J', 'ɉ' => 'j', 'Ɍ' => 'R', 'ɍ' => 'r', 'Ɏ' => 'Y', 'ɏ' => 'y',
            'ͺ' => 'i', 'Ά' => 'A', 'Έ' => 'E', 'Ή' => 'E', 'Ί' => 'I', 'Ό' => 'O',
            'Ύ' => 'Y', 'Ώ' => 'O', 'ΐ' => 'i', 'Α' => 'A', 'Β' => 'B', 'Γ' => 'G',
            'Δ' => 'D', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'E', 'Θ' => 'Th', 'Ι' => 'I',
            'Κ' => 'K', 'Λ' => 'L', 'Μ' => 'M', 'Ν' => 'N', 'Ξ' => 'X', 'Ο' => 'O',
            'Π' => 'P', 'Ρ' => 'R', 'Σ' => 'S', 'Τ' => 'T', 'Υ' => 'Y', 'Φ' => 'Ph',
            'Χ' => 'Ch', 'Ψ' => 'Ps', 'Ω' => 'O', 'Ϊ' => 'I', 'Ϋ' => 'Y', 'ά' => 'a',
            'έ' => 'e', 'ή' => 'e', 'ί' => 'i', 'ΰ' => 'y', 'α' => 'a', 'β' => 'b',
            'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'ζ' => 'z', 'η' => 'e', 'θ' => 'th',
            'ι' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => 'x',
            'ο' => 'o', 'π' => 'p', 'ρ' => 'r', 'ς' => 's', 'σ' => 's', 'τ' => 't',
            'υ' => 'y', 'φ' => 'ph', 'χ' => 'ch', 'ψ' => 'ps', 'ω' => 'o', 'ϊ' => 'i',
            'ϋ' => 'y', 'ό' => 'o', 'ύ' => 'y', 'ώ' => 'o', 'ϐ' => 'b', 'ϑ' => 'th',
            'ϒ' => 'Y', 'ϓ' => 'Y', 'ϔ' => 'Y', 'ϕ' => 'ph', 'ϖ' => 'p', 'ϰ' => 'k',
            'ϱ' => 'r', 'ϲ' => 's', 'ϳ' => 'j', 'ϴ' => 'Th', 'ϵ' => 'e', 'Ϸ' => 'S',
            'ϸ' => 's', 'Ϲ' => 'S', 'Ϻ' => 'S', 'ϻ' => 's', 'Ѐ' => 'E', 'Ё' => 'Yo',
            'Ђ' => 'Dj', 'Ѓ' => 'Gj', 'Є' => 'Ye', 'Ѕ' => 'Dz', 'І' => 'I', 'Ї' => 'Yi',
            'Ј' => 'J', 'Љ' => 'Lj', 'Њ' => 'Nj', 'Ћ' => 'C', 'Ќ' => 'Kj', 'Ѝ' => 'I',
            'Ў' => 'U', 'Џ' => 'Dz', 'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G',
            'Д' => 'D', 'Е' => 'E', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I', 'Й' => 'Y',
            'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P',
            'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'Kh',
            'Ц' => 'Ts', 'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Shch', 'Ы' => 'Y', 'Э' => 'E',
            'Ю' => 'Yu', 'Я' => 'Ya', 'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g',
            'д' => 'd', 'е' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y',
            'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p',
            'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh',
            'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y', 'э' => 'e',
            'ю' => 'yu', 'я' => 'ya', 'ѐ' => 'e', 'ё' => 'yo', 'ђ' => 'dj', 'ѓ' => 'gj',
            'є' => 'ye', 'ѕ' => 'dz', 'і' => 'i', 'ї' => 'yi', 'ј' => 'j', 'љ' => 'lj',
            'њ' => 'nj', 'ћ' => 'c', 'ќ' => 'kj', 'ѝ' => 'i', 'ў' => 'u', 'џ' => 'dz',
            'Ґ' => 'G', 'ґ' => 'g', 'Ғ' => 'Gh', 'ғ' => 'gh', 'Ҕ' => 'G', 'ҕ' => 'g',
            'Ҙ' => 'Z', 'ҙ' => 'z', 'Қ' => 'Q', 'қ' => 'q', 'Ӂ' => 'Zh', 'ӂ' => 'zh',
            'Ӑ' => 'A', 'ӑ' => 'a', 'Ӓ' => 'A', 'ӓ' => 'a', 'Ӕ' => 'Ae', 'ӕ' => 'ae',
            'Ӗ' => 'E', 'ӗ' => 'e', 'Ӝ' => 'Zh', 'ӝ' => 'zh', 'Ӟ' => 'Z', 'ӟ' => 'z',
            'Ӣ' => 'I', 'ӣ' => 'i', 'Ӥ' => 'I', 'ӥ' => 'i', 'Ӧ' => 'O', 'ӧ' => 'o',
            'Ӭ' => 'E', 'ӭ' => 'e', 'Ӯ' => 'U', 'ӯ' => 'u', 'Ӱ' => 'U', 'ӱ' => 'u',
            'Ӳ' => 'U', 'ӳ' => 'u', 'Ӵ' => 'Ch', 'ӵ' => 'ch', 'Ӹ' => 'Y', 'ӹ' => 'y',
            'Ƅ' => 'B', 'ƅ' => 'b', 'Ɔ' => 'O', 'ƍ' => 'd', 'Ǝ' => 'E', 'Ə' => 'E',
            'Ɣ' => 'G', 'ƛ' => 'l', 'Ɯ' => 'M', 'Ɵ' => 'O', 'Ʀ' => 'R', 'Ƨ' => 'S',
            'ƨ' => 's', 'Ʃ' => 'Sh', 'ƪ' => 'sh', 'Ʊ' => 'U', 'Ʒ' => 'Zh', 'Ƹ' => 'Zh',
            'ƹ' => 'zh', 'ƺ' => 'zh', 'ƾ' => 'ts', 'ƿ' => 'w', 'ǀ' => '', 'ǁ' => '',
            'ǂ' => '', 'ǃ' => '', 'ǝ' => 'e', 'Ǯ' => 'Zh', 'ǯ' => 'zh', 'Ƕ' => 'Hv',
            'Ƿ' => 'W', 'Ȝ' => 'Y', 'ȝ' => 'y', 'Ƞ' => 'N', 'Ȣ' => 'Ou', 'ȣ' => 'ou',
            'Ɂ' => '', 'ɂ' => '', 'Ʌ' => 'A', 'Ɋ' => 'Q', 'ɋ' => 'q', 'Ͱ' => 'H',
            'ͱ' => 'h', 'Ͳ' => 'T', 'ͳ' => 't', 'ʹ' => '', 'Ͷ' => 'W', 'ͷ' => 'w',
            'ͻ' => 's', 'ͼ' => 's', 'ͽ' => 's', 'Ϳ' => 'J', 'Ϗ' => 'Kai', 'ϗ' => 'kai',
            'Ϙ' => 'Q', 'ϙ' => 'q', 'Ϛ' => 'St', 'ϛ' => 'st', 'Ϝ' => 'W', 'ϝ' => 'w',
            'Ϟ' => 'K', 'ϟ' => 'k', 'Ϡ' => 'S', 'ϡ' => 's', 'Ϣ' => 'Sh', 'ϣ' => 'sh',
            'Ϥ' => 'F', 'ϥ' => 'f', 'Ϧ' => 'H', 'ϧ' => 'h', 'Ϩ' => 'H', 'ϩ' => 'h',
            'Ϫ' => 'Dj', 'ϫ' => 'dj', 'Ϭ' => 'G', 'ϭ' => 'g', 'Ϯ' => 'Ti', 'ϯ' => 'ti',
            'ϼ' => 'r', 'Ͻ' => 'S', 'Ͼ' => 'S', 'Ͽ' => 'S', 'Ъ' => '', 'Ь' => '', 'ъ' => '',
            'ь' => '', 'Ѡ' => 'O', 'ѡ' => 'o', 'Ѣ' => 'Ye', 'ѣ' => 'ye', 'Ѥ' => 'Je',
            'ѥ' => 'je', 'Ѧ' => 'Ya', 'ѧ' => 'ya', 'Ѩ' => 'Ya', 'ѩ' => 'ya', 'Ѫ' => 'A',
            'ѫ' => 'a', 'Ѭ' => 'Yu', 'ѭ' => 'yu', 'Ѯ' => 'Ks', 'ѯ' => 'ks', 'Ѱ' => 'Ps',
            'ѱ' => 'ps', 'Ѳ' => 'F', 'ѳ' => 'f', 'Ѵ' => 'Y', 'ѵ' => 'y', 'Ѷ' => 'Y',
            'ѷ' => 'y', 'Ѹ' => 'Ou', 'ѹ' => 'ou', 'Ѻ' => 'O', 'ѻ' => 'o', 'Ѽ' => 'O',
            'ѽ' => 'o', 'Ѿ' => 'Ot', 'ѿ' => 'ot', 'Ҁ' => '', 'ҁ' => '', 'Ҋ' => 'J',
            'ҋ' => 'j', 'Ҍ' => 'R', 'ҍ' => 'r', 'Ҏ' => 'R', 'ҏ' => 'r', 'Ө' => 'O',
            'ө' => 'o', 'Ӫ' => 'O', 'ӫ' => 'o', 'Ӷ' => 'G', 'ӷ' => 'g', 'Ӻ' => 'G',
            'ӻ' => 'g', 'Ӽ' => 'Kh', 'ӽ' => 'kh', 'Ӿ' => 'Kh', 'ӿ' => 'kh', 'Җ' => 'Zh',
            'җ' => 'zh', 'Ҝ' => 'K', 'ҝ' => 'k', 'Ҟ' => 'K', 'ҟ' => 'k', 'Ҡ' => 'Q',
            'ҡ' => 'q', 'Ң' => 'Ng', 'ң' => 'ng', 'Ҥ' => 'Ng', 'ҥ' => 'ng', 'Ҧ' => 'P',
            'ҧ' => 'p', 'Ҩ' => 'O', 'ҩ' => 'o', 'Ҫ' => 'S', 'ҫ' => 's', 'Ҭ' => 'T',
            'ҭ' => 't', 'Ү' => 'U', 'ү' => 'u', 'Ұ' => 'U', 'ұ' => 'u', 'Ҳ' => 'H',
            'ҳ' => 'h', 'Ҵ' => 'Ts', 'ҵ' => 'ts', 'Ҷ' => 'Ch', 'ҷ' => 'ch', 'Ҹ' => 'Ch',
            'ҹ' => 'ch', 'Һ' => 'H', 'һ' => 'h', 'Ҽ' => 'Ch', 'ҽ' => 'ch', 'Ҿ' => 'Ch',
            'ҿ' => 'ch', 'Ӏ' => 'I', 'ӏ' => 'i', 'Ӄ' => 'Q', 'ӄ' => 'q', 'Ӆ' => 'L',
            'ӆ' => 'l', 'Ӈ' => 'Ng', 'ӈ' => 'ng', 'Ӊ' => 'N', 'ӊ' => 'n', 'Ӌ' => 'Ch',
            'ӌ' => 'ch', 'Ӎ' => 'M', 'ӎ' => 'm', 'Ә' => 'A', 'ә' => 'a', 'Ӛ' => 'A',
            'ӛ' => 'a', 'Ӡ' => 'Dz', 'ӡ' => 'dz', 'Ԁ' => 'D', 'ԁ' => 'd', 'Ԃ' => 'D',
            'ԃ' => 'd', 'Ԅ' => 'D', 'ԅ' => 'd', 'Ԇ' => 'D', 'ԇ' => 'd', 'Ԉ' => 'L',
            'ԉ' => 'l', 'Ԋ' => 'N', 'ԋ' => 'n', 'Ԍ' => 'G', 'ԍ' => 'g', 'Ԏ' => 'T',
            'ԏ' => 't', 'Ԑ' => 'E', 'ԑ' => 'e', 'Ԓ' => 'L', 'ԓ' => 'l', 'Ԕ' => 'P',
            'ԕ' => 'p', 'Ԗ' => 'R', 'ԗ' => 'r', 'Ԙ' => 'Y', 'ԙ' => 'y', 'Ԛ' => 'Q',
            'ԛ' => 'q', 'Ԝ' => 'W', 'ԝ' => 'w', 'Ԟ' => 'T', 'ԟ' => 't', 'Ԡ' => 'L',
            'ԡ' => 'l', 'Ԣ' => 'N', 'ԣ' => 'n', 'Ԥ' => 'P', 'ԥ' => 'p', 'Ԧ' => 'H',
            'ԧ' => 'h', 'Ԩ' => 'N', 'ԩ' => 'n', 'Ԫ' => 'Ch', 'ԫ' => 'ch', 'Ԭ' => 'T',
            'ԭ' => 't', 'Ԯ' => 'L', 'ԯ' => 'l',
        ];
        $f = strtr($f, $replace_chars);
        // convert & to "and", @ to "at", and # to "number"
        $f = preg_replace(array('/[\&]/', '/[\@]/', '/[\#]/'), array('-and-', '-at-', '-number-'), $f);
        $f = preg_replace('/[^(\x20-\x7F)]*/', '', $f); // removes any special chars we missed
        $f = str_replace(' ', $a_space_replace, $f); // convert space to hyphen
        $f = str_replace("'", '', $f); 	// removes single apostrophes
        $f = str_replace('"', '', $f);  // removes double apostrophes
        $f = preg_replace('/[^\w\-\.\,_ ]+/', '', $f); // remove non-word chars (leaving hyphens and periods)
        $f = preg_replace('/[\-]+/', '-', $f); // converts groups of hyphens into one
        $f = preg_replace('/[_]+/', '_', $f); // converts groups of dashes into one
        return $f;
    }

    /**
     * Remove the sots from a path
     * @see https://stackoverflow.com/questions/10064499/php-normalize-path-of-not-existing-directories-to-prevent-directory-traversals
     * @param string $path
     * @return string
     */
    public function removeDots(string $path): string
    {
        $root = ($path[0] === '/') ? '/' : '';

        $segments = explode('/', trim($path, '/'));
        $ret = array();
        foreach ($segments as $segment) {
            if (($segment == '.') || strlen($segment) === 0) {
                continue;
            }
            if ($segment == '..') {
                array_pop($ret);
            } else {
                array_push($ret, $segment);
            }
        }
        return $root . implode('/', $ret);
    }
}
