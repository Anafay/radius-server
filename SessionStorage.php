<?php

namespace Anafay\RadiusServer;

/**
 * Хранилище RADIUS-сессий
 */
class SessionStorage implements \IteratorAggregate {

    protected $_storage = [];

    /**
     * Проверяет наличие сессии в хранилище
     * @param string $sid
     * @param string $nas
     * @return bool
     */
    public function has(string $sid, string $nas): bool {
        return \array_key_exists($this->key($sid, $nas), $this->_storage);
    }

    /**
     * Добаавляет сессию в хранилище
     * #require !$this->has($sid,$nas)
     * @param string $sid
     * @param string $nas
     * @param \App\Radius\Session $session
     * #ensure $this->has($sid,$nas)
     */
    public function add(Session $session) {
        $this->_storage[$this->key($session->sid(), $session->nasIpAddress())] = $session;
    }

    /**
     * Удаляет сессию из хранилища
     * @param string $sid
     * @param string $nas
     * #ensure !$this->has($sid,$nas)
     */
    public function remove(string $sid, string $nas) {
        unset($this->_storage[$this->key($sid, $nas)]);
    }

    /**
     * Получение сессии по ее идентификаторам
     * #require $this->has($sid,$nas)
     * @param string $sid
     * @param string $nas
     * @return \App\Radius\Session
     */
    public function get(string $sid, string $nas): Session {
        return $this->_storage[$this->key($sid, $nas)];
    }

    /**
     * @inherit
     * @return \Traversable
     */
    public function getIterator(): \Traversable {
        return new \ArrayIterator($this->_storage);
    }

    /**
     * Создает ключ из сессии и адреса NAS
     * Склеивает со вставкой сивмолов, которые в идентификаторах NAS не встречаются
     * @param string $sid
     * @param string $nas
     * @return string
     */
    protected function key(string $sid, string $nas): string {
        return $sid . '$&%' . $nas;
    }

}
