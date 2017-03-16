<?php
namespace Fructify\Reload\Protocol;

use Fructify\Reload\Application\ServerApplication;
use React\Socket\Server as SocketServer;
use React\Socket\Connection as SocketConnection;
use Symfony\Component\HttpFoundation\Request;
use Fructify\Reload\Response\Response;
use Symfony\Component\Console\Output\OutputInterface;

class HttpProtocol
{
    protected $app;

    public function __construct(SocketServer $socket, ServerApplication $app)
    {
        $this->app = $app;
        $this->initEvent($socket);
    }

    protected function initEvent(SocketServer $socket)
    {
        $socket->on('connection', function(SocketConnection $conn){
            $this->onConnect($conn);
        });
    }

    protected function onConnect(SocketConnection $conn)
    {
        $conn->on('data', function($data) use($conn){
            $this->onData($conn, $data);
        });
    }

    protected function onData(SocketConnection $conn, $data)
    {
        $request = $this->doHttpHandshake($data);
        $this->handleRequest($conn, $request);
    }

    protected function handleRequest(SocketConnection $conn, Request $request)
    {
        switch($request->getPathInfo()){
            case '/livereload':
                $this->initWebSocket($conn, $request);
                break;
            case '/livereload.js':
                $this->serveFile($conn, __DIR__.'/../../web/js/livereload.js');
                break;
            case '/changed':
                $this->notifyChanged($conn, $request);
                break;
            default:
                $this->serve404Error($conn);
        }
    }

    protected function initWebSocket(SocketConnection $conn, Request $request)
    {
        $conn->removeAllListeners('data');
        return new WebSocketProtocol($conn,  $this->app, $request);
    }

    protected function getRequestChangedFiles(Request $request)
    {
        if(($files = $request->query->get('files')) != null){
            return explode(',', $files);
        }
        $requestJson = json_decode($request->getContent(), true);
        return isset($requestJson['files'])?(is_array($requestJson['files'])?$requestJson['files']:[$requestJson['files']]):[];
    }

    protected function notifyChanged(SocketConnection $conn, Request $request)
    {
        foreach($this->getRequestChangedFiles($request) as $file){
            $this->app->getOutput()->writeln(strftime('%T')." - info - Receive request reload $file", OutputInterface::VERBOSITY_VERBOSE);
            $this->app->reloadFile($file);
        }
        $response = new Response(json_encode(array('status' => true)));
        $conn->write($response);
    }

    protected function serveFile(SocketConnection $conn, $file)
    {
        if(($path = realpath($file)) === null){
            return ;
        }
        $content = file_get_contents($file);
        $response = new Response($content);
        $response->setContentType('text/plain', 'utf-8');
        $conn->write($response);
    }

    protected function serve404Error(SocketConnection $conn)
    {
        $response = new Response('file not found.', Response::HTTP_NOT_FOUND);
        $conn->write($response);
        $conn->end();
    }

    protected function doHttpHandshake($data)
    {
        $pos = strpos($data, "\r\n\r\n");
        if($pos === false){
            return false;
        }
        $body = substr($data, $pos + 4);
        $rawHeaders = explode("\r\n", substr($data, 0, $pos));
        $requestLine = $this->parseRequest(array_shift($rawHeaders));
        $headers = $this->parseHeaders($rawHeaders);
        return Request::create($requestLine['uri'], $requestLine['method'], array(), array(), array(), $headers, $body);
    }

    protected function parseRequest($rawRequest)
    {
        return array_combine(array('method', 'uri', 'protocol'), explode(' ', $rawRequest));
    }

    protected function parseHeaders($rawHeaders)
    {
        $headers = array();
        foreach($rawHeaders as $headerLine){
            if(($pos = strpos($headerLine, ':')) === false){
                continue;
            }
            $headers['HTTP_'.str_replace('-', '_', trim(strtoupper(substr($headerLine, 0, $pos))))] = trim(substr($headerLine, $pos + 1));
        }
        return $headers;
    }
}
