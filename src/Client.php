<?php


namespace BotMan\Middleware\DialogFlow\V2;

use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use Google\ApiCore\ApiException;
use Google\Cloud\Dialogflow\V2\DetectIntentResponse;
use Google\Cloud\Dialogflow\V2\QueryInput;
use Google\Cloud\Dialogflow\V2\QueryResult;
use Google\Cloud\Dialogflow\V2\SessionsClient;
use Google\Cloud\Dialogflow\V2\TextInput;

class Client
{
    /**
     * @var SessionsClient
     */
    private $sessionsClient;
    /**
     * @var string
     */
    private $languageCode;

    /**
     * Client constructor.
     * @param string $languageCode
     */
    public function __construct(string $languageCode)
    {
        $this->languageCode = $languageCode;
        $this->sessionsClient = new SessionsClient();
    }


    /**
     * @param IncomingMessage $message
     * @return Response
     */
    public function getResponse(IncomingMessage $message): Response
    {
        $queryInput = $this->queryInput($message->getText(), $this->languageCode);
        try {
            $intentResponse = $this->getIntentResponse(md5($message->getConversationIdentifier()), $queryInput);
            $queryResult = $intentResponse->getQueryResult();
        } catch (ApiException $apiException) {
            $queryResult = null;
        }

        $response = new Response();
        if (null === $queryResult || null === $queryResult->getIntent()) {
            return $response;
        }

        $response->setIntent($queryResult->getIntent()->getDisplayName())
            ->setParameters($this->getParameters($queryResult))
            ->setContexts($this->getContexts($queryResult))
            ->setAction($queryResult->getAction())
            ->setReply($queryResult->getFulfillmentText())
            ->setIsComplete(!$queryResult->getAllRequiredParamsPresent());

        return $response;
    }

    /**
     * @param $text
     * @param string $languageCode
     * @return QueryInput
     */
    private function queryInput($text, string $languageCode): QueryInput
    {
        $textInput = new TextInput();
        $textInput->setText($text);
        $textInput->setLanguageCode($languageCode);

        $queryInput = new QueryInput();
        $queryInput->setText($textInput);
        return $queryInput;
    }

    /**
     * @param $sessionId
     * @param $queryInput
     * @return DetectIntentResponse
     * @throws ApiException
     */
    private function getIntentResponse($sessionId, $queryInput): DetectIntentResponse
    {
        $sessionName = $this->sessionsClient::sessionName(
            getenv('GOOGLE_CLOUD_PROJECT'),
            $sessionId ?: uniqid('', true)
        );

        $response = $this->sessionsClient->detectIntent($sessionName, $queryInput);
        $this->sessionsClient->close();
        return $response;
    }

    /**
     * @param QueryResult $queryResult
     * @return array
     */
    private function getParameters(QueryResult $queryResult): array
    {
        $parameters = [];
        $queryParameters = $queryResult->getParameters();
        if (null !== $queryParameters) {
            foreach ($queryParameters->getFields() as $name => $field) {
                $parameters[$name] = $field->getStringValue();
            }
        }

        return $parameters;
    }

    /**
     * @param QueryResult|null $queryResult
     * @return array
     */
    private function getContexts(QueryResult $queryResult): array
    {
        $contexts = [];
        foreach ($queryResult->getOutputContexts() as $context) {
            $contextParams = [];
            $parameters = $context->getParameters();
            if (null !== $parameters) {
                foreach ($parameters->getFields() as $name => $field) {
                    $contextParams[$name] = $field->getStringValue();
                }
            }

            $contexts[] = [
                'name' => substr(strrchr($context->getName(), '/'), 1),
                'parameters' => $contextParams,
                'lifespan' => $context->getLifespanCount(),
            ];
        }
        return $contexts;
    }
}