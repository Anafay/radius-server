<?php

namespace App\Radius;

use React\Datagram\Socket;
use React\EventLoop\LoopInterface;

/**
 * Фабрика объектов для RADIUS-сервера
 */
class Factory {

    /**
     *
     * @var string                      $_secret        Secret для RADIUS-сервера, заполняется конструктором
     * @var RadiusServerInterface       $_radius        Собственно, сервер RADIUS с логикой
     * @var array                       $_nas           Хранилище идентификаторов по NAS
     * @var App\Radius\SessionStorage   $_sessions      Хранилище сессий
     */
    protected $_secret, $_radius, $_nas = [], $_sessions;

    public function __construct(RadiusServerInterface $radius, string $secret) {
        $this->_radius = $radius;
        $this->_secret = $secret;
        $this->_sessions = new SessionStorage();
    }

    /**
     * Secret для RADIUS-сервера
     * @return string
     */
    public function secret(): string {
        return $this->_secret;
    }

    /**
     * Хранилище RADIUS-сессий
     * @return \App\Radius\SessionStorage
     */
    public function sessions(): SessionStorage {
        return $this->_sessions;
    }

    /**
     * Пришедшее на сервер сообщение
     * @param Socket $server
     * @param string $data
     * @param string $address
     * @return \App\Radius\IncomingMessage
     */
    public function makeIncomingMessage(Socket $server, string $data, string $address): IncomingMessage {
        $message = new IncomingMessage($this, $server, $address);
        $message->fromBinary($data);
        return $message;
    }

    /**
     * Обработка сообщения $message
     * @param \App\Radius\IncomingMessage $message
     */
    public function process(IncomingMessage $message) {
        //  Пропускаем дубли сообщений
        if ($this->identifierStorage($message->nasIpAddress())->check($message->identifier())) {
            $message->process($this->_radius);
        }
    }

    /**
     * Создание двух UDP-серверов для RADIUS-сервера
     * @param LoopInterface $loop   Главный цикл ReactPHP
     * @param string $ip            IP сервера
     * @param int $portAuth         Порт Auth сервера
     * @param int $portAcc          Порт Accounting сервера
     */
    public function listen(LoopInterface $loop, string $ip,
            int $portAuth = 1645, int $portAcc = 1646) {
        $factory = new \React\Datagram\Factory($loop);
        $factory->createServer($ip . ':' . $portAuth)
                ->then(function (Socket $server) {
                    $server->on('message', function($message, $address, $server) {
                        $msg = $this->makeIncomingMessage($server, $message, $address);
                        $msg->process($this->_radius);
                    });
                }, function(\Exception $error) {
                    echo "ERROR: " . $error->getMessage() . "\n";
                });
        $factory->createServer($ip . ':' . $portAcc)
                ->then(function (Socket $server) {
                    $server->on('message', function($message, $address, $server) {
                        $msg = $this->makeIncomingMessage($server, $message, $address);
                        $msg->process($this->_radius);
                    });
                }, function(\Exception $error) {
                    echo "ERROR: " . $error->getMessage() . "\n";
                });
    }

    /**
     * Возвращает хранилище идентификаторов заданного NAS
     * При необходимости создает его
     * @param string $nas
     * @return \App\Radius\IdentifierStorage
     */
    protected function identifierStorage(string $nas): IdentifierStorage {
        if (!\array_key_exists($nas, $this->_nas)) {
            //  Такого хранилища еще нет
            $storage = new IdentifierStorage();
            $this->_nas[$nas] = $storage;
            return $storage;
        } else {
            return $this->_nas[$nas];
        }
    }

}
