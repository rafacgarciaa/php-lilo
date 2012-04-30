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

/** @see \diacronos\Lilo\FsUtils */
require_once 'diacronos/Lilo/FsUtils.php';

/** @see \diacronos\DepGraph\DepGraph */
require_once 'diacronos/DepGraph/DepGraph.php';
use \diacronos\DepGraph\DepGraph;

/**
 * Lilo
 *
 * Lilo is a fast engine that allow you scan a file to extract a dependency graph using
 * a subset of Sprockets directives. This class supports the following directives:
 * 
 * //= require
 * //= require_directory
 * //= require_tree
 * 
 * For more information about them please visit Sprockets in {@link https://github.com/sstephenson/sprockets}.
 * 
 * Additionaly, unlike Snockets, Lilo supports multiple load path like the original, Sprockets.
 * For more information about load paths visit Sprockets in {@link https://github.com/sstephenson/sprockets}.
 * For more information about Snockets please visit it in {@link https://github.com/TrevorBurnham/snockets}.
 *
 * The class has the next public api:
 * 
 * preppendLoadPath( mixed $object )
 * appendLoadPath( mixed $object )
 * getRegisteredExtensions()
 * scan( string $filename )
 * getChain( string $filename )
 * getFileChain( string $filename )
 * 
 * I, personally, use this class for implementing diferent JavaScript package management solutions in PHP according my needs.
 * 
 * @package Lilo
 * @author  Rafael García
 * @since   1.0.0
 */
class Lilo
{
	/**
	 * @var string
	 */
	const HEADER = '/(?:(\#\#\#.*\#\#\#\n*)|(\/\/.*\n*)|(\#.*\n*))+/';
	
	/**
	 * @var string
	 */
	const DIRECTIVE = '@^[\W]*=\s*(\w+.*?)(\*\\\/)?$@m';
	
	/**
	 * @var DepGraph
	 */
	private $_dep;
	
	/**
	 * @var FsUtils
	 */
	private $_fs;
	
	/**
	 * @var array
	 */
	private $_files = array();
	
	/**
	 * @var array
	 */
	private $_scanExcludes = array();
	
	/**
	 * Constructor
	 * -- Valid files: files that can content valid Sprockets directives 
	 * 
	 * @access  public
	 * @param   array   $extensions  extensions of valid files
	 * @return  void
	 */
	public function __construct(array $extensions)
	{
		$this->_dep = new DepGraph();
		$this->_fs = new FsUtils();
		foreach ($extensions as $ext) {
			$this->_fs->addWorkExtension($ext);
		}
	}
	
	/**
	 * Preppend a load path
	 * @access  public
	 * @param   string   $path
	 * @return  void
	 */
	public function preppendLoadPath($path)
	{
		$this->_fs->preppendPath($path);
	}
	
	/**
	 * Append a load path
	 * @access  public 
	 * @param   string   $path
	 * @return  void
	 */
	public function appendLoadPath($path)
	{
		$this->_fs->appendPath($path);
	}
	
	/**
	 * Scan a file and extract a dependency graph.
	 * Returns `false` if that file is already scaned.
	 * 
	 * @access  public
	 * @param   string   $filepath
	 * @return  bool
	 */
	public function scan($filepath)
	{
		if (in_array($filepath, $this->_scanExcludes)) {
			return false;
		}
		
		array_push($this->_scanExcludes, $filepath);
		
		$dirFilePath = dirname($filepath);
		$directives = self::parseDirectives($this->_content($filepath));
		foreach ($directives as $item) {
			$words = preg_split('/\s+/', preg_replace('/[\'"]/', '', $item));
			$command = array_shift($words);
				
			switch ($command) {
				case 'require' : 
					foreach ($words as $relPath) {
						$this->_req($relPath, $filepath);
					}
						
					break;
				case 'require_tree' :
					foreach ($words as $relPath) {
						$this->_reqDirectory(FsUtils::join($dirFilePath, $relPath), $filepath, true);
					}
						
					break;
				case 'require_directory' :
					foreach ($words as $relPath) {
						$this->_reqDirectory(FsUtils::join($dirFilePath, $relPath), $filepath, false);
					}
					
					break;
			}
		}
		
		return true;
	}
	
	/**
	 * Get a dependency graph for a file previously scaned.
	 * Return a array with the filename of each dependency.
	 * Returns `false` if that file is not scaned.
	 * 
	 * @access  public
	 * @param   string   $filepath
	 * @return  array|false
	 * @throws \Exception if a cyclic dependecy is detected
	 */
	public function getChain($filepath)
	{
		if (!in_array($filepath, $this->_scanExcludes)) {
			return false;
		}
		
		return $this->_dep->getChain($filepath);
	}
	
	/**
	 * Get a dependency graph for a file previously scaned.
	 * Return a array with the filename and content of each dependency.
	 * Returns `false` if that file is not scaned.
	 * 
	 * @access  public
	 * @param   string   $filepath
	 * @return  array|false
	 * @throws \Exception if a cyclic dependecy is detected
	 */
	public function getFileChain($filepath)
	{
		$dependences = $this->getChain($filepath);
		if ($dependences === false) {
			return false;
		}
		
		$result = array();
		foreach ($dependences as $dep) {
			array_push($result, array('filename' => $dep, 'content' => $this->_content($dep)));
		}
		
		array_push($result, array('filename' => $filepath, 'content' => $this->_content($filepath)));
		
		return $result;
	}
	
	/**
	 * Get a array with the extensions of files that will processed.
	 * @access  public
	 * @return  array
	 */
	public function getRegisteredExtensions()
	{
		return $this->_fs->getWorkExtensions();
	}
	
	/**
	 * Read a file. Returns its content.
	 * @access  private
	 * @param   string  $filepath
	 * @return  string
	 * @throws \Exception if a filepath doesn't exists
	 */
	private function _content($filepath)
	{
		try {
			$fullpath = $this->_fs->expand($filepath);
		} catch (\Exception $e) {
			throw new \Exception("File not found: '{$filepath}'");
		}
		
		if (isset($this->_files[$filepath]) == 0) {
			$this->_files[$filepath] = file_get_contents($fullpath);
		}
				
		return $this->_files[$filepath];
	}
	
	/**
	 * Get an array with the names of readed files.
	 * @access  private 
	 * @return  array
	 */
	private function _files()
	{
		return array_keys($this->_files);
	}
	
	/**
	 * Get a relative path from an absolute path.
	 * @access  private
	 * @param   string  $absFilename	file to match
	 * @param   array   $filePaths		relative paths to match
	 * @return 	string|false
	 * @throws \Exception if a relative path doesn't exists
	 */
	private function _tryFiles($absFilename, $filePaths)
	{
		foreach ($filePaths as $filePath) {
			$absFilePath = $this->_fs->expand($filePath);
			if (strcmp($absFilePath, $absFilename) == 0) {
				return $filePath;
			}
		}
		
		return false;
	}
	
	/**
	 * Find a file without extension in all load paths.
	 * Return the first match or `false`.
	 * 
	 * @access  private
	 * @param   string   $filename
	 * @return  string|false
	 * @throws \Exception if a filename doesn't exists
	 */
	private function _findMatchingFile($filename)
	{
		$dirFilename = dirname($filename);
		
		try {
			$absFilename = $this->_fs->tryExtensionsForExpand($filename);
		} catch (\Exception $e) {
			throw new \Exception("File not found: '{$filename}'");
		}
		
		$filePaths = $this->_files();
		$result = $this->_tryFiles($absFilename, $filePaths);
		if ($result) {
			return $result;
		}
		
		$dirEntries = FsUtils::getEntries(dirname($absFilename));
		for ($i=0, $ci=count($dirEntries); $i < $ci; $i++) {
			$dirEntries[$i] = FsUtils::join($dirFilename, $dirEntries[$i]);
		}
		
		return $this->_tryFiles($absFilename, $dirEntries);
	}
	 
	/**
	 * require command
	 * @access  private
     * @param   string   $relPath
     * @param   string   $filePath
     * @return  void
     */
	private function _req($relPath, $filePath, $keyPath = false)
	{
		$relName = $this->_fs->stripExtension($relPath);
		if (FsUtils::isExplicit($relName)) {
			$depPath = $relName + '.js';
		} else {
			$depName = FsUtils::join(dirname($filePath), $relPath);
			$depPath = $this->_findMatchingFile($depName);
		}
		
		$depPath = FsUtils::normalize($depPath);
				
		// skip files with unknown extensions
		if (in_array(FsUtils::extension($depPath), $this->getRegisteredExtensions())) {
			if (!$keyPath) {
				$keyPath = $filePath;
			}
			
			$this->_dep->add($keyPath, $depPath);
			$this->scan($depPath);
		}
	}
	
	/**
	 * require_tree/require_directory command
	 * @access  private
     * @param   string   $relPath
     * @param   string   $filePath
     * @return  void
     */
	private function _reqDirectory($dirName, $filePath, $recursive = false)
	{
		try {
			$dirPath = $this->_fs->expand($dirName);
		} catch (\Exception $e) {
			throw new \Exception("Dir not found: '{$dirName}'");
		}
		
		$files = FsUtils::getEntries($dirPath);
		$absFilePath = $this->_fs->expand($filePath);
		foreach ($files as $item) {
			$itemPath = FsUtils::join($dirName, $item);
			$fullPath = $this->_fs->expand($itemPath);
			
			if (!FsUtils::equals($fullPath, $absFilePath)) {
				if (is_file($fullPath)) {
					$this->_req(basename($itemPath), $itemPath, $filePath);
				} elseif ($recursive) {
					$this->_reqDirectory($itemPath, $filePath, true);
				}
			}
		}
	}
		
	/**
	 * Find sprockets directives.
	 * @static
	 * @access  public
     * @param   string  $filecontent
     * @return  array	the directives founded
     */
	static public function parseDirectives($filecontent)
	{
		$filecontent = preg_replace('/[\r\t ]+$/m', '\n', $filecontent);
		if (preg_match(self::HEADER, $filecontent, $matches) == 0) {
			return array();
		}
		
		$header = $matches[0];
		preg_match_all(self::DIRECTIVE, $header, $results);
		
		return $results[1];
	}
}