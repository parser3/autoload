###############################################################################
# $ID: Autoload.p, Leonid 'n3o' Knyazev $
###############################################################################
@CLASS
Parser/Autoload


@OPTIONS
locals


###############################################################################
# @PUBLIC
###############################################################################
@create[params][locals]
# @{string} [root] Document root.
$self.root[/]

# @{table} [files] Autoloaded files.
$self.includes[^table::create{hash:path:used}[
	$.separator[:]
]]

# @{table} [prefixes] Registred prefixes.
$self.prefixes[^table::create{name:path}[
	$.separator[:]
]]

^self._configure[$params]
#end @create[]


###############################################################################
@use[class;params][locals]

$class[^class.trim[]]
$params[^hash::create[$params]]

^try{
	^self._use[$class;$params]
}{
	$exception.handled(true)

	^rem{ *** @{hash} with class definition *** }
	$class[^self._parseClass[$class]]

	^rem{ *** @{string} full path to file *** }
	$path[^if(def $class.path){${class.path}/}${class.name}]

	^if(-f "${path}.${class.type}"){
		^self._use[${path}.${class.type};$params]
		$class.loaded(true)
	}{
		^rem{ *** Otherwise, we must try to find file manual *** }
		^if(def $class.prefix){
			$path[^path.replace[$class.prefix;]]
			$path[^path.trim[left;/]]

			^if(!def $path){
				$path[$class.name]
			}

			$prefixes[^self.prefixes.select($self.prefixes.name eq $class.prefix)]
		}{
			$prefixes[^self.prefixes.select($self.prefixes.name eq "*")]
		}

		^prefixes.menu{
			^if(!$class.loaded){
				^if(-f "${prefixes.path}/${path}.${class.type}"){
					^self._use[${prefixes.path}/${path}.${class.type};$params]
					$class.loaded(true)
				}($prefixes.name ne "*" && -f "${prefixes.path}/${prefixes.name}/${path}.${class.type}"){
					^self._use[${prefixes.path}/${prefixes.name}/${path}.${class.type};$params]
					$class.loaded(true)
				}
			}
		}
	}

	^rem{ *** process class alias, if needed *** }
	^if(def $class.alias && def $class.class && $class.loaded){
		$exist(false)

		^try{
			$exist(def ^reflection:class_by_name[$class.alias])
		}{
			$exception.handled(true)
		}

		^if(!$exist){
			^process[$MAIN:CLASS]{@CLASS^#0A${class.alias}^#0A^#0A@BASE^#0A${class.class}^#0A}
		}
	}
}
#end @use[]


###############################################################################
@autouse[path]
^try{
	^self.use[$path]
}{
	$exception.handled(true)

	^if($self._autouse is junction){
		^self._autouse[$path]
	}
}
#end @autouse[]


###############################################################################
@register[prefix;path;force][locals]
$force(^force.bool(false))

$prefix[^self._normalizePrefix[$prefix]]

^if($force || $prefix eq "*" || !^self.prefixes.locate[name;$prefix]){
	$path[^self._toPosix[$path]]
	$path[^self._resolve[$path]]
	$path[^self._relative[$self.root;$path]]

	^if(^path.left(1) ne "/"){
		$path[/$path]
	}

	^if(!^MAIN:CLASS_PATH.locate[path;$path]){
		^MAIN:CLASS_PATH.append{$path}
	}

	^if($prefix eq ""){
		$prefix[*]
	}

	^self.prefixes.append[
		$.name[$prefix]
		$.path[$path]
	]
}
#end @register[]


###############################################################################
@include[path;hash][locals]
^if(!def $hash){
	$hash[^math:md5[$path]]
}

^if(!^self.includes.locate[hash;$hash]){
	$used[false]

	$path[^self._toPosix[$path]]
	$path[^self._resolve[$path]]
	$path[^self._relative[$self.root;$path]]

	^if(^path.left(1) ne "/"){
		$path[/$path]
	}

	^if(-f "${path}"){
		^self._use[$path]
		$used[true]
	}

	^self.includes.append[
		$.hash[$hash]
		$.path[$path]
		$.used[$used]
	]
}
#end @include[]



###############################################################################
# @PRIVATE
###############################################################################
@_configure[params][locals]
$params[^hash::create[$params]]

# find Document root.
$self.root[^self._findRoot[$params.root]]

# find $MAIN:CLASS_PATH
^if(!def $MAIN:CLASS_PATH){
	$MAIN:CLASS_PATH[^table::create{path}]
}($MAIN:CLASS_PATH is string){
	$MAIN:CLASS_PATH[^table::create{path^#0A${MAIN:CLASS_PATH}}]
}

# extend @MIAN:use[]
$self._use[$MAIN:use]

^process[$MAIN:CLASS]{@use[path^;params][locals]
	^^MAIN:AUTOLOAD.use[^$path^;^$params]
}

# extend @MIAN:autouse[]
$self._autouse[$MAIN:autouse]

^process[$MAIN:CLASS]{@autouse[path][locals]
	^^MAIN:AUTOLOAD.autouse[^$path^]
}

# process includes files
^if(def $params.includes && $params.includes is table){
	^params.includes.menu{
		^self.include[$params.includes.path;$params.includes.hash]
	}
}

# process namespaces
^if(def $params.prefixes && $params.prefixes is table){
	^params.prefixes.menu{
		^self.register[$params.prefixes.name;$params.prefixes.path]
	}
}
#end @_configure[]


###############################################################################
@_findRoot[root][locals]
$result[$root]

^if(def $env:PWD){
	$result[$env:PWD]
}(def $env:DOCUMENT_ROOT_VIRTUAL){
	$result[$env:DOCUMENT_ROOT_VIRTUAL]
}(def $env:DOCUMENT_ROOT){
	$result[$env:DOCUMENT_ROOT]
}

$result[^self._toPosix[$result]]

^if(^result.left(1) ne "/"){
	$result[/$result]
}
#end @_findRoot[]


###############################################################################
@_toPosix[path][locals]
$result[^path.trim[]]

^if(def $result){
	$length(^result.length[])

	^if($length > 2 && ^result.mid(1;1) eq ":" && (^result.mid(2;1) eq "/" || ^result.mid(2;1) eq "\")){
		$result[^result.mid(2;$length - 1)]
	}
}
#end @_toPosix[]


###############################################################################
@_normalizePrefix[prefix][locals]
$result[^prefix.replace[_;/]]
$result[^result.replace[\;/]]
$result[^result.trim[both;\/.]]
#end @_normalizePrefix[]


###############################################################################
@_parseClass[class]
# origin class request
$request[$class]

# check alias for class
^if(^request.pos[ AS ] != -1){
	$class[^request.split[ AS ;lh]]

	$alias[^class.1.trim[]]
	$class[^class.0.trim[]]
}

# normalize slashes and underscores
$class[^self._normalizeClass[$class]]

# find prefix
$prefix[^self._findPrefix[$class.path;$class.name]]

$result[^hash::create[
	$.request[$request]

	$.class[$class.class]
	$.path[$class.path]
	$.name[$class.name]
	$.type[$class.type]

	$.alias[$alias]
	$.prefix[$prefix]
	$.loaded(false)
]]
#end @_parseClass[]


###############################################################################
@_normalizeClass[class][locals]
$result[^hash::create[
	$.class[]
	$.path[]
	$.name[]
	$.type[]
]]

^if(def $class){
	$type[^file:justext[$class]]

	^if(!def $type){
		$type[p]
	}

	$path[^file:dirname[$class]]

	^if($path eq "."){
		$path[]
	}

	$name[^file:justname[$class]]

	^if(^name.pos[_] != -1){
		$class[$name]

		$name[^name.replace[_;/]]
		$name[^name.trim[both;\/]]
	}{
		$class[^if(def $path){${path}/}${name}]
	}

	^if(^class.left(1) eq "/"){
		$class[]
	}

	$result[
		$.class[$class]
		$.path[$path]
		$.name[$name]
		$.type[$type]
	]
}
#end @_normalizeClass[]


###############################################################################
@_findPrefix[path;name][locals]
$class[^if(def $path){${path}/}${name}]

^if(^self.prefixes.locate[name;$class]){
	$prefix[$class]
}{
	$_parts[^class.split[/;r]]

	^_parts.menu{
		$_piece[${_parts.piece}^if(def $_piece){/$_piece}]

		$_prefix[^class.match[$_piece][gi]{}]
		$_prefix[^_prefix.trim[both;/]]

		^if(^self.prefixes.locate[name;$_prefix]){
			$prefix[$_prefix]
			^break[]
		}
	}
}

$result[$prefix]
#end @_findPrefix[]





###############################################################################
@_relative[from;to][locals]
$result[]

^if($from ne $to){
	^for[fromStart](1;^from.length[]){
		^if(^from.mid($fromStart;1) ne "/"){
			^break[]
		}
	}
	$fromEnd(^from.length[])
	$fromLen($fromEnd - $fromStart)

	^for[toStart](1;^to.length[]){
		^if(^to.mid($toStart;1) ne "/"){
			^break[]
		}
	}
	$toEnd(^to.length[])
	$toLen($toEnd - $toStart)

	$length(^if($fromLen < $toLen){$fromLen}{$toLen})
	$lastCommonSep(-1)

	^for[i](0;$length){
		^if($i == $length){
			^if($toLen > $length){
				^if(^to.mid(($toStart + $i);1) eq "/"){
					$result[^to.mid(($toStart + $i + 1);^to.length[])]
				}($i == 0){
					$result[^to.mid(($toStart + $i);^to.length[])]
				}
			}($fromLen > $length){
				^if(^from.mid(($fromStart + $i);1) eq "/"){
					$lastCommonSep($i)
				}($i == 0){
					$lastCommonSep(0)
				}
			}

			^break[]
		}

		$fromCode[^from.mid(($fromStart + $i);1)]
		$toCode[^to.mid(($toStart + $i);1)]

		^if($fromCode ne $toCode){
			^break[]
		}($fromCode eq "/"){
			$lastCommonSep($i)
		}
	}

	^if(!def $result){
		$return[]
		$index($fromStart + $lastCommonSep + 1)

		^while($index <= $fromEnd){
			^if($index == $fromEnd || ^from.mid($index;1) eq "/"){
				^if(^return.length[] == 0){
					$return[..]
				}{
					$return[${return}/..]
				}
			}

			^index.inc[]
		}


		^if(^return.length[] > 0){
			$result[${return}^to.mid(($toStart + $lastCommonSep);^to.length[])]
		}{
			^toStart.inc($lastCommonSep)

			^if(^to.mid($toStart;1) eq "/"){
				^toStart.inc[]
			}

			$result[^to.mid($toStart;^to.length[])]
		}
	}
}
#end @_relative[]


###############################################################################
@_resolve[*paths]
$result[]

$paths[^hash::create[$paths]]
$count(^paths.count[] - 1)

$isAbsolute(false)

^if($count >= 0){
	^while($count >= 0){
		$path[^paths.at($count)[value]]
		$path[^path.trim[]]

		^if(^path.length[] == 0){
			^count.dec[]
			^continue[]
		}

		$result[${path}^if(def $result){/${result}}]

		$isAbsolute(^self._isAbsolute[$path])

		^if($isAbsolute){
			^break[]
		}{
			^count.dec[]
		}
	}

	$result[^self._normalize[$result;!$isAbsolute]]

	^if($isAbsolute){
		^if(^result.length[] > 0){
			$result[/${result}]
		}{
			$result[/]
		}
	}(^result.length[] > 0){
		$result[${self.root}/${result}]
	}{
		$result[$self.root]
	}
}{
	$result[$self.root]
}
#end @_resolve[]


###############################################################################
@_normalize[path;isAbsolute]
$result[]

$isAbsolute(^isAbsolute.bool(^self._isAbsolute[$path]))

$path[^path.trim[both;/]]

$parts[^path.split[/;l]]
$parts[^parts.select(def ^parts.piece.trim[] && ^parts.piece.trim[] ne ".")]

$paths[^table::create{path}]

^parts.menu{
	$part[^parts.piece.trim[]]

	^if($part eq '..'){
		^if($isAbsolute){
			^if(^paths.count[]){
				^paths.delete[]
			}
		}{
			^if(^paths.count[]){
				^if($paths.path eq '..'){
					^paths.append{$part}
				}{
					^paths.delete[]
				}
			}{
				^paths.append{$part}
			}
		}
	}{
		^paths.append{$part}
	}

	^paths.offset[set]($paths - 1)
}

$result[^paths.menu{$paths.path}[/]]
#end @_normalize[]


###############################################################################
@_isAbsolute[path][locals]
$result(false)

^if(def $path){
	^if(^path.left(1) eq "/" || ^path.left(1) eq "\"){
		$result(true)
	}(^path.length[] > 2 && ^path.mid(1;1) eq ":" && (^path.mid(2;1) eq "/" || ^path.mid(2;1) eq "\")){
		$result(true)
	}
}
#end @_isAbsolute[]
