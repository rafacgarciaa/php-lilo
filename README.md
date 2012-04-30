# Lilo

Lilo is a fast engine that allow you scan a file to extract a dependency graph using a subset of Sprockets directives. This class supports the following directives:

	//= require
	//= require_directory
	//= require_tree

For more information about them please visit [Sprockets] (https://github.com/sstephenson/sprockets).

## Usage (file side)

In your files, write Sprockets-style comments to indicate dependencies, e.g.

    //= require dependency

If you want to bring in a whole folder of files, use

    //= require_tree dir

Lilo supports the three Sprockets-style comments, for more information about them please visit [Sprockets] (https://github.com/sstephenson/sprockets).

## Usage (PHP script)

In your script:

	require_once '../libs/diacronos/Lilo/Lilo.php';
	use \diacronos\Lilo\Lilo;

	$lilo = new Lilo(array(/* extensions */));

The extensions allow to `Lilo` knows the types of files that must be processed.
For example, for working with Javascript and CoffeeScript files:

	require_once '../libs/diacronos/Lilo/Lilo.php';
	use \diacronos\Lilo\Lilo;

	$lilo = new Lilo(array('js', 'coffee'));
	$lilo->appendPath('path/to/files/dependencies/1');
	$lilo->appendPath('path/to/files/dependencies/2');
	....

Each `Lilo` instance use a [`DepGraph`](https://github.com/rafarga/php-dep-graph) instance. You can `scan` a file to just update the dependency graph:

	$lilo->scan('dir/foo.coffee');

Later you can get an array of filenames showing the series of dependencies the scanned file has:
	
	$result = $lilo->getChain('dir/foo.coffee');

You can get an array of filenames and contents showing the series of dependencies the scanned file has:

	$result = $lilo->getFileChain('dir/foo.coffee');

The result is in the format `array(array('filename' => 'dependency1.js', 'content' => "// file dependency1.js content"), ...)`.

## Credits
Lilo is PHP library inspired by [Sprockets] (https://github.com/sstephenson/sprockets) and based in [Snockets] (https://github.com/TrevorBurnham/snockets). All credits for them.
