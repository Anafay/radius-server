<?php

namespace Anafay\RadiusServer;

use React\Datagram\Socket;

/**
 * Сообщение протокола RADIUS
 */
class Message {

    /**
     * Атрибуты, касающиеся хранения сообщения
     * @var Factory                 $_factory   Объектная фабрика для библиотеки
     * @var React\Datagram\Socket   $_socket    UDP-сервер
     * @var string                  $_address   Адрес клиента, на который отправляется ответ
     * @var string                  $_data      Бинарные данные сообщения
     * @var array                   $_cdata     Массив значений int8 сообщения
     */
    protected $_factory, $_server, $_address, $_data, $_cdata;

    /**
     * Атрибуты разобранного RADIUS-сообщения
     * @var int     $_code          Код типа сообщения
     * @var int     $_identifier    Короткоживущий идентификатор сообщения
     * @var int     $_length        Общая длина сообщения
     * @var int     $_auth          Аутентификатор сообщения
     * @var array   $_attrib        Ассоциативный массив с реальными представлениями
     *                              атрибутов, индексированными по их типу
     */
    protected $_code, $_identifier, $_length, $_auth, $_attrib = [];

    /*
     * Типы сообщений
     */

    const CODE_ACCESS_REQUEST = 1;
    const CODE_ACCESS_ACCEPT = 2;
    const CODE_ACCESS_REJECT = 3;
    const CODE_ACCOUNTING_REQUEST = 4;
    const CODE_ACCOUNTING_RESPONSE = 5;

    /*
     * Типы атрибутов
     */
    const ATTR_USER_NAME = 1;
    const ATTR_USER_PASSWORD = 2;
    //const ATTR_CHAP_PASSWORD = 3;
    const ATTR_NAS_IP_ADDRESS = 4;
    const ATTR_NAS_PORT = 5;
    const ATTR_SERVICE_TYPE = 6;
    const ATTR_FRAMED_PROTOCOL = 7;
    const ATTR_FRAMED_IP_ADDRESS = 8;
    const ATTR_FRAMED_IP_NETMASK = 9;
    const ATTR_FRAMED_ROUTING = 10;
    const ATTR_FILTER_ID = 11;
    const ATTR_FRAMED_MTU = 12;
    const ATTR_FRAMED_COMPRESSION = 13;
    const ATTR_LOGIN_IP_HOST = 14;
    const ATTR_LOGIN_SERVICE = 15;
    const ATTR_LOGIN_TCP_PORT = 16;
    const ATTR_REPLY_MESSAGE = 18;
    const ATTR_CALLBACK_NUMBER = 19;
    const ATTR_CALLBACK_ID = 20;
    const ATTR_FRAMED_ROUTE = 22;
    const ATTR_FRAMED_IPX_NETWORK = 23;
    const ATTR_STATE = 24;
    const ATTR_CLASS = 25;
    const ATTR_VENDOR_SPECIFIC = 26;
    const ATTR_SESSION_TIMEOUT = 27;
    const ATTR_IDLE_TIMEOUT = 28;
    const ATTR_TERMINATION_ACTION = 29;
    const ATTR_CALLED_STATION_ID = 30;
    const ATTR_CALLING_STATION_ID = 31;
    const ATTR_NAS_IDENTIFIER = 32;
    const ATTR_PROXY_STATE = 33;
    const ATTR_LOGIN_LAT_SERVICE = 34;
    const ATTR_LOGIN_LAT_NODE = 35;
    const ATTR_LOGIN_LAT_GROUP = 36;
    const ATTR_FRAMED_APPLETALK_LINK = 37;
    const ATTR_FRAMED_APPLETALK_NETWORK = 38;
    const ATTR_FRAMED_APPLETALK_ZONE = 39;
    const ATTR_STATUS_TYPE = 40;
    const ATTR_DELAY_TIME = 41;
    const ATTR_INPUT_OCTETS = 42;
    const ATTR_OUTPUT_OCTETS = 43;
    const ATTR_SESSION_ID = 44;
    const ATTR_AUTHENTIC = 45;
    const ATTR_SESSION_TIME = 46;
    const ATTR_INPUT_PACKETS = 47;
    const ATTR_OUTPUT_PACKETS = 48;
    const ATTR_TERMINATE_CAUSE = 49;
    const ATTR_MULTI_SESSION_ID = 50;
    const ATTR_LINK_COUNT = 51;
    const ATTR_INPUT_GIGAWORDS = 52;
    const ATTR_OUTPUT_GIGAWORDS = 53;
    const ATTR_CHAP_CHALLENGE = 60;
    const ATTR_NAS_PORT_TYPE = 61;
    const ATTR_PORT_LIMIT = 62;
    const ATTR_LOGIN_LAT_PORT = 63;
    const ATTR_TUNNEL_TYPE = 64;
    const ATTR_TUNNEL_MEDIUM_TYPE = 65;
    const ATTR_TUNNEL_CLIENT_ENDPOINT = 66;
    const ATTR_TUNNEL_SERVER_ENDPOINT = 67;
    const ATTR_TUNNEL_PASSWORD = 69;
    const ATTR_CONNECT_INFO = 77;
    const ATTR_TUNNEL_PRIVATE_GROUP_ID = 81;
    const ATTR_TUNNEL_ASSIGNMENT_ID = 82;
    const ATTR_TUNNEL_PREFERENCE = 83;
    const ATTR_NAS_PORT_ID = 87;
    const ATTR_TUNNEL_CLIENT_AUTH_ID = 90;
    const ATTR_TUNNEL_SERVER_AUTH_ID = 91;

    /*
     * Типы состояния сессии для тарификации
     */
    const STATUSTYPE_START = 1;
    const STATUSTYPE_STOP = 2;
    const STATUSTYPE_INTERIM = 3;

    public function __construct(Factory $factory, Socket $server, string $address) {
        $this->_factory = $factory;
        $this->_server = $server;
        $this->_address = $address;
    }

    /**
     * Идентификатор сообщения
     * @return int
     */
    public function identifier(): int {
        return $this->_identifier;
    }

    /* -----------------------------------------------------------
     * Атрибуты сообщения
      ------------------------------------------------------------ */

    /**
     * Проверяет наличие RADIUS-атрибута в сообщении
     * @param int $type Тип атрибута
     * @return bool
     */
    public function hasAttribute(int $type): bool {
        return \array_key_exists($type, $this->_attrib);
    }

    /**
     * Возвращает значение атрибута
     * #require $this->haqsAttrbute($type)
     * @param int $type
     * @return mixed
     */
    public function getAttribute(int $type) {
        return $this->_attrib[$type];
    }

}
