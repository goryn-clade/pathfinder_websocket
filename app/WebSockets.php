<?php
/**
 * Created by PhpStorm.
 * User: Exodus
 * Date: 01.11.2016
 * Time: 18:21
 */

namespace Exodus4D\Socket;


use Exodus4D\Socket\Log\Store;
use Exodus4D\Socket\Socket\TcpSocket;
use React\EventLoop;
use React\Socket;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\Http\OriginCheck;
use Ratchet\WebSocket\WsServer;

class WebSockets {

    /**
     * @var string
     */
    protected $dsn;

    /**
     * @var int
     */
    protected $wsListenPort;

    /**
     * @var string
     */
    protected $wsListenHost;

    /**
     * @var int
     */
    protected $debug;

    /**
     * @var array<int, string>
     */
    protected $allowedOrigins;

    /**
     * @var string
     */
    protected $appEnv;

    /**
     * WebSockets constructor.
     * @param string $dsn
     * @param int $wsListenPort
     * @param string $wsListenHost
     * @param int $debug
     * @param array<int, string> $allowedOrigins
     * @param string $appEnv
     */
    function __construct(string $dsn, int $wsListenPort, string $wsListenHost, int $debug = 1, array $allowedOrigins = [], string $appEnv = ''){
        $this->dsn = $dsn;
        $this->wsListenPort = $wsListenPort;
        $this->wsListenHost = $wsListenHost;
        $this->debug = $debug;
        $this->allowedOrigins = $allowedOrigins;
        $this->appEnv = $appEnv;

        $this->startMapSocket();
    }

    /**
     * @return array<int, string>
     */
    private function resolveOrigins(): array {
        $origins = $this->allowedOrigins;
        if($this->appEnv !== 'production'){
            $origins = array_values(array_unique(array_merge(['localhost', '127.0.0.1'], $origins)));
        }
        return $origins;
    }

    private function startMapSocket(): void{
        // global EventLoop
        $loop   = EventLoop\Factory::create();

        // new Stores for logging -------------------------------------------------------------------------------------
        $webSocketLogStore = new Store(Component\MapUpdate::COMPONENT_NAME);
        $webSocketLogStore->setLogLevel($this->debug);

        $tcpSocketLogStore = new Store(TcpSocket::COMPONENT_NAME);
        $tcpSocketLogStore->setLogLevel($this->debug);

        // global MessageComponent (main app) (handles all business logic) --------------------------------------------
        $mapUpdate = new Component\MapUpdate($webSocketLogStore);

        $loop->addPeriodicTimer(3, function(EventLoop\TimerInterface $timer) use ($mapUpdate): void {
            $mapUpdate->housekeeping($timer);
        });

        // TCP Socket -------------------------------------------------------------------------------------------------
        $tcpSocket = new TcpSocket($loop, $mapUpdate, $tcpSocketLogStore);
        // TCP Server (WebServer <-> TCPServer <-> TCPSocket communication)
        $server = new Socket\Server($this->dsn, $loop, [
            'tcp' => [
                'backlog' => 20,
                'so_reuseport' => true
            ]
        ]);

        $server->on('connection', function(Socket\ConnectionInterface $connection) use ($tcpSocket): void {
            $tcpSocket->onConnect($connection);
        });

        $server->on('error', function(\Exception $e) use ($tcpSocket): void {
            $tcpSocket->log(['debug', 'error'], null, 'onError', $e->getMessage());
        });

        // WebSocketServer --------------------------------------------------------------------------------------------

        // Binding to 0.0.0.0 means remotes can connect (Web Clients)
        $webSocketURI = $this->wsListenHost . ':' . $this->wsListenPort;

        // Set up our WebSocket server for clients subscriptions
        $webSock = new Socket\TcpServer($webSocketURI, $loop);
        new IoServer(
            new HttpServer(
                new OriginCheck(
                    new WsServer($mapUpdate),
                    $this->resolveOrigins()
                )
            ),
            $webSock
        );

        $loop->run();
    }

}