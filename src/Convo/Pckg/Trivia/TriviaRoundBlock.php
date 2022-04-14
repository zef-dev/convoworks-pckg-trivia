<?php

declare(strict_types=1);

namespace Convo\Pckg\Trivia;


use Convo\Core\Preview\PreviewBlock;
use Convo\Core\Preview\PreviewSection;
use Convo\Core\Preview\PreviewUtterance;
use Convo\Core\Workflow\IConvoRequest;
use Convo\Core\Workflow\IRequestFilter;
use Convo\Core\Workflow\IRequestFilterResult;

class TriviaRoundBlock extends \Convo\Pckg\Core\Elements\ConversationBlock implements IRequestFilter
{

	/**
	 * @var \Convo\Core\Factory\PackageProviderFactory
	 */
	private $_packageProviderFactory;

	/**
	 * @var \Convo\Core\Workflow\IConversationElement[]
	 */
	private $_answeredOk = [];

	/**
	 * @var \Convo\Core\Workflow\IConversationElement[]
	 */
	private $_answeredNok = [];

	/**
	 * @var \Convo\Core\Workflow\IConversationElement[]
	 */
	private $_done = [];

	private $_questions;
	private $_users;
	private $_item;
	private $_correctLetter;
	private $_correctAnswer;
	private $_skipReset;

	/**
	 * @var IRequestFilter[]
	 */
	private $_filters  =   [];

	public function __construct(
		$properties,
		\Convo\Core\ConvoServiceInstance $service,
		\Convo\Core\Factory\PackageProviderFactory $packageProviderFactory
	) {
		parent::__construct($properties);
		$this->setService($service);
		$this->_packageProviderFactory    =   $packageProviderFactory;

		$this->_questions = $properties['questions'];
		$this->_users = $properties['users'];
		$this->_item = $properties['status_var'];
		$this->_correctLetter = $properties['correct_letter'];
		$this->_correctAnswer = $properties['correct_answer'];
		$this->_skipReset  =   $properties['skip_reset'];

		$readers  =   [];
		foreach ($properties['additional_readers'] as $reader) {
			/* @var $element \Convo\Core\Intent\IIntentAdapter */
			$readers[] =   $reader;
			$this->addChild($reader);
		}

		foreach ($properties['answer_ok'] as $element) {
			/* @var $element \Convo\Core\Workflow\IConversationElement */
			$this->_answeredOk[] =   $element;
			$this->addChild($element);
		}

		foreach ($properties['answer_nok'] as $element) {
			/* @var $element \Convo\Core\Workflow\IConversationElement */
			$this->_answeredNok[] =   $element;
			$this->addChild($element);
		}

		if (isset($properties['done'])) {
			foreach ($properties['done'] as $done) {
				$this->_done[]  =   $done;
				$this->addChild($done);
			}
		}

		$reader   =   new \Convo\Pckg\Core\Filters\ConvoIntentReader(['intent' => 'convo-trivia.LetterAnswerIntent'], $this->_packageProviderFactory);
		$reader->setLogger($this->_logger);
		$reader->setService($this->getService());
		$readers[]    =   $reader;


		$reader   =   new \Convo\Pckg\Core\Filters\ConvoIntentReader([
			'intent' => 'convo-trivia.AnswerFallbackA',
			'values' => [
				'letter' => 'a'
			]
		], $this->_packageProviderFactory);
		$reader->setLogger($this->_logger);
		$reader->setService($this->getService());
		$readers[]    =   $reader;

		$reader   =   new \Convo\Pckg\Core\Filters\ConvoIntentReader([
			'intent' => 'convo-trivia.AnswerFallbackB',
			'values' => [
				'letter' => 'b'
			]
		], $this->_packageProviderFactory);
		$reader->setLogger($this->_logger);
		$reader->setService($this->getService());
		$readers[]    =   $reader;

		$reader   =   new \Convo\Pckg\Core\Filters\ConvoIntentReader([
			'intent' => 'convo-trivia.AnswerFallbackC',
			'values' => [
				'letter' => 'c'
			]
		], $this->_packageProviderFactory);
		$reader->setLogger($this->_logger);
		$reader->setService($this->getService());
		$readers[]    =   $reader;

		$reader   =   new \Convo\Pckg\Core\Filters\ConvoIntentReader([
			'intent' => 'convo-trivia.AnswerFallbackD',
			'values' => [
				'letter' => 'd'
			]
		], $this->_packageProviderFactory);
		$reader->setLogger($this->_logger);
		$reader->setService($this->getService());
		$readers[]    =   $reader;

		$reader   =   new \Convo\Pckg\Core\Filters\ConvoIntentReader([
			'intent' => 'convo-trivia.GiveAnswerIntent'
		], $this->_packageProviderFactory);
		$reader->setLogger($this->_logger);
		$reader->setService($this->getService());
		$readers[]    =   $reader;

		$filter =   new \Convo\Pckg\Core\Filters\IntentRequestFilter([
			'readers' => $readers
		]);
		$filter->setLogger($this->_logger);
		$filter->setService($this->getService());
		$this->addChild($filter);
		$this->_filters[] =   $filter;

		// put myself as last filter - not to catch dialogflow text
		$this->_filters[] =   $this;
	}

	public function getPreview()
	{
		$pblock = new PreviewBlock($this->getName(), $this->getComponentId());
		$pblock->setLogger($this->_logger);

		$read = new PreviewSection('Read');
		$read->setLogger($this->_logger);

		try {
			$read->collect($this->getElements(), '\Convo\Core\Preview\IBotSpeechResource');

			if (!$read->isEmpty()) {
				$pblock->addSection($read);
			}
		} catch (\Exception $e) {
			$this->_logger->error($e);
		}

		$correct_answer = new PreviewSection('Correct Answer Given');
		$correct_answer->setLogger($this->_logger);

		try {
			$correct_answer->collect($this->_answeredOk, '\Convo\Core\Preview\IBotSpeechResource');

			if (!$correct_answer->isEmpty()) {
				$pblock->addSection($correct_answer);
			}
		} catch (\Exception $e) {
			$this->_logger->error($e);
		}

		$incorrect_answer = new PreviewSection('Incorrect Answer Given');
		$incorrect_answer->setLogger($this->_logger);

		try {
			$incorrect_answer->collect($this->_answeredNok, '\Convo\Core\Preview\IBotSpeechResource');

			if (!$incorrect_answer->isEmpty()) {
				$pblock->addSection($incorrect_answer);
			}
		} catch (\Exception $e) {
			$this->_logger->error($e);
		}

		foreach ($this->getProcessors() as $processor) {
			$processor_section = new PreviewSection('Process - ' . (new \ReflectionClass($processor))->getShortName() . ' [' . $processor->getId() . ']');
			$processor_section->setLogger($this->_logger);

			try {
				$processor_section->collectOne($processor, '\Convo\Core\Preview\IUserSpeechResource');
				$processor_section->collectOne($processor, '\Convo\Core\Preview\IBotSpeechResource');

				if (!$processor_section->isEmpty()) {
					$pblock->addSection($processor_section);
				}
			} catch (\Exception $e) {
				$this->_logger->error($e);
				continue;
			}
		}

		$additional_readers = new PreviewSection('Additional intent readers');
		$additional_readers->setLogger($this->_logger);

		try {
			$additional_readers->collectOne($this->_filters[0], '\Convo\Core\Preview\IUserSpeechResource');

			if (!$additional_readers->isEmpty()) {
				$pblock->addSection($additional_readers);
			}
		} catch (\Exception $e) {
			$this->_logger->error($e);
		}

		$fallback = new PreviewSection('Fallback');
		$fallback->setLogger($this->_logger);

		try {
			$fallback->collect($this->getFallback(), '\Convo\Core\Preview\IBotSpeechResource');

			if (!$fallback->isEmpty()) {
				$pblock->addSection($fallback);
			}
		} catch (\Exception $e) {
			$this->_logger->error($e);
		}

		$done = new PreviewSection('Done');
		$done->setLogger($this->_logger);

		try {
			$done->collect($this->_done, '\Convo\Core\Preview\IBotSpeechResource');

			if (!$done->isEmpty()) {
				$pblock->addSection($done);
			}
		} catch (\Exception $e) {
			$this->_logger->error($e);
		}

		return $pblock;
	}

	public function getQuestions()
	{
		$items         =   $this->evaluateString($this->_questions);
		if (is_array($items) && count($items)) {
			$this->_logger->debug('Got questions [' . $this->_questions . '][' . print_r($items, true) . ']');
			return $items;
		}
		throw new \Exception('Provide non empty indexed array for [' . $this->_questions . '] component parameter');
	}

	public function getUsers()
	{
		if (empty($this->_users)) {
			return [];
		}

		$items         =   $this->evaluateString($this->_users);
		if (is_array($items) && count($items)) {
			$this->_logger->debug('Got users [' . $this->_users . '][' . print_r($items, true) . ']');
			return $items;
		}
		throw new \Exception('Provide non empty indexed array for [' . $this->_users . '] component parameter');
	}

	// BLOCK & ELEM INTERFACE
	public function read(\Convo\Core\Workflow\IConvoRequest $request, \Convo\Core\Workflow\IConvoResponse $response)
	{
		$this->_loadItem();

		parent::read($request, $response);
	}

	/**
	 * {@inheritDoc}
	 * @see \Convo\Core\Workflow\IRunnableBlock::run()
	 */
	public function run(\Convo\Core\Workflow\IConvoRequest $request, \Convo\Core\Workflow\IConvoResponse $response)
	{
		$status    =   $this->_loadItem();

		$filter    =   $this->_chooseFilter($request);
		$result    =   $filter->filter($request);

		if ($result->isEmpty()) {
			parent::run($request, $response);
			return;
		}

		if ($this->_isCorrect($request, $result)) {
			$this->_logger->debug('Reading answer ok flow');
			foreach ($this->_answeredOk as $element) {
				/* @var $element \Convo\Core\Workflow\IConversationElement */
				$element->read($request, $response);
			}
		} else {
			$this->_logger->debug('Reading answer nok flow');
			foreach ($this->_answeredNok as $element) {
				/* @var $element \Convo\Core\Workflow\IConversationElement */
				$element->read($request, $response);
			}
		}

		if ($status['last_question']) {
			// last process was done
			foreach ($this->_done as $element) {
				/* @var $element \Convo\Core\Workflow\IConversationElement */
				$element->read($request, $response);
			}
			return;
		}

		$users        =   $this->getUsers();

		if (empty($users)) {
			$next_user = 0;
		} else {
			$next_user    =   $status['user_index'] + 1;
			$this->_logger->debug('Got users [' . count($users) . '] and next as [' . $next_user . ']');

			if ($next_user >= count($users)) {
				$this->_logger->debug('Reseting next user at 0');
				$next_user = 0;
			}
		}


		$questions     =   $this->getQuestions();
		$next_question =   $status['question_index'] + 1;
		$last_question =   false;

		if ((count($questions) === 1) || (count($questions) - 1 === $next_question)) {
			$last_question = true;
		}

		$status        =   array_merge($status, [
			'question' => null,
			'user' => null,
			'question_index' => $status['question_index'] + 1,
			'user_index' => $next_user,
			'last_question' => $last_question
		]);

		$block_params  =   $this->getBlockParams(\Convo\Core\Params\IServiceParamsScope::SCOPE_TYPE_INSTALLATION);
		$slot_name     =   $this->evaluateString($this->_item);
		$block_params->setServiceParam($slot_name, $status);

		// start over
		$this->read($request, $response);
	}

	// FILTER INTERFACE
	public function accepts(IConvoRequest $request)
	{
		if (empty($request->getText())) {
			$this->_logger->warning('Empty text request in request filter [' . $this . ']');
			return false;
		}

		return true;
	}

	public function filter(IConvoRequest $request)
	{
		// 	    $correct_letter    =   $this->evaluateString( $this->_correctLetter);
		// 	    $correct_answer    =    $this->evaluateString( $this->_correctAnswer);

		$result = new \Convo\Core\Workflow\DefaultFilterResult();

		$text              =   trim($request->getText());


		if (strlen($text) === 1) {
			$result->setSlotValue('letter', $text);
		} else {
			$result->setSlotValue('answer', $text);
		}

		return $result;
	}

	// COMMON
	private function _isCorrect(\Convo\Core\Workflow\IConvoRequest $request, IRequestFilterResult $result)
	{

		if ($result->isSlotEmpty('letter') && $result->isSlotEmpty('letter') && !empty($request->getText())) {
			$this->_logger->debug('EMpty data. Will check text [' . $request->getText() . '] and replace result');
			$result =   $this->filter($request);
			$this->_logger->debug('New result [' . $result . ']');
		}

		if (!$result->isSlotEmpty('letter')) {
			$letter            =   $result->getSlotValue('letter');

			// if( strtolower( $letter) == 'b.') {
			//     $letter =   trim( $letter, '.');
			// }

			if (strlen($letter) === 2 && strpos($letter, '.') === 1) {
				$letter = trim($letter, '.');
			}

			$correct_letter    =   $this->evaluateString($this->_correctLetter);
			$this->_logger->debug('Checking letter [' . $letter . '] against correct one [' . $correct_letter . ']');

			return strtolower($letter) === strtolower($correct_letter);
		}

		//fix for letter ending up in the answer slot
		if (!$result->isSlotEmpty('answer') && preg_match("/^[a-z]$/i", $result->getSlotValue('answer'))) {
			$answer         =    $result->getSlotValue('answer');
			$correct_letter =   $this->evaluateString($this->_correctLetter);

			$this->_logger->debug('Checking answer [' . $answer . '] against correct letter [' . $correct_letter . ']');

			return strtolower($answer) === strtolower($correct_letter);
		}

		if (!$result->isSlotEmpty('answer')) {
			$user_answer       =    $result->getSlotValue('answer');
			$correct_answer    =    $this->evaluateString($this->_correctAnswer);

			$answer            =    $this->_cleanAnswer($user_answer);
			$correct_answer    =    $this->_cleanAnswer($correct_answer);

			$this->_logger->debug('Checking answer [' . $answer . '] against correct one [' . $correct_answer . ']');

			if (strtolower($answer) === strtolower($correct_answer)) {
				return true;
			}

			// 	        $variations        =    $this->_interpolateAnswer( $answer);

			return false;
		}


		throw new \Exception('Neither letter or answer slots are populated');
	}

	private function _interpolateAnswer($answer)
	{
		$variations    =   [];
		$variations[]  =   $answer;

		return $variations;
	}

	/**
	 * @param \Convo\Core\Workflow\IConvoRequest $request
	 * @throws \Exception
	 * @return \Convo\Core\Workflow\IRequestFilter
	 */
	private function _chooseFilter(\Convo\Core\Workflow\IConvoRequest $request)
	{
		foreach ($this->_filters as $filter) {
			if ($filter->accepts($request)) {
				return $filter;
			}
		}

		throw new \Exception('Could not find filter for request [' . $request . ']');
	}

	private function _loadItem()
	{
		$questions     =   $this->getQuestions();
		$users         =   $this->getUsers();
		$slot_name     =   $this->evaluateString($this->_item);
		$status        =   $this->_getStatus($questions, $users);

		if (empty($users)) {
			$user  =   null;
		} else {
			$user  =   $users[$status['user_index']];
		}

		$status        =   array_merge(
			$status,
			[
				'question' => $questions[$status['question_index']],
				'user' => $user,
			]
		);


		$block_params  =   $this->getBlockParams(\Convo\Core\Params\IServiceParamsScope::SCOPE_TYPE_INSTALLATION);
		$block_params->setServiceParam($slot_name, $status);
		return $status;
	}

	private function _getStatus($questions, $users)
	{
		$slot_name     =   $this->evaluateString($this->_item);
		$skip_reset    =   $this->evaluateString($this->_skipReset);

		$block_params  =   $this->getBlockParams(\Convo\Core\Params\IServiceParamsScope::SCOPE_TYPE_INSTALLATION);
		$req_params    =   $this->getService()->getServiceParams(\Convo\Core\Params\IServiceParamsScope::SCOPE_TYPE_REQUEST);
		$returning     =   $req_params->getServiceParam('returning');

		$this->_logger->debug('Got returning [' . $returning . ']');
		$this->_logger->debug('Got skip reset [' . $skip_reset . ']');


		if (!$returning && !$skip_reset) {
			$this->_logger->debug('Reset array iterration status when coming first time');
			$block_params->setServiceParam($slot_name, $this->_getDefaultStatus($questions, $users));
		}

		$status        =   $block_params->getServiceParam($slot_name);
		$this->_logger->debug('Got loop status [' . print_r($status, true) . ']');
		if (empty($status)) {
			$status    =   $this->_getDefaultStatus($questions, $users);
		}

		$this->_logger->debug('Returning loop status [' . print_r($status, true) . ']');

		return $status;
	}

	private function _getDefaultStatus($questions, $users)
	{
		$status    =   [
			'question' => null,
			'user' => null,
			'question_index' => 0,
			'user_index' => 0,
			'last_question' => count($questions) <= 1
		];
		return $status;
	}

	private function _cleanAnswer($answer)
	{
		$punctuation    =   array('(', ')', ':', '-', '.', ',', '!');
		$suffixes   =   array('th', 'st', 'nd', 'rd');

		$replace_punctuation    =  str_replace($punctuation, '', $answer);
		$replace_suffixes       =  str_replace($suffixes, '', $replace_punctuation);

		return trim($replace_suffixes);
	}

	// UTIL
	public function __toString()
	{
		return parent::__toString() . '[]';
	}
}
