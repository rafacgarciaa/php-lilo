<?php

/** @see \diacronos\Lilo\Lilo */
require_once '../libs/diacronos/Lilo/Lilo.php';
use \diacronos\Lilo\Lilo;
require_once('../libs/simpletest/autorun.php');

/**
 * Tests for \Lilo\Lilo class
 *
 * @package DepGraph
 * @author  Rafael García
 * @since   1.0.0
 */
class TestOfLilo extends UnitTestCase
{
	/**
	 * @var \diacronos\Lilo\Lilo
	 */
	private $_lilo;
	
	/**
	 * Constructor
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_lilo = new Lilo(array('js', 'coffee'));
		$this->_lilo->appendLoadPath('../etc/assets');
	}
	
	public function getLilo()
	{
		return $this->_lilo;
	}
	
	public function testIndependentJSFilesHaveNoDependencies()
	{
		$sp = $this->getLilo();
		$sp->scan('b.js');
				
		$this->assertTrue(
			array_compare($sp->getChain('b.js'), array()),
			'Independent JS files have no dependencies'
		);
	}
	
	public function testSingle_StepDependenciesAreCorrectlyRecorded()
	{
		$sp = $this->getLilo();
		$sp->scan('a.coffee');
		$this->assertTrue(
			array_compare($sp->getChain('a.coffee'), array('b.js')),
			'Single-step dependencies are correctly recorded'
		);
	}
	
	public function testDependenciesWithMultiplExtensionsAreAccepted()
	{
		$sp = $this->getLilo();
		$sp->scan('testing.js');
	
		$this->assertTrue(
			array_compare($sp->getChain('testing.js'), array('1.2.3.coffee')),
			'Dependencies with multiple extensions are accepted'
		);
	}
	
	public function testDependenciesCanHaveSubdirectory_RelativePaths()
	{
		$sp = $this->getLilo();
		$sp->scan('song/loveAndMarriage.js');
		
		$this->assertTrue(
			array_compare(
				$sp->getChain('song/loveAndMarriage.js'),
				array('song/horseAndCarriage.coffee')
			), 'Dependencies can have subdirectory-relative paths'
		);
	}
	
	public function testMultipleDependenciesCanBeDeclaredInOneRequireDirective()
	{
		$sp = $this->getLilo();
		$sp->scan('poly.coffee');
	
		$this->assertTrue(
			array_compare($sp->getChain('poly.coffee'), array('b.js', 'x.coffee')),
			'Multiple dependencies can be declared in one require directive'
		);
	}
	
	public function testChainedDependenciesAreCorrectlyRecorded()
	{
		$sp = $this->getLilo();
		$sp->scan('z.coffee');
		
		$this->assertTrue(
			array_compare($sp->getChain('z.coffee'), array('x.coffee', 'y.js')),
			'Chained dependencies are correctly recorded'
		);
	}
	
	public function testDependencyCyclesCauseNoErrorsDuringScanning()
	{
		$sp = $this->getLilo();
		$sp->scan('yin.js');
		
		$this->expectException('Exception', 'Dependency cycles cause no errors during scanning');
		
		$sp->getChain('yin.js');
		$sp->getChain('yang.coffee');
	}
	
	public function testRequireTreeWorksForSameDirectory()
	{
		$sp = $this->getLilo();
		$sp->scan('branch/center.coffee');
	
		$this->assertTrue(
			array_compare(
				$sp->getChain('branch/center.coffee'),
				array(
					'branch/edge.coffee',
					'branch/periphery.js',
					'branch/subbranch/leaf.js'
				)
			), 'require_tree works for same directory'
		);
	}
	
	public function testRequireWorksForIncludesThatAreRelativeToOrigFileUsingDotDot()
	{
		$sp = $this->getLilo();
		$sp->scan('first/syblingFolder.js');
	
		$this->assertTrue(
			array_compare($sp->getChain('first/syblingFolder.js'), array('sybling/sybling.js')),
			'require works for includes that are relative to orig file using ../'
		);
	}
	
	public function testRequireTreeWorksForNestedDirectories()
	{
		$sp = $this->getLilo();
		$sp->scan('fellowship.js');
	
		$this->assertTrue(
			array_compare(
				$sp->getChain('fellowship.js'),
				array(
					'middleEarth/legolas.coffee',
					'middleEarth/shire/bilbo.js',
					'middleEarth/shire/frodo.coffee'
				)
			), 'require_tree works for nested directories'
		);
	}
	
	public function testRequireTreeWorksForRedundantDirectories()
	{
		$sp = $this->getLilo();
		$sp->scan('trilogy.coffee');
	
		$this->assertTrue(
			array_compare(
				$sp->getChain('trilogy.coffee'),
				array(
					'middleEarth/shire/bilbo.js',
					'middleEarth/shire/frodo.coffee',
					'middleEarth/legolas.coffee'
				)
			), 'require_tree works for redundant directories'
		);
	}
	
	public function testGetFileChainReturnsCorrectDotJsFilenamesAndCode()
	{
		$sp = $this->getLilo();
		$sp->scan('z.coffee');
		
		$x_coffee =<<<COFFEE
"""
Double rainbow
SO INTENSE
"""
COFFEE;
		
		$this->assertTrue(
			record_compare(
				$sp->getFileChain('z.coffee'),
				array(
					array(
						'filename' => 'x.coffee',
						'content'  => $x_coffee
					),
					array(
						'filename' => 'y.js',
						'content'  => '//= require x'
					),
					array(
						'filename' => 'z.coffee',
						'content'  => '#= require y'
					)
				)
			), 'getFileChain returns correct .js filenames and code'
		);
	}
	
	public function testGetFileChainReturnsCorrectDotJsFilenamesAndCodeWithDotDotSlashInRequirePath()
	{
		$sp = $this->getLilo();
		$sp->scan('first/syblingFolder.js');
		
		$this->assertTrue(
			record_compare(
				$sp->getFileChain('first/syblingFolder.js'),
				array(
					array(
						'filename' => 'sybling/sybling.js',
						'content' => 'var thereWillBeJS = 3;'
					),
					array(
						'filename' => 'first/syblingFolder.js',
						'content' => '//= require ../sybling/sybling.js'
					)
				)
			), 'getFileChain returns correct .js filenames and code with ../ in require path'
		);
	}
}

// utility compare functions
function array_compare($array1, $array2)
{
	if (count($array1) != count($array2)) {
		return false;
	}
	
	for ($i=0, $ci=count($array1); $i < $ci; $i++) {
		if ($array1[$i] != $array2[$i]) {
			return false;
		}
	}
	
	return true;
}

function filerecord_compare($record1, $record2)
{
	return $record1['filename'] == $record2['filename'] &&
		   $record1['content'] == $record2['content'];
}

function record_compare($array1, $array2)
{
	if (count($array1) != count($array2)) {
		return false;
	}
	
	for ($i=0, $ci=count($array1); $i < $ci; $i++) {
		if (!filerecord_compare($array1[$i], $array2[$i])) {
			return false;
		}
	}
	
	return true;
}
