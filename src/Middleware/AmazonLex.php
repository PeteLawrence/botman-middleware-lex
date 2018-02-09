<?php

namespace PeteLawrence\BotMan\Middleware;

use BotMan\BotMan\BotMan;
use BotMan\BotMan\Http\Curl;
use BotMan\BotMan\Interfaces\HttpInterface;
use BotMan\BotMan\Interfaces\MiddlewareInterface;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use Aws\LexRuntimeService\LexRuntimeServiceClient;

class AmazonLex implements MiddlewareInterface {

    private $lex;
    private $botName;
    private $botAlias;

    public function __construct($region, $botName, $botAlias)
    {
        $this->lex = LexRuntimeServiceClient::factory(
            [
                'region' => $region,
                'version' => 'latest'
            ]
        );

        $this->botName = $botName;
        $this->botAlias = $botAlias;
    }


    /**
     * Create a new Wit middleware instance.
     * @param string $token wit.ai access token
     * @return ApiAi
     */
    public static function create($token)
    {
        return new static($token, new Curl());
    }

    /**
     * Handle a captured message.
     *
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $message
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
        // Send the text to Amazon Lex for processing
        $result = $this->lex->postText([
            'botAlias' => $this->botAlias,
            'botName' => $this->botName,
            'inputText' => $message->getText(),
            'userId' => $message->getRecipient()
        ]);

        // Get important bits from the response
        $intent = $result->get('intentName');
        $slots = $result->get('slots');
        $messageText = $result->get('message');
        $dialogState = $result->get('dialogState');
        $slotToElicit = $result->get('slotToElicit');

        // Add parts of the response to the message
        $message->addExtras('lexIntent', $intent);
        $message->addExtras('lexSlots', $slots);
        $message->addExtras('lexReply', $messageText);
        $message->addExtras('lexDialogState', $dialogState);
        $message->addExtras('lexSlotToElicit', $slotToElicit);

        return $next($message);
    }


    /**
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $message
     * @param string $pattern
     * @param bool $regexMatched Indicator if the regular expression was matched too
     * @return bool
     */
    public function matching(IncomingMessage $message, $pattern, $regexMatched)
    {
        $pattern = '/^'.$pattern.'$/i';

        return (bool) preg_match($pattern, $message->getExtras()['lexIntent']);
    }


    /**
     * Handle a message that was successfully heard, but not processed yet.
     *
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $message
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
