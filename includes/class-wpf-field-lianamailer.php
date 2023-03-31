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
 * LianaMailer custom field class for WPForms plugin
 *
 * PHP Version 7.4
 *
 * @package  LianaMailer
 * @license  https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link     https://www.lianatech.com
 */
class WPF_Field_LianaMailer extends \WPForms_Field {

	/**
	 * Form instance
	 *
	 * @var form_instance object
	 */
	private $form_instance;

	/**
	 * LianaMailer properties
	 *
	 * @var lianamailer_properties array
	 */
	private $lianamailer_properties = array();

	/**
	 * LianaMailer site data
	 *
	 * @var site_data array
	 */
	private $site_data = array();

	/**
	 * Is LianaMailer REST ApI connection valid.
	 *
	 * @var is_connection_valid boolean
	 */
	private $is_connection_valid = true;

	/**
	 * Constructor.
	 */
	public function init() {

		// Define field type information.
		$this->name  = 'LianaMailer';
		$this->type  = 'lianamailer';
		$this->icon  = 'lianamailer';
		$this->order = 999;

		$consent_description       = '';
		$this->is_connection_valid = apply_filters( 'wpf_get_lianamailer_connection_status', $this );

		if ( is_admin() ) {
			$form_id             = null;
			$consent_description = null;
			// Loading form builder.
			if ( isset( $_GET['form_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$form_id = absint( $_GET['form_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
			// When adding new field.
			if ( isset( $_POST['action'] ) && 'wpforms_new_field_lianamailer' === $_POST['action'] && isset( $_POST['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$form_id = absint( $_POST['id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}

			if ( $form_id ) {
				$form = wpforms()->form->get( $form_id );

				if ( is_object( $form ) ) {
					$form = wpforms_decode( $form->post_content );

					$this->form_instance          = $form;
					$this->site_data              = apply_filters( 'wpf_get_lianamailer_site_data', $this, $form );
					$this->lianamailer_properties = apply_filters( 'wpf_get_lianamailer_properties', $this, $form );
				}

				if ( ! empty( $this->site_data ) ) {
					$consent_id  = $form['lianamailer_settings']['lianamailer_consent'] ?? null;
					$consent_key = array_search( $consent_id, array_column( $this->site_data['consents'], 'consent_id' ), true );

					if ( false !== $consent_key ) {
						$consent_data        = $this->site_data['consents'][ $consent_key ];
						$consent_description = $consent_data['description'];
					}

					// Check that selected mailing list still available in LianaMailer.
					$mailing_list_id  = intval( $form['lianamailer_settings']['lianamailer_mailing_list'] ) ?? null;
					$mailing_list_key = array_search( $mailing_list_id, array_column( $this->site_data['lists'], 'id' ), true );
					if ( false === $mailing_list_key ) {
						// If not null the setting value to print error.
						$this->form_instance['lianamailer_settings']['lianamailer_mailing_list'] = null;
					}
				}
			}
			$this->defaults = array(
				0 => array(
					'label'   => $consent_description,
					'value'   => '1',
					'default' => '',
				),
			);
		}

		// Set field to default to required.
		add_filter( 'wpforms_field_new_required', array( $this, 'field_default_required' ), 10, 2 );

		// Define additional field properties.
		add_filter( 'wpforms_field_properties_lianamailer', array( $this, 'field_properties' ), 10, 3 );
	}

	/**
	 * Checks if LianaMailer field has any setting errors
	 *
	 * @param array $form_data Form data and settings.
	 *
	 * @return boolean true if errors found.
	 */
	private function has_settings_error( $form_data ) {
		$is_plugin_enabled   = $form_data['lianamailer_settings']['lianamailer_enabled'] ?? false;
		$is_mailing_list_set = $form_data['lianamailer_settings']['lianamailer_mailing_list'] ?? false;
		$is_consent_set      = $form_data['lianamailer_settings']['lianamailer_consent'] ?? false;

		if ( ! $is_plugin_enabled || ! $is_mailing_list_set || ! $is_consent_set || ! $this->is_connection_valid ) {
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

		// If consent is not selected on form settings, hide consent field from public form.
		if ( $this->has_settings_error( $form_data ) ) {
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
	 * Whether current field can be populated dynamically.
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Current field specific data.
	 *
	 * @return bool
	 */
	public function is_dynamic_population_allowed( $properties, $field ) {
		return false;
	}


	/**
	 * Field options panel inside the builder.
	 *
	 * @since 1.0.0
	 * @param array $field Field data and settings.
	 */
	public function field_options( $field ) {

		// --------------------------------------------------------------------//
		// Basic field options
		// --------------------------------------------------------------------//

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

		// Options open markup.
		$this->field_option( 'basic-options', $field, array( 'markup' => 'open' ) );
		// if LianaMailer site data couldnt be fetched. Problem on API credentials or API itself.
		// Print previously saved mappings as hidden inputs if theres any, so theyre not get lost when saving the form.
		if ( empty( $this->site_data ) || ! $this->is_connection_valid ) {

			$html = '';
			if ( ! $this->is_connection_valid ) {
				$html = '<div class="lianamailer-error rest-api-error"><p>REST API error. Ensure <a href="' . ( isset( $_SERVER['PHP_SELF'] ) ? sanitize_text_field( wp_unslash( $_SERVER['PHP_SELF'] ) ) : '' ) . '?page=lianamailerwpf" target="_blank">API settings</a> are propertly set</p></div>';
			} elseif ( empty( $this->site_data ) ) {
				$html = '<div class="lianamailer-error rest-api-error"><p>LianaMailer site is not selected. Check settings</p></div>';
			}

			$fields            = $this->form_instance['fields'];
			$lianamailer_field = null;
			// Fetch LianaMailer field for settings.
			foreach ( $fields as $key => $single_field ) {
				if ( 'lianamailer' !== $single_field['type'] || ! empty( $lianamailer_field ) ) {
					continue;
				}
				$lianamailer_field = $single_field;
			}
			if ( $lianamailer_field ) {
				$field_id = $lianamailer_field['id'];
				if ( isset( $lianamailer_field['lianamailer_properties'] ) ) {
					// Print property mappings.
					foreach ( $lianamailer_field['lianamailer_properties'] as $lm_field => $form_field ) {
						$html .= '<input type="hidden" name="fields[' . $field_id . '][lianamailer_properties][' . $lm_field . ']" value="' . $form_field . '" />';
					}
				}
				if ( isset( $lianamailer_field['choices'] ) ) {
					// Print consent label, value and default value.
					foreach ( $lianamailer_field['choices'] as $key => $choice_data ) {
						$html .= '<input type="hidden" name="fields[' . $field_id . '][choices][' . $key . '][label]" value="' . htmlspecialchars( $choice_data['label'] ) . '" />';
						$html .= '<input type="hidden" name="fields[' . $field_id . '][choices][' . $key . '][value]" value="' . $choice_data['value'] . '" />';
						$html .= '<input type="hidden" name="fields[' . $field_id . '][choices][' . $key . '][default]" value="' . $choice_data['default'] . '" />';
					}
				}
			}

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

		} else {
			// LianaMailer properties.
			$this->field_option_lianamailer_properties( $field );

			// Choices.
			$this->field_option_choices( $field );
		}

		// Description.
		$this->field_option( 'description', $field );

		/**
		 * Required toggle.
		 * $this->field_option( 'required', $field, ['default' => '1']);
		 */

		// Options close markup.
		$this->field_option( 'basic-options', $field, array( 'markup' => 'close' ) );

		// --------------------------------------------------------------------//
		// Advanced field options
		// --------------------------------------------------------------------//

		// Options open markup.
		$this->field_option( 'advanced-options', $field, array( 'markup' => 'open' ) );

		// Custom CSS classes.
		$this->field_option( 'css', $field );

		// Options close markup.
		$this->field_option( 'advanced-options', $field, array( 'markup' => 'close' ) );
	}

	/**
	 * Prints LianaMailer property map into form editor.
	 *
	 * @param array $field Field data.
	 */
	private function field_option_lianamailer_properties( $field ) {

		// Field option label.
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

		$form_fields = array();
		// Fetch all non LianaMailer fields for settings.
		foreach ( $fields as $key => $single_field ) {
			if ( ! isset( $single_field['label'] ) || 'lianamailer' === $single_field['type'] ) {
				continue;
			}
			$form_fields[] = $single_field;
		}

		$values              = ! empty( $field['choices'] ) ? $field['choices'] : $this->defaults;
		$field['choices'][0] = $values;

		$html = '';
		if ( empty( $this->lianamailer_properties ) ) {
			$html .= '<div class="lianamailer-error no-properties-found">No LianaMailer properties found</div>';
		}

		foreach ( $this->lianamailer_properties as $property ) {
			$html .= '<div class="property">';

				$html     .= '<label for="field_lianamailer_property" class="section_label">';
					$html .= '<b>' . $property['name'] . ( isset( $property['handle'] ) && is_int( $property['handle'] ) ? ' (#' . $property['handle'] . ')' : '' ) . '</b>';
				$html     .= '</label>';

				$field_id = ( isset( $property['handle'] ) ? $property['handle'] : $property['name'] );

				$html     .= '<select name="fields[' . $field['id'] . '][lianamailer_properties][' . $field_id . ']" data-field-id="' . $field['id'] . '" data-field-type="' . $property['type'] . '">';
					$html .= '<option value="">Choose</option>';
			foreach ( $form_fields as $form_field ) {
				$current_value = ( isset( $field['lianamailer_properties'][ $field_id ] ) ? $field['lianamailer_properties'][ $field_id ] : null );
				$html         .= '<option value="' . $form_field['id'] . '"' . selected( $form_field['id'], $current_value, false ) . '>' . $form_field['label'] . '</option>';
			}
				$html .= '</select>';
			$html     .= '</div>';
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

	/**
	 * Prints choice options for LianaMailer field.
	 *
	 * @param array $field Field data.
	 */
	private function field_option_choices( $field ) {

		$is_consent_set = isset( $this->form_instance['lianamailer_settings']['lianamailer_consent'] ) && ! empty( $this->form_instance['lianamailer_settings']['lianamailer_consent'] ) ?? false;

		$tooltip = __( 'Set your sign-up label text and whether it should be pre-checked.', 'wpforms_lianamailer' );
		$values  = ! empty( $field['choices'] ) ? $field['choices'] : $this->defaults;
		$class   = ! empty( $field['show_values'] ) && 1 === (int) $field['show_values'] ? 'show-values' : '';

		// Field option label.
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

		// Field option choices inputs.
		$option_choices = sprintf( '<ul class="choices-list %s" data-field-id="%d" data-field-type="%s">', $class, $field['id'], $this->type );
		foreach ( $values as $key => $value ) {
			$default         = ! empty( $value['default'] ) ? $value['default'] : '';
			$option_choices .= sprintf( '<li data-key="%d">', $key );
			$option_choices .= sprintf( '<input type="checkbox" name="fields[%s][choices][%s][default]" class="default" value="1" %s>', $field['id'], $key, checked( '1', $default, false ) );
			$option_choices .= sprintf( '<input type="text" name="fields[%s][choices][%s][label]" value="%s" class="label"' . ( ! $is_consent_set ? ' readonly' : '' ) . '>', $field['id'], $key, esc_attr( $value['label'] ) );
			$option_choices .= sprintf( '<input type="text" name="fields[%s][choices][%s][value]" value="%s" class="value">', $field['id'], $key, esc_attr( $value['value'] ) );
			$option_choices .= '</li>';
		}
		$option_choices .= '</ul>';

		if ( ! $is_consent_set ) {
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
	 * @param array $field Field data and settings.
	 */
	public function field_preview( $field ) {

		$is_plugin_enabled   = $this->form_instance['lianamailer_settings']['lianamailer_enabled'] ?? false;
		$is_site_selected    = $this->form_instance['lianamailer_settings']['lianamailer_site'] ?? false;
		$is_mailing_list_set = $this->form_instance['lianamailer_settings']['lianamailer_mailing_list'] ?? false;
		$is_consent_set      = $this->form_instance['lianamailer_settings']['lianamailer_consent'] ?? false;

		$values = ! empty( $field['choices'] ) ? $field['choices'] : $this->defaults;

		$html = '';
		// Field checkbox elements.
		$html .= '<ul class="primary-input">';

		// Notify if currently empty.
		if ( empty( $values ) ) {
			$values = array( 'label' => __( '(empty)', 'wpforms' ) );
		}

		// Individual checkbox options.
		foreach ( $values as $key => $value ) {
			$default  = isset( $value['default'] ) ? $value['default'] : '';
			$selected = checked( '1', $default, false );

			$html .= sprintf( '<li><input type="checkbox" %s disabled> <span class="label">%s</span> </li>', $selected, $value['label'] );
		}

		$html .= '</ul>';

		// Description.
		$this->field_preview_option( 'description', $field );

		// Print error messages into preview.
		// If REST API connection is not valid.
		if ( ! $this->is_connection_valid ) {
			$html .= '<div class="lianamailer-error rest-api-error">REST API error. Ensure <a href="' . ( isset( $_SERVER['PHP_SELF'] ) ? sanitize_text_field( wp_unslash( $_SERVER['PHP_SELF'] ) ) : '' ) . '?page=lianamailerwpf" target="_blank">API settings</a> are propertly set</div>';
		}
		// Plugin is disabled on current form.
		if ( ! $is_plugin_enabled ) {
			$html .= '<div class="lianamailer-error plugin-not-enabled">Plugin is not enabled</div>';
		}
		// Site has not been selected on current form.
		if ( ! $is_site_selected ) {
			$html .= '<div class="lianamailer-error plugin-not-enabled">Site is not selected</div>';
		}
		// Mailing list has not been selected on current form.
		if ( ! $is_mailing_list_set ) {
			$html .= '<div class="lianamailer-error no-mailing-list">Mailing list is not selected</div>';
		}
		// Consent has not been selected on current form.
		if ( ! $is_consent_set ) {
			$html .= '<div class="lianamailer-error no-consent">Consent is not selected</div>';
		}

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
	 * Field display on the form front-end.
	 *
	 * @since 1.0.0
	 * @param array $field Field data.
	 * @param array $field_atts Field attributes (deprecated).
	 * @param array $form_data Form data.
	 */
	public function field_display( $field, $field_atts, $form_data ) {

		// Setup and sanitize the necessary data.
		$field_required = ! empty( $field['required'] ) ? ' required' : '';
		$container      = $field['properties']['container'];
		$form_id        = $form_data['id'];
		$choices        = $field['choices'];

		$is_plugin_enabled   = $form_data['lianamailer_settings']['lianamailer_enabled'] ?? false;
		$is_mailing_list_set = $form_data['lianamailer_settings']['lianamailer_mailing_list'] ?? false;
		$is_consent_set      = $form_data['lianamailer_settings']['lianamailer_consent'] ?? false;

		$force_select = false;
		if ( $this->has_settings_error( $form_data ) ) {
			$force_select = true;
		}

		$html = '';
		// List.
		$html .= sprintf(
			'<ul %s>',
			wpforms_html_attributes( $container['id'], $container['class'], $container['data'], $container['attr'] )
		);

		foreach ( $choices as $key => $choice ) {
			// If plugin is not enabled or consent is not set, select checkbox by default.
			$selected = isset( $choice['default'] ) || $force_select ? '1' : '0';
			$depth    = isset( $choice['depth'] ) ? absint( $choice['depth'] ) : 1;

			$html .= sprintf( '<li class="choice-%d depth-%d">', $key, $depth );

			// Checkbox elements.
			$html .= sprintf(
				'<input type="checkbox" id="wpforms-%d-field_%d_%d" name="wpforms[fields][%d]" value="%s" %s %s>',
				$form_id,
				$field['id'],
				$key,
				$field['id'],
				esc_attr( $choice['value'] ),
				checked( '1', $selected, false ),
				$field_required
			);

			$html .= sprintf( '<label class="wpforms-field-label-inline" for="wpforms-%d-field_%d_%d">%s</label>', $form_id, $field['id'], $key, wp_kses_post( $choice['label'] ) );

			$html .= '</li>';
		}

		$html .= '</ul>';

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
	 * Formats and sanitizes field on form submission.
	 *
	 * @since 1.0.2
	 * @param int   $field_id     Field ID.
	 * @param array $field_submit Field value that was submitted.
	 * @param array $form_data    Form data and settings.
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
