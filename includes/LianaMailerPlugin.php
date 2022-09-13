<?php

namespace WPForms_LianaMailer;

class LianaMailerPlugin {

	private $post_data;

	private static $lianaMailerConnection;
	private static $site_data = [];


	public function __construct() {
		self::$lianaMailerConnection = new LianaMailerConnection();
		self::addActions();
	}

	public function addActions() {

		add_action( 'init', [$this, 'registerField'] );

		// add LianaMailer settings tab
		add_filter( 'wpforms_builder_settings_sections', [$this, 'addLianaMailerSettingsTab'], 20, 2 );
		// add content for tab above
		add_filter( 'wpforms_form_settings_panel_content', [$this, 'addLianaMailerSettingsTabContent'], 20 );

		add_action( 'admin_enqueue_scripts', [ $this, 'addLianaMailerPluginScripts' ], 10, 1 );
		add_action( 'wp_ajax_getSiteDataForWPFormSettings', [ $this, 'getSiteDataForSettings'], 10, 1);


		// Filter integration settings for custom field options
		add_filter( 'wpform_get_lianamailer_connection_status', [$this, 'wpform_get_lianamailer_connection_status'], 10, 1);
		add_filter( 'wpform_get_lianamailer_site_data', [$this, 'wpform_get_lianamailer_site_data'], 10, 2);
		add_filter( 'wpform_get_lianamailer_properties', [$this, 'wpform_get_lianamailer_properties'], 10, 2);

		// Filter for form builder save
		add_filter( 'wpforms_builder_save_form', [$this, 'afterFormSave'], 20, 2);
		// On form submission do newsleter subscription
		add_action( 'wpforms_process', [$this, 'doNewsletterSubscription'], 10, 3 );
	}

	/**
	 * add_filter( 'wpforms_builder_save_form', [$this, 'afterFormSave'], 10, 2);
	 * Fired after form save to update LianaMailer custom field consent label and possibe do the page load
	 */
	public function afterFormSave($form_id, $data) {

		$updateForm = false;
		if(isset($data['fields']) && !empty($data['fields'])) {
			$selectedSite = $selectedConsent = $consentLabel = null;

			if(!isset($data['lianamailer_settings']['lianamailer_consent']) || empty($data['lianamailer_settings']['lianamailer_consent'])) {
				$this->sendJSONSuccess($form_id);
				return;
			}

			$selectedConsent = $data['lianamailer_settings']['lianamailer_consent'];

			if(isset($data['lianamailer_settings']['lianamailer_site'])) {
				$selectedSite = $data['lianamailer_settings']['lianamailer_site'];
				self::getLianaMailerSiteData($selectedSite);
			}

			foreach($data['fields'] as $id => &$field) {
				if($field['type'] == 'lianamailer' && empty($field['choices'][0]['label'])) {
					$updateForm = true;

					$consentKey = array_search($selectedConsent, array_column(self::$site_data['consents'], 'consent_id'));
					if($consentKey !== false) {
						$consentLabel = self::$site_data['consents'][$consentKey]['description'];
					}

					if($consentLabel) {
						$field['choices'][0]['label'] = $consentLabel;
					}
					break;
				}
			}
		}

		// Update consent text to default if it was emptied.
		if($updateForm) {
			$form_id = wpforms()->form->update( $data['id'], $data );
		}

		$this->sendJSONSuccess($form_id);
	}

	/**
	 * Send success message which is handled in lianamailer-plugin.js => $builder.on( 'wpformsSaved', function( e, data ) {...});
	 * Page is reloaded if :
	 *	form is saved on LianaMailer settings page view
	 *	form is saved on form builder view and form has LianaMailer field added into form
	 */
	private function sendJSONSuccess($form_id) {

		if ( wpforms_current_user_can( 'edit_form_single', $form_id ) ) {
			wp_send_json_success(
				[
					'id'       => $form_id,
					'redirect' => add_query_arg(
						[
							'view'    => 'fields',
							'form_id' => $form_id,
						],
						admin_url( 'admin.php?page=wpforms-builder' )
					),
				]
			);
		}
	}

	public function wpform_get_lianamailer_site_data($field, $form) {

		$selectedSite = null;
		if(isset($form['lianamailer_settings']['lianamailer_site']) && $form['lianamailer_settings']['lianamailer_site']) {
			$selectedSite = $form['lianamailer_settings']['lianamailer_site'];
			self::getLianaMailerSiteData($selectedSite);
			return self::$site_data;
		}
		return;
	}

	public function wpform_get_lianamailer_properties($field, $form) {

		$selectedSite =		null;
		$site_properties =	[];
		$fields = 			[];
		if(isset($form['lianamailer_settings']['lianamailer_site']) && $form['lianamailer_settings']['lianamailer_site']) {
			$selectedSite = $form['lianamailer_settings']['lianamailer_site'];
			self::getLianaMailerSiteData($selectedSite);
			if(!empty(self::$site_data)) {
				$site_properties = isset(self::$site_data['properties']) ? self::$site_data['properties'] : [];
				$fields = $this->getLianaMailerProperties(true, $site_properties);
			}
		}
		return $fields;
	}

	public function wpform_get_lianamailer_connection_status($field) {
		return self::$lianaMailerConnection->getStatus();
	}

	public function addLianaMailerSettingsTab($sections, $form_data) {
		$sections['lianamailer_wpforms'] = __( 'LianaMailer', 'integrate_lianamailer_wpforms' );
		return $sections;
	}

	public function addLianaMailerSettingsTabContent($form_instance) {

		if(!is_admin()) {
			return;
		}

		$accountSites = self::$lianaMailerConnection->getAccountSites();
		$disableSettings = false;
		// if LianaMailer sites could not fetch or theres no any, print error message
		if(empty($accountSites)) {
			$disableSettings = true;
		}

		$selectedSite = null;
		if(isset($form_instance->form_data['lianamailer_settings']['lianamailer_site'])) {
			$selectedSite = $form_instance->form_data['lianamailer_settings']['lianamailer_site'];
		}
		self::getLianaMailerSiteData($selectedSite);

		$siteChoices = $mailingListChoices = $consentListChoices = ['' => 'Choose'];
		foreach($accountSites as $accountSite) {
			$siteChoices[$accountSite['domain']] = $accountSite['domain'];
		}

		if(isset(self::$site_data['lists'])) {
			foreach(self::$site_data['lists'] as $list) {
				$mailingListChoices[$list['id']] = $list['name'];
			}
		}

		if(isset(self::$site_data['consents'])) {
			foreach(self::$site_data['consents'] as $consent) {
				$consentListChoices[$consent['consent_id']] = $consent['name'];
			}
		}

		$html = '<div class="wpforms-panel-content-section wpforms-panel-content-section-lianamailer_wpforms lianamailer_wpforms">';
			$html .= '<div class="wpforms-panel-content-section-title">' . __( 'LianaMailer settings', 'wpforms_lianamailer' ) . '</div>';

			// If settings disabled set existing values into hidden inputs to ensure those arent wiped out if saving the form
			if($disableSettings) {
				$enabled		= isset($form_instance->form_data['lianamailer_settings']['lianamailer_enabled']) ? $form_instance->form_data['lianamailer_settings']['lianamailer_enabled'] : '';
				$site			= isset($form_instance->form_data['lianamailer_settings']['lianamailer_site']) ? $form_instance->form_data['lianamailer_settings']['lianamailer_site'] : '';
				$mailingList	= isset($form_instance->form_data['lianamailer_settings']['lianamailer_mailing_list']) ? $form_instance->form_data['lianamailer_settings']['lianamailer_mailing_list'] : '';
				$consent		= isset($form_instance->form_data['lianamailer_settings']['lianamailer_consent']) ? $form_instance->form_data['lianamailer_settings']['lianamailer_consent'] : '';

				$html .= '<p class="rest-api-error">Could not find any LianaMailer sites. Ensure <a href="'.$_SERVER['PHP_SELF'].'?page=lianamailerwpforms" target="_blank">API settings</a> are propertly set and LianaMailer account has at least one subscription site.</p>';
				$html .= '<input type="hidden" name="lianamailer_settings[lianamailer_enabled]" value="'.$enabled.'" />';
				$html .= '<input type="hidden" name="lianamailer_settings[lianamailer_site]" value="'.$site.'" />';
				$html .= '<input type="hidden" name="lianamailer_settings[lianamailer_mailing_list]" value="'.$mailingList.'" />';
				$html .= '<input type="hidden" name="lianamailer_settings[lianamailer_consent]" value="'.$consent.'" />';
			}
			else {
				// Plugin enabled
				$html .=  wpforms_panel_field(
					'toggle',
					'lianamailer_settings',
					'lianamailer_enabled',
					$form_instance->form_data,
					__( 'Enable LianaMailer -integration on this form', 'wpforms_lianamailer' ),
					[],
					false
				);
				// lianamailer_enabled toggle will hide / show settings
				$html .= '<div class="wpforms_lianamailer_settings">';
					// Site
					$html .=  wpforms_panel_field(
						'select',
						'lianamailer_settings',
						'lianamailer_site',
						$form_instance->form_data,
						__( 'Site', 'wpforms_lianamailer' ),
						[
							//'field_map'   => array( 'text', 'name' ),
							//'placeholder' => __( 'Choose', 'wpforms_lianamailer' ),
							'options' => $siteChoices,
						],
						false
					);
					// Mailing list
					$html .=  wpforms_panel_field(
						'select',
						'lianamailer_settings',
						'lianamailer_mailing_list',
						$form_instance->form_data,
						__( 'Mailing list', 'wpforms_lianamailer' ),
						[
							//'placeholder' => __( 'Choose', 'wpforms_lianamailer' ),
							'options'   => $mailingListChoices,
						],
						false
					);
					// Consent
					$html .=  wpforms_panel_field(
						'select',
						'lianamailer_settings',
						'lianamailer_consent',
						$form_instance->form_data,
						__( 'Consent', 'wpforms_lianamailer' ),
						[
							//'placeholder' => __( 'Choose', 'wpforms_lianamailer' ),
							'options'   => $consentListChoices,
						],
						false
					);
				$html .= '</div>';
			}

		$html .= '</div>';

		echo $html;
	}

	/**
	 * Register custom WPForms_Field_LianaMailer field
	 */
	public function registerField() {
		require_once('class-field.php');
		new WPForms_Field_LianaMailer();
	}

	/**
	 * add_action( 'wpforms_process_complete', [$this, 'doNewsletterSubscription'], 10, 4 );
	 * Make newsletter subscription
	 */
	public function doNewsletterSubscription( $fields, $entry, $form_data) {

		$lianaMailerSettings	= $form_data['lianamailer_settings'];
		$isPluginEnabled		= $lianaMailerSettings['lianamailer_enabled'] ?? false;
		$list_id				= $lianaMailerSettings['lianamailer_mailing_list'] ?? null;
		$consent_id				= $lianaMailerSettings['lianamailer_consent'] ?? null;
		$selectedSite			= $lianaMailerSettings['lianamailer_site'] ?? null;

		// works only in public form and check if plugin is enablen on current form
		if( !$isPluginEnabled) {
			return;
		}

		$this->getLianaMailerSiteData($selectedSite);
		if(empty(self::$site_data)) {
			return;
		}

		// if mailing list was saved in settings but do not exists anymore on LianaMailers subscription page, null the value
		if($list_id) {
			$key = array_search($list_id, array_column(self::$site_data['lists'], 'id'));
			// if selected list is not found anymore from LianaMailer subscription page, do not allow subscription
			if($key === false) {
				$list_id = null;
			}
		}

		if(!$list_id) {
			error_log('No mailing lists set');
			return;
		}

		$formFields		= $form_data['fields'];
		$property_map	= $this->getLianaMailerPropertyMap($formFields);

		$fieldMapEmail	= (array_key_exists('email', $property_map) && !empty($property_map['email']) ? $property_map['email'] : null);
		$fieldMapSMS	= (array_key_exists('sms', $property_map) && !empty($property_map['sms']) ? $property_map['sms'] : null);

		$email = $sms = $recipient = null;
		$postedData = [];
		/**
		 * Loop posted fields
		 * Search mapped email and SMS fields
		 */
		foreach ( $fields as $field ) {
			$value = $field['value'];
			$postedData[$field['id']] = $value;

			if($field['id'] == $fieldMapEmail) {
				$email = $value;
			}
			if($field['id'] == $fieldMapSMS) {
				$sms = $value;
			}
		}

		if(empty($email) && empty($sms)) {
			error_log('No email or SMS -field set');
			return;
		}

		$this->post_data = $postedData;

		try {

			$subscribeByEmail	= false;
			$subscribeBySMS 	= false;
			if($email) {
				$subscribeByEmail = true;
			}
			else if($sms) {
				$subscribeBySMS = true;
			}

			if( $subscribeByEmail ||  $subscribeBySMS ) {

				$customerSettings = self::$lianaMailerConnection->getMailerCustomer();
				// autoconfirm subscription if:
				// * LM site has "registration_needs_confirmation" disabled
				// * email set
				// * LM site has welcome mail set
				$autoConfirm = (empty($customerSettings['registration_needs_confirmation']) || !$email || !self::$site_data['welcome']);

				$properties = $this->filterRecipientProperties($property_map);
				self::$lianaMailerConnection->setProperties($properties);

				if($subscribeByEmail) {
					$recipient = self::$lianaMailerConnection->getRecipientByEmail($email);
				}
				else {
					$recipient = self::$lianaMailerConnection->getRecipientBySMS($sms);
				}

				// if recipient found from LM and it not enabled and subscription had email set, re-enable it
				if (!is_null($recipient) && isset($recipient['recipient']['enabled']) && $recipient['recipient']['enabled'] === false && $email) {
					self::$lianaMailerConnection->reactivateRecipient($email, $autoConfirm);
				}
				self::$lianaMailerConnection->createAndJoinRecipient($email, $sms, $list_id, $autoConfirm);

				$consentKey = array_search($consent_id, array_column(self::$site_data['consents'], 'consent_id'));
				if($consentKey !== false) {
					$consentData = self::$site_data['consents'][$consentKey];
					//  Add consent to recipient
					self::$lianaMailerConnection->addRecipientConsent($consentData);
				}

				// if not existing recipient or recipient was not confirmed and site is using welcome -mail and LM account has double opt-in enabled and email address set
				if((!$recipient || !$recipient['recipient']['confirmed']) && self::$site_data['welcome'] && $customerSettings['registration_needs_confirmation'] && $email) {
					self::$lianaMailerConnection->sendWelcomeMail(self::$site_data['domain']);
				}

			}
		}
		catch(\Exception $e) {
			$failure_reason = $e->getMessage();
			error_log('Failure: '.$failure_reason);
		}
		return;
	}

	/**
	 * Fetch custom LianaMailer field property map
	 * @return array property map
	 */
	private function getLianaMailerPropertyMap($fields) {
		$lianaMailerField = null;
		$property_map = [];
		foreach($fields as $field) {
			if($field['type'] == 'lianamailer' && isset($field['lianamailer_properties'])) {
				$property_map = $field['lianamailer_properties'];
				break;
			}
		}

		return $property_map;
	}

	/**
	 * Filters properties which not found from LianaMailer site
	 */
	private function filterRecipientProperties($property_map = []) {

		$properties = $this->getLianaMailerProperties(false, self::$site_data['properties']);

		$props = [];
		foreach($properties as $property) {
			$propertyName = $property['name'];
			$propertyHandle = $property['handle'];
			$field_id = (isset($property_map[$propertyHandle]) ? $property_map[$propertyHandle] : null);

			// if Property value havent been posted, leave it as it is
			if( !isset( $this->post_data[$field_id] ) ) {
				continue;
			}
			// otherwise update it into LianaMailer
			$props[$propertyName] = sanitize_text_field( $this->post_data[$field_id] );
		}
		return $props;
	}

	/**
	 * Generates array of LianaMailer properties
	 */
	private function getLianaMailerProperties($core_fields = false, $properties = []) {
		$fields = [];
		// append Email and SMS fields
		if($core_fields) {
			$fields[] = [
				'name'         => 'email',
				'required'     => true,
				'type'         => 'text'
			];
			$fields[] = [
				'name'         => 'sms',
				'required'     => false,
				'type'         => 'text'
			];
		}

		if( !empty( $properties ) ) {
			$properties = array_map( function( $field ){
				return [
					'name'			=> $field[ 'name' ],
					'handle'		=> $field['handle'],
					'required'		=> $field[ 'required' ],
					'type'			=> $field[ 'type' ]
				];
			}, $properties );

			$fields = array_merge($fields, $properties);
		}

		return $fields;

	}

	/**
	 * AJAX callback for fetching lists and consents for specific LianaMailer site
	 */
	public function getSiteDataForSettings() {

		$accountSites = self::$lianaMailerConnection->getAccountSites();
		$selectedSite = $_POST['site'];

		$data = [];
		foreach($accountSites as &$site) {
			if($site['domain'] == $selectedSite) {
				$data['lists'] = $site['lists'];
				$data['consents'] = (self::$lianaMailerConnection->getSiteConsents($site['domain']) ?? []);
				break;
			}
		}
		echo json_encode($data);
		wp_die();
	}

	/**
	 * Enqueue plugin CSS and JS
	 * add_action( 'admin_enqueue_scripts', [ $this, 'addLianaMailerPluginScripts' ], 10, 1 );
	 */
	public function addLianaMailerPluginScripts() {
		wp_enqueue_style('lianamailer-wpforms-admin-css', dirname( plugin_dir_url( __FILE__ ) ).'/css/admin.css');

		$js_vars = [
			'url' => admin_url( 'admin-ajax.php' )
		];
		wp_register_script('lianamailer-wpforms-plugin',  dirname( plugin_dir_url( __FILE__ ) ) . '/js/lianamailer-plugin.js', [ 'jquery' ], false, false );
		wp_localize_script('lianamailer-wpforms-plugin', 'lianaMailerConnection', $js_vars );
		wp_enqueue_script('lianamailer-wpforms-plugin','',[],false,true);
	}

	/**
	 * Get selected LianaMailer site data:
	 * domain, welcome, properties, lists and consents
	 */
	private static function getLianaMailerSiteData($selectedSite = null) {

		if(!empty(self::$site_data)) {
			return;
		}

		// if site is not selected
		if(!$selectedSite) {
			return;
		}

		// Getting all sites from LianaMailer
		$accountSites = self::$lianaMailerConnection->getAccountSites();
		if(empty($accountSites)) {
			return;
		}

		// Getting all properties from LianaMailer
		$lianaMailerProperties = self::$lianaMailerConnection->getLianaMailerProperties();

		$siteData = [];
		foreach($accountSites as &$site) {
			if($site['domain'] == $selectedSite) {
				$properties = [];
				$siteConsents = (self::$lianaMailerConnection->getSiteConsents($site['domain']) ?? []);

				$siteData['domain'] = $site['domain'];
				$siteData['welcome'] = $site['welcome'];
				foreach($site['properties'] as &$prop) {
					// Add required and type -attributes because getAccountSites() -endpoint doesnt return these
					// https://rest.lianamailer.com/docs/#tag/Sites/paths/~1v1~1sites/post
					$key = array_search($prop['handle'], array_column($lianaMailerProperties, 'handle'));
					if($key !== false) {
						$prop['required'] = $lianaMailerProperties[$key]['required'];
						$prop['type'] = $lianaMailerProperties[$key]['type'];
					}
				}
				$siteData['properties'] = $site['properties'];
				$siteData['lists'] = $site['lists'];
				$siteData['consents'] = $siteConsents;
				self::$site_data = $siteData;
			}
		}
	}
}
?>
