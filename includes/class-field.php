<?php

namespace WPForms_LianaMailer;

/**
 * Checkbox field.
 *
 * @package    WPForms
 * @author     WPForms
 * @since      1.0.0
 * @license    GPL-2.0+
 * @copyright  Copyright (c) 2016, WPForms LLC
 */
class WPForms_Field_LianaMailer extends \WPForms_Field {

	private $form_instance;
	private $LMproperties = [];
	private $site_data = [];
	private $is_connection_valid = true;

	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		// Define field type information
		$this->name     = 'LianaMailer';
		$this->type     = 'lianamailer';
		$this->icon     = 'lianamailer';
		$this->order    = 999;

		$consentDescription = '';
		if(is_admin()) {
			$form_id = $consentDescription = null;
			// loading form builder
			if(isset($_GET['form_id'])) {
				$form_id = absint($_GET['form_id']);
			}
			// when adding new field
			if(isset($_POST['action']) && $_POST['action'] == 'wpforms_new_field_lianamailer' && isset($_POST['id'])) {
				$form_id = absint($_POST['id']);
			}

			if($form_id) {
				$form = wpforms()->form->get( $form_id );

				if ( is_object( $form ) ) {
					$form = wpforms_decode( $form->post_content );

					$this->form_instance		= $form;
					$this->is_connection_valid	= apply_filters('wpform_get_lianamailer_connection_status', $this);
					$this->site_data			= apply_filters('wpform_get_lianamailer_site_data', $this, $form);
					$this->LMproperties			= apply_filters('wpform_get_lianamailer_properties', $this, $form);
				}

				if(!empty($this->site_data)) {
					$consent_id = $form['lianamailer_settings']['lianamailer_consent'] ?? null;

					$consentKey = array_search($consent_id, array_column($this->site_data['consents'], 'consent_id'));
					if($consentKey !== false) {
						$consentData = $this->site_data['consents'][$consentKey];
						$consentDescription = $consentData['description'];
					}
				}
			}
			$this->defaults = array(
				0 => array(
					'label'		=> $consentDescription,
					'value'		=> '1',
					'default'	=> ''
				),
			);
		}

		// Set field to default to required.
		add_filter( 'wpforms_field_new_required', array( $this, 'field_default_required' ), 10, 2 );

		// Define additional field properties.
		add_filter( 'wpforms_field_properties_lianamailer', array( $this, 'field_properties' ), 10, 3 );
	}

	private function hasSettingsError($form_data) {
		$isPluginEnabled	= $form_data['lianamailer_settings']['lianamailer_enabled'] ?? false;
		$isMailingListSet	= $form_data['lianamailer_settings']['lianamailer_mailing_list'] ?? false;
		$isConsentSet 		= $form_data['lianamailer_settings']['lianamailer_consent'] ?? false;

		if(!$isPluginEnabled || !$isMailingListSet || !$isConsentSet || !$this->is_connection_valid) {
			return true;
		}
		return false;
	}

	/**
	 * Define additional field properties.
	 *
	 * @since 1.4.5
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Field settings.
	 * @param array $form_data  Form data and settings.
	 *
	 * @return array
	 */
	public function field_properties( $properties, $field, $form_data ) {

		//  if consent is not selected on form settings, hide consent field from public form
		if($this->hasSettingsError($form_data)) {
			$properties['container']['attr']['style'] = 'display:none';
		}

		return $properties;
	}

	/**
	 * Field should default to being required.
	 *
	 * @since 1.4.6
	 *
	 * @param bool  $required Required status, true is required.
	 * @param array $field    Field settings.
	 *
	 * @return bool
	 */
	public function field_default_required( $required, $field ) {

		if ( $this->type === $field['type'] ) {
			return true;
		}

		return $required;
	}

	/**
	 * @inheritdoc
	 */
	public function is_dynamic_population_allowed( $properties, $field ) {
		return false;
	}


	/**
	 * Field options panel inside the builder.
	 *
	 * @since 1.0.0
	 * @param array $field
	 */
	public function field_options( $field ) {

		//--------------------------------------------------------------------//
		// Basic field options
		//--------------------------------------------------------------------//

		// Field is always required.
		$this->field_element(
			'text',
			$field,
			array(
				'type'  => 'hidden',
				'slug'  => 'required',
				'value' => '1',
			)
		);

		// Options open markup
		$this->field_option( 'basic-options', $field, array( 'markup' => 'open' ) );
		// if LianaMailer site data couldnt be fetched. Problem on API credentials or API itself
		// Print previously saved mappings as hidden inputs if theres any, so theyre not get lost when saving the form
		if(empty($this->site_data) || !$this->is_connection_valid) {

			if(!$this->is_connection_valid) {
				echo '<div class="lianamailer-error rest-api-error"><p>REST API error. Ensure <a href="'.$_SERVER['PHP_SELF'].'?page=lianamailerwpforms" target="_blank">API settings</a> are propertly set</p></div>';
			}
			else if(empty($this->site_data)) {
				echo '<div class="lianamailer-error rest-api-error"><p>LianaMailer site is not selected. Check settings</p></div>';
			}

			$fields = $this->form_instance['fields'];
			$lianaMailerField = null;
			// Fetch LianaMailer field for settings
			foreach($fields as $key => $singleField) {
				if($singleField['type'] != 'lianamailer' || !empty($lianaMailerField)) {
					continue;
				}
				$lianaMailerField = $singleField;
			}
			if($lianaMailerField) {
				$fieldID = $lianaMailerField['id'];
				// Print property mappings
				foreach($lianaMailerField['lianamailer_properties'] as $lmField => $formField) {
					echo '<input type="hidden" name="fields['.$fieldID.'][lianamailer_properties]['.$lmField.']" value="'.$formField.'" />';
				}
				// Print consent label, value and default value
				foreach($lianaMailerField['choices'] as $key => $choiceData) {
					echo '<input type="hidden" name="fields['.$fieldID.'][choices]['.$key.'][label]" value="'.htmlspecialchars($choiceData['label']).'" />';
					echo '<input type="hidden" name="fields['.$fieldID.'][choices]['.$key.'][value]" value="'.$choiceData['value'].'" />';
					echo '<input type="hidden" name="fields['.$fieldID.'][choices]['.$key.'][default]" value="'.$choiceData['default'].'" />';
				}
			}
		}
		else {
			// LianaMailer properties
			$this->field_option_lianamailer_properties( $field );

			// Choices
			$this->field_option_choices( $field );
		}

		// Description
		$this->field_option( 'description', $field );

		// Required toggle
		//$this->field_option( 'required', $field, ['default' => '1']);

		// Options close markup
		$this->field_option( 'basic-options', $field, array( 'markup' => 'close' ) );

		//--------------------------------------------------------------------//
		// Advanced field options
		//--------------------------------------------------------------------//

		// Options open markup
		$this->field_option( 'advanced-options', $field, array( 'markup' => 'open' ) );

		// Custom CSS classes
		$this->field_option( 'css', $field );

		// Options close markup
		$this->field_option( 'advanced-options', $field, array( 'markup' => 'close' ) );
	}

	private function field_option_lianamailer_properties( $field ) {

		// Field option label
		$tooltip      = __( 'Map WPForm fields into LianaMailer properties.', 'wpforms_lianamailer' );
		$option_label = $this->field_element(
			'label',
			$field,
			array(
				'slug'    => 'lianamailer-properties',
				'value'   => __( 'LianaMailer properties', 'wpforms_lianamailer' ),
				'tooltip' => $tooltip,
			),
			false
		);

		$fields = $this->form_instance['fields'];

		$formFields = [];
		// Fetch all non LianaMailer fields for settings
		foreach($fields as $key => $singleField) {
			if(!isset($singleField['label']) || $singleField['type'] == 'lianamailer') {
				continue;
			}
			$formFields[] = $singleField;
		}

		$values  = ! empty( $field['choices'] ) ? $field['choices'] : $this->defaults;
		$field['choices'][0] = $values;


		$html = '';
		if(empty($this->LMproperties)) {
			$html .= '<div class="lianamailer-error no-properties-found">No LianaMailer properties found</div>';
		}
		foreach($this->LMproperties as $property) {
			$html .= '<div class="property">';

				$html .= '<label for="field_lianamailer_property" class="section_label">';
					$html .= '<b>'.$property['name'].(isset($property['handle']) && is_int($property['handle']) ? ' (#'.$property['handle'].')' : '').'</b>';
				$html .= '</label>';

				$field_id = (isset($property['handle']) ? $property['handle'] : $property['name']);

				$html .= '<select name="fields['.$field['id'].'][lianamailer_properties]['.$field_id.']" data-field-id="'.$field['id'].'" data-field-type="'.$property['type'].'">';
					$html .= '<option value="">Choose</option>';
					foreach($formFields as $formField) {
						$current_value = (isset($field['lianamailer_properties'][$field_id]) ? $field['lianamailer_properties'][$field_id] : null);
						$html .= '<option value="'.$formField['id'].'"'.selected($formField['id'], $current_value, false).'>'.$formField['label'].'</option>';
					}
				$html .= '</select>';
			$html .= '</div>';
		}

		// Field option row (markup) including label and input.
		$output = $this->field_element(
			'row',
			$field,
			array(
				'slug'    => 'lianamailer-properties',
				'content' => $option_label . $html,
			)
		);
	}

	private function field_option_choices( $field ) {

		$isConsentSet = isset($this->form_instance['lianamailer_settings']['lianamailer_consent']) && !empty($this->form_instance['lianamailer_settings']['lianamailer_consent']) ?? false;

		$tooltip = __( 'Set your sign-up label text and whether it should be pre-checked.', 'wpforms_lianamailer' );
		$values  = ! empty( $field['choices'] ) ? $field['choices'] : $this->defaults;
		$class   = ! empty( $field['show_values'] ) && (int) $field['show_values'] === 1 ? 'show-values' : '';

		// Field option label
		$option_label = $this->field_element(
			'label',
			$field,
			array(
				'slug'    => 'lianamailer-consent-checkbox',
				'value'   => __( 'Sign-up checkbox', 'wpforms_lianamailer' ),
				'tooltip' => $tooltip,
			),
			false
		);

		// Field option choices inputs
		$option_choices = sprintf( '<ul class="choices-list %s" data-field-id="%d" data-field-type="%s">', $class, $field['id'], $this->type );
		foreach ( $values as $key => $value ) {
			$default         = ! empty( $value['default'] ) ? $value['default'] : '';
			$option_choices .= sprintf( '<li data-key="%d">', $key );
			$option_choices .= sprintf( '<input type="checkbox" name="fields[%s][choices][%s][default]" class="default" value="1" %s>', $field['id'], $key, checked( '1', $default, false ) );
			$option_choices .= sprintf( '<input type="text" name="fields[%s][choices][%s][label]" value="%s" class="label"'.(!$isConsentSet ? ' readonly' : '').'>', $field['id'], $key, esc_attr( $value['label'] ) );
			$option_choices .= sprintf( '<input type="text" name="fields[%s][choices][%s][value]" value="%s" class="value">', $field['id'], $key, esc_attr( $value['value'] ) );
			$option_choices .= '</li>';
		}
		$option_choices .= '</ul>';

		if(!$isConsentSet) {
			$option_choices .= '<div class="lianamailer-error">No consent set in settings</div>';
		}

		// Field option row (markup) including label and input.
		$output = $this->field_element(
			'row',
			$field,
			array(
				'slug'    => 'choices',
				'content' => $option_label . $option_choices,
			)
		);
	}

	/**
	 * Field preview inside the builder.
	 *
	 * @since 1.0.0
	 * @param array $field
	 */
	public function field_preview( $field ) {

		$isPluginEnabled	= $this->form_instance['lianamailer_settings']['lianamailer_enabled'] ?? false;
		$isSiteSelected		= $this->form_instance['lianamailer_settings']['lianamailer_site'] ?? false;
		$isMailingListSet	= $this->form_instance['lianamailer_settings']['lianamailer_mailing_list'] ?? false;
		$isConsentSet 		= $this->form_instance['lianamailer_settings']['lianamailer_consent'] ?? false;

		$values = ! empty( $field['choices'] ) ? $field['choices'] : $this->defaults;

		// Field checkbox elements
		echo '<ul class="primary-input">';

		// Notify if currently empty
		if ( empty( $values ) ) {
			$values = array( 'label' => __( '(empty)', 'wpforms' ) );
		}

		// Individual checkbox options
		foreach ( $values as $key => $value ) {
			$default  = isset( $value['default'] ) ? $value['default'] : '';
			$selected = checked( '1', $default, false );

			printf( '<li><input type="checkbox" %s disabled> <span class="label">%s</span> </li>', $selected, $value['label'] );
		}

		echo '</ul>';

		// Description
		$this->field_preview_option( 'description', $field );

		// Print error messages into preview
		// if REST API connection is not valid
		if(!$this->is_connection_valid) {
			echo '<div class="lianamailer-error rest-api-error">REST API error. Ensure <a href="'.$_SERVER['PHP_SELF'].'?page=lianamailerwpforms" target="_blank">API settings</a> are propertly set</div>';
		}
		// Plugin is disabled on current form
		if(!$isPluginEnabled) {
			echo '<div class="lianamailer-error plugin-not-enabled">Plugin is not enabled</div>';
		}
		// Site has not been selected on current form
		if(!$isSiteSelected) {
			echo '<div class="lianamailer-error plugin-not-enabled">Site is not selected</div>';
		}
		// Mailing list has not been selected on current form
		if(!$isMailingListSet) {
			echo '<div class="lianamailer-error no-mailing-list">Mailing list is not selected</div>';
		}
		// Consent has not been selected on current form
		if(!$isConsentSet) {
			echo '<div class="lianamailer-error no-consent">Consent is not selected</div>';
		}
	}

	/**
	 * Field display on the form front-end.
	 *
	 * @since 1.0.0
	 * @param array $field
	 * @param array $form_data
	 */
	public function field_display( $field, $field_atts, $form_data ) {

		// Setup and sanitize the necessary data
		$field_required = ! empty( $field['required'] ) ? ' required' : '';
		$field_class    = implode( ' ', array_map( 'sanitize_html_class', $field_atts['input_class'] ) );
		$field_id       = implode( ' ', array_map( 'sanitize_html_class', $field_atts['input_id'] ) );
		$form_id        = $form_data['id'];
		$choices        = $field['choices'];

		$isPluginEnabled	= $form_data['lianamailer_settings']['lianamailer_enabled'] ?? false;
		$isMailingListSet	= $form_data['lianamailer_settings']['lianamailer_mailing_list'] ?? false;
		$isConsentSet 		= $form_data['lianamailer_settings']['lianamailer_consent'] ?? false;

		$forceSelect = false;
		if($this->hasSettingsError($form_data)) {
			$forceSelect = true;
		}

		// List
		printf( '<ul id="%s" class="%s">', $field_id, $field_class);

		foreach ( $choices as $key => $choice ) {
			// if plugin is not enabled or consent is not set, select checkbox by default
			$selected = isset( $choice['default'] ) || $forceSelect ? '1' : '0';
			$depth    = isset( $choice['depth'] ) ? absint( $choice['depth'] ) : 1;

			printf( '<li class="choice-%d depth-%d">', $key, $depth );

			// Checkbox elements
			printf(
				'<input type="checkbox" id="wpforms-%d-field_%d_%d" name="wpforms[fields][%d]" value="%s" %s %s>',
				$form_id,
				$field['id'],
				$key,
				$field['id'],
				esc_attr( $choice['value'] ),
				checked( '1', $selected, false ),
				$field_required
			);

			printf( '<label class="wpforms-field-label-inline" for="wpforms-%d-field_%d_%d">%s</label>', $form_id, $field['id'], $key, wp_kses_post( $choice['label'] ) );

			echo '</li>';
		}

		echo '</ul>';
	}

	/**
	 * Formats and sanitizes field on form submission.
	 *
	 * @since 1.0.2
	 * @param int $field_id
	 * @param array $field_submit
	 * @param array $form_data
	 */
	public function format( $field_id, $field_submit, $form_data ) {
		$field  = $form_data['fields'][ $field_id ];
		$choice = array_pop( $field['choices'] );
		$name   = sanitize_text_field( $choice['label'] );

		$data = array(
			'name'      => $name,
			'value'     => empty( $field_submit ) ? __( 'No', 'wpforms_lianamailer' ) : __( 'Yes', 'wpforms_lianamailer' ),
			'value_raw' => $field_submit,
			'id'        => absint( $field_id ),
			'type'      => $this->type,
		);

		wpforms()->process->fields[ $field_id ] = $data;
	}
}
