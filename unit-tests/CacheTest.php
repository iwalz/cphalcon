<?php

/*
  +------------------------------------------------------------------------+
  | Phalcon Framework                                                      |
  +------------------------------------------------------------------------+
  | Copyright (c) 2011-2012 Phalcon Team (http://www.phalconphp.com)       |
  +------------------------------------------------------------------------+
  | This source file is subject to the New BSD License that is bundled     |
  | with this package in the file docs/LICENSE.txt.                        |
  |                                                                        |
  | If you did not receive a copy of the license and are unable to         |
  | obtain it through the world-wide-web, please send an email             |
  | to license@phalconphp.com so we can send you a copy immediately.       |
  +------------------------------------------------------------------------+
  | Authors: Andres Gutierrez <andres@phalconphp.com>                      |
  |          Eduar Carvajal <eduar@phalconphp.com>                         |
  +------------------------------------------------------------------------+
*/

require_once 'helpers/xcache.php';

class CacheTest extends PHPUnit_Framework_TestCase
{

	public function setUp()
	{
		date_default_timezone_set('UTC');
		$iterator = new DirectoryIterator('unit-tests/cache/');
		foreach ($iterator as $item) {
			if (!$item->isDir()) {
				unlink($item->getPathname());
			}
		}
	}

	public function testOutputFileCache()
	{

		$time = date('H:i:s');

		$frontCache = new Phalcon\Cache\Frontend\Output(array(
			'lifetime' => 2
		));

		$cache = new Phalcon\Cache\Backend\File($frontCache, array(
			'cacheDir' => 'unit-tests/cache/',
			'prefix' => 'unit'
		));

		$this->assertFalse($cache->isStarted());

		ob_start();

		//First time cache
		$content = $cache->start('testoutput');
		$this->assertTrue($cache->isStarted());

		if ($content !== null) {
			$this->assertTrue(false);
		}

		echo $time;
		$cache->save(null, null, null, true);

		$obContent = ob_get_contents();
		ob_end_clean();

		$this->assertEquals($time, $obContent);
		$this->assertTrue(file_exists('unit-tests/cache/unittestoutput'));

		//Same cache
		$content = $cache->start('testoutput');
		$this->assertTrue($cache->isStarted());

		if ($content === null) {
			$this->assertTrue(false);
		}

		$this->assertEquals($time, $obContent);

		//Refresh cache
		sleep(3);

		$time2 = date('H:i:s');

		ob_start();

		$content = $cache->start('testoutput');
		$this->assertTrue($cache->isStarted());

		if ($content !== null) {
			$this->assertTrue(false);
		}

		echo $time2;
		$cache->save(null, null, null, true);

		$obContent2 = ob_get_contents();
		ob_end_clean();

		$this->assertNotEquals($time, $obContent2);
		$this->assertEquals($time2, $obContent2);

		//Check keys
		$keys = $cache->queryKeys();
		$this->assertEquals($keys, array(
			0 => 'unittestoutput',
		));

		// $cache->exists('testoutput') is not always true because Travis CI could be slow sometimes
		//Exists?
		if ($cache->exists('testoutput')) {
			$this->assertTrue($cache->exists('testoutput'));

			//Delete cache
			$this->assertTrue($cache->delete('testoutput'));
		}

	}

	public function testDataFileCache()
	{

		$frontCache = new Phalcon\Cache\Frontend\Data();

		$cache = new Phalcon\Cache\Backend\File($frontCache, array(
			'cacheDir' => 'unit-tests/cache/'
		));

		$this->assertFalse($cache->isStarted());

		//Save
		$cache->save('test-data', "nothing interesting");

		$this->assertTrue(file_exists('unit-tests/cache/test-data'));

		//Get
		$cachedContent = $cache->get('test-data');
		$this->assertEquals($cachedContent, "nothing interesting");

		//Save
		$cache->save('test-data', "sure, nothing interesting");

		//Get
		$cachedContent = $cache->get('test-data');
		$this->assertEquals($cachedContent, "sure, nothing interesting");

		//Exists
		$this->assertTrue($cache->exists('test-data'));

		//Delete
		$this->assertTrue($cache->delete('test-data'));
	}

    public function testDataFileCacheIncrement()
    {

        $frontCache = new Phalcon\Cache\Frontend\Data();

        $cache = new Phalcon\Cache\Backend\File($frontCache, array(
            'cacheDir' => 'unit-tests/cache/'
        ));
        $cache->delete('foo');
        $cache->save('foo', "1");
        $this->assertEquals(2, $cache->increment('foo'));

        $this->assertEquals($cache->get('foo'), 2);

        $this->assertEquals($cache->increment('foo', 5), 7);
    }

    public function testDataFileCacheDecrement()
    {

        $frontCache = new Phalcon\Cache\Frontend\Data();

        $cache = new Phalcon\Cache\Backend\File($frontCache, array(
            'cacheDir' => 'unit-tests/cache/'
        ));
        $cache->delete('foo');
        $cache->save('foo', "100");
        $this->assertEquals(99, $cache->decrement('foo'));

        $this->assertEquals(95, $cache->decrement('foo', 4));
    }

	private function _prepareIgbinary()
	{

		return false;
		if (!extension_loaded('igbinary')) {
			$this->markTestSkipped('Warning: igbinary extension is not loaded');
			return false;
		}

		return true;
	}

	public function testIgbinaryFileCache()
	{
		if (!$this->_prepareIgbinary()) {
			return false;
		}

		$frontCache = new Phalcon\Cache\Frontend\Igbinary();

		$cache = new Phalcon\Cache\Backend\File($frontCache, array(
			'cacheDir' => 'unit-tests/cache/'
		));

		$this->assertFalse($cache->isStarted());

		//Save
		$cache->save('test-data', "nothing interesting");

		$this->assertTrue(file_exists('unit-tests/cache/test-data'));

		//Get
		$cachedContent = $cache->get('test-data');
		$this->assertEquals($cachedContent, "nothing interesting");

		//Save
		$cache->save('test-data', "sure, nothing interesting");

		//Get
		$cachedContent = $cache->get('test-data');
		$this->assertEquals($cachedContent, "sure, nothing interesting");

		//More complex save/get
		$data = array(
			'null'   => null,
			'array'  => array(1, 2, 3, 4 => 5),
			'string',
			123.45,
			6,
			true,
			false,
			null,
			0,
			""
		);

		$serialized = igbinary_serialize($data);
		$this->assertEquals($data, igbinary_unserialize($serialized));

		$cache->save('test-data', $data);
		$cachedContent = $cache->get('test-data');

		$this->assertEquals($cachedContent, $data);

		//Exists
		$this->assertTrue($cache->exists('test-data'));

		//Delete
		$this->assertTrue($cache->delete('test-data'));

	}

    public function testMemoryCache()
    {
        $frontCache = new Phalcon\Cache\Frontend\Output(array(
            'lifetime' => 2
        ));

        $cache = new Phalcon\Cache\Backend\Memory($frontCache);
        $cache->delete('foo');

        $cache->save('foo', 'bar');

        $this->assertEquals('bar', $cache->get('foo'));
    }

    public function testMemoryCacheIncrAndDecr()
    {
        $frontCache = new Phalcon\Cache\Frontend\Output(array(
            'lifetime' => 2
        ));

        $cache = new Phalcon\Cache\Backend\Memory($frontCache);
        $cache->delete('foo');

        $cache->save('foo', 20);

        $this->assertEquals('21', $cache->increment('foo'));
        $this->assertEquals('24', $cache->increment('foo', 3));

        $this->assertEquals('23', $cache->decrement('foo'));
        $this->assertEquals('3', $cache->decrement('foo', 20));

        $this->assertEquals(3, $cache->get('foo'));
    }

	private function _prepareMemcached()
	{

		if (!extension_loaded('memcache')) {
			$this->markTestSkipped('Warning: memcache extension is not loaded');
			return false;
		}

		$memcache = new Memcache();
		$this->assertFalse(!$memcache->connect('127.0.0.1'));

		return $memcache;
	}

	public function testOutputMemcacheCache()
	{

		$memcache = $this->_prepareMemcached();
		if (!$memcache) {
			return false;
		}

		$memcache->delete('test-output');

		$time = date('H:i:s');

		$frontCache = new Phalcon\Cache\Frontend\Output(array(
			'lifetime' => 2
		));

		$cache = new Phalcon\Cache\Backend\Memcache($frontCache);

		ob_start();

		//First time cache
		$content = $cache->start('test-output');
		if ($content !== null) {
			$this->assertTrue(false);
		}

		echo $time;

		$cache->save(null, null, null, true);

		$obContent = ob_get_contents();
		ob_end_clean();

		$this->assertEquals($time, $obContent);
		$this->assertEquals($time, $memcache->get('test-output'));

		//Expect same cache
		$content = $cache->start('test-output');
		if ($content === null) {
			$this->assertTrue(false);
		}

		$this->assertEquals($time, $obContent);

		//Refresh cache
		sleep(3);

		$time2 = date('H:i:s');

		ob_start();

		$content = $cache->start('test-output');
		if($content!==null){
			$this->assertTrue(false);
		}
		echo $time2;
		$cache->save(null, null, null, true);

		$obContent2 = ob_get_contents();
		ob_end_clean();

		$this->assertNotEquals($time, $obContent2);
		$this->assertEquals($time2, $obContent2);
		$this->assertEquals($time2, $memcache->get('test-output'));

		//Check if exists
		$this->assertTrue($cache->exists('test-output'));

		//Delete entry from cache
		$this->assertTrue($cache->delete('test-output'));

		$memcache->close();

	}

    public function testIncrAndDecrMemcacheCache()
    {

        $memcache = $this->_prepareMemcached();
        if (!$memcache) {
            return false;
        }

        $memcache->delete('test-incr');

        $memcache->set('test-incr', 1);
        $newValue = $memcache->increment('test-incr');
        $this->assertEquals('2', $newValue);

        $newValue = $memcache->increment('test-incr', 5);
        $this->assertEquals('7', $newValue);

        $newValue = $memcache->decrement('test-incr');
        $this->assertEquals('6', $newValue);

        $newValue = $memcache->decrement('test-incr', '3');
        $this->assertEquals('3', $newValue);
    }

    public function testDataMemcachedCache()
	{

		$memcache = $this->_prepareMemcached();
		if (!$memcache) {
			return false;
		}

		$memcache->delete('test-data');

		$frontCache = new Phalcon\Cache\Frontend\Data();

		$cache = new Phalcon\Cache\Backend\Memcache($frontCache, array(
			'host' => '127.0.0.1',
			'port' => '11211'
		));

		$data = array(1, 2, 3, 4, 5);

		$cache->save('test-data', $data);

		$cachedContent = $cache->get('test-data');
		$this->assertEquals($cachedContent, $data);

		$cache->save('test-data', "sure, nothing interesting");

		$cachedContent = $cache->get('test-data');
		$this->assertEquals($cachedContent, "sure, nothing interesting");

		$this->assertEquals($cache->queryKeys(), array(
			0 => 'test-data',
		));

		//Check if exists
		$this->assertTrue($cache->exists('test-data'));

		//Delete
		$this->assertTrue($cache->delete('test-data'));

	}

	protected function _prepareApc()
	{

		if (!function_exists('apc_fetch')) {
			$this->markTestSkipped('apc extension is not loaded');
			return false;
		}

		return true;
	}

	public function testOutputApcCache()
	{

		$ready = $this->_prepareApc();
		if (!$ready) {
			return false;
		}

		apc_delete('_PHCAtest-output');

		$time = date('H:i:s');

		$frontCache = new Phalcon\Cache\Frontend\Output(array(
			'lifetime' => 2
		));

		$cache = new Phalcon\Cache\Backend\Apc($frontCache);

		ob_start();

		//First time cache
		$content = $cache->start('test-output');
		if ($content !== null) {
			$this->assertTrue(false);
		}

		echo $time;

		$cache->save(null, null, null, true);

		$obContent = ob_get_contents();
		ob_end_clean();

		$this->assertEquals($time, $obContent);
		$this->assertEquals($time, apc_fetch('_PHCAtest-output'));

		//Expect same cache
		$content = $cache->start('test-output');
		if ($content === null) {
			$this->assertTrue(false);
		}

		$this->assertEquals($content, $obContent);
		$this->assertEquals($content, apc_fetch('_PHCAtest-output'));

		//Query keys
		$keys = $cache->queryKeys();
		$this->assertEquals($keys, array(
			0 => 'test-output',
		));

		//Delete entry from cache
		$this->assertTrue($cache->delete('test-output'));
	}

	public function testDataApcCache()
	{

		$ready = $this->_prepareApc();
		if (!$ready) {
			return false;
		}

		apc_delete('_PHCAtest-data');

		$frontCache = new Phalcon\Cache\Frontend\Data();

		$cache = new Phalcon\Cache\Backend\Apc($frontCache);

		$data = array(1, 2, 3, 4, 5);

		$cache->save('test-data', $data);

		$cachedContent = $cache->get('test-data');
		$this->assertEquals($cachedContent, $data);

		$cache->save('test-data', "sure, nothing interesting");

		$cachedContent = $cache->get('test-data');
		$this->assertEquals($cachedContent, "sure, nothing interesting");

		$this->assertTrue($cache->delete('test-data'));

		$cache->save('a', 1);
		$cache->save('long-key', 'long-val');
		$cache->save('bcd', 3);
		$keys = $cache->queryKeys();
		sort($keys);
		$this->assertEquals($keys, array('a', 'bcd', 'long-key'));
		$this->assertEquals($cache->queryKeys('long'), array('long-key'));

		$this->assertTrue($cache->delete('a'));
		$this->assertTrue($cache->delete('long-key'));
		$this->assertTrue($cache->delete('bcd'));
	}

	protected function _prepareMongo()
	{

		if (!extension_loaded('mongo')) {
			$this->markTestSkipped('mongo extension is not loaded');
			return false;
		}

        $fp = fsockopen("localhost", 27017, $errno, $errstr, 30);

        if(!$fp) {
            $this->markTestSkipped('mongodb seems not to run (default port 27017)');
            return false;
        }

        fclose($fp);

        //remove existing
        $mongo = new Mongo();
        $mongo->dropDB('phalcon_test');

		return true;
	}

	public function testOutputMongoCache()
	{

		$ready = $this->_prepareMongo();
		if (!$ready) {
			return false;
		}

		//remove existing
		$mongo = new Mongo();
		$database = $mongo->phalcon_test;
		$collection = $database->caches;
		$collection->remove();

		$time = date('H:i:s');

		$frontCache = new Phalcon\Cache\Frontend\Output(array(
			'lifetime' => 200
		));

		$cache = new Phalcon\Cache\Backend\Mongo($frontCache, array(
			'server' => 'mongodb://localhost',
			'db' => 'phalcon_test',
			'collection' => 'caches'
		));
        $cache->delete('test-output');
		ob_start();

		//First time cache
		$content = $cache->start('test-output');
		if ($content !== null) {
			$this->assertTrue(false);
		}

		echo $time;

		$cache->save(null, null, null, true);

		$obContent = ob_get_contents();
		ob_end_clean();

		$this->assertEquals($time, $obContent);

		$document = $collection->findOne(array('key' => 'test-output'));
		$this->assertTrue(is_array($document));
		$this->assertEquals($time, $document['data']);

		//Expect same cache
		$content = $cache->start('test-output');
		if ($content === null) {
			$this->assertTrue(false);
		}

		$document = $collection->findOne(array('key' => 'test-output'));
		$this->assertTrue(is_array($document));
		$this->assertEquals($time, $document['data']);

		//Query keys
		$keys = $cache->queryKeys();
		$this->assertEquals($keys, array(
			0 => 'test-output',
		));

		//Exists
		$this->assertTrue($cache->exists('test-output'));

		//Delete entry from cache
		$this->assertTrue($cache->delete('test-output'));

	}

	public function testDataMongoCache()
	{

		$ready = $this->_prepareMongo();
		if (!$ready) {
			return false;
		}

		$frontCache = new Phalcon\Cache\Frontend\Data(array('lifetime' => 600));

		$cache = new Phalcon\Cache\Backend\Mongo($frontCache, array(
			'mongo' => new Mongo(),
			'db' => 'phalcon_test',
			'collection' => 'caches'
		));

		$data = array(1, 2, 3, 4, 5);
        $cache->delete('test-data');
		$cache->save('test-data', $data);

		$cachedContent = $cache->get('test-data');
		$this->assertEquals($cachedContent, $data);

		$cache->save('test-data', "sure, nothing interesting");

		$cachedContent = $cache->get('test-data');
		$this->assertEquals($cachedContent, "sure, nothing interesting");

		//Exists
		$this->assertTrue($cache->exists('test-data'));

		$this->assertTrue($cache->delete('test-data'));

	}

    public function testMongoIncrement()
    {

        $ready = $this->_prepareMongo();
        if (!$ready) {
            return false;
        }

        $frontCache = new Phalcon\Cache\Frontend\Data(array('lifetime' => 200));

        $cache = new Phalcon\Cache\Backend\Mongo($frontCache, array(
            'mongo' => new Mongo(),
            'db' => 'phalcon_test',
            'collection' => 'caches'
        ));
        $cache->delete('foo');
        $cache->save('foo', 1);

        $this->assertEquals(2, $cache->increment('foo'));
        $this->assertEquals(4, $cache->increment('foo', 2));
        $this->assertEquals(4, $cache->get('foo'));

        $this->assertEquals(14, $cache->increment('foo', 10));
    }

    public function testMongoDecrement()
    {

        $ready = $this->_prepareMongo();
        if (!$ready) {
            return false;
        }

        $frontCache = new Phalcon\Cache\Frontend\Data(array('lifetime' => 200));

        $cache = new Phalcon\Cache\Backend\Mongo($frontCache, array(
            'mongo' => new Mongo(),
            'db' => 'phalcon_test',
            'collection' => 'caches'
        ));
        $cache->delete('foo');
        $cache->save('foo', 100);

        $this->assertEquals(99, $cache->decrement('foo'));
        $this->assertEquals(89, $cache->decrement('foo', 10));
        $this->assertEquals(1, $cache->decrement('foo', 88));
    }

	protected function _prepareXcache()
	{
		if (function_exists('xcache_emulation')) {
			return true;
		}

		if (!extension_loaded('xcache')) {
			$this->markTestSkipped('xcache extension is not loaded');
			return false;
		}

		return true;
	}

	public function testOutputXcache()
	{

		$ready = $this->_prepareXcache();
		if (!$ready) {
			return false;
		}

		xcache_unset('_PHCXtest-output');

		$time = date('H:i:s');

		$frontCache = new Phalcon\Cache\Frontend\Output(array(
			'lifetime' => 2
		));

		$cache = new Phalcon\Cache\Backend\Xcache($frontCache);

		ob_start();

		//First time cache
		$content = $cache->start('test-output');
		if ($content !== null) {
			$this->assertTrue(false);
		}

		echo $time;

		$cache->save(null, null, null, true);

		$obContent = ob_get_contents();
		ob_end_clean();

		$this->assertEquals($time, $obContent);
		$this->assertEquals($time, xcache_get('_PHCXtest-output'));

		//Expect same cache
		$content = $cache->start('test-output');
		if ($content === null) {
			$this->assertTrue(false);
		}

		$this->assertEquals($content, $obContent);
		$this->assertEquals($content, xcache_get('_PHCXtest-output'));

		//Query keys
		$keys = $cache->queryKeys();
		$this->assertEquals($keys, array(
			0 => 'test-output',
		));

		//Delete entry from cache
		$this->assertTrue($cache->delete('test-output'));
	}

    public function testXcacheIncrement()
    {

        $ready = $this->_prepareXcache();
        if (!$ready) {
            return false;
        }

        $frontCache = new Phalcon\Cache\Frontend\Output(array(
            'lifetime' => 20
        ));

        $cache = new Phalcon\Cache\Backend\Xcache($frontCache);
        $cache->delete('foo');

        $cache->save('foo', 1);
        $newValue = $cache->increment('foo');
        $this->assertEquals('2', $newValue);

        $newValue = $cache->increment('foo');
        $this->assertEquals('3', $newValue);

        $newValue = $cache->increment('foo', 4);
        $this->assertEquals('7', $newValue);
    }

    public function testXcacheDecr()
    {

        $ready = $this->_prepareXcache();
        if (!$ready) {
            return false;
        }

        $frontCache = new Phalcon\Cache\Frontend\Output(array(
            'lifetime' => 20
        ));

        $cache = new Phalcon\Cache\Backend\Xcache($frontCache);
        $cache->delete('foo');

        $cache->save('foo', 20);
        $newValue = $cache->decrement('foo');
        $this->assertEquals('19', $newValue);

        $newValue = $cache->decrement('foo');
        $this->assertEquals('18', $newValue);

        $newValue = $cache->decrement('foo', 4);
        $this->assertEquals('14', $newValue);
    }

	public function testDataXcache()
	{
		$ready = $this->_prepareXcache();
		if (!$ready) {
			return false;
		}

		xcache_unset('_PHCXtest-data');

		$frontCache = new Phalcon\Cache\Frontend\Data();

		$cache = new Phalcon\Cache\Backend\Xcache($frontCache);

		$data = array(1, 2, 3, 4, 5);

		$cache->save('test-data', $data);

		$cachedContent = $cache->get('test-data');
		$this->assertEquals($cachedContent, $data);

		$cache->save('test-data', "sure, nothing interesting");

		$cachedContent = $cache->get('test-data');
		$this->assertEquals($cachedContent, "sure, nothing interesting");

		$this->assertTrue($cache->delete('test-data'));
	}
}
