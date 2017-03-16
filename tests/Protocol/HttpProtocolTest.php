<?php
namespace Fructify\Reload\Tests\Protocol;

use Symfony\Component\HttpFoundation\Request;
use Fructify\Reload\Response\Response;

/**
 * Description of Message
 *
 * @author ricky
 */
class HttpProtocolTest extends \PHPUnit\Framework\TestCase
{
    public function test_construct()
    {
        $calls = [];
        $app = $this->getMockBuilder('\\Fructify\\Reload\\Application\\ServerApplication')
            ->disableOriginalConstructor()
            ->getMock();
        $socket = $this->getMockBuilder('\\React\\Socket\\Server')
            ->disableOriginalConstructor()
            ->getMock();
        $httpProtocol = $this->getMockBuilder('\\Fructify\\Reload\\Protocol\\HttpProtocol')
             ->setMethods(array('initEvent'))
             ->disableOriginalConstructor()
             ->getMock();
        $httpProtocol->expects($this->any())
            ->method('initEvent')
            ->will($this->returnCallback(function() use(&$calls){
                $calls[] = 'initEvent';
            }));

        $reflectedClass = new \ReflectionClass($httpProtocol);
        $constructor = $reflectedClass->getConstructor();
        $constructor->invoke($httpProtocol, $socket, $app);
        $this->assertEquals($app, $this->getObjectAttribute($httpProtocol, 'app'));
        $this->assertEquals(array('initEvent'), $calls);
    }

    public function test_initEvent()
    {
        $calls = [];
        $socketConn = $this->getMockBuilder('\\React\\Socket\\Connection')
            ->disableOriginalConstructor()
            ->getMock();
        $socket = $this->getMockBuilder('\\React\\Socket\\Server')
            ->setMethods(array('on'))
            ->disableOriginalConstructor()
            ->getMock();
        $socket->expects($this->any())
            ->method('on')
            ->will($this->returnCallback(function($event, $callback) use(&$calls, $socketConn){
                $calls[] = 'on';
                $callback($socketConn);
                $this->assertEquals('connection', $event);
            }));

        $httpProtocol = $this->getMockBuilder('\\Fructify\\Reload\\Protocol\\HttpProtocol')
             ->setMethods(array('onConnect'))
             ->disableOriginalConstructor()
             ->getMock();
        $httpProtocol->expects($this->any())
            ->method('onConnect')
            ->will($this->returnCallback(function($conn) use(&$calls, $socketConn){
                $calls[] = 'onConnect';
                $this->assertEquals($socketConn, $conn);
            }));

        $reflectedClass = new \ReflectionClass($httpProtocol);
        $method = $reflectedClass->getMethod('initEvent');
        $method->setAccessible(true);
        $method->invoke($httpProtocol, $socket);
        $this->assertEquals(array('on', 'onConnect'), $calls);
    }

    public function test_onConnect()
    {
        $calls = [];
        $socketConn = $this->getMockBuilder('\\React\\Socket\\Connection')
            ->setMethods(array('on'))
            ->disableOriginalConstructor()
            ->getMock();
        $socketConn->expects($this->any())
            ->method('on')
            ->will($this->returnCallback(function($event, $callback) use(&$calls, $socketConn){
                $calls[] = 'on';
                $callback('dataForReceive');
                $this->assertEquals('data', $event);
            }));
        $httpProtocol = $this->getMockBuilder('\\Fructify\\Reload\\Protocol\\HttpProtocol')
             ->setMethods(array('onData'))
             ->disableOriginalConstructor()
             ->getMock();
        $httpProtocol->expects($this->any())
            ->method('onData')
            ->will($this->returnCallback(function($conn, $data) use(&$calls, $socketConn){
                $calls[] = 'onData';
                $this->assertEquals('dataForReceive', $data);
                $this->assertEquals($socketConn, $conn);
            }));
        $reflectedClass = new \ReflectionClass($httpProtocol);
        $method = $reflectedClass->getMethod('onConnect');
        $method->setAccessible(true);
        $method->invoke($httpProtocol, $socketConn);
        $this->assertEquals(array('on', 'onData'), $calls);
    }

    public function test_onData()
    {
        $calls = [];
        $request = new Request();
        $socketConn = $this->getMockBuilder('\\React\\Socket\\Connection')
            ->disableOriginalConstructor()
            ->getMock();
        $httpProtocol = $this->getMockBuilder('\\Fructify\\Reload\\Protocol\\HttpProtocol')
             ->setMethods(array('doHttpHandshake', 'handleRequest'))
             ->disableOriginalConstructor()
             ->getMock();
        $httpProtocol->expects($this->any())
            ->method('doHttpHandshake')
            ->will($this->returnCallback(function($data) use(&$calls, $request){
                $calls[] = 'doHttpHandshake';
                $this->assertEquals('dataForReceive', $data);
                return $request;
            }));
        $httpProtocol->expects($this->any())
            ->method('handleRequest')
            ->will($this->returnCallback(function($conn, $requestForTest) use(&$calls, $socketConn, $request){
                $calls[] = 'handleRequest';
                $this->assertEquals($socketConn, $conn);
                $this->assertEquals($request, $requestForTest);
            }));
        $reflectedClass = new \ReflectionClass($httpProtocol);
        $method = $reflectedClass->getMethod('onData');
        $method->setAccessible(true);
        $method->invoke($httpProtocol, $socketConn, 'dataForReceive');
        $this->assertEquals(array('doHttpHandshake', 'handleRequest'), $calls);
    }

    public function test_handleRequest()
    {
        $calls = [];
        $pathInfo = '';

        $socketConn = $this->getMockBuilder('\\React\\Socket\\Connection')
            ->disableOriginalConstructor()
            ->getMock();

        $request = $this->getMockBuilder('\\Symfony\\Component\\HttpFoundation\\Request')
            ->setMethods(array('getPathInfo'))
            ->disableOriginalConstructor()
            ->getMock();
        $request->expects($this->any())
            ->method('getPathInfo')
            ->will($this->returnCallback(function() use(&$pathInfo){
                return $pathInfo;
            }));

        $methods = array('initWebSocket', 'serveFile', 'notifyChanged', 'serve404Error');
        $httpProtocol = $this->getMockBuilder('\\Fructify\\Reload\\Protocol\\HttpProtocol')
             ->setMethods($methods)
             ->disableOriginalConstructor()
             ->getMock();
        foreach($methods as $method){
            $httpProtocol->expects($this->any())
                ->method($method)
                ->will($this->returnCallback(function() use(&$calls, $method){
                    $calls[] = $method;
                }));
        }

        $reflectedClass = new \ReflectionClass($httpProtocol);
        $method = $reflectedClass->getMethod('handleRequest');
        $method->setAccessible(true);

        $pathInfo = '/livereload';
        $calls = [];
        $method->invoke($httpProtocol, $socketConn, $request);
        $this->assertEquals(array('initWebSocket'), $calls);

        $pathInfo = '/livereload.js';
        $calls = [];
        $method->invoke($httpProtocol, $socketConn, $request);
        $this->assertEquals(array('serveFile'), $calls);

        $pathInfo = '/changed';
        $calls = [];
        $method->invoke($httpProtocol, $socketConn, $request);
        $this->assertEquals(array('notifyChanged'), $calls);

        $pathInfo = '/asjjahkhakjs';  //error 404
        $calls = [];
        $method->invoke($httpProtocol, $socketConn, $request);
        $this->assertEquals(array('serve404Error'), $calls);
    }

    public function test_serveFile()
    {
        $calls = [];
        $data = md5(microtime().rand());
        $httpProtocol = $this->getMockBuilder('\\Fructify\\Reload\\Protocol\\HttpProtocol')
             ->disableOriginalConstructor()
             ->getMock();
        $socketConn = $this->getMockBuilder('\\React\\Socket\\Connection')
            ->disableOriginalConstructor()
            ->setMethods(array('write'))
            ->getMock();
        $socketConn->expects($this->any())
            ->method('write')
            ->will($this->returnCallback(function(Response $response) use($data, &$calls){
                $calls[] = 'write';
                $this->assertEquals($data, $response->getContent());
                $this->assertEquals('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
            }));
        $reflectedClass = new \ReflectionClass($httpProtocol);
        $method = $reflectedClass->getMethod('serveFile');
        $method->setAccessible(true);

        $tempname = tempnam(sys_get_temp_dir(), 'livereloadtest-');
        file_put_contents($tempname, $data);
        $method->invoke($httpProtocol, $socketConn, $tempname);
        unlink($tempname);
        $this->assertEquals(array('write'), $calls);
    }

    public function test_serve404Error()
    {
        $calls = [];
        $httpProtocol = $this->getMockBuilder('\\Fructify\\Reload\\Protocol\\HttpProtocol')
             ->disableOriginalConstructor()
             ->getMock();
        $socketConn = $this->getMockBuilder('\\React\\Socket\\Connection')
            ->disableOriginalConstructor()
            ->setMethods(array('write', 'end'))
            ->getMock();
        $socketConn->expects($this->any())
            ->method('write')
            ->will($this->returnCallback(function(Response $response) use(&$calls){
                $calls[] = 'write';
                $this->assertEquals(Response::HTTP_NOT_FOUND ,$response->getStatusCode());
            }));
        $socketConn->expects($this->any())
            ->method('end')
            ->will($this->returnCallback(function() use(&$calls){
                $calls[] = 'end';
            }));
        $reflectedClass = new \ReflectionClass($httpProtocol);
        $method = $reflectedClass->getMethod('serve404Error');
        $method->setAccessible(true);

        $method->invoke($httpProtocol, $socketConn);
        $this->assertEquals(array('write', 'end'), $calls);
    }
}
