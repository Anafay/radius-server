<?php

namespace App\Radius;

/**
 * Ответное сообщение
 */
class OutgoingMessage extends Message {

    /**
     * Отправка ответа RADIUS-клиенту
     */
    public function send() {
        $data = $this->pack();
        $data = \substr($data, 0, 4)
                . $this->respondAuth($data)
                . \substr($data, 20);
        $this->_server->send($data, $this->_address);
    }

    /**
     * Добавляет атрибут в сообщшение
     * @param int $type
     * @param int|string|array $value
     * #ensure $this->hasAttribute($type)
     */
    public function addAttribute(int $type, $value) {
        if (!$this->hasAttribute($type)) {
            $this->_attrib[$type] = $value;
        } else {
            $val = $this->_attrib[$type];
            if (\is_array($val) and!\is_array($value)) {
                //  Хранится массив, добавляется скаляр
                $val[] = $value;
                $this->_attrib[$type] = $val;
            } elseif (\is_array($val)) {
                //  Хранится массив, добавляется массив
                $this->_attrib[$type] = \array_merge($val, $value);
            } elseif (\is_array($value)) {
                //  Хранится скаляр, добавляется массив
                $this->_attrib[$type] = \array_merge([$val], $value);
            } else {
                //  Хранится скаляр, добавляется скаляр
                $this->attrib[$type] = [$val, $value];
            }
        }
    }

    /**
     * Упаковывает сообщение
     * @return string
     */
    protected function pack(): string {
        //  Подготавливаем атрибуты
        $data = $this->_auth;
        foreach ($this->_attrib as $type => $value) {
            if (!\is_array($value)) {
                $data .= $this->packAttrib($type, $value);
            } else {
                foreach ($value as $val) {
                    $data .= $this->packAttrib($type, $value);
                }
            }
        }

        //  Заголовок
        $length = \strlen($data) + 4;
        $dbg = \pack(
                'C*',
                $this->_code,
                $this->_identifier,
                \intdiv($length, 256),
                $length % 256
        );

        //  OK
        return $dbg . $data;
    }

    /**
     * Расчет ответного аутентификатора
     * @param string $data
     * @return string
     */
    protected function respondAuth(string $data): string {
        return \md5($data . \pack('a*', $this->_factory->secret()), true);
    }

    /**
     * Упавковка атрибута
     * @param int $type
     * @param int|string $data
     * @return string
     */
    protected function packAttrib(int $type, $data): string {
        switch ($type) {
            case Self::ATTR_VENDOR_SPECIFIC:
                //  VSA
                return \pack('C*', $type, 2 + \strlen($data)) . $data;
            case Self::ATTR_CALLED_STATION_ID:
            case Self::ATTR_CALLING_STATION_ID:
            case Self::ATTR_CONNECT_INFO:
            case Self::ATTR_FRAMED_ROUTE:
            case Self::ATTR_FILTER_ID:
            case Self::ATTR_USER_NAME:
            case Self::ATTR_REPLY_MESSAGE:
            case Self::ATTR_CALLBACK_NUMBER:
            case Self::ATTR_CALLBACK_ID:
            case Self::ATTR_PROXY_STATE:
            case Self::ATTR_LOGIN_LAT_SERVICE:
            case Self::ATTR_LOGIN_LAT_NODE:
            case Self::ATTR_LOGIN_LAT_GROUP:
            case Self::ATTR_FRAMED_APPLETALK_ZONE:
            case Self::ATTR_LOGIN_LAT_PORT:
            case Self::ATTR_TUNNEL_CLIENT_ENDPOINT:
            case Self::ATTR_TUNNEL_SERVER_ENDPOINT:
            case Self::ATTR_TUNNEL_PASSWORD:
            case Self::ATTR_TUNNEL_PRIVATE_GROUP_ID:
            case Self::ATTR_TUNNEL_ASSIGMENT_ID:
            case Self::ATTR_TUNNEL_CLIENT_AUTH_ID:
            case Self::ATTR_TUNNEL_SERVER_AUTH_ID:
                //  Строки
                return \pack('C*', $type, 2 + \strlen($data)) . $data;
            case Self::ATTR_SESSION_TIMEOUT:
            case Self::ATTR_FRAMED_ROUTING:
            case Self::ATTR_FRAMED_MTU:
            case Self::ATTR_FRAMED_COMPRESSION:
            case Self::ATTR_LOGIN_SERVICE:
            case Self::ATTR_LOGIN_TCP_PORT:
            case Self::ATTR_FRAMED_IPX_NETWORK:
            case Self::ATTR_STATE:
            case Self::ATTR_CLASS:
            case Self::ATTR_IDLE_TIMEOUT:
            case Self::ATTR_TERMINATION_ACTION:
            case Self::ATTR_FRAMED_APPLETALK_LINK:
            case Self::ATTR_FRAMED_APPLETALK_NETWORK:
            case Self::ATTR_PORT_LIMIT:
            case Self::ATTR_TUNNEL_TYPE:
            case Self::ATTR_TUNNEL_MEDIUM_TYPE:
            case Self::ATTR_TUNNEL_PREFERENCE:
                //  Целое число
                return \pack('C*', $type, 6) . \pack('N*', $data);
            case Self::ATTR_FRAMED_IP_ADDRESS:
            case Self::ATTR_FRAMED_IP_NETMASK:
            case Self::ATTR_LOGIN_IP_HOST:
                //Упаковка IP-адреса к четырехоктетную строку
                $ip = \explode('.', $data);
                return \pack('C*', $type, 6, $ip[0], $ip[1], $ip[2], $ip[3]);
        }
        return $data;
    }

    /**
     * Подготовка текущего сообщения как ответа
     * @param int $code
     * @param int $identifier
     * @param string $auth
     */
    public function prepareReply(int $code, int $identifier, string $auth) {
        $this->_code = $code;
        $this->_identifier = $identifier;
        $this->_auth = $auth;
    }

}
