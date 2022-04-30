<?php

namespace Anafay\RadiusServer;

/**
 * Сессия на RADIUS-сервере
 */
class Session implements \IteratorAggregate {

    /**
     * Идентификаторы
     * @var string  $_sid       ID сессии. Уникально в пределах NAS
     * @var string  $_nas       IP или идентификатор NAS, на котором зарегистрирована сессия
     */
    protected $_sid, $_nas;

    /**
     * Атрибуты сессии
     * @var array   $_attrib    Атрибуты сессии
     */
    protected $_attrib = [];

    public function __construct(string $sid, string $nas) {
        $this->_sid = $sid;
        $this->_nas = $nas;
    }

    /**
     * Идентификатор сесии
     * @return string
     */
    public function sid(): string {
        return $this->_sid;
    }

    /**
     * IP-адрес NAS, обрабатывающего абонента
     * @return string
     */
    public function nasIpAddress(): string {
        return $this->_nas;
    }

    /**
     * Добавление нового атрибута в сессию
     * @param string $key
     * @param type $value
     * #ensure $this->has($key)
     */
    public function add(string $key, $value) {
        $this->_attrib[$key] = $value;
    }

    /**
     * ПРроверка на наличие атрибута в сессии
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool {
        return \array_key_exists($key, $this->_attrib);
    }

    /**
     * Получение значения атрибута
     * @param string $key
     * @param mixed $default
     */
    public function get(string $key, $default) {
        if (\array_key_exists($key, $this->_attrib)) {
            return $this->_attrib[$key];
        } else {
            return $default;
        }
    }

    /**
     * @inherit
     * @return \Traversable
     */
    public function getIterator(): \Traversable {
        return new \ArrayIterator($this->_attrib);
    }

}
