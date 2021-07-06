<?php declare(strict_types=1);

namespace Convo\Pckg\Trivia;

use Convo\Core\Util\IHttpFactory;
use Convo\Core\Workflow\IConvoRequest;
use Convo\Core\Workflow\IConvoResponse;

class OpenTDBTriviaAdapterElement extends \Convo\Core\Workflow\AbstractWorkflowContainerComponent implements \Convo\Core\Workflow\IConversationElement
{
    const BASE_URL = 'https://opentdb.com/api.php';
    const LETTERS = ['a', 'b', 'c', 'd'];
   
    /**
     * HTTP Factory
     * @var \Convo\Core\Util\IHttpFactory
     */
    private $_httpFactory;

    private $_amount;
    private $_category;

    private $_scopeType;
    private $_scopeName;

    /**
     * @var \Convo\Core\Workflow\IConversationElement[]
     */
    private $_ok = [];

    /**
     * @var \Convo\Core\Workflow\IConversationElement[]
     */
    private $_nok = [];

    public function __construct($properties, $httpFactory)
    {
        parent::__construct($properties);

        $this->_httpFactory = $httpFactory;

        $this->_amount = $properties['amount'];
        $this->_category = $properties['category'];

        $this->_scopeType = $properties['scope_type'];
        $this->_scopeName = $properties['scope_name'];

        foreach ($properties['ok'] as $element) {
	        $this->_ok[] = $element;
	        $this->addChild($element);
	    }
    	
	    foreach ($properties['nok'] as $element) {
	        $this->_nok[] = $element;
	        $this->addChild($element);
	    }
    }

    public function read(IConvoRequest $request, IConvoResponse $response)
    {
        $http_client = $this->_httpFactory->getHttpClient();

        $uri = $this->_httpFactory->buildUri(
            self::BASE_URL,
            [
                'amount' => $this->evaluateString($this->_amount),
                'category' => $this->evaluateString($this->_category),
                'type' => 'multiple'
            ]
        );

        $this->_logger->info('Final URI ['.$uri.']');

        $res = $http_client->sendRequest(
            $this->_httpFactory->buildRequest(IHttpFactory::METHOD_GET, $uri)
        );

        if ($res->getStatusCode() !== 200) {
            $this->_logger->error('Could not fetch trivia: '.$res->getReasonPhrase());
            
            foreach ($this->_nok as $nok) {
                $nok->read($request, $response);
            }

            return;
        }

        $result = json_decode($res->getBody()->__toString(), true);

        $this->_logger->info('Got response trivia ['.print_r($result, true).']');

        $questions = [];

        foreach ($result['results'] as $item)
        {
            $cw_answers = [];
            $correct = [];

            $possible_answers = array_merge([$item['correct_answer']], $item['incorrect_answers']);
            shuffle($possible_answers);
            $possible_answers = array_values($possible_answers);

            foreach ($possible_answers as $index => $possible_answer) {
                $cw_answers[] = [
                    'text' => $this->_decodeSpecialChars($possible_answer),
                    'letter' => self::LETTERS[$index],
                    'is_correct' => ($possible_answer === $item['correct_answer'])
                ];

                if ($cw_answers[$index]['is_correct']) {
                    $correct = $cw_answers[$index];
                }
            }

            $questions[] = [
                'text' => $this->_decodeSpecialChars($item['question']),
                'answers' => $cw_answers,
                'correct_answer' => $correct
            ];
        }

        $params = $this->getService()->getServiceParams($this->evaluateString($this->_scopeType));
        $params->setServiceParam($this->evaluateString($this->_scopeName), $questions);

        foreach ($this->_ok as $ok) {
            $ok->read($request, $response);
        }
    }

    private function _decodeSpecialChars($string)
    {
        $string = html_entity_decode($string, ENT_QUOTES);
        $string = htmlspecialchars_decode($string);
        $string = urldecode($string);

        return $string;
    }
}