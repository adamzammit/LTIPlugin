<?php
/**
 * Make LimeSurvey an LTI provider
 * Plugin based on "zesthook" by Evently-nl
 *
 * @author Adam Zammit <adam@acspri.org.au>
 * @copyright 2018,2020,2021 ACSPRI <https://www.acspri.org.au>
 * @author Renaat De Muynck <renaat.demuynck@arteveldehs.be>
 * @copyright 2021 Artevelde UAS <https://www.artevelde-uas.be>
 * @author Stefan Verweij <stefan@evently.nl>
 * @copyright 2016 Evently <https://www.evently.nl>
 * @license GPL v3
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

require_once  __DIR__ . '/LTI-Tool-Provider-Library-PHP/vendor/autoload.php';
require_once  __DIR__ . '/ArrayOAuthDataStore.php';

use IMSGlobal\LTI\OAuth\OAuthServer;
use IMSGlobal\LTI\OAuth\OAuthSignatureMethod_HMAC_SHA1;
use IMSGlobal\LTI\OAuth\OAuthRequest;
use IMSGlobal\LTI\ToolProvider;

class LTIResourceLink extends ToolProvider\ResourceLink
{
    public function setConsumer($consumer)
    {
        $this->consumer = $consumer;
    }
}

class LTIConsumer
{
    public $secret;
    private $key;
    public function getKey()
    {
        return $this->key;
    }
    public function __construct($key,$secret)
    {
        $this->secret = $secret;
        $this->key = $key;
    }
}


class LTIResource
{
    public $outcomeServiceURL;
    public function getSetting($setting)
    {
        if ($setting == "lis_outcome_service_url") {
            return $this->outcomeServiceURL;
        }
        return false;
    }
    public function __construct($outcomeserviceurl)
    {
        $this->outcomeServiceURL = $outcomeserviceurl;
    }
}


class LTIUser
{
    public $ltiResultSourcedId;
    private $resourceLink;

    public function getResourceLink()
    {
        return $this->resourceLink;
    }

    public function __construct($outcomeserviceurl,$sourceid) {
        $this->ltiResultSourcedId = $sourceid;
        $this->resourceLink = new LTIResource($outcomeserviceurl);
    }
}


class LTIPlugin extends PluginBase
{
    protected $storage = 'DbStorage';
    static protected $name = 'LTIPlugin';
    static protected $description = 'LimeSurvey Plugin that allows LimeSurvey to act as an LTI provider';

    public function init()
    {
        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');
        $this->subscribe('newDirectRequest'); //for LTI call
        $this->subscribe('newUnsecureRequest', 'newDirectRequest'); //for LTI call
        $this->subscribe('afterSurveyComplete'); //for LTI result return
    }

    protected $settings = [
        'sResourceIdAttribute' => [
            'type' => 'string',
            'default' => 'resource_link_id',
            'label' => 'REQUIRED: The LTI attributes that stores the unique Resource ID - this is how the LTI system identifies the resources that contains the LTI Consumer (eg the Unit)',
            'help' => 'For openEdX, Blackboard it is probably resource_link_id, for Canvas it is probably custom_canvas_course_id. This maps to ATTRIBUTE_3 in your participant table'
        ],
        'sUserIdAttribute' => [
            'type' => 'string',
            'default' => 'user_id',
            'label' => 'REQUIRED: The LTI attributes that stores the unique User ID',
            'help' => 'For openEdX, Blackboard it is probably user_id, for Canvas it is probably custom_canvas_user_id. This maps to ATTRIBUTE_4 in your participant table'
        ],
        'sUrlAttribute' => [
            'type' => 'string',
            'default' => 'launch_presentation_return_url',
            'label' => 'Optional: The LTI attributes that stores the return URL',
            'help' => 'Leave blank for no data to be stored. For Canvas and Blackboard it appears to be launch_presentation_return_url. This maps to ATTRIBUTE_1 in your participant table'
        ],
        'sCourseTitleAttribute' => [
            'type' => 'string',
            'default' => 'context_title',
            'label' => 'Optional: The LTI attributes that stores the course title',
            'help' => 'Leave blank for no data to be stored. For openEdX, Blackboard and Canvas it appears to be context_title. This maps to ATTRIBUTE_2 in your participant table'
        ],
        'sEmailAttribute' => [
            'type' => 'string',
            'default' => 'lis_person_contact_email_primary',
            'label' => 'Optional: The LTI attributes that stores the participants email address',
            'help' => 'Leave blank for no data to be stored. For openEdX, Blackboard and Canvas it appears to be lis_person_contact_email_primary. This maps to email in your participant table'
        ],
        'sFirstNameAttribute' => [
            'type' => 'string',
            'default' => 'lis_person_name_given',
            'label' => 'Optional: The LTI attributes that stores the first name of the participant',
            'help' => 'Leave blank for no data to be stored. For openEdX, Blackboard and Canvas it appears to be lis_person_name_given. This maps to firstname in your participant table'
        ],
        'sLastNameAttribute' => [
            'type' => 'string',
            'default' => 'lis_person_name_family',
            'label' => 'Optional: The LTI attributes that stores the last name of the participant',
            'help' => 'Leave blank for no data to be stored. For openEdX, Blackboard and Canvas it appears to be lis_person_name_family. This maps to lastname in your participant table'
        ],
        'sResultSourceAttribute' => [
            'type' => 'string',
            'default' => 'lis_result_sourcedid',
            'label' => 'Optional: The LTI attributes that stores the result sourcedid - this is required when you want to return a result to the LMS. The default appears to be lis_result_sourcedid',
            'help' => 'Leave blank for no data to be stored. This maps to ATTRIBUTE_5'
        ],
        'sOutcomeServiceURLAttribute' => [
            'type' => 'string',
            'default' => 'lis_outcome_service_url',
            'label' => 'Optional: The LTI attributes that stores the outcome service URL - this is required when you want to return a result to the LMS',
            'help' => 'Leave blank for no data to be stored. This maps to ATTRIBUTE_6. The default appears to be lis_outcome_service_url'
        ],
        'bDebugMode' => [
            'type' => 'select',
            'options' => [
                0 => 'No',
                1 => 'Yes'
            ],
            'default' => 0,
            'label' => 'Enable Debug Mode',
            'help' => 'Enable debugmode to see what data is transmitted'
        ]
    ];

    public function newDirectRequest()
    {
        $event = $this->getEvent();

        if ($event->get('target') !== $this->getName()) {
            return;
        }

        $action = $event->get('function');

        if (empty($action)) {
            exit('No survey id passed');
        }

        $surveyId = (int) $action;

        if (Survey::model()->findByPk($surveyId) === null) {
            exit("Survey $surveyId does not exist");
        }

        try {
            $params = $this->handleRequest($this->get('sAuthSecret', 'Survey', $surveyId));
        } catch (Exception $e) {
            exit("Bad OAuth: {$e->getMessage()}");
        }

        // Check if the correct key is being sent
        if ($params['oauth_consumer_key'] !== $this->get('sAuthKey', 'Survey', $surveyId)) {
            exit('Wrong key passed');
        }

        $this->debug('Valid LTI Connection', $params, microtime(true));

        if (!tableExists("{{tokens_$surveyId}}")) {
            exit("No participant table for survey $surveyId");
        }

        // Store the return url somewhere if it exists
        $urlAttribute = $this->get('sUrlAttribute', null, null, $this->settings['sUrlAttribute']);
        $url = (!empty($urlAttribute) && isset($params[$urlAttribute])) ? $params[$urlAttribute] : '';

        // If we want to limit completion to one per course/user combination:
        $multipleCompletions = (bool) $this->get('bMultipleCompletions', 'Survey', $surveyId);

        // Search for token based on attribute_3 and attribute_4 (resource id and user id)
        $tokenQuery = [
            'attribute_3' => $params[$this->get('sResourceIdAttribute', null, null, $this->settings['sResourceIdAttribute'])],
            'attribute_4' => $params[$this->get('sUserIdAttribute', null, null, $this->settings['sUserIdAttribute'])]
        ];

        // Get the current token count
        $tokenCount = $multipleCompletions ? 0 : (int) Token::model($surveyId)->countByAttributes($tokenQuery);
        // If no token, then create a new one and start survey
        if ($multipleCompletions || $tokenCount === 0) {
            $firstname = $params[$this->get('sFirstNameAttribute', null, null, $this->settings['sFirstNameAttribute'])] ?? '';
            $lastname = $params[$this->get('sLastNameAttribute', null, null, $this->settings['sLastNameAttribute'])] ?? '';
            $email = $params[$this->get('sEmailAttribute', null, null, $this->settings['sEmailAttribute'])] ?? '';
            $attribute2 = $params[$this->get('sCourseTitleAttribute', null, null, $this->settings['sCourseTitleAttribute'])] ?? '';
            $tokenAdd = [
                'attribute_1' => $url,
                'attribute_2' => $attribute2,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'email' => $email
            ];
            $tokenReturn = [];
            if (!empty($this->get('sReturnExpression', 'Survey', $surveyId))) {
                $tokenReturn = [
                'attribute_5' => $params[$this->get('sResultSourceAttribute', null, null, $this->settings['sResultSourceAttribute'])] ?? '',
                'attribute_6' => $params[$this->get('sOutcomeServiceURLAttribute', null, null, $this->settings['sOutcomeServiceURLAttribute'])] ?? ''
                ];
            }
            $token = Token::create($surveyId);
            $token->setAttributes(array_merge($tokenQuery, $tokenAdd, $tokenReturn));
            $token->generateToken();

            if (!$token->save()) {
                exit('Error creating token');
            }

            // Create the survey URL
            $redirectUrl = Yii::app()->createAbsoluteUrl('survey/index', [
                'sid' => $surveyId,
                'token' => $token->token,
                'newtest' => 'Y'
            ]);
        }
        // Else if a token continue where left off
        else {
            $token = Token::model($surveyId)->findByAttributes($tokenQuery);

            // Already completed.
            if ($token->completed !== 'N') {
                exit('Survey already completed');
            }

            // Create the survey URL
            $redirectUrl = Yii::app()->createAbsoluteUrl('survey/index', [
                'sid' => $surveyId,
                'token' => $token->token
            ]);
        }

        // Redirect to the survey
        Yii::app()->getController()->redirect($redirectUrl);
    }

    /**
     * If result return is enabled - send a result back
     */
    public function afterSurveyComplete()
    {
        $event = $this->event;
        $surveyId = $event->get('surveyId');

        $rr = $this->get('sReturnExpression', 'Survey', $surveyId);

        if (!empty($rr)) { //return the assessment value
            $survey = Survey::model()->findByPk($surveyId);
            if (isset($survey->tokenAttributes['attribute_5']) &&
                isset($survey->tokenAttributes['attribute_6'])) {

                $responseId = $event->get('responseId');
                $response = $this->api->getResponse($surveyId, $responseId);
                $token = Token::model($surveyId)->findByToken($response['token']);
                $pr = LimeExpressionManager::ProcessString($rr, null, array(), 3, 1, false, false, true);
                if (!empty($token->attribute_5) && !empty($token->attribute_6)) {
                    //send result back
                    $lti_outcome = new ToolProvider\Outcome($pr);
                    $resource_link = new LTIResourceLink();
                    $consumer = new LTIConsumer($this->get('sAuthKey', 'Survey', $surveyId),$this->get('sAuthSecret', 'Survey', $surveyId));
                    $resource_link->setConsumer($consumer);
                    $user = new LTIUser($token->attribute_6,$token->attribute_5);
                    $res = $resource_link->doOutcomesService(ToolProvider\ResourceLink::EXT_WRITE, $lti_outcome, $user);
                    $token->attribute_7 = print_r($res,TRUE);
                    $token->save();
                }
            }
        }
    }


    /**
     * Add setting on survey level: provide URL for LTI connector and check that tokens table / attributes exist
     */
    public function beforeSurveySettings()
    {
        $event = $this->event;

        $survey = Survey::model()->findByPk($event->get('survey'));

        $info = '';

        if (!tableExists($survey->responsesTableName)) {
            $info = 'Please activate the survey before continuing';
        }

        $rr = $this->get('sReturnExpression', 'Survey', $event->get('survey'));

        if (!(isset($survey->tokenAttributes['attribute_1']) &&
            isset($survey->tokenAttributes['attribute_2']) &&
            isset($survey->tokenAttributes['attribute_3']) &&
            isset($survey->tokenAttributes['attribute_4']))
            || ((!empty($rr)) &&
            !(isset($survey->tokenAttributes['attribute_5']) &&
              isset($survey->tokenAttributes['attribute_6']) &&
              isset($survey->tokenAttributes['attribute_7'])))
        ) {
            $info = 'Please ensure the survey participant function has been enabled, and that there at least ' . (empty($rr) ? "4" : "7") .  ' attributes created';
        }

        $apiKey = $this->get('sAuthKey', 'Survey', $event->get('survey'));
        if (empty($apiKey) || trim($apiKey) === '') {
            $info = 'Set an Auth key and save these settings before you can access the LTI URL';
        }

        $apiSecret = $this->get('sAuthSecret', 'Survey', $event->get('survey'));
        if (empty($apiKey) || trim($apiSecret) === '') {
            $info = 'Set an Auth secret and save these settings before you can access the LTI URL';
        }

        $info2 = $info;

        if ($info === '') {
            $info =  Yii::app()->createAbsoluteUrl('plugins/unsecure', [
                'plugin' => 'LTIPlugin',
                'function' => $event->get('survey')
            ]);
            $info2 = "'Advanced Module List' in 'Advanced Settings' contains: ['lti_consumer'] and 'LTI_Passports' contains: ['limesurvey:$apiKey:$apiSecret']";
        }

        $defaultAuthKey = $this->get('sAuthKey', null, null, $this->generateRandomString());
        $defaultAuthSecret = $this->get('sAuthSecret', null, null, $this->generateRandomString());

        $sets = [
            'sAuthKey' => [
                'type' => 'string',
                'label' => 'REQUIRED: The key used as a password in your LTI system',
                'help' => 'Please use something random',
                'current' => $this->get('sAuthKey', 'Survey', $event->get('survey'), $defaultAuthKey),
            ],
            'sAuthSecret' => [
                'type' => 'string',
                'label' => 'REQUIRED: The secret used as a password in your LTI system',
                'help' => 'Please use something random',
                'current' => $this->get('sAuthSecret', 'Survey', $event->get('survey'), $defaultAuthSecret),
            ],
            'bMultipleCompletions' => [
                'type' => 'select',
                'options' => [
                    0 => 'No',
                    1 => 'Yes'
                ],
                'current' => $this->get('bMultipleCompletions', 'Survey', $event->get('survey')),
                'label' => 'Allow a user in a course to complete this survey more than once',
                'help' => 'This will allow multiple tokens to be created for the same user each time they go to access the survey'
            ],
            'sReturnExpression' => [
                'type' => 'string',
                'label' => 'If returning a result, please enter the text or expression you wish to return here. Leave blank to not return a result. LMS systems typically accept a value between 0 and 1',
                'help' => 'For example, {A1} will return whatever was stored in question A1, 1 will just return the score of 1',
                'current' => $this->get('sReturnExpression', 'Survey', $event->get('survey')),
            ],
            'sInfo' => [
                'type' => 'info',
                'label' => 'The URL to access this survey via the LTI Provider',
                'help' =>  $info
            ],
            'sInfo2' => [
                'type' => 'info',
                'label' => 'If using OpenEdX ensure the following: ',
                'help' =>  $info2
            ]
        ];

        $event->set("surveysettings.{$this->id}", [
            'name' => get_class($this),
            'settings' => $sets
        ]);
    }

    /**
     * Save the settings
     */
    public function newSurveySettings()
    {
        $event = $this->event;
        foreach ($event->get('settings') as $name => $value) {
            /* In order use survey setting, if not set, use global, if not set use default */
            $default = $event->get($name, null, null, $this->settings[$name]['default'] ?? null);
            $this->set($name, $value, 'Survey', $event->get('survey'), $default);
        }
    }

    private function handleRequest($secret)
    {
        // If this request is not an LTI Launch, give up
        if (($_REQUEST['lti_message_type'] !== 'basic-lti-launch-request') || ($_REQUEST['lti_version'] !== 'LTI-1p0')) {
            throw new Exception('Not a valid LTI launch request');
        }

        if (!isset($_REQUEST['resource_link_id'])) {
            throw new Exception('No resource link id provided');
        }

        // Insure we have a valid launch
        if (empty($_REQUEST['oauth_consumer_key'])) {
            throw new Exception('Missing oauth_consumer_key in request');
        }

        // Verify the message signature
        $store = new ArrayOAuthDataStore();
        $store->add_consumer($_REQUEST['oauth_consumer_key'], $secret);

        $server = new OAuthServer($store);

        $method = new OAuthSignatureMethod_HMAC_SHA1();
        $server->add_signature_method($method);

        $request = OAuthRequest::from_request();
        $server->verify_request($request);

        // Strip OAuth parameters (except consumer key)
        return array_filter($_POST, function ($value, $key) {
            return ((strpos($key, 'oauth_') === false) || ($key === 'oauth_consumer_key'));
        }, ARRAY_FILTER_USE_BOTH);
    }

    private function generateRandomString()
    {
        $randomString = Yii::app()->securityManager->generateRandomString(32);

        return str_replace(['~', '_', ':'], ['a', 'z', 'e'], $randomString);
    }

    private function debug($parameters, $hookSent, $timeStart)
    {
        if ($this->get('bDebugMode', null, null, $this->settings['bDebugMode'])) {
            echo '<pre>';
            var_dump($parameters);
            echo '<br><br> ----------------------------- <br><br>';
            var_dump($hookSent);
            echo '<br><br> ----------------------------- <br><br>';
            echo 'Total execution time in seconds: ' . (microtime(true) - $timeStart);
            echo '</pre>';
        }
    }
}
