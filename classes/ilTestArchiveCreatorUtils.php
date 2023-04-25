<?php

/**
 * Former ILIAS ilUtil Functions that are needed by the plugin
 */
class ilTestArchiveCreatorUtils
{

    public function escapeShellArg($a_arg)
    {
        setlocale(LC_CTYPE, "UTF8", "en_US.UTF-8"); // fix for PHP escapeshellcmd bug. See: http://bugs.php.net/bug.php?id=45132
        // see also ilias bug 5630
        return escapeshellarg($a_arg);
    }

    /**
     * escape shell cmd
     *
     * @access public
     * @param
     * @return
     * @static
     *
     */
    public function escapeShellCmd($a_arg)
    {
        if (ini_get('safe_mode') == 1) {
            return $a_arg;
        }
        setlocale(LC_CTYPE, "UTF8", "en_US.UTF-8"); // fix for PHP escapeshellcmd bug. See: http://bugs.php.net/bug.php?id=45132
        return escapeshellcmd($a_arg);
    }

    /**
     * exec command and fix spaces on windows
     *
     * @param	string $cmd
     * @param	string $args
     * @return array
     * @static
     *
     */
    public function execQuoted($cmd, $args = null)
    {
        global $DIC;

        if (ilUtil::isWindows() && strpos($cmd, " ") !== false && substr($cmd, 0, 1) !== '"') {
            // cmd won't work without quotes
            $cmd = '"' . $cmd . '"';
            if ($args) {
                // args are also quoted, workaround is to quote the whole command AGAIN
                // was fixed in php 5.2 (see php bug #25361)
                if (version_compare(phpversion(), "5.2", "<") && strpos($args, '"') !== false) {
                    $cmd = '"' . $cmd . " " . $args . '"';
                }
                // args are not quoted or php is fixed, just append
                else {
                    $cmd .= " " . $args;
                }
            }
        }
        // nothing to do, just append args
        elseif ($args) {
            $cmd .= " " . $args;
        }
        exec($cmd, $arr);

        $DIC->logger()->root()->debug("ilUtil::execQuoted: " . $cmd . ".");

        return $arr;
    }



    /**
     * removes a dir and all its content (subdirs and files) recursively
     *
     * @access    public
     *
     * @param string    $a_dir          dir to delete
     * @param bool      $a_clean_only
     *
     * @author    Unknown <flexer@cutephp.com> (source: http://www.php.net/rmdir)
     * @static
     *
     * @deprecated in favour of Filesystem::deleteDir() located at the filesystem service.
     *
     * @see \ILIAS\Filesystem\Filesystem::deleteDir()
     */
    public function delDir($a_dir, $a_clean_only = false)
    {
        if (!is_dir($a_dir) || is_int(strpos($a_dir, ".."))) {
            return;
        }

        $current_dir = opendir($a_dir);

        $files = array();

        // this extra loop has been necessary because of a strange bug
        // at least on MacOS X. A looped readdir() didn't work
        // correctly with larger directories
        // when an unlink happened inside the loop. Getting all files
        // into the memory first solved the problem.
        while ($entryname = readdir($current_dir)) {
            $files[] = $entryname;
        }

        foreach ($files as $file) {
            if (is_dir($a_dir . "/" . $file) and ($file != "." and $file != "..")) {
                $this->delDir($a_dir . "/" . $file);
            } elseif ($file != "." and $file != "..") {
                unlink($a_dir . "/" . $file);
            }
        }

        closedir($current_dir);
        if (!$a_clean_only) {
            @rmdir($a_dir);
        }
    }

    /**
     * creates a new directory and inherits all filesystem permissions of the parent directory
     * You may pass only the name of your new directory or with the entire path or relative path information.
     *
     * examples:
     * a_dir = /tmp/test/your_dir
     * a_dir = ../test/your_dir
     * a_dir = your_dir (--> creates your_dir in current directory)
     *
     * @access	public
     * @param	string	[path] + directory name
     * @return	boolean
     * @static
     *
     * @deprecated in favour of Filesystem::createDir() located at the filesystem service.
     *
     * @see \ILIAS\Filesystem\Filesystem::createDir()
     */
    public function makeDir($a_dir)
    {
        $a_dir = trim($a_dir);

        // remove trailing slash (bugfix for php 4.2.x)
        if (substr($a_dir, -1) == "/") {
            $a_dir = substr($a_dir, 0, -1);
        }

        // check if a_dir comes with a path
        if (!($path = substr($a_dir, 0, strrpos($a_dir, "/") - strlen($a_dir)))) {
            $path = ".";
        }

        // create directory with file permissions of parent directory
        umask(0000);
        return @mkdir($a_dir, fileperms($path));
    }


    /**
     * Create a new directory and all parent directories
     *
     * Creates a new directory and inherits all filesystem permissions of the parent directory
     * If the parent directories doesn't exist, they will be created recursively.
     * The directory name NEEDS TO BE an absolute path, because it seems that relative paths
     * are not working with PHP's file_exists function.
     *
     * @author Helmut Schottmüller <hschottm@tzi.de>
     * @param string $a_dir The directory name to be created
     * @access public
     * @static
     *
     * @return bool
     *
     * @deprecated in favour of Filesystem::createDir() located at the filesystem service.
     *
     * @see \ILIAS\Filesystem\Filesystem::createDir()
     */
    public function makeDirParents($a_dir)
    {
        $dirs = array($a_dir);
        $a_dir = dirname($a_dir);
        $last_dirname = '';

        while ($last_dirname != $a_dir) {
            array_unshift($dirs, $a_dir);
            $last_dirname = $a_dir;
            $a_dir = dirname($a_dir);
        }

        // find the first existing dir
        $reverse_paths = array_reverse($dirs, true);
        $found_index = -1;
        foreach ($reverse_paths as $key => $value) {
            if ($found_index == -1) {
                if (is_dir($value)) {
                    $found_index = $key;
                }
            }
        }

        umask(0000);
        foreach ($dirs as $dirindex => $dir) {
            // starting with the longest existing path
            if ($dirindex >= $found_index) {
                if (!file_exists($dir)) {
                    if (strcmp(substr($dir, strlen($dir) - 1, 1), "/") == 0) {
                        // on some systems there is an error when there is a slash
                        // at the end of a directory in mkdir, see Mantis #2554
                        $dir = substr($dir, 0, strlen($dir) - 1);
                    }
                    if (!mkdir($dir, $umask)) {
                        error_log("Can't make directory: $dir");
                        return false;
                    }
                } elseif (!is_dir($dir)) {
                    error_log("$dir is not a directory");
                    return false;
                } else {
                    // get umask of the last existing parent directory
                    $umask = fileperms($dir);
                }
            }
        }
        return true;
    }


    /**
     *	zips given directory/file into given zip.file
     *
     * @static
     *
     */
    public function zip($a_dir, $a_file, $compress_content = false)
    {
        $cdir = getcwd();

        if ($compress_content) {
            $a_dir .= "/*";
            $pathinfo = pathinfo($a_dir);
            chdir($pathinfo["dirname"]);
        }

        $pathinfo = pathinfo($a_file);
        $dir = $pathinfo["dirname"];
        $file = $pathinfo["basename"];

        if (!$compress_content) {
            chdir($dir);
        }

        $zip = PATH_TO_ZIP;

        if (!$zip) {
            chdir($cdir);
            return false;
        }

        if (is_array($a_dir)) {
            $source = "";
            foreach ($a_dir as $dir) {
                $name = basename($dir);
                $source .= " " . $this->escapeShellArg($name);
            }
        } else {
            $name = basename($a_dir);
            if (trim($name) != "*") {
                $source = $this->escapeShellArg($name);
            } else {
                $source = $name;
            }
        }

        $zipcmd = "-r " . $this->escapeShellArg($a_file) . " " . $source;
        $this->execQuoted($zip, $zipcmd);
        chdir($cdir);
        return true;
    }

}