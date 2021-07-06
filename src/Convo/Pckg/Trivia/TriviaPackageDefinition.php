<?php declare(strict_types=1);

namespace Convo\Pckg\Trivia;

use Convo\Core\Factory\AbstractPackageDefinition;
use Convo\Core\Factory\IComponentFactory;
use Convo\Core\Intent\SystemEntity;
use Convo\Core\Intent\EntityModel;
use Convo\Core\Workflow\IRunnableBlock;

class TriviaPackageDefinition extends AbstractPackageDefinition
{
    const NAMESPACE	=	'convo-trivia';

    /**
     * @var \Convo\Core\Factory\PackageProviderFactory
     */
    private $_packageProviderFactory;


    public function __construct( \Psr\Log\LoggerInterface $logger, \Convo\Core\Factory\PackageProviderFactory $packageProviderFactory)
    {
        $this->_packageProviderFactory    =   $packageProviderFactory;

        parent::__construct( $logger, self::NAMESPACE, __DIR__);

        $this->addTemplate( $this->_loadFile(__DIR__ . '/convo-trivia.template.json'));
        $this->addTemplate( $this->_loadFile(__DIR__ . '/convo-trivia-multiplayer.template.json'));

    }


    protected function _initIntents()
    {
        return $this->_loadIntents( __DIR__ .'/system-intents.json');
    }


    protected function _initEntities()
    {
        $entities  =    [];

        $model     =   new EntityModel( 'letter', false);
        $model->load( ['values' => [[
            'value' => 'a',
            'synonyms' => [ 'A', 'a.']
        ], [
            'value' => 'b',
            'synonyms' => [ 'B', 'b.']
        ], [
            'value' => 'c',
            'synonyms' => [ 'C', 'c.']
        ], [
            'value' => 'd',
            'synonyms' => [ 'D', 'd.']
        ]
        ]]);

        $entities['letter'] =   new SystemEntity( 'letter');
        $entities['letter']->setPlatformModel( 'amazon', $model);
        $entities['letter']->setPlatformModel( 'dialogflow', $model);

        return $entities;
    }


    /**
     * {@inheritDoc}
     * @see \Convo\Core\Factory\AbstractPackageDefinition::_initDefintions()
     */
    protected function _initDefintions()
    {
        return array(
            new \Convo\Core\Factory\ComponentDefinition(
                $this->getNamespace(),
                '\Convo\Pckg\Trivia\TriviaRoundBlock',
                'Trivia round block',
                'Special conversation block type that will ask each user round questions.',
                array(
                    'role' => array(
                        'defaultValue' => IRunnableBlock::ROLE_CONVERSATION_BLOCK
                    ),
                    'block_id' => array(
                        'editor_type' => 'block_id',
                        'editor_properties' => array(),
                        'defaultValue' => 'new-block-id',
                        'name' => 'Block ID',
                        'description' => 'Unique string identificator',
                        'valueType' => 'string'
                    ),
                    'name' => array(
                        'editor_type' => 'text',
                        'editor_properties' => array(),
                        'defaultValue' => 'New block',
                        'name' => 'Block name',
                        'description' => 'A user friendly name for the block',
                        'valueType' => 'string'
                    ),
                    'questions' => array(
                        'editor_type' => 'text',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'Questions',
                        'description' => 'Questions array',
                        'valueType' => 'string'
                    ),
                    'users' => array(
                        'editor_type' => 'text',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'Users',
                        'description' => 'Users array. This parameter is optional and use it only if you have multiple users. When ommited, ${status.user} will be null',
                        'valueType' => 'string'
                    ),
                    'status_var' => array(
                        'editor_type' => 'text',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'Status variable name',
                        'description' => 'Name under which to provide full iteration status (round, user)',
                        'valueType' => 'string'
                    ),
                    'correct_letter' => array(
                        'editor_type' => 'text',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'Correct letter',
                        'description' => 'Expression to evaluate corrrect letter',
                        'valueType' => 'string'
                    ),
                    'correct_answer' => array(
                        'editor_type' => 'text',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'Correct answer',
                        'description' => 'Expression to evaluate corrrect answer (text)',
                        'valueType' => 'string'
                    ),
                    'skip_reset' => array(
                        'editor_type' => 'text',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'Skip reset',
                        'description' => 'Optional. Remember block param values when outside of trivia block. Enter a value that evaluates to true or false.',
                        'valueType' => 'string'
                    ),
                    'elements' => array(
                        'editor_type' => 'service_components',
                        'editor_properties' => array(
                            'allow_interfaces' => array('\Convo\Core\Workflow\IConversationElement'),
                            'multiple' => true
                        ),
                        'defaultValue' => array(),
                        'name' => 'Read phase',
                        'description' => 'Elements to be executed in read phase',
                        'valueType' => 'class'
                    ),
                    'answer_ok' => array(
                        'editor_type' => 'service_components',
                        'editor_properties' => array(
                            'allow_interfaces' => array('\Convo\Core\Workflow\IConversationElement'),
                            'multiple' => true
                        ),
                        'defaultValue' => array(),
                        'name' => 'Correct answer given',
                        'description' => 'Elements to be executed after user gave correct answer',
                        'valueType' => 'class'
                    ),
                    'answer_nok' => array(
                        'editor_type' => 'service_components',
                        'editor_properties' => array(
                            'allow_interfaces' => array('\Convo\Core\Workflow\IConversationElement'),
                            'multiple' => true
                        ),
                        'defaultValue' => array(),
                        'name' => 'Incorrect answer given',
                        'description' => 'Elements to be executed after user gave incorrect answer',
                        'valueType' => 'class'
                    ),
                    'additional_readers' => array(
                        'editor_type' => 'service_components',
                        'editor_properties' => array(
                            'allow_interfaces' => array('\Convo\Core\Intent\IIntentAdapter'),
                            'multiple' => true
                        ),
                        'defaultValue' => array(),
                        'defaultOpen' => false,
                        'name' => 'Additional intent readers',
                        'description' => 'Additional intent readers to be applied against request. They have to poulate either "letter" or "answer" slots',
                        'valueType' => 'class'
                    ),
                    'processors' => array(
                        'editor_type' => 'service_components',
                        'editor_properties' => array(
                            'allow_interfaces' => array('\Convo\Core\Workflow\IConversationProcessor'),
                            'multiple' => true
                        ),
                        'defaultValue' => array(),
                        'name' => 'Other processors',
                        'description' => 'Other processors to be executed in process phase. E.g. help, repeat ... This procoessors will not trigger loop iteration.',
                        'valueType' => 'class'
                    ),
                    'fallback' => array(
                        'editor_type' => 'service_components',
                        'editor_properties' => array(
                            'allow_interfaces' => array('\Convo\Core\Workflow\IConversationElement'),
                            'multiple' => true
                        ),
                        'defaultValue' => array(),
                        'name' => 'Fallback',
                        'description' => 'Elements to be read if none of the processors match',
                        'valueType' => 'class'
                    ),
                    'done' => array(
                        'editor_type' => 'service_components',
                        'editor_properties' => array(
                            'allow_interfaces' => array('\Convo\Core\Workflow\IConversationElement'),
                            'multiple' => true
                        ),
                        'defaultValue' => array(),
                        'name' => 'Done',
                        'description' => 'Elements to be read after loop is done. Use it for cleanup and moving the conversation focus to some other block.',
                        'valueType' => 'class'
                    ),
                    '_workflow' => 'read',
                    '_system' => true,
                    '_help' =>  array(
                        'type' => 'file',
                        'filename' => 'trivia-round-block.html'
                    ),
                    '_factory' => new class ( $this->_packageProviderFactory) implements \Convo\Core\Factory\IComponentFactory
                    {
                        private $_packageProviderFactory;
                        public function __construct( \Convo\Core\Factory\PackageProviderFactory $packageProviderFactory)
                        {
                            $this->_packageProviderFactory	=	$packageProviderFactory;
                        }
                        public function createComponent( $properties, $service)
                        {
                            return new \Convo\Pckg\Trivia\TriviaRoundBlock( $properties, $service, $this->_packageProviderFactory);
                        }
                    }
                )
            ),
            new \Convo\Core\Factory\ComponentDefinition(
                $this->getNamespace(),
                '\Convo\Pckg\Trivia\TriviaScoresReader',
                'Trivia Scores',
                'Display the score and the name of each player in the trivia quiz.',
                array(
                    'players' => array(
                        'editor_type' => 'text',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'players',
                        'description' => 'Players array. Collection of player names and their scores',
                        'valueType' => 'string'
                    ),
                    'status_var' => array(
                        'editor_type' => 'text',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'Status variable name',
                        'description' => 'Name under which to provide full iteration status (name, score)',
                        'valueType' => 'string'
                    ),
                    'name_field' => array(
                        'editor_type' => 'text',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'Name field',
                        'description' => 'Name of the player name field in the players array',
                        'valueType' => 'string'
                    ),
                    'score_field' => array(
                        'editor_type' => 'text',
                        'editor_properties' => array(),
                        'defaultValue' => '',
                        'name' => 'Score field',
                        'description' => 'Name of the player score field in the players array',
                        'valueType' => 'string'
                    ),
                    'single' => [
                        'editor_type' => 'service_components',
                        'editor_properties' => [
                            'allow_interfaces' => [\Convo\Core\Workflow\IConversationElement::class],
                            'multiple' => true
                        ],
                        'defaultValue' => [],
                        'defaultOpen' => false,
                        'name' => 'Single',
                        'description' => 'Flow to be executed when the player scores are unique',
                        'valueType' => 'class'
                    ],
                    'multiple' => [
                        'editor_type' => 'service_components',
                        'editor_properties' => [
                            'allow_interfaces' => [\Convo\Core\Workflow\IConversationElement::class],
                            'multiple' => true,
                        ],
                        'defaultValue' => [],
                        'defaultOpen' => false,
                        'name' => 'Multiple',
                        'description' => 'Flow to be executed when multiple players have the same score',
                        'valueType' => 'class'
                    ],
                    'all' => [
                        'editor_type' => 'service_components',
                        'editor_properties' => [
                            'allow_interfaces' => [\Convo\Core\Workflow\IConversationElement::class],
                            'multiple' => true,
                        ],
                        'defaultValue' => [],
                        'defaultOpen' => false,
                        'name' => 'All',
                        'description' => 'Flow to be executed when all players have the same score',
                        'valueType' => 'class'
                    ],
                    '_help' =>  array(
                        'type' => 'file',
                        'filename' => 'trivia-scores-reader.html'
                    ),
                    '_workflow' => 'read',
                )
            ),
            new \Convo\Core\Factory\ComponentDefinition(
                $this->getNamespace(),
                '\Convo\Pckg\Trivia\OpenTDBTriviaAdapterElement',
                'OpenTDB Adapter Element',
                'Adapt an OpenTDB multiple choice question quiz into a suitable format for Convoworks Trivia',
                [
                    'scope_type' => [
                        'editor_type' => 'select',
                        'editor_properties' => [
                            'options' => ['request' => 'Request', 'session' => 'Session', 'installation' => 'Installation']
                        ],
                        'defaultValue' => 'session',
                        'name' => 'Storage type',
                        'description' => 'Where to store the adapted quiz',
                        'valueType' => 'string'
                    ],
                    'scope_name' => [
                        'editor_type' => 'text',
                        'editor_properties' => array(
                            'multiple' => false
                        ),
                        'defaultValue' => 'questions',
                        'name' => 'Name',
                        'description' => 'Name under which to store the quiz',
                        'valueType' => 'string'
                    ],
                    'amount' => [
                        'editor_type' => 'text',
                        'editor_properties' => [],
                        'defaultValue' => '4',
                        'name' => 'Question Amount',
                        'description' => 'How many questions to fetch from OpenTDB',
                        'valueType' => 'string'
                    ],
                    'category' => [
                        'editor_type' => 'text',
                        'editor_properties' => [],
                        'defaultValue' => null,
                        'name' => 'Question Category',
                        'description' => 'OpenTDB category to fetch questions for',
                        'valueType' => 'string'
                    ],
                    'ok' => [
                        'editor_type' => 'service_components',
                        'editor_properties' => [
                            'allow_interfaces' => ['\Convo\Core\Workflow\IConversationElement'],
                            'multiple' => true
                        ],
                        'defaultValue' => [],
                        'name' => 'OK',
                        'description' => 'Executed if the quiz is successfully loaded',
                        'valueType' => 'class'
                    ],
                    'nok' => [
                        'editor_type' => 'service_components',
                        'editor_properties' => [
                            'allow_interfaces' => ['\Convo\Core\Workflow\IConversationElement'],
                            'multiple' => true
                        ],
                        'defaultValue' => [],
                        'name' => 'Not OK',
                        'description' => 'Executed if an error occurred',
                        'valueType' => 'class'
                    ],
                    '_preview_angular' => [
                        'type' => 'html',
                        'template' => '<div class="code">' .
                        'Get {{ component.properties.amount }} question(s) from OpenTDB quiz category <code>{{ component.properties.category }}</code>' .
                        '</div>'
                    ],
                    '_workflow' => 'read',
                    '_help' => [
                        'type' => 'file',
                        'filename' => 'opentdb-trivia-adapter-element.html'
                    ],
                    '_factory' => new class ($this->_httpFactory) implements IComponentFactory
                    {
                        private $_httpFactory;

                        public function __construct(\Convo\Core\Util\IHttpFactory $httpFactory)
                        {
                            $this->_httpFactory = $httpFactory;
                        }

                        public function createComponent($properties, $service)
                        {
                            return new \Convo\Pckg\Trivia\OpenTDBTriviaAdapterElement($properties, $this->_httpFactory);
                        }
                    }
                ]
            )
        );
    }
}
