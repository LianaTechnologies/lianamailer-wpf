/**
 * LianaMailer / WPForms JavaScript functionality
 *
 * @package  LianaMailer
 * @author   Liana Technologies <websites@lianatech.com>
 * @license  GPL-3.0-or-later https://www.gnu.org/licenses/gpl-3.0-standalone.html
 * @link     https://www.lianatech.com
 */

jQuery( document ).ready(
	function($) {

		var $enableCb               = $( '.wpforms-panel-content .lianamailer_wpforms input#wpforms-panel-field-lianamailer_settings-lianamailer_enabled' );
		var $siteSelect             = $( '.wpforms-panel-content .lianamailer_wpforms select#wpforms-panel-field-lianamailer_settings-lianamailer_site' );
		var $mailingListSelect      = $( '.wpforms-panel-content .lianamailer_wpforms select#wpforms-panel-field-lianamailer_settings-lianamailer_mailing_list' );
		var $consentSelect          = $( '.wpforms-panel-content .lianamailer_wpforms select#wpforms-panel-field-lianamailer_settings-lianamailer_consent' );
		var $tab                    = $( '.wpforms-panel-sidebar [data-section="lianamailer_wpforms"]' );
		var $lianaMailerCustomField = $( '.wpforms-panel-sidebar-content' ).find( '#wpforms-add-fields-lianamailer' );

		var $builder = $( '#wpforms-builder' );

		// Make sure user cannot add more than one LianaMailer field into form.
		$lianaMailerCustomField.click(
			function(e){
				if ($( '.wpforms-panel-content-wrap .wpforms-field-wrap' ).find( '.wpforms-field-lianamailer' ).length > 0) {
					$lianaMailerCustomField.addClass( 'disabled warning-modal' );
				} else {
					$lianaMailerCustomField.removeClass( 'disabled warning-modal' );
				}
			}
		);

		// Update consent text in real time for preview.
		$builder.on(
			'input',
			'.wpforms-field-option-lianamailer input.label',
			function() {
				var value = $( this ).val();
				$( '.wpforms-panel-content-wrap' ).find( '.wpforms-field-lianamailer ul.primary-input li span.label' ).html( value );
			}
		);

		// on wpform save, reload page if consent text was updated.
		$builder.on(
			'wpformsSaved',
			function( e, data ) {

				if (typeof data == 'undefined' || typeof data.redirect == 'undefined') {
					return;
				}
				var url = data.redirect;

				var redirect = false;
				if ($( '#wpforms-panel-settings' ).is( ':visible' ) && $( '#wpforms-panel-settings .wpforms-panel-sidebar-section-lianamailer_wpforms' ).hasClass( 'active' )) {
					redirect = true;
				}
				if ($( '#wpforms-panel-fields' ).is( ':visible' ) && $( '.wpforms-panel-content' ).find( '.wpforms-field-lianamailer' ).length > 0) {
					redirect = true;
				}

				if (redirect && url) {
					window.location.href = url;
				}
			}
		);

		toggleLianaMailerPlugin();

		function toggleLianaMailerPlugin() {
			var $settingsBlock = $enableCb.closest( '.wpforms-panel-content-section' ).find( '.wpforms_lianamailer_settings' );
			var $enabled       = $enableCb.is( ':checked' );

			if ($enabled) {
				$settingsBlock.show( 300 );
			} else {
				$settingsBlock.hide( 300 );
			}
		}

		$enableCb.change(
			function() {
				toggleLianaMailerPlugin();
			}
		);

		if ( ! $siteSelect.val()) {
			$mailingListSelect.addClass( 'disabled' );
			$consentSelect.addClass( 'disabled' );
		}

		$siteSelect.change(
			function() {
				var siteValue = $( this ).val();

				disableElement( $mailingListSelect );
				disableElement( $consentSelect );

				$mailingListSelect.find( "option:gt(0)" ).remove();
				$consentSelect.find( "option:gt(0)" ).remove();

				if ( ! siteValue) {
					setError( $siteSelect );
					setError( $mailingListSelect );
					$mailingListSelect.addClass( 'disabled' ).find( "option:gt(0)" ).remove();
					$consentSelect.addClass( 'disabled' ).find( "option:gt(0)" ).remove();
				} else {

					unsetError( $siteSelect );
					unsetError( $mailingListSelect );
					let params = {
						url: lianaMailerConnection.url,
						method: 'POST',
						dataType: 'json',
						data: {
							'action': 'getSiteDataForWPFormSettings',
							'site': siteValue
						}
					};

					$.ajax( params ).done(
						function( data ) {

							disableElement( $mailingListSelect );
							disableElement( $consentSelect );

							var lists    = data.lists;
							var consents = data.consents;

							if (lists.length) {
								$mailingListSelect.find( "option:gt(0)" ).remove();
								var options = [];
								$.each(
									lists,
									function( index, listData ) {
										var opt   = document.createElement( 'option' );
										opt.value = listData.id;
										opt.text  = listData.name;
										options.push( opt );
									}
								);
								$mailingListSelect.append( options );
								enableElement( $mailingListSelect );
							}

							if (consents.length) {
								$consentSelect.find( "option:gt(0)" ).remove();
								var options = [];
								$.each(
									consents,
									function( index, consentData ) {
										var opt   = document.createElement( 'option' );
										opt.value = consentData.consent_id;
										opt.text  = consentData.name;
										options.push( opt );
									}
								);
								$consentSelect.append( options );
								enableElement( $consentSelect );
							}
						}
					);
				}
			}
		);

		if ( ! $mailingListSelect.val() ) {
			setError( $mailingListSelect );
		}

		$mailingListSelect.change(
			function() {
				var mailingListValue = $( this ).val();
				if ( ! mailingListValue) {
					setError( $mailingListSelect );
				} else {
					unsetError( $mailingListSelect );
				}
			}
		);

		function enableElement($elem) {
			$elem.removeClass( 'disabled' ).prop( 'disabled', false );
		}

		function disableElement($elem) {
			$elem.addClass( 'disabled' ).prop( 'disabled', true );
		}

		function setError($elem) {
			$elem.addClass( 'error' );
		}

		function unsetError($elem) {
			$elem.removeClass( 'error' );
		}
	}
);
