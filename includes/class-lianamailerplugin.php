<?php
/**
 * LianaMailer - WPForms plugin
 *
 * PHP Version 7.4
 *
 * @package  LianaMailer
 * @license  https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link     https://www.lianatech.com
 */

namespace WPF_LianaMailer;

/**
 * LianaMailer - WPForms plugin class
 *
 * PHP Version 7.4
 *
 * @package  LianaMailer
 * @license  https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link     https://www.lianatech.com
 */
class LianaMailerPlugin {

	/**
	 * Posted data
	 *
	 * @var post_data array
	 */
	private $post_data;

	/**
	 * LianaMailer connection object
	 *
	 * @var liana_mailer_connection object
	 */
	private static $lianamailer_connection;

	/**
	 * Site data fetched from LianaMailer
	 *
	 * @var site_data array
	 */
	private static $site_data = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		self::$lianamailer_connection = new LianaMailerConnection();
		self::add_actions();
	}

	/**
	 * Adds actions for the plugin
	 */
	public function add_actions() {

		add_action( 'init', array( $this, 'register_field' ) );

		// add LianaMailer settings tab.
		add_filter( 'wpforms_builder_settings_sections', array( $this, 'add_lianamailer_settings_tab' ), 20, 2 );
		// add content for tab above.
		add_filter( 'wpforms_form_settings_panel_content', array( $this, 'add_lianamailer_settings_tab_content' ), 20 );

		add_action( 'admin_enqueue_scripts', array( $this, 'add_lianamailer_plugin_scripts' ), 10, 1 );
		add_action( 'wp_ajax_getSiteDataForWPFormSettings', array( $this, 'get_site_data_for_settings' ), 10, 1 );

		// Filter integration settings for custom field options.
		add_filter( 'wpf_get_lianamailer_connection_status', array( $this, 'wpf_get_lianamailer_connection_status' ), 10, 1 );
		add_filter( 'wpf_get_lianamailer_site_data', array( $this, 'wpf_get_lianamailer_site_data' ), 10, 2 );
		add_filter( 'wpf_get_lianamailer_properties', array( $this, 'wpf_get_lianamailer_properties' ), 10, 2 );

		// Filter for form builder save.
		add_filter( 'wpforms_builder_save_form', array( $this, 'after_form_save' ), 20, 2 );
		// On form submission do newsleter subscription.
		add_action( 'wpforms_process', array( $this, 'do_newsletter_subscription' ), 10, 3 );
	}

	/**
	 * Fired after form save to update LianaMailer custom field consent label and possibe do the page load
	 *
	 * @param int   $form_id Form id.
	 * @param array $data Form data.
	 */
	public function after_form_save( $form_id, $data ) {
		$update_form = false;
		if ( isset( $data['fields'] ) && ! empty( $data['fields'] ) ) {
			$selected_site    = null;
			$selected_consent = null;
			$consent_label    = null;

			if ( ! isset( $data['lianamailer_settings']['lianamailer_consent'] ) || empty( $data['lianamailer_settings']['lianamailer_consent'] ) ) {
				$this->send_json_success( $form_id );
				return;
			}

			$selected_consent = intval( $data['lianamailer_settings']['lianamailer_consent'] );

			if ( isset( $data['lianamailer_settings']['lianamailer_site'] ) ) {
				$selected_site = $data['lianamailer_settings']['lianamailer_site'];
				self::get_lianamailer_site_data( $selected_site );
			}

			foreach ( $data['fields'] as $id => &$field ) {
				if ( 'lianamailer' === $field['type'] && empty( $field['choices'][0]['label'] ) ) {
					$update_form = true;

					$consent_key = array_search( $selected_consent, array_column( self::$site_data['consents'], 'consent_id' ), true );
					if ( false !== $consent_key ) {
						$consent_label = self::$site_data['consents'][ $consent_key ]['description'];
					}

					if ( $consent_label ) {
						$field['choices'][0]['label'] = $consent_label;
						$field['choices'][0]['value'] = '1';
					}
					break;
				}
			}
		}

		// Update consent text to default if it was emptied.
		if ( $update_form ) {
			$form_id = wpforms()->form->update( $data['id'], $data );
		}

		$this->send_json_success( $form_id );
	}

	/**
	 * Send success message which is handled in lianamailer-plugin.js => $builder.on( 'wpformsSaved', function( e, data ) {...});
	 * Page is reloaded if :
	 *  form is saved on LianaMailer settings page view
	 *  form is saved on form builder view and form has LianaMailer field added into form
	 *
	 * @param int $form_id WPForm id.
	 */
	private function send_json_success( $form_id ) {

		if ( wpforms_current_user_can( 'edit_form_single', $form_id ) ) {
			wp_send_json_success(
				array(
					'id'       => $form_id,
					'redirect' => add_query_arg(
						array(
							'view'    => 'fields',
							'form_id' => $form_id,
						),
						admin_url( 'admin.php?page=wpforms-builder' )
					),
				)
			);
		}
	}

	/**
	 * Filter for custom LianaMailer field to fetch LianaMailer site data.
	 *
	 * @param array $field Field data.
	 * @param array $form Form data.
	 *
	 * @return array Site data
	 */
	public function wpf_get_lianamailer_site_data( $field, $form ) {

		$selected_site = null;
		if ( isset( $form['lianamailer_settings']['lianamailer_site'] ) && $form['lianamailer_settings']['lianamailer_site'] ) {
			$selected_site = $form['lianamailer_settings']['lianamailer_site'];
			self::get_lianamailer_site_data( $selected_site );
			return self::$site_data;
		}
		return array();
	}

	/**
	 * Filter for custom LianaMailer field to fetch LianaMailer site property data.
	 *
	 * @param array $field Field data.
	 * @param array $form Form data.
	 *
	 * @return array LianaMailer site properties
	 */
	public function wpf_get_lianamailer_properties( $field, $form ) {

		$selected_site   = null;
		$site_properties = array();
		$fields          = array();
		if ( isset( $form['lianamailer_settings']['lianamailer_site'] ) && $form['lianamailer_settings']['lianamailer_site'] ) {
			$selected_site = $form['lianamailer_settings']['lianamailer_site'];
			self::get_lianamailer_site_data( $selected_site );
			if ( ! empty( self::$site_data ) ) {
				$site_properties = isset( self::$site_data['properties'] ) ? self::$site_data['properties'] : array();
				$fields          = $this->get_lianamailer_properties( true, $site_properties );
			}
		}
		return $fields;
	}

	/**
	 * Filter for custom LianaMailer field to check REST API connection status.
	 *
	 * @param array $field Field data.
	 *
	 * @return array LianaMailer site properties
	 */
	public function wpf_get_lianamailer_connection_status( $field ) {
		return ( self::$lianamailer_connection->get_status() ? true : false );
	}

	/**
	 * Adds new tab for LianaMailer
	 *
	 * @param array $sections Section data.
	 * @param array $form_data Form data.
	 *
	 * @return array $sections Modified sections.
	 */
	public function add_lianamailer_settings_tab( $sections, $form_data ) {
		$sections['lianamailer_wpforms'] = __( 'LianaMailer', 'integrate_lianamailer_wpforms' );
		return $sections;
	}

	/**
	 * Adds content for LianaMailer tab.
	 *
	 * @param object $form_instance WPForms instance.
	 *
	 * @return string $html Content as HTML.
	 */
	public function add_lianamailer_settings_tab_content( $form_instance ) {

		if ( ! is_admin() ) {
			return;
		}

		$account_sites = self::$lianamailer_connection->get_account_sites();
		if ( empty( $account_sites ) ) {
			$account_sites = array();
		}
		$disable_settings = false;
		// if LianaMailer sites could not fetch or theres no any, print error message.
		if ( empty( $account_sites ) ) {
			$disable_settings = true;
		}

		$selected_site = null;
		if ( isset( $form_instance->form_data['lianamailer_settings']['lianamailer_site'] ) ) {
			$selected_site = $form_instance->form_data['lianamailer_settings']['lianamailer_site'];
		}
		self::get_lianamailer_site_data( $selected_site );

		$site_choices         = array( '' => 'Choose' );
		$mailing_list_choices = array( '' => 'Choose' );
		$consent_list_choices = array( '' => 'Choose' );

		foreach ( $account_sites as $account_site ) {
			$site_choices[ $account_site['domain'] ] = $account_site['domain'];
		}

		if ( isset( self::$site_data['lists'] ) ) {
			foreach ( self::$site_data['lists'] as $list ) {
				$mailing_list_choices[ $list['id'] ] = $list['name'];
			}
		}

		if ( isset( self::$site_data['consents'] ) ) {
			foreach ( self::$site_data['consents'] as $consent ) {
				$consent_list_choices[ $consent['consent_id'] ] = $consent['name'];
			}
		}

		$html      = '<div class="wpforms-panel-content-section wpforms-panel-content-section-lianamailer_wpforms lianamailer_wpforms">';
			$html .= '<div class="wpforms-panel-content-section-title">' . __( 'LianaMailer settings', 'wpforms_lianamailer' ) . '</div>';

			// If settings disabled set existing values into hidden inputs to ensure those arent wiped out if saving the form.
		if ( $disable_settings ) {
			$enabled      = isset( $form_instance->form_data['lianamailer_settings']['lianamailer_enabled'] ) ? $form_instance->form_data['lianamailer_settings']['lianamailer_enabled'] : '';
			$site         = isset( $form_instance->form_data['lianamailer_settings']['lianamailer_site'] ) ? $form_instance->form_data['lianamailer_settings']['lianamailer_site'] : '';
			$mailing_list = isset( $form_instance->form_data['lianamailer_settings']['lianamailer_mailing_list'] ) ? $form_instance->form_data['lianamailer_settings']['lianamailer_mailing_list'] : '';
			$consent      = isset( $form_instance->form_data['lianamailer_settings']['lianamailer_consent'] ) ? $form_instance->form_data['lianamailer_settings']['lianamailer_consent'] : '';

			$html .= '<p class="rest-api-error">Could not find any LianaMailer sites. Ensure <a href="' . ( isset( $_SERVER['PHP_SELF'] ) ? sanitize_text_field( wp_unslash( $_SERVER['PHP_SELF'] ) ) : '' ) . '?page=lianamailerwpforms" target="_blank">API settings</a> are propertly set and LianaMailer account has at least one subscription site.</p>';
			$html .= '<input type="hidden" name="lianamailer_settings[lianamailer_enabled]" value="' . $enabled . '" />';
			$html .= '<input type="hidden" name="lianamailer_settings[lianamailer_site]" value="' . $site . '" />';
			$html .= '<input type="hidden" name="lianamailer_settings[lianamailer_mailing_list]" value="' . $mailing_list . '" />';
			$html .= '<input type="hidden" name="lianamailer_settings[lianamailer_consent]" value="' . $consent . '" />';
		} else {
			// Plugin enabled.
			$html .= wpforms_panel_field(
				'toggle',
				'lianamailer_settings',
				'lianamailer_enabled',
				$form_instance->form_data,
				__( 'Enable LianaMailer -integration on this form', 'wpforms_lianamailer' ),
				array(),
				false
			);
			// Lianamailer_enabled toggle will hide / show settings.
			$html .= '<div class="wpforms_lianamailer_settings">';
				// Site.
				$html .= wpforms_panel_field(
					'select',
					'lianamailer_settings',
					'lianamailer_site',
					$form_instance->form_data,
					__( 'Site', 'wpforms_lianamailer' ),
					array(
						'options' => $site_choices,
					),
					false
				);
				// Mailing list.
				$html .= wpforms_panel_field(
					'select',
					'lianamailer_settings',
					'lianamailer_mailing_list',
					$form_instance->form_data,
					__( 'Mailing list', 'wpforms_lianamailer' ),
					array(
						'options' => $mailing_list_choices,
					),
					false
				);
				// Consent.
				$html .= wpforms_panel_field(
					'select',
					'lianamailer_settings',
					'lianamailer_consent',
					$form_instance->form_data,
					__( 'Consent', 'wpforms_lianamailer' ),
					array(
						'options' => $consent_list_choices,
					),
					false
				);
			$html     .= '</div>';
		}

		$html .= '</div>';

		$allowed_html   = wp_kses_allowed_html( 'post' );
		$custom_allowed = array();

		$custom_allowed['input'] = array(
			'class'   => 1,
			'id'      => 1,
			'name'    => 1,
			'value'   => 1,
			'type'    => 1,
			'checked' => 1,
		);

		$custom_allowed['select'] = array(
			'class'       => 1,
			'id'          => 1,
			'name'        => 1,
			'value'       => 1,
			'type'        => 1,
			'disabled'    => 1,
			'data-handle' => 1,
			'onchange'    => 1,
		);

		$custom_allowed['option'] = array(
			'selected' => 1,
			'class'    => 1,
			'value'    => 1,
		);

		$allowed_html = array_merge( $allowed_html, $custom_allowed );
		echo wp_kses( $html, $allowed_html );
	}

	/**
	 * Register custom WPF_Field_LianaMailer field
	 */
	public function register_field() {
		require_once 'class-wpf-field-lianamailer.php';
		new WPF_Field_LianaMailer();
	}

	/**
	 * Make newsletter subscription on form submit
	 *
	 * @param array $fields Posted fields.
	 * @param array $entry Submission data.
	 * @param array $form_data Current form data. Settings etc.
	 * @throws \Exception If subscription failed.
	 *
	 * @return void
	 */
	public function do_newsletter_subscription( $fields, $entry, $form_data ) {

		$lianamailer_settings = $form_data['lianamailer_settings'];
		$is_plugin_enabled    = $lianamailer_settings['lianamailer_enabled'] ?? false;
		$list_id              = intval( $lianamailer_settings['lianamailer_mailing_list'] ) ?? null;
		$consent_id           = intval( $lianamailer_settings['lianamailer_consent'] ) ?? null;
		$selected_site        = $lianamailer_settings['lianamailer_site'] ?? null;

		// works only in public form and check if plugin is enablen on current form.
		if ( ! $is_plugin_enabled ) {
			return;
		}

		self::get_lianamailer_site_data( $selected_site );
		// If site data not found from LianaMailer.
		if ( empty( self::$site_data ) ) {
			return;
		}

		// if mailing list was saved in settings but do not exists anymore on LianaMailers subscription page, null the value.
		if ( $list_id ) {
			$key = array_search( $list_id, array_column( self::$site_data['lists'], 'id' ), true );
			// if selected list is not found anymore from LianaMailer subscription page, do not allow subscription.
			if ( false === $key ) {
				$list_id = null;
			}
		}

		if ( ! $list_id ) {
			return;
		}

		$form_fields  = $form_data['fields'];
		$property_map = $this->get_lianamailer_property_map( $form_fields );

		$lianamailer_field    = $this->get_lianamailer_field_from_form( $form_data );
		$lianamailer_field_id = $lianamailer_field['id'];

		// If LianaMailer field was not posted, bail out.
		if ( ! array_key_exists( $lianamailer_field_id, $entry['fields'] ) || empty( $entry['fields'][ $lianamailer_field_id ] ) ) {
			return;
		}

		$field_map_email = ( array_key_exists( 'email', $property_map ) && ! empty( $property_map['email'] ) ? intval( $property_map['email'] ) : null );
		$field_map_sms   = ( array_key_exists( 'sms', $property_map ) && ! empty( $property_map['sms'] ) ? intval( $property_map['sms'] ) : null );

		$email       = null;
		$sms         = null;
		$recipient   = null;
		$posted_data = array();

		/**
		 * Loop fields from entry
		 * Search mapped email and SMS fields
		 */
		$entry_fields = $entry['fields'];
		foreach ( $entry_fields as $field_id => $value ) {

			// Checkbox values are in array.
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}

			$posted_data[ $field_id ] = $value;

			if ( $field_id === $field_map_email ) {
				$email = $value;
			}
			if ( $field_id === $field_map_sms ) {
				$sms = $value;
			}
		}

		if ( empty( $email ) && empty( $sms ) ) {
			return;
		}

		$this->post_data = $posted_data;

		try {

			$subscribe_by_email = false;
			$subscribe_by_sms   = false;
			if ( $email ) {
				$subscribe_by_email = true;
			} elseif ( $sms ) {
				$subscribe_by_sms = true;
			}

			if ( $subscribe_by_email || $subscribe_by_sms ) {

				$customer_settings = self::$lianamailer_connection->get_lianamailer_customer();
				/**
				 * Autoconfirm subscription if:
				 * LM site has "registration_needs_confirmation" disabled
				 * email set
				 * LM site has welcome mail set
				 */
				$auto_confirm = ( empty( $customer_settings['registration_needs_confirmation'] ) || ! $email || ! self::$site_data['welcome'] );

				$properties = $this->filter_recipient_properties( $property_map );
				self::$lianamailer_connection->set_properties( $properties );

				if ( $subscribe_by_email ) {
					$recipient = self::$lianamailer_connection->get_recipient_by_email( $email );
				} else {
					$recipient = self::$lianamailer_connection->get_recipient_by_sms( $sms );
				}

				// if recipient found from LM and it not enabled and subscription had email set, re-enable it.
				if ( ! is_null( $recipient ) && isset( $recipient['recipient']['enabled'] ) && false === $recipient['recipient']['enabled'] && $email ) {
					self::$lianamailer_connection->reactivate_recipient( $email, $auto_confirm );
				}
				self::$lianamailer_connection->create_and_join_recipient( $recipient, $email, $sms, $list_id, $auto_confirm );

				$consent_key = array_search( $consent_id, array_column( self::$site_data['consents'], 'consent_id' ), true );
				if ( false !== $consent_key ) {
					$consent_data = self::$site_data['consents'][ $consent_key ];
					// Add consent to recipient.
					self::$lianamailer_connection->add_recipient_consent( $consent_data );
				}

				// if not existing recipient or recipient was not confirmed and site is using welcome -mail and LM account has double opt-in enabled and email address set.
				if ( ( ! $recipient || ! $recipient['recipient']['confirmed'] ) && self::$site_data['welcome'] && $customer_settings['registration_needs_confirmation'] && $email ) {
					self::$lianamailer_connection->send_welcome_mail( self::$site_data['domain'] );
				}
			}
		} catch ( \Exception $e ) {
			$failure_reason = $e->getMessage();
		}
	}

	/**
	 * Gets LianaMailer field object from form fields.
	 *
	 * @param object $form_data Form object.
	 *
	 * @return object $lianamailer_field LianaMailer field object.
	 */
	private function get_lianamailer_field_from_form( $form_data ) {

		if ( ! isset( $form_data['fields'] ) ) {
			return array();
		}

		$fields            = $form_data['fields'];
		$lianamailer_field = array();
		// Fetch all non LianaMailer properties for settings.
		foreach ( $fields as $field ) {
			if ( 'lianamailer' !== $field['type'] ) {
				continue;
			}
			$lianamailer_field = $field;
		}

		return $lianamailer_field;
	}

	/**
	 * Fetch custom LianaMailer field property map
	 *
	 * @param array $fields Form fields.
	 *
	 * @return array $property_map LianaMailer field property settings
	 */
	private function get_lianamailer_property_map( $fields ) {
		$property_map = array();
		foreach ( $fields as $field ) {
			if ( 'lianamailer' === $field['type'] && isset( $field['lianamailer_properties'] ) ) {
				$property_map = $field['lianamailer_properties'];
				break;
			}
		}

		return $property_map;
	}

	/**
	 * Filters properties which not found from LianaMailer site
	 *
	 * @param array $property_map Property map for LianaMailer field.
	 *
	 * @return array $props Array of LianaMailer site properties with values matched for posted data.
	 */
	private function filter_recipient_properties( $property_map = array() ) {

		$properties = $this->get_lianamailer_properties( false, self::$site_data['properties'] );

		$props = array();
		foreach ( $properties as $property ) {
			$property_name   = $property['name'];
			$property_handle = $property['handle'];
			$field_id        = ( isset( $property_map[ $property_handle ] ) ? $property_map[ $property_handle ] : null );

			// if Property value havent been posted, leave it as it is.
			if ( ! isset( $this->post_data[ $field_id ] ) ) {
				continue;
			}
			// Otherwise update it into LianaMailer property name matched with posted data.
			$props[ $property_name ] = sanitize_text_field( $this->post_data[ $field_id ] );
		}
		return $props;
	}

	/**
	 * Generates array of LianaMailer properties
	 *
	 * @param boolean $core_fields Should we fetch LianaMailer core fields also. Defaults to false.
	 * @param array   $properties LianaMailer site property data as array.
	 *
	 * @return array $properties
	 */
	private function get_lianamailer_properties( $core_fields = false, $properties = array() ) {
		$fields = array();
		// Append Email and SMS fields.
		if ( $core_fields ) {
			$fields[] = array(
				'name'     => 'email',
				'required' => true,
				'type'     => 'text',
			);
			$fields[] = array(
				'name'     => 'sms',
				'required' => false,
				'type'     => 'text',
			);
		}

		if ( ! empty( $properties ) ) {
			$properties = array_map(
				function( $field ) {
					return array(
						'name'     => $field['name'],
						'handle'   => $field['handle'],
						'required' => $field['required'],
						'type'     => $field['type'],
					);
				},
				$properties
			);

			$fields = array_merge( $fields, $properties );
		}

		return $fields;

	}

	/**
	 * AJAX callback for fetching lists and consents for specific LianaMailer site
	 */
	public function get_site_data_for_settings() {

		$account_sites = self::$lianamailer_connection->get_account_sites();

		if ( ! isset( $_POST['site'] ) || ! sanitize_text_field( wp_unslash( $_POST['site'] ) ) ) {
			wp_die();
		}

		$selected_site = ( isset( $_POST['site'] ) ? sanitize_text_field( wp_unslash( $_POST['site'] ) ) : null );
		$action        = __FUNCTION__ . '-' . $selected_site;
		$nonce         = sanitize_key( wp_create_nonce( $action ) );
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die();
		}

		$data = array();
		foreach ( $account_sites as &$site ) {
			if ( $selected_site === $site['domain'] ) {
				$data['lists']    = $site['lists'];
				$data['consents'] = ( self::$lianamailer_connection->get_site_consents( $site['domain'] ) ?? array() );
				break;
			}
		}
		echo wp_json_encode( $data );
		wp_die();
	}

	/**
	 * Enqueue plugin CSS and JS
	 */
	public function add_lianamailer_plugin_scripts() {
		wp_enqueue_style( 'lianamailer-wpf-admin-css', dirname( plugin_dir_url( __FILE__ ) ) . '/css/admin.css', array(), LMWP_FORMS_VERSION );

		$js_vars = array(
			'url' => admin_url( 'admin-ajax.php' ),
		);
		wp_register_script( 'lianamailer-wpf-plugin', dirname( plugin_dir_url( __FILE__ ) ) . '/js/lianamailer-plugin.js', array( 'jquery' ), LMWP_FORMS_VERSION, false );
		wp_localize_script( 'lianamailer-wpf-plugin', 'lianaMailerConnection', $js_vars );
		wp_enqueue_script( 'lianamailer-wpf-plugin' );
	}

	/**
	 * Get selected LianaMailer site data:
	 * domain, welcome, properties, lists and consents
	 *
	 * @param string $selected_site Selected site domain.
	 */
	private static function get_lianamailer_site_data( $selected_site = null ) {

		if ( ! empty( self::$site_data ) ) {
			return;
		}

		// if site is not selected.
		if ( ! $selected_site ) {
			return;
		}

		// Getting all sites from LianaMailer.
		$account_sites = self::$lianamailer_connection->get_account_sites();
		if ( empty( $account_sites ) ) {
			return;
		}

		// Getting all properties from LianaMailer.
		$lianamailer_properties = self::$lianamailer_connection->get_lianamailer_properties();

		$site_data = array();
		foreach ( $account_sites as &$site ) {
			if ( $site['domain'] === $selected_site ) {
				$properties    = array();
				$site_consents = ( self::$lianamailer_connection->get_site_consents( $site['domain'] ) ?? array() );

				$site_data['domain']  = $site['domain'];
				$site_data['welcome'] = $site['welcome'];
				foreach ( $site['properties'] as &$prop ) {
					/**
					 * Add required and type -attributes because get_account_sites() -endpoint doesnt return these.
					 * https://rest.lianamailer.com/docs/#tag/Sites/paths/~1v1~1sites/post
					 */
					$key = array_search( $prop['handle'], array_column( $lianamailer_properties, 'handle' ), true );
					if ( false !== $key ) {
						$prop['required'] = $lianamailer_properties[ $key ]['required'];
						$prop['type']     = $lianamailer_properties[ $key ]['type'];
					}
				}
				$site_data['properties'] = $site['properties'];
				$site_data['lists']      = $site['lists'];
				$site_data['consents']   = $site_consents;
				self::$site_data         = $site_data;
			}
		}
	}
}
