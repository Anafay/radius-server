<?php

namespace Anafay\RadiusServer;

/**
 * Description of IncomingMessage
 */
class IncomingMessage extends Message {

    /**
     * Парсинг сообщения из бинарного представления
     * @param string $data
     */
    public function fromBinary(string $data) {
        $this->_data = $data;
        $this->_cdata = \unpack('C*', $data);
        $this->_code = $this->_cdata[1];
        $this->_identifier = $this->_cdata[2];
        $this->_length = 256 * $this->_cdata[3] + $this->_cdata[4];
        $this->_auth = \substr($data, 4, 16);
        $this->parseAttributes();
    }

    /* -----------------------------------------------------------
     * Ответы на сообщения
      ------------------------------------------------------------ */

    /**
     * Подготовка ответного сообщения типа Access-Reject
     * @return \App\Radius\Message
     */
    public function replyReject(): OutgoingMessage {
        $message = new OutgoingMessage($this->_factory, $this->_server, $this->_address);
        $message->prepareReply(Self::CODE_ACCESS_REJECT, $this->_identifier, $this->_auth);
        $message->_attrib = [];
        return $message;
    }

    /**
     * Подготовка ответного сообщения типа Access-Accept
     * @return \App\Radius\Message
     */
    public function replyAccept(): OutgoingMessage {
        $message = new OutgoingMessage($this->_factory, $this->_server, $this->_address);
        $message->prepareReply(Self::CODE_ACCESS_ACCEPT, $this->_identifier, $this->_auth);
        $message->_attrib = [];
        return $message;
    }

    /* -----------------------------------------------------------
     * Атрибуты
      ------------------------------------------------------------ */

    /**
     * Имя пользователя
     * #require $this->hasAttribute(Self::ATTR_USER_NAME)
     * @return string
     */
    public function userName(): string {
        if (\array_key_exists(Self::ATTR_USER_NAME, $this->_attrib)) {
            return $this->_attrib[Self::ATTR_USER_NAME];
        } else {
            return '';
        }
    }

    /**
     * Пароль
     * #require $this->hasAttribute(Self::ATTR_USER_PASSWORD)
     * @return string
     */
    public function userPassword(): string {
        return $this->_attrib[Self::ATTR_USER_PASSWORD];
    }

    /**
     * CallingStationId
     * #require $this->hasAttribute(Self::ATTR_CALLING_STATION_ID)
     * @return string
     */
    public function callingStationId(): string {
        return $this->_attrib[Self::ATTR_CALLING_STATION_ID];
    }

    /**
     * CalledStationId
     * #require $this->hasAttribute(Self::ATTR_CALLED_STATION_ID)
     * @return string
     */
    public function calledStationId(): string {
        return $this->_attrib[Self::ATTR_CALLED_STATION_ID];
    }

    /**
     * IP-адрес NAS
     * При отсутствии возвращает 'N/A'
     * @return string
     */
    public function nasIpAddress(): string {
        if ($this->hasAttribute(Self::ATTR_NAS_IP_ADDRESS)) {
            return $this->_attrib[Self::ATTR_NAS_IP_ADDRESS];
        } else {
            return 'N/A';
        }
    }

    /**
     * Идентификатор NAS
     * #require $this->hasAttribute(Self::ATTR_NAS_IDENTIFIER)
     * @return string
     */
    public function nasIdentifier(): string {
        return $this->_attrib[Self::ATTR_NAS_IDENTIFIER];
    }

    /**
     * Порт NAS
     * #require $this->hasAttribute(Self::ATTR_NAS_PORT)
     * @return string
     */
    public function nasPort(): string {
        return $this->_attrib[Self::ATTR_NAS_PORT];
    }

    /**
     * Порт NAS
     * #require $this->hasAttribute(Self::ATTR_NAS_PORT_ID)
     * @return string
     */
    public function nasPortId(): string {
        return $this->_attrib[Self::ATTR_NAS_PORT_ID];
    }

    /**
     * Идентификатор сессии
     * #require $this->hasAttribute(Self::ATTR_NAS_PORT)
     * @return string
     */
    public function sessionId(): string {
        return $this->_attrib[Self::ATTR_SESSION_ID];
    }

    /**
     * Состояние сессии
     * #require $this->hasAttribute(Self::ATTR_STATUS_TYPE)
     * @return int
     */
    public function statusType(): int {
        return $this->_attrib[Self::ATTR_STATUS_TYPE];
    }

    /**
     * Локальный IP
     * #require $this->hasAttribute(Self::ATTR_TUNNEL_CLIENT_ENDPOINT)
     * @return string
     */
    public function clientIp(): string {
        return $this->_attrib[Self::ATTR_TUNNEL_CLIENT_ENDPOINT];
    }

    /**
     * VSA
     * #require $this->hasAttribute(Self::ATTR_VENDOR_SPECIFIC)
     * @return string
     */
    public function vsa(): string {
        return $this->_attrib[Self::ATTR_VENDOR_SPECIFIC];
    }

    /* ------------------------------------------------------------
     *  Служебные методы
      ------------------------------------------------------------ */

    /**
     * Обработка сообщения RADIUS-сервером
     * @param \App\Radius\RadiusServerInterface $radius
     * Вызывается фабрикой
     */
    public function process(RadiusServerInterface $radius) {
        if ($this->_code === Self::CODE_ACCESS_REQUEST) {
            $radius->onAccessRequest($this);
        } elseif ($this->_code === Self::CODE_ACCOUNTING_REQUEST && $this->hasAttribute(Self::ATTR_STATUS_TYPE)) {
            switch ($this->statusType()) {
                case Self::STATUSTYPE_START:
                    $session = $this->retrieveSession(true);
                    $session->add('started', new \DateTime());
                    $radius->onAccountingStart($this, $session);
                    break;
                case Self::STATUSTYPE_STOP:
                    $session = $this->retrieveSession();
                    $session->add('stopped', new \DateTime());
                    $session->add('input-octets', $this->inputOctets());
                    $session->add('output-octets', $this->outputOctets());
                    $session->add('terminate-cause', $this->terminateCause());
                    if (!$session->has('started')) {
                        $now = new \DateTime();
                        $started = $now->sub(new \DateInterval('PT' . $this->sessionTime() . 'S'));
                        $session->add('started', $started);
                    }
                    $radius->onAccountingStop($this, $session);
                    $this->_factory->sessions()->remove($this->sid(), $this->nasIpAddress());
                    break;
                case Self::STATUSTYPE_INTERIM:
                    $radius->onInterimUpdate($this, $this->retrieveSession());
            }
            // Отвечаем автоматически
            $message = new OutgoingMessage($this->_factory, $this->_server, $this->_address);
            $message->prepareReply(Self::CODE_ACCOUNTING_RESPONSE, $this->_identifier, $this->_auth);
            $message->send();
        }
    }

    /**
     * Возвращает причину завершения сессии
     * @return int
     */
    protected function terminateCause(): int {
        if ($this->hasAttribute(Self::ATTR_TERMINATE_CAUSE)) {
            return $this->_attrib[Self::ATTR_TERMINATE_CAUSE];
        } else {
            return 0;
        }
    }

    /**
     * Возвращает длительность сессии в секундах
     * @return int
     */
    protected function sessionTime(): int {
        if ($this->hasAttribute(Self::ATTR_SESSION_TIME)) {
            return $this->_attrib[Self::ATTR_SESSION_TIME];
        } else {
            return 0;
        }
    }

    /**
     * Возвращает количество принятых байт, если таковое есть
     * При отсутствии сответствующего атрибута возвращает 0
     * @return int
     */
    protected function inputOctets(): int {
        if ($this->hasAttribute(Self::ATTR_INPUT_OCTETS)) {
            return $this->_attrib[Self::ATTR_INPUT_OCTETS];
        } else {
            return 0;
        }
    }

    /**
     * Возвращает количество переданных байт, если таковое есть
     * При отсутствии сответствующего атрибута возвращает 0
     * @return int
     */
    protected function outputOctets(): int {
        if ($this->hasAttribute(Self::ATTR_OUTPUT_OCTETS)) {
            return $this->_attrib[Self::ATTR_OUTPUT_OCTETS];
        } else {
            return 0;
        }
    }

    /**
     * Возващает текущую сессию, создает при необходимости
     * @param string $sid
     * @param string $nas
     * @param bool $create  true - создавать без проверки
     * @return \App\Radius\Session
     */
    protected function retrieveSession(bool $create = false): Session {
        if ($create || !$this->_factory->sessions()->has($this->sid(), $this->nasIpAddress())) {
            //  Создаем сессию: новая или потерянная при перезагрузке
            $session = new Session($this->sid(), $this->nasIpAddress());
            if ($this->hasAttribute(Self::ATTR_USER_NAME)) {
                $session->add('username', $this->userName());
            }
            if ($this->hasAttribute(Self::ATTR_TUNNEL_CLIENT_ENDPOINT)) {
                $session->add('client-ip', $this->_attrib[Self::ATTR_TUNNEL_CLIENT_ENDPOINT]);
            }
            if ($this->hasAttribute(Self::ATTR_TUNNEL_ASSIGMENT_ID)) {
                $session->add('tunnel', $this->_attrib[Self::ATTR_TUNNEL_ASSIGMENT_ID]);
            }
            if ($this->hasAttribute(Self::ATTR_VENDOR_SPECIFIC)) {
                $session->add('vsa', $this->_attrib[Self::ATTR_VENDOR_SPECIFIC]);
            }
            if ($this->hasAttribute(Self::ATTR_FRAMED_IP_ADDRESS)) {
                $session->add('framed-ip', $this->_attrib[Self::ATTR_FRAMED_IP_ADDRESS]);
            }
            $this->_factory->sessions()->add($session);
            return $session;
        } else {
            return $this->_factory->sessions()->get($this->sid(), $this->nasIpAddress());
        }
    }

    /**
     * Идентификатор сессии или его эрзацы
     * @return string
     */
    protected function sid(): string {
        if ($this->hasAttribute(Self::ATTR_SESSION_ID)) {
            return $this->sessionId();
        } elseif ($this->hasAttribute(Self::ATTR_USER_NAME)) {
            return $this->userName();
        } elseif ($this->hasAttribute(Self::ATTR_NAS_PORT_ID)) {
            return $this->nasPortId();
        } else {
            app('log')->error('There is no ATTR_USER_NAME or ATTR_SESSION_ID or ATTR_NAS_PORT_ID in session');
            return 'N/A';
        }
    }

    /**
     * Разбор RADIUS-атрибутов
     */
    protected function parseAttributes() {
        //  Проходим по массиву int8
        for ($i = 21; $i < $this->_length;) {
            //  Пытаемся разобрать
            $type = $this->_cdata[$i];  //  Тип атрибута
            $length = $this->_cdata[$i + 1];    //  Длина атрибута +2
            $data = \substr($this->_data, $i + 1, $length - 2); //  Бинарное представление значения атрибута
            switch ($type) {
                case Self::ATTR_USER_NAME:
                case Self::ATTR_NAS_IDENTIFIER:
                case Self::ATTR_NAS_PORT_ID:
                case Self::ATTR_SESSION_ID:
                case Self::ATTR_TUNNEL_CLIENT_ENDPOINT:
                case Self::ATTR_TUNNEL_SERVER_ENDPOINT:
                case Self::ATTR_CALLED_STATION_ID:
                case Self::ATTR_CALLING_STATION_ID:
                case Self::ATTR_MULTI_SESSION_ID:
                case Self::ATTR_TUNNEL_CLIENT_AUTH_ID:
                case Self::ATTR_TUNNEL_SERVER_AUTH_ID:
                case Self::ATTR_CONNECT_INFO:
                case Self::ATTR_CALLBACK_NUMBER:
                case Self::ATTR_STATE:
                case Self::ATTR_CLASS:
                case Self::ATTR_LOGIN_LAT_SERVICE:
                case Self::ATTR_LOGIN_LAT_NODE:
                case Self::ATTR_LOGIN_LAT_GROUP:
                case Self::ATTR_CHAP_CHALLENGE:
                case Self::ATTR_LOGIN_LAT_PORT:
                case Self::ATTR_TUNNEL_PRIVATE_GROUP_ID:
                case Self::ATTR_TUNNEL_ASSIGMENT_ID:
                    //  Строки
                    $this->_attrib[$type] = \unpack('a*', $data)[1];
                    break;
                case Self::ATTR_USER_PASSWORD:
                    //  Пароли
                    $this->_attrib[$type] = $this->extractPassword($data);
                    break;
                case Self::ATTR_SERVICE_TYPE:
                case Self::ATTR_NAS_PORT:
                case Self::ATTR_FRAMED_PROTOCOL:
                case Self::ATTR_NAS_PORT_TYPE:
                case Self::ATTR_STATUS_TYPE:
                case Self::ATTR_DELAY_TIME:
                case Self::ATTR_INPUT_OCTETS:
                case Self::ATTR_OUTPUT_OCTETS:
                case Self::ATTR_AUTHENTIC:
                case Self::ATTR_SESSION_TIME:
                case Self::ATTR_INPUT_PACKETS:
                case Self::ATTR_OUTPUT_PACKETS:
                case Self::ATTR_TERMINATE_CAUSE:
                case Self::ATTR_TUNNEL_MEDIUM_TYPE:
                case Self::ATTR_TUNNEL_TYPE:
                case Self::ATTR_LINK_COUNT:
                case Self::ATTR_INPUT_GIGAWORDS:
                case Self::ATTR_OUTPUT_GIGAWORDS:
                case Self::ATTR_FRAMED_COMPRESSION:
                case Self::ATTR_PORT_LIMIT:
                case Self::ATTR_TUNNEL_PREFERENCE:
                    //  Целые
                    $this->_attrib[$type] = \unpack('N*', $data)[1];
                    break;
                case Self::ATTR_NAS_IP_ADDRESS:
                case Self::ATTR_FRAMED_IP_ADDRESS:
                case Self::ATTR_FRAMED_IP_NETMASK:
                case Self::ATTR_LOGIN_IP_HOST:
                    //  IP-адреса
                    $this->_attrib[$type] = $this->extractIp($i);
                    break;
                case Self::ATTR_VENDOR_SPECIFIC:
                    //  VSA с вырезанием типа пары
                    if (\array_key_exists($type, $this->_attrib)) {
                        $this->_attrib[$type] .= '|' . \unpack('a*', \substr($data, 6))[1];
                    } else {
                        $this->_attrib[$type] = \unpack('a*', \substr($data, 6))[1];
                    }
                    break;
                default:
                //echo $type . PHP_EOL;
            }
            //  Пропускаем атрибут
            $i += $length;
        }
    }

    /**
     * Выделяем IP-адрес
     * @param type $i   Позиция начала атрибута
     * @return string Строка с IP-адресом
     */
    protected function extractIp($i): string {
        return $this->_cdata[$i + 2]
                . '.' . $this->_cdata[$i + 3]
                . '.' . $this->_cdata[$i + 4]
                . '.' . $this->_cdata[$i + 5];
    }

    /**
     * Декодирует пароль
     * @param string $data  Бинарная строка с данными
     * @return string   Текст пароля
     */
    protected function extractPassword(string $data): string {
        $md5 = \md5(\pack('a*', $this->_factory->secret()) . $this->_auth, true);
        $mask = \unpack('C*', $md5);
        $cdata = \unpack('C*', $data);
        $pwd = '';
        for ($j = 1; $j <= 16; $j++) {
            $ch = \chr($cdata[$j] ^ $mask[$j]);
            if ($ch !== "\0") {
                $pwd .= \chr($cdata[$j] ^ $mask[$j]);
            }
        }
        return $pwd;
    }

}
