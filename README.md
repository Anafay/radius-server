Implementation of RADIUS server: both authorization and accounting (RFC 2865, 21866, 2867).

# Usage
## RADIUS server class:
```php
class RadiusServer implements RadiusServerInterface {
    public function onAccessRequest(IncomingMessage $message) {
        if(check($message)){
            $accept = $message->replyAccept();
            $accept->addAttribute(Message::ATTR_FRAMED_IP_ADDRESS, '192.168.0.1');
            $accept->addAttribute(Message::ATTR_SESSION_TIMEOUT,180);
            $accept->send();
        }else{
            $reject = $message->replyReject();
            $reject->addAttribute(Message::ATTR_REPLY_MESSAGE,'Restricted');
            $reject->send();
        }
    }

    public function onAccountingStart(IncomingMessage $message, Session $session) {
        log($session,'start');
    }

    public function onAccountingStop(IncomingMessage $message, Session $session) {
        log($session,'stop');
        save($session);
    }

    public function onInterimUpdate(IncomingMessage $message, Session $session) {
        log($session,'interim');
    }
    ....
}
```
## ReactPHP application:
```php
$loop = React\EventLoop\Factory::create();
$radius = new Factory(new RadiusServer(),'10.1.0.1');
$radius->listen();
$loop->run();
```

# Requirements
The library requires PHP>=7.0 and ReactPHP>=1.1.
