<?php

namespace Anafay\RadiusServer;

/**
 * Интерфейс для RADIUS-сервера
 */
interface RadiusServerInterface {

    /**
     * Обработчик запроса авторизации
     * Требует ответа Access-Accept или Access-Reject
     * @param \App\Radius\IncomingMessage $message
     */
    public function onAccessRequest(IncomingMessage $message);

    /**
     * Обработчик запроса старта тарификации
     * Не требует ответа
     * @param \App\Radius\IncomingMessage $message
     * @param \App\Radius\Session $session
     */
    public function onAccountingStart(IncomingMessage $message, Session $session);

    /**
     * Обработчик запроса завершения тарификации
     * Не требует ответа
     * @param \App\Radius\IncomingMessage $message
     * @param \App\Radius\Session $session
     */
    public function onAccountingStop(IncomingMessage $message, Session $session);

    /**
     * Обработчик промежуточных запросов тарификации
     * Не требует ответа
     * @param \App\Radius\IncomingMessage $message
     * @param \App\Radius\Session $session
     */
    public function onInterimUpdate(IncomingMessage $message, Session $session);
}
