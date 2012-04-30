<?php
/**
 * Lilo - A file concatenation tool for PHP inspired by Sprockets and based in Snockets
 * 
 * @author      Rafael García <rafaelgarcia@profesionaldiacronos.com>
 * @copyright   2012 Rafael García
 * @link        http://lilo.profesionaldiacronos.com
 * @license     http://lilo.profesionaldiacronos.com/license
 * @version     1.0.0
 * @package     Lilo
 *
 * MIT LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace diacronos\Lilo;

/**
 * FsUtils
 *
 * This class groups general utilities related to working with the filesystem. 
 * His interface has two parts, the first consists of all static methods:
 *
 * equals( string $file1, string $file2 )
 * isExplicit( string $relName )
 * extension( string $filePath )
 * join( string $path1, string $path2... )
 * normalize( string $relPath )
 * getEntries( string $dir, bool $quitDots )
 * 
 * and the last, has the following instance methods:
 * 
 * preppendSourcePath( string $source )
 * appendSourcePath( string $source )
 * addWorkExtension( string $extension )
 * getWorkExtensions( )
 * expand( string $relPath )
 * tryExtensionsForExpand( string $relPath, array $extensions )
 * stripExtension( string $filePath, array $extensions )
 *
 * This class must be internal
 *
 * @package Lilo
 * @author  Rafael García
 * @since   1.0.0
 */
class FsUtils
{
	/**
	 * @var string
	 */
	const EXPLICIT_PATH = '/^\/|:/';
	
	/**
	 * @var array
     */
    private $_sources = array();
    
    /**
     * @var array
     */
    private $_extensions = array();
    
    /**
	 * Preppend a load path.
	 * Directories at the beginning of the load path have precedence over subsequent directories.
	 * Theese paths are used for expand relative paths to absolute paths.
	 *
	 * @access  public
	 * @param   string   $path
	 * @return  void
	 */
    public function preppendPath($path)
    {
    	$this->_addPath($path, true);
    }
    
    /**
	 * Append a load path.
	 * Directories at the beginning of the load path have precedence over subsequent directories.
	 * Theese paths are used for expand relative paths to absolute paths.
	 * 
	 * @access  public
	 * @param   string   $path
	 * @return  void
	 */
    public function appendPath($source)
    {
    	$this->_addPath($source, false);
    }
    
    /**
     * @access  private
     * @param   string   $path
     * @param   bool  	 $preppend 	`true` for preppend
     * @return  void
     */
    private function _addPath($source, $preppend = false)
    {
    	$source = self::normalize($source);
    	$pos = array_search($source, $this->_sources);
    	if ($pos !== false) {
    		array_splice($this->_sources, $pos, 1);
    	}
    	
    	if ($preppend) {
    		array_unshift($this->_sources, $source);
    	} else {
    		array_push($this->_sources, $source);
    	}
    }
    
    /**
     * @access  public
     * @param   string   $extension
     * @return  void
     */
    public function addWorkExtension($extension)
    {
    	if (($pos = array_search($extension, $this->_extensions)) > -1) {
    		array_splice($this->_extensions, $pos, 1);
    	}
    	
    	array_unshift($this->_extensions, $extension);
    }
    
    /**
     * Returns a array with all extensions registered for work.
     * @access public
     * @return array
     */
    public function getWorkExtensions()
    {
    	return $this->_extensions;
    }
    
    /**
     * Expand a relative path to an absolute path.
     * Find matches in every path load and returns the first matched.
     *
     * @access  public
     * @param   string   $relPath
     * @return  string
     * @throws \Exception if a $relPath doesn't exists
     */
    public function expand($relPath)
    {
    	if (preg_match(self::EXPLICIT_PATH, $relPath) > 0) {
    		return $relPath;
    	}
    	 
    	foreach ($this->_sources as $source) {
    		if (preg_match(self::EXPLICIT_PATH, $source) > 0) {
    			$path = self::join($source, $relPath);
    			if (file_exists($path)) {
    				return $path;
    			}
    		}
    
    		$path = self::join(getcwd(), $source, $relPath);
    		if (file_exists($path)) {
    			return $path;
    		}
    	}
    	 
    	throw new \Exception("Path {$relPath} can't be expanded to an absolute path");
    }
    
    /**
     * Expand a relative path without extension to an absolute path.
     * Find matches in every path load and returns the first matched.
     *
     * @access  public
     * @param   string   $relPath
     * @param   array    $extensions 	extensions to try
     * @return  string
     * @throws \Exception if a $relPath doesn't exists
     */
    public function tryExtensionsForExpand($relPath)
    {
    	foreach ($this->getWorkExtensions() as $ext) {
    		try {
    			return $this->expand($relPath . '.' . $ext);
    		} catch (\Exception $e) {
    			// doesn't nothing
    		}
    	}
    	 
    	return $this->expand($relPath);
    }
    
    /**
     * @access  public
     * @param   string   $filePath
     * @return  string
     */
    public function stripExtension($filePath)
    {
    	$ext = self::extension($filePath);
    	$chars = strlen($ext);
    	if ($chars == 0 || !in_array($ext, $this->getWorkExtensions())) {
    		return $filePath;
    	}
    
    	return substr($filePath, 0, ++$chars * (-1));
    }
    
    /**
     * Compare two paths. Return `true` is both references the same resource.
     * @static
     * @access  public
     * @param   string   $file1
     * @param   string   $file2
     * @return  bool
     */
    static public function equals($file1, $file2)
    {
    	return (strcmp(realpath($file1), realpath($file2)) == 0);
    }
    
    /**
     * Return `true` if a path is absolute. 
     * @static
     * @access  public
     * @param   string   $relName
     * @return  bool
     */
    static public function isExplicit($relName)
    {
    	return (preg_match(self::EXPLICIT_PATH, $relName) > 0);
    }
    
    /**
     * Return the extension from a filename 
     * @static
     * @access  public
     * @param   string   $filePath
     * @return  string
     */
    static public function extension($filePath)
    {
    	return pathinfo($filePath, PATHINFO_EXTENSION);
    }
    
    /**
     * Join segments from a path.
     * @static
     * @access  public 
     * @params  string
     * @return  string
     */
    static public function join()
    {
    	return implode(DIRECTORY_SEPARATOR, func_get_args());
    }
    
    /**
     * Strip the superflous segment (.. , .) of a path.
     * @static
     * @access  public
     * @param   string   $relPath
     * @return  string
     */
    static public function normalize($relPath)
    {
    	$path_segments = preg_split('@\\/@', $relPath);
    	$final_segments = array();
    	
    	$index = self::isExplicit($relPath) ? 1 : 0;
    	for ($i=$index, $ci=count($path_segments); $i < $ci; $i++) {
    		$segment = trim($path_segments[$i]);
    		if ($segment == '' || $segment == '.') {
    			continue;
    		}

    		if ($segment == '..') {
    			$last = $final_segments[count($final_segments) - 1];
    			if ($last != '..' && $last != '') {
    				array_pop($final_segments);
    				continue;
    			}
    		}
    		
    		array_push($final_segments, $segment);
    	}
    	
    	return call_user_func_array('self::join', $final_segments);
    }
    
    /**
     * Get all entries from a dir, ordered alphabetically.
     * @static
     * @access  public
     * @param   string   $dir		dependency identifier
     * @param   bool     $quitDots 	`true` for skips dot dirs
     * @return  array
     */
    static function getEntries($dir, $quitDots = true)
    {
    	$files = scandir($dir);
    	if ($quitDots) {
    		array_shift($files);
    		array_shift($files);
    	}
    	
    	return $files;
    }
}