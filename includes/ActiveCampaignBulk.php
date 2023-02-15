<?php

//namespace \FluentFormPro\Integrations\ActiveCampaign;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}


use \FluentForm\App\Services\Integrations\IntegrationManager;
use \FluentForm\Framework\Foundation\Application;
use \FluentForm\Framework\Helpers\ArrayHelper;
use \FluentFormPro\Integrations\ActiveCampaign\ActiveCampaignApi;

class ActiveCampaignBulk extends IntegrationManager
{	
	public function __construct(Application $app = null)
	{
		parent::__construct(
			$app,
			'ActiveCampaignBulk',							// title
			'activecampaign_bulk',             				// integration key
			'_fluentform_activecampaign_settings',			// option key (uses same key as Active Campaign integration)
			'fluentform_activecampaign_bulk_feed',			// settings key
			16												// priority (immediately after Active Campaign)
		);

		$this->description = 'Bulk import contacts into Active Campaign using Repeater Field';                     
		//$this->logo = '/public/img/integrations/activecampaign.png';
        $this->logo = plugin_dir_url( __DIR__ ) . '/assets/activecampaignbulk.png';
		$this->category = 'crm';
		$this->hasGlobalMenu = false;
		$this->disableGlobalSettings = 'yes';

		$this->registerAdminHooks();
	
	 	add_filter('fluentform_notifying_async_activecampaign_bulk', '__return_false');

		add_action('fluentform_insert_response_data', array($this, 'before_insert_response'), 10, 3);

	}

    protected function getApiClient()
    {
        $settings = get_option($this->optionKey);

        return new ActiveCampaignApi(
            $settings['apiUrl'], $settings['apiKey']
        );
    }

	public function before_insert_response ( $formData, $formId, $inputConfigs)
	{
		// hijack the response rendering for repeater fields so it's not an html table
		remove_filter('fluentform_response_render_repeater_field', array( '\FluentFormPro\Components\RepeaterField', 'renderResponse'), 10);
		add_filter('fluentform_response_render_repeater_field', array($this, 'renderRepeaterResponse'), 10, 3);
		return $formData;
	}

	public function isConfigured()
    {
        $globalModules = get_option('fluentform_global_modules_status');
        return $globalModules && isset($globalModules['activecampaign']) && $globalModules['activecampaign'] == 'yes';
    }

	public function pushIntegration( $integrations, $formId)
    {
        $integrations[$this->integrationKey] = [
            'title' => $this->title . ' Integration',
            'logo' => $this->logo,
            'is_active' => $this->isConfigured(),
            'configure_title' => 'Configuration required!',
            'global_configure_url' => admin_url('admin.php?page=fluent_forms_settings#general-activecampaign-settings'),
            'configure_message' => 'Please configure your ActiveCampaign API first',
            'configure_button_text' => 'Set ActiveCampaign API'
        ];
        return $integrations;
    }

	public function getIntegrationDefaults( $settings, $formId )
	{
		return [
            'name' => '',
            'list_id' => '',
            'repeater_field' => '',
            'repeater_field_columns' => [
	            [
		            'item_value' => '',
		            'label' => ''
	            ]
            ],
            'custom_field_mappings' => [
	            [
		            'item_value' => '',
		            'label' => ''
	            ]
            ],
            'note' => '',
            'tags' => '',
            'tag_routers'            => [],
            'tag_ids_selection_type' => 'simple',
            'conditionals' => [
                'conditions' => [],
                'status' => false,
                'type' => 'all'
            ],
            'instant_responders' => false,
            'last_broadcast_campaign' => false,
            'enabled' => true
        ];
	}

	public function getSettingsFields ($settings, $formId ) 
	{
		return [
			'fields' => [
				[
					'key'           => 'name',
					'label'         => 'Name',
					'required'      => true,
					'placeholder'   => 'Your Feed Name',
					'component'     => 'text'
				],
				[
                    'key' => 'list_id',
                    'label' => 'ActiveCampaign List',
                    'placeholder' => 'Select ActiveCampaign Mailing List',
                    'tips' => 'Select the ActiveCampaign Mailing List you would like to add your contacts to.',
                    'component' => 'list_ajax_options',
                    'options' => $this->getLists(),
                ],
                [
                    'key' => 'repeater_field',
                    'require_list' => false,
                    'label' => 'Repeater Field',
                    'required' => true,
                    'component'    => 'value_text',
                    'placeholder' => 'example: {inputs.repeater_field}',
                    'inline_tip' => 'Select the repeater field used to input multiple contacts'
                ],
                [
		            'key'                => 'repeater_field_columns',
		            'require_list'       => false,
		            'label'              => 'Repeater Field Columns',
		            'tips'               => 'Select which contact fields coorespond to each colum in the repeater field.',
		            'component'          => 'dropdown_many_fields',
		            'options'            => $this->getCustomFields(),
                    
	            ],
                [
		            'key'                => 'custom_field_mappings',
		            'require_list'       => false,
		            'label'              => 'Extra Custom Fields',
		            'tips'               => 'Select additional contact fields to set on every contact imported.',
		            'component'          => 'dropdown_many_fields',
		            'field_label_remote' => 'ActiveCampaign Field',
		            'field_label_local'  => 'Form Field',
		            'options'            => $this->getCustomFields()
	            ],
				[
                    'key' => 'tags',
                    'require_list' => true,
                    'label' => 'Tags',
                    'tips' => 'Associate tags to your ActiveCampaign contacts with a comma separated list (e.g. new lead, FluentForms, web source). Commas within a merge tag value will be created as a single tag.',
                    'component'    => 'selection_routing',
                    'simple_component' => 'value_text',
                    'routing_input_type' => 'text',
                    'routing_key'  => 'tag_ids_selection_type',
                    'settings_key' => 'tag_routers',
                    'labels'       => [
                        'choice_label'      => 'Enable Dynamic Tag Input',
                        'input_label'       => '',
                        'input_placeholder' => 'Tag'
                    ],
                    'inline_tip' => 'Please provide each tag by comma separated value, You can use dynamic smart codes'
                ],
				[
					'key'        => 'conditionals',
					'label'      => 'Conditional Logics',
					'tips'       => 'Push data to your Integration conditionally based on your submission values',
					'component'  => 'conditional_block'
				],
				[
					'key'            => 'enabled',
					'label'          => 'Status',
					'component'      => 'checkbox-single',
					'checkbox_label' => 'Enable This feed'
				]
			],
			'integration_title' => $this->title
		];
	}

	protected function getLists()
    {
        $api = $this->getApiClient();
        if (!$api) {
            return [];
        }

        $lists = $api->get_lists();

        $formattedLists = [];
        foreach ($lists as $list) {
            if (is_array($list)) {
                $formattedLists[strval($list['id'])] = $list['name'];
            }
        }

        return $formattedLists;
    }

	protected function getCustomFields()
    {   
        $default_fields = [
            'email' => 'Email Address',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'phone' => 'Phone',
        ];
        $fields = [];
        $api = $this->getApiClient();
        $response = $api->get_custom_fields();
        if ($response['result_code']) {
            $fields = array_filter($response, function ($item) {
                return is_array($item);
            });
            $formattedFields = [];
            foreach ($fields as $field) {
                $formattedFields[$field['id']] = $field['title'];
            }
           
            $fields = array_merge($default_fields, $formattedFields);
        } else {
            $fields = $default_fields;
        }
        
        return $fields;
    }

	public function getMergeFields($list, $listId, $formId)
    {
        return []; 
        //return $this->getCustomFields();
    }

	/** Function Notify is required by the class IntegrationManager
     * This is the primary callback to process and send form data to Active Campaign
     * @var Array $feed         All feed information, including field settings, etc...
     * @var Array $formData     The data processed through $_POST
     * @var Object $entry       All form fields
     * @var Object $form        All form data in JSON format
     */

	 public function notify($feed, $formData, $entry, $form)
	 {

		$feedData = $feed['processedValues'];
        $feedSettings = $feed['settings'];    
        $parsedRepeater = $this->parseRepeaterData($feedSettings, $formData);


		//die( '<pre>'.print_r($parsedValues, true).'<pre>' );
       		
		 // Set fields and tags sent with every request
 
		 $tags = $this->getSelectedTagIds($feedData, $formData, 'tags');
		 if(!is_array($tags)) {
			 $tags = explode(',', $tags);
		 }
 
		 $tags = array_map('trim', $tags);
		 $tags = array_filter($tags);
 
		 if ($tags) {
			 $addData['tags'] = implode(',', $tags);
		 }
 
		 $list_id = $feedData['list_id'];
		 $addData['p[' . $list_id . ']'] = $list_id;
		 $addData['status[' . $list_id . ']'] = '1';
 
         // Map the additional custom fields
         foreach (ArrayHelper::get($feedData, 'custom_field_mappings', []) as $key => $value) {
            if (!$value) {
                continue;
            }
            $contact_key = 'field[' . $key . ',0]';
            $addData[$contact_key] = $value;
        }
 
		 if (ArrayHelper::isTrue($feedData, 'instant_responders')) {
			 $addData['instantresponders[' . $list_id . ']'] = 1;
		 }
 
		 if (ArrayHelper::isTrue($feedData, 'last_broadcast_campaign')) {
			 $addData['lastmessage[' . $list_id . ']'] = 1;
		 }
 
		 if (!empty($feedData['double_optin_form'])) {
			 $formId = str_replace('item_', '', $feedData['double_optin_form']);
			 if ($formId) {
				 $addData['form'] = $formId;
			 }
		 }

         // Loop through each entry in the repeater and call API to sync data
         foreach ($parsedRepeater as $entry){
            
            // continue to next entry if email is invalid
            if (!is_email( $entry['email'] )) {
                do_action('ff_integration_action_result', $feed, 'failed', 'Active Campaign API call has been skipped because no valid email available');
                continue;
            }
            // merge in favor the repeater entry and reverse it
            $syncData = array_reverse( array_merge($addData,$entry) );

            //die( '<pre>'.print_r($syncData, true).'<pre>' );

            $syncData = apply_filters('fluentform_integration_data_'.$this->integrationKey, $syncData, $feed, $entry);

            // Now let's prepare the data and push to ActiveCampaign
            $api = $this->getApiClient();
            $response = $api->sync_contact($syncData);
            

            if (is_wp_error($response)) {
                do_action('ff_integration_action_result', $feed, 'failed', $response->get_error_message());
                return false;
            } else if ($response['result_code'] == 1) {
                do_action('ff_integration_action_result', $feed, 'success', 'Active Campaign has been successfully initialed and pushed data');
                if (ArrayHelper::get($feedData, 'note')) {
                    // Contact Added
                    $api->add_note($response['subscriber_id'], $list_id, ArrayHelper::get($feedData, 'note'));
                }
                return true;
            }
    
            do_action('ff_integration_action_result', $feed, 'failed', $response['result_message']);

        }
 
	 }


	 public function renderRepeaterResponse($response, $field, $form_id)
	 {
        // check that the response is not already an array
        // the default action returns a HTML table or CSV (as a STRING)
        if (is_array($response)) {
            return $response;
        } 

        return $this->convertTableToArray($response);	
	 }


    /**
     * Retrieve and format the $response as an associative array
     */
	protected function getRepeaterArray($response, $fields, $columns)
    {
		$repeater = [];
		
		foreach ($fields as $field => $index){

			$key = trim(ArrayHelper::get($field, '.settings.label', ' '));
			$value = ArrayHelper::get($field, $index);
			$row[$key] = $value;
			array_push($repeater, $row);
		}    

        return $repeater;
    }

    /**
     * Convert an HTML table into an array
     */
    protected function convertTableToArray($table, $return_associative = false)
    {
        $DOM = new DOMDocument();
	    $DOM->loadHTML($table);


        $header = $DOM->getElementsByTagName('th');
	    $cells = $DOM->getElementsByTagName('td');

        $keys = array();
        $rows = array();

        // Get array keys from table headings
        foreach($header as $th) 
        {
            $keys[] = trim($th->textContent);
        }

        // Get row data/detail table without header name as key
        $i = 0;
        $r = 0;
        foreach($cells as $cell) 
        {
            $rows[$r][] = trim($cell->textContent);
            $i = $i + 1;
            $r = $i % count($keys) == 0 ? $r + 1 : $r;
        } 
        

        if ($return_associative){
            // map the rows array as key and outer array index as row number
            for($i = 0; $i < count($rows); $i++)
            {
                for($r = 0; $r < count($keys); $r++)
                {
                    $rows_assoc[$i][$keys[$r]] = $rows[$i][$r];
                }
            }
            $rows = $rows_assoc; 
            unset($rows_assoc);
        }

        return $rows;
    }

    /** Parse Repeater Field Data 
     * @var Array  $feedSettings
     * @var Array  $formData
     * @return Array or False if either param is not an array
     */

    protected function parseRepeaterData($feedSettings, $formData)
     {
        $repeater_field = $this->formatInput( ArrayHelper::get($feedSettings, 'repeater_field') );
        $repeater_columns = ArrayHelper::get($feedSettings, 'repeater_field_columns');
        $repeater_data = ArrayHelper::get($formData, $repeater_field );

        // get only the label value for each column
        $repeater_columns = array_column($repeater_columns, 'label');

		return $this->mapArrayKeys( $repeater_columns, $repeater_data);
     }

     /** Clean input value
      * @var String $input
      * @return String
      */
     protected function formatInput($input){
        
        if ( empty($input) ) return '';
        
        //remove curly braces and whitespace
        $input = trim($input,'{ }');
        return str_replace('inputs.', '', $input);
     }

     protected function mapArrayKeys($cols, $rows){
        if (!is_array($cols) || !is_array($rows)) return false;

        $out = [];
        foreach ($rows as $row){
            $col = [];
            foreach ($row as $index => $value){
                $key = strval($cols[$index]);
                if ($key !== ''){
                    $col[ $key ] = $value;    
                }
            }
            array_push($out, $col);
        }
        return $out;
    }
}
