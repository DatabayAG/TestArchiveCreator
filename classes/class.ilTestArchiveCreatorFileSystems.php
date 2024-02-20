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
    protected Filesystem $templates;

    public function __construct()
    {
        global $DIC;

        /** @var DelegatingFilesystemFactory $factory */
        $factory = $DIC['filesystem.factory'];

        $this->modules = $factory->getLocal(new LocalConfig(ILIAS_ABSOLUTE_PATH . '/Modules'), true);
        $this->templates = $factory->getLocal(new LocalConfig(ILIAS_ABSOLUTE_PATH . '/templates'), true);
    }

    /**
     * Get the plugin specific relation from paths to filesystems
     * @return array path => file system
     */
    private function systemsByPath() : array
    {
        return [
            './Modules' => $this->modules,
            './templates' => $this->templates,
            ILIAS_ABSOLUTE_PATH . '/Modules' => $this->modules,
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
        }
        catch (Exception $e) {
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

        }
        catch (Exception $e) {
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
     * @see http://www.house6.com/blog/?p=83
     * @param string $f
     * @param string $a_space_replace
     * @return mixed|string
     */
    public function sanitizeFilename($f, $a_space_replace = '_') {
        // a combination of various methods
        // we don't want to convert html entities, or do any url encoding
        // we want to retain the "essence" of the original file name, if possible
        // char replace table found at:
        // http://www.php.net/manual/en/function.strtr.php#98669
        $replace_chars = array(
            'Š'=>'S', 'š'=>'s', 'Ð'=>'Dj','Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'Ae',
            'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I',
            'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'Oe', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U',
            'Û'=>'U', 'Ü'=>'Ue', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'ss','à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'ae', 'ä'=>'a',
            'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i',
            'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'oe', 'ø'=>'o', 'ù'=>'u',
            'ü'=>'ue', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y', 'ƒ'=>'f'
        );
        $f = strtr($f, $replace_chars);
        // convert & to "and", @ to "at", and # to "number"
        $f = preg_replace(array('/[\&]/', '/[\@]/', '/[\#]/'), array('-and-', '-at-', '-number-'), $f);
        $f = preg_replace('/[^(\x20-\x7F)]*/','', $f); // removes any special chars we missed
        $f = str_replace(' ', $a_space_replace, $f); // convert space to hyphen
        $f = str_replace("'", '', $f); 	// removes single apostrophes
        $f = str_replace('"', '', $f);  // removes double apostrophes
        $f = preg_replace('/[^\w\-\.\,_ ]+/', '', $f); // remove non-word chars (leaving hyphens and periods)
        $f = preg_replace('/[\-]+/', '-', $f); // converts groups of hyphens into one
        $f = preg_replace('/[_]+/', '_', $f); // converts groups of dashes into one
        return $f;
    }
}
