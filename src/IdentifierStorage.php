<?php

namespace Anafay\RadiusServer;

/**
 * Проверка таймингов идентификатопров сообщений
 * Выдерживает 2 сек для каждого сообщения
 */
class IdentifierStorage {

    /**
     * @var array $_storage Хранилище идентификаторов
     */
    protected $_storage = [];

    /**
     * Проверяет таймер сообщения $id и обновляет его
     * @param int $id
     * @return bool true, если предыдущее сообщение было не позднее 2 сек
     */
    public function check(int $id): bool {
        $now = \microtime(true);
        if (!\array_key_exists($id, $this->_storage)) {
            //  Идентификатор получен впервые
            $this->_storage[$id] = $now;
            return true;
        } else {
            $timestamp = $this->_storage[$id];
            if ($now - $timestamp > 2.0) {
                //  Прошло более 2 сек
                $this->_storage[$id] = $now;
                return true;
            } else {
                //  Это была повторная отправка уже обработанного сообщения
                return false;
            }
        }
    }

}
