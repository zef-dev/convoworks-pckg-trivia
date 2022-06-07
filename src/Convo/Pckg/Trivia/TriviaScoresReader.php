<?php declare(strict_types=1);
namespace Convo\Pckg\Trivia;


class TriviaScoresReader extends \Convo\Core\Workflow\AbstractWorkflowContainerComponent implements \Convo\Core\Workflow\IConversationElement
{
    /**
     * @var \Convo\Core\Workflow\IConversationElement[]
     */
    private $_single;

    /**
     * @var \Convo\Core\Workflow\IConversationElement[]
     */
    private $_multiple;

    /**
     * @var \Convo\Core\Workflow\IConversationElement[]
     */
    private $_all;


    /** @var array */
    private $_players;
    private $_item;
    private $_name_field;
    private $_score_field;

    public function __construct($properties)
    {
        parent::__construct($properties);

        $this->_single = $properties['single'];
        foreach ($this->_single as $single) {
            $this->addChild($single);
        }

        $this->_multiple = $properties['multiple'];
        foreach ($this->_multiple as $multiple) {
            $this->addChild($multiple);
        }

        $this->_all = $properties['all'];
        foreach ($this->_all as $all) {
            $this->addChild($all);
        }

        $this->_players = $properties['players'];
        $this->_item = $properties['status_var'];
        $this->_name_field = $properties['name_field'];
        $this->_score_field = $properties['score_field'];
    }

    public function read(\Convo\Core\Workflow\IConvoRequest $request, \Convo\Core\Workflow\IConvoResponse $response)
    {
        $users  =   $this->_getUsers();
        $slot_name = $this->evaluateString($this->_item);

        $scope_type	= \Convo\Core\Params\IServiceParamsScope::SCOPE_TYPE_REQUEST;
        $params = $this->getService()->getComponentParams( $scope_type, $this);

        $start = 0;
        $end = count( $users);

        $this->_logger->debug('Got the users array['.print_r($users, true).']');

        for ($i = $start; $i < $end; ++$i) {
            $val = $users[$i];

            $status =   [
                'score' => $val['score'],
                'rank' => $val["rank"],
                'first' => $i === $start,
                'last' => $i === $end - 1
            ];

            if( isset( $val['names']) && count( $users) == 1) {
                $this->_logger->debug('All users same score case');

                $status =   array_merge( $status, ['names'=> $val['names']]);

                $params->setServiceParam($slot_name, $status);

                foreach ($this->_all as $all) {
                    $all->read($request, $response);
                }

            } elseif( isset( $val['names'])) {
                $this->_logger->debug('Multiple score case');

                $status =   array_merge( $status, ['names'=> $val['names']]);

                $params->setServiceParam($slot_name, $status);

                foreach ($this->_multiple as $multiple) {
                    $multiple->read($request, $response);
                }

            } else {
                $this->_logger->debug('Single score case');

                $status =   array_merge( $status, ['name'=> $val['name']]);

                $params->setServiceParam($slot_name, $status);

                foreach ($this->_single as $single) {
                    $single->read($request, $response);
                }

            }
        }
    }

    private function _getUsers()
    {
        $items = $this->evaluateString($this->_players);
        $name_field  = $this->evaluateString( $this->_name_field);
        $score_field = $this->evaluateString( $this->_score_field);



        //sort the array by the score field, desc
        $score = array_column($items, $score_field);
        array_multisort($score, SORT_DESC, $items);

        $users = array();
        $i = 0;
        $prevScore  =   null;
        foreach ( $items as $item) {
            $score = $item[$score_field];
            $nextScore = isset($items[$i+1][$score_field])? $items[$i+1][$score_field] : null;

            if( $score == $prevScore){
                $users[ $score ]['names'][] = $item[ $name_field ];
                $users[ $score ]['score'] = $score;
            } elseif( $score == $nextScore){
                $users[ $score ]['names'][] = $item[ $name_field ];
                $users[ $score ]['score'] = $score;
            } else {
                $users[ $score ]['name'] = $item[ $name_field ];
                $users[ $score ]['score'] = $score;
            }

            $i++;
            $prevScore = $score;

        }

        $users  =   array_values( $users);

        //calculate and add user rank
        $i = 0;
        $prevScore = null;
        foreach ( $users as &$user ) {
            if( $user[ 'score' ] !== $prevScore ){
                $i++;
            }
            $prevScore = $user[ 'score' ];
            $user[ 'rank' ] = $i;
        }


        return $users;
    }


    // UTIL
    public function __toString()
    {
        return parent::__toString().'['.count( $this->_single).']';
    }
}