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

        // Process {"autoload":{"files":[]}}
        $files = $this->getAutoloadFiles($rootDir, $vendorDir);

        // Process {"autoload":{"psr-0":[]}} and {"autoload":{"psr-4":[]}}
        $namespaces = $this->getAutoloadNamespaces($rootDir, $vendorDir);

        // Get parser/autoload version
        $version = $this->getAutoloadVersion($vendorDir);

        return $this->generateParserClass($files, $namespaces, $version, $rootDir);
    }


    /**
     * @param $rootDir
     * @param $vendorDir
     * @return string
     */
    private function getAutoloadFiles($rootDir, $vendorDir)
    {
        $result = "";

        if (file_exists($vendorDir . '/composer/autoload_files.php')) {
            $files = require $vendorDir . '/composer/autoload_files.php';

            if (!empty($files)) {
                foreach ($files as $hash => $path) {
                    $result .= $this->parsePath($path, $rootDir) . "\n";
                }
            }
        }

        return $result;
    }


    /**
     * @param $rootDir
     * @param $vendorDir
     * @return string
     */
    private function getAutoloadNamespaces($rootDir, $vendorDir)
    {
        $result = "";

        $namespaces = array();

        if (file_exists($vendorDir . '/composer/autoload_namespaces.php')) {
            $namespaces = array_merge($namespaces, require $vendorDir . '/composer/autoload_namespaces.php');
        }

        if (file_exists($vendorDir . '/composer/autoload_psr4.php')) {
            $namespaces = array_merge($namespaces, require $vendorDir . '/composer/autoload_psr4.php');
        }

        if (!empty($namespaces)) {
            foreach ($namespaces as $namespace => $paths) {
                foreach ($paths as $path) {
                    if (!$this->isDumper($path)) {
                        $result .= $this->parseNamespace($namespace) . ":" . $this->parsePath($path, $rootDir) . "\n";
                    }
                }
            }
        }

        return $result;
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
     * @param $rootDir
     * @return mixed
     */
    private function parsePath($path, $rootDir)
    {
        if (strpos($path.'/', $rootDir.'/') === 0) {
            $path = substr($path, strlen($rootDir.'/'));
        }

        return $path;
    }


    /**
     * @param $namespace
     * @return mixed
     */
    private function parseNamespace($namespace)
    {
        $namespace = trim($namespace, "\\");

        return str_replace("\\", "/", $namespace);
    }


    /**
     * @param $path
     * @return bool
     */
    private function isDumper($path)
    {
        return (strpos($path, self::PACKAGE_NAME) !== false);
    }


    private function generateParserClass($files, $namespaces, $version, $rootDir)
    {
        return <<<AUTOLOAD
###############################################################################
# THIS IS AN AUTOGENERATED FILE. DO NOT EDIT THIS FILE DIRECTLY.
###############################################################################
# \$ID: autoload.p, v$version, Leonid 'n3o' Knyazev $
###############################################################################
@auto[base][locals]
\$MAIN:COMPOSER[^COMPOSER::create[\$base]]
#end @auto[]


###############################################################################
# Parser3 Class Loader
###############################################################################
@CLASS
COMPOSER


@OPTIONS
locals


###############################################################################
# @PUBLIC
###############################################################################
@create[base][locals]
# @{string} [_root] Document root.
\$self.root[^self._getRoot[]]

# find \$MAIN:CLASS_PATH
^if(!def \$MAIN:CLASS_PATH){
	\$MAIN:CLASS_PATH[^table::create{path}]
}(\$MAIN:CLASS_PATH is string){
	\$MAIN:CLASS_PATH[^table::create{path^#0A\${MAIN:CLASS_PATH}}]
}

# @{table} [_namespaces] Registred namespaces.
\$self.namespaces[^table::create{namespace:path}[ $.separator[:] ]]

# extend @MIAN:use[]
\$self._use[\$MAIN:use]

^process[\$MAIN:CLASS]{@use[path^;params][locals]
	^^MAIN:COMPOSER.use[^\$path^;^\$params]
}

# extend @MIAN:autouse[]
\$self._autouse[\$MAIN:autouse]

^process[\$MAIN:CLASS]{@autouse[path][locals]
	^^MAIN:COMPOSER.autouse[^\$path^]
}

^self._update[]
#end @create[]


###############################################################################
@use[class;params][locals]
\$params[^hash::create[\$params]]

^try{
	^self._use[\$class;\$params]
}{
	\$exception.handled(true)

	^rem{*** find file type ***}
	\$type[^file:justext[\$class]]

	^if(!def \$type){
		\$type[p]
	}

	^rem{** remove file name ***}
	\$class[^class.trim[right;.\${type}]]

	^rem{*** find namespace ***}
	\$found(false)

	^if(^self.namespaces.locate[namespace;\$class]){
		\$found(true)
		\$namespace[\$class]
	}{
		\$_parts[^class.split[/;r]]

		^_parts.menu{
			\$piece[\${piece}/\${_parts.piece}]
			\$namespace[^class.match[\$piece][gi]{}]

			^if(^self.namespaces.locate[namespace;\$namespace]){
				\$found(true)
				^break[]
			}
		}
	}

	^rem{*** find file name ***}
	\$name[^class.match[\$namespace][gi]{}]
	\$name[^name.trim[both;/]]

	^if(!def \$name){
		\$name[^file:basename[\$class]]
	}

	^if(\$found){
		\$paths[^self.namespaces.select(\$self.namespaces.namespace eq \$namespace)]
	}{
		\$paths[^self.namespaces.select(\$self.namespaces.namespace eq "*")]
	}
	
	^if(\$paths){
		^paths.menu{
			\$path[\$paths.path]

			^rem{*** find class file ***}
			^if(-f "\${path}/\${name}.\${type}"){
				^self._use[\${path}/\${name}.\${type}]
				^break[]
			}(-f "\${path}/\${namespace}.\${type}"){
				^self._use[\${path}/\${namespace}.\${type}]
				^break[]
			}(-f "\${path}/\${namespace}/\${name}.\${type}"){
				^self._use[\${path}/\${namespace}/\${name}.\${type}]
				^break[]
			}
		}
	}
}
#end @use[]


###############################################################################
@autouse[path]
^try{
	^self.use[\$path]
}{
	\$exception.handled(true)

	^if(\$self._autouse is junction){
		^self._autouse[\$path]
	}
}
#end @autouse[]



###############################################################################
# @PRIVATE
###############################################################################
@_update[][locals]
# process auto include files
\$_includes[^self._getIncludes[]]

^if(\$_includes){
	^_includes.menu{
		\$path[\${self.root}/\$_includes.path]
		
		^if(-f "\${path}"){
			^self._use[\$path]
		}
	}
}

# process namespaces
\$_namespaces[^self._getNamespaces[]]

^if(\$_namespaces){
	^_namespaces.menu{
		\$name[^_namespaces.name.trim[]]
		\$path[\${self.root}/^_namespaces.path.trim[]]

		^if(!^MAIN:CLASS_PATH.locate[path;\$path]){
			^MAIN:CLASS_PATH.append{\$path}
		}
		
		^if(\$name eq ""){
			\$name[*]
		}

		^self.namespaces.append{\$name	\$path}
	}
}
#end @_update[]


###############################################################################
@_getRoot[][locals]
\$result[]

\$root[$rootDir]

# find \$_root
^if(def \$env:PWD){
	\$root[^env:PWD.match[\$root][gi]{}]
}(def \$env:DOCUMENT_ROOT_VIRTUAL){
	\$root[^env:DOCUMENT_ROOT_VIRTUAL.match[\$root][gi]{}]
}(def \$env:DOCUMENT_ROOT){
	\$root[^env:DOCUMENT_ROOT.match[\$root][gi]{}]
}

^if(def \$root && \$root ne ""){
	\$root[^root.trim[both;/]]
	\$root[^root.split[/;l]]

	\$result[/^root.menu{..}[/]]
}
#end @_getRoot[]


###############################################################################
@_getIncludes[][locals]
\$result[^table::create{path
$files}[ \$.separator[:] ]]
#end @_getIncludes[]


###############################################################################
@_getNamespaces[][locals]
\$result[^table::create{name:path
$namespaces}[ \$.separator[:] ]]
#end @_getNamespaces[]

AUTOLOAD;
    }
}
