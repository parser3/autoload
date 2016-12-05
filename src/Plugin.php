<?php
/*
 * @link https://github.com/parser3/autoload
 * @copyright Copyright (c) 2016 Art. Lebedev Studio, Ltd
 * @author Leonid Knyazev <leonid@knyazev.me> <n3o@design.ru>
 */

namespace Parser\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Script\ScriptEvents;
use Composer\Json\JsonFile;
use Composer\Util\Filesystem;

/**
 * Class Plugin
 * @package Parser\Composer
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * Offical package name
     */
    const PACKAGE_NAME = 'parser/autoload';

    /**
     * @var IOInterface $io
     */
    protected $io;

    /**
     * @var Composer $composer
     */
    protected $composer;

    /**
     * @var Filesystem $filesystem
     */
    protected $filesystem;



    /**
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->io = $io;
        $this->composer = $composer;
        $this->filesystem = new Filesystem();
    }


    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::POST_AUTOLOAD_DUMP => array(
                array('onPostAutoloadDump', 0)
            )
        );
    }


    /**
     * Handler for ScriptEvents::POST_AUTOLOAD_DUMP
     */
    public function onPostAutoloadDump()
    {
        // Project vendor directory.
        $vendorDir = $this->composer->getConfig()->get('vendor-dir');
        $vendorDir = $this->filesystem->normalizePath(realpath(realpath($vendorDir)));

        // autoload.p
        $autoload = $this->getAutoload($vendorDir);

        if (!empty($autoload)) {
            file_put_contents($vendorDir.'/autoload.p', $autoload);

            $this->io->writeError('<info>Generating autoload completed</info>');
        }
    }


    /**
     * @param $vendorDir
     * @return string
     */
    private function getAutoload($vendorDir)
    {
        // Project root directory.
        $rootDir = $this->filesystem->normalizePath(realpath(realpath(getcwd())));

        // Get autoload files
        $includes = $this->getAutoloadIncludes($vendorDir);

        // Get autoload prefixes
        $prefixes = $this->getAutoloadPrefixes($vendorDir);

        // Get parser/autoload version
        $version = $this->getAutoloadVersion($vendorDir);

        return $this->generateParserClass($includes, $prefixes, $version, $rootDir);
    }


    /**
     * @param $vendorDir
     * @return string
     */
    private function getAutoloadIncludes($vendorDir)
    {
        if (file_exists($vendorDir . '/composer/autoload_files.php')) {
            $includes = require $vendorDir . '/composer/autoload_files.php';

            if (!empty($includes)) {
                $result = "\n";

                foreach ($includes as $hash => $path) {
                    $result .= $hash ."|". $this->parsePath($path) . "\n";
                }

                return $result;
            }
        }

        return "";
    }


    /**
     * @param $vendorDir
     * @return string
     */
    private function getAutoloadPrefixes($vendorDir)
    {
        $prefixes = array();

        if (file_exists($vendorDir . '/composer/autoload_namespaces.php')) {
            $prefixes = array_merge($prefixes, require $vendorDir . '/composer/autoload_namespaces.php');
        }

        if (file_exists($vendorDir . '/composer/autoload_psr4.php')) {
            $prefixes = array_merge($prefixes, require $vendorDir . '/composer/autoload_psr4.php');
        }

        if (!empty($prefixes)) {
            $result = "\n";

            foreach ($prefixes as $prefix => $paths) {
                foreach ($paths as $path) {
                    if (!$this->isDumper($path)) {
                        $result .= $this->parsePrefix($prefix) ."|". $this->parsePath($path) . "\n";
                    }
                }
            }

            return $result;
        }

        return "";
    }


    /**
     * @param $vendorDir
     * @return string
     */
    private function getAutoloadVersion($vendorDir)
    {
        $version = "1.0.0";

        $path = $vendorDir .DIRECTORY_SEPARATOR. self::PACKAGE_NAME .DIRECTORY_SEPARATOR. "composer.json";

        if (file_exists($path)) {
            $file = new JsonFile($path);
            $json = $file->read();

            if (!isset($json['version'])) {
                $json['version'] = '1.0.0';
            }

            $version = $json['version'];
        }

        return $version;
    }


    /**
     * @param $path
     * @return mixed
     */
    private function parsePath($path)
    {
        return str_replace("\\", "/", $path);
    }


    /**
     * @param $prefix
     * @return mixed
     */
    private function parsePrefix($prefix)
    {
        $prefix = trim($prefix, "\\");
        $prefix = trim($prefix, "_");

        if (empty($prefix)) {
            $prefix = "*";
        }

        return str_replace(array("\\", "_"), "/", $prefix);
    }


    /**
     * @param $path
     * @return bool
     */
    private function isDumper($path)
    {
        return (strpos($path, self::PACKAGE_NAME) !== false);
    }



    private function generateParserClass($includes, $prefixes, $version, $rootDir)
    {
        return <<<AUTOLOAD
###############################################################################
# THIS IS AN AUTOGENERATED FILE. DO NOT EDIT THIS FILE DIRECTLY.
###############################################################################
# \$ID: autoload.p, v$version, Leonid 'n3o' Knyazev $
###############################################################################
@auto[][locals]
\$includes[^table::create{hash|path$includes}[
	$.separator[|]
]]

\$prefixes[^table::create{name|path$prefixes}[
	$.separator[|]
]]

^use[parser/autoload/src/Autoload.p]

\$MAIN:AUTOLOAD[^Parser/Autoload::create[
	\$.root[$rootDir]
	\$.includes[\$includes]
	\$.prefixes[\$prefixes]
]]
#end @auto[]

AUTOLOAD;
    }
}
