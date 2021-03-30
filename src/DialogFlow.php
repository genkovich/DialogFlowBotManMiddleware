<?php

namespace BotMan\Middleware\DialogFlow\V2;

use BotMan\BotMan\BotMan;
use BotMan\BotMan\Interfaces\MiddlewareInterface;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use Google\ApiCore\ApiException;

class DialogFlow implements MiddlewareInterface
{
    /**
     * @var bool
     */
    private $isIgnoreIntentPattern = false;

    /**
     * @var Client
     */
    private $client;

    /**
     * constructor.
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Create a new Dialogflow middleware instance.
     * @param string $languageCode
     *
     * @return DialogFlow
     */
    public static function create(string $languageCode = 'en'): DialogFlow
    {
        $client = new Client($languageCode);
        return new static($client);
    }

    /**
     * Allow the middleware to listen all dialogflow actions.
     *
     * @return $this
     */
    public function ignoreIntentPattern(): DialogFlow
    {
        $this->isIgnoreIntentPattern = true;
        return $this;
    }

    /**
     * Handle a captured message.
     *
     * @param IncomingMessage $message
     * @param BotMan $bot
     * @param $next
     *
     * @return mixed
     */
    public function captured(IncomingMessage $message, $next, BotMan $bot)
    {
        return $next($message);
    }

    /**
     * Handle an incoming message.
     *
     * @param IncomingMessage $message
     * @param BotMan $bot
     * @param $next
     *
     * @return mixed
     */
    public function received(IncomingMessage $message, $next, BotMan $bot)
    {

        $response = $this->client->getResponse($message);
        $message->addExtras('apiReply', $response->getReply() ?? '');
        $message->addExtras('apiAction', $response->getAction() ?? '');
        $message->addExtras('apiActionIncomplete', $response->isComplete() ?? false);
        $message->addExtras('apiIntent', $response->getIntent() ?? '');
        $message->addExtras('apiParameters', $response->getParameters() ?? []);
        $message->addExtras('apiContexts', $response->getContexts() ?? []);

        return $next($message);
    }

    /**
     * @param IncomingMessage $message
     * @param $pattern
     * @param bool $regexMatched Indicator if the regular expression was matched too
     *
     * @return bool
     */
    public function matching(IncomingMessage $message, $pattern, $regexMatched): bool
    {
        if (empty($message->getExtras()['apiAction'])) {
            return false;
        }

        if ($this->isIgnoreIntentPattern) {
            return true;
        }

        $pattern = '/^' . $pattern . '$/i';
        return (bool)preg_match($pattern, $message->getExtras()['apiAction']);
    }

    /**
     * Handle a message that was successfully heard, but not processed yet.
     *
     * @param IncomingMessage $message
     * @param BotMan $bot
     * @param $next
     *
     * @return mixed
     */
    public function heard(IncomingMessage $message, $next, BotMan $bot)
    {
        return $next($message);
    }

    /**
     * Handle an outgoing message payload before/after it
     * hits the message service.
     *
     * @param mixed $payload
     * @param BotMan $bot
     * @param $next
     *
     * @return mixed
     */
    public function sending($payload, $next, BotMan $bot)
    {
        return $next($payload);
    }
}