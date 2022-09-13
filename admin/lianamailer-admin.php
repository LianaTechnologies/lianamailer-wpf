<?php
/**
 * LianaMailer WPForms admin panel
 *
 * PHP Version 7.4
 *
 * @category Components
 * @package  WordPress
 * @author   Liana Technologies <websites@lianatech.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link     https://www.lianatech.com
 */

/**
 * LianaMailer / WPForms options panel class
 *
 * @category Components
 * @package  WordPress
 * @author   Liana Technologies <websites@lianatech.com>
 * @license  https://www.gnu.org/licenses/gpl-3.0-standalone.html GPL-3.0-or-later
 * @link     https://www.lianatech.com
 */

namespace WPForms_LianaMailer;

class LianaMailerWPForms {

	private $lianamailer_wpforms_options = [
		'lianamailer_userid' => '',
		'lianamailer_secret_key' => '',
		'lianamailer_realm' => '',
		'lianamailer_url' => ''
	];


    /**
     * Constructor
     */
    public function __construct() {
        add_action(
            'admin_menu',
            [ $this, 'lianaMailerWPFormsAddPluginPage' ]
        );

		add_action(
            'admin_init',
            [ $this, 'lianaMailerWPFormsPageInit' ]
        );
    }

    /**
     * Add an admin page
     *
     * @return null
     */
    public function lianaMailerWPFormsAddPluginPage() {
        global $admin_page_hooks;

        // Only create the top level menu if it doesn't exist (via another plugin)
        if (!isset($admin_page_hooks['lianamailer'])) {
            add_menu_page(
                'LianaMailer', // page_title
                'LianaMailer', // menu_title
                'manage_options', // capability
                'lianamailer', // menu_slug
				[$this, 'lianaMailerWPFormsCreateAdminPage' ],
                'dashicons-admin-settings', // icon_url
                65 // position
            );
        }
        add_submenu_page(
            'lianamailer',
            'WPForms',
            'WPForms',
            'manage_options',
            'lianamailerwpforms',
            [ $this, 'lianaMailerWPFormsCreateAdminPage' ],
        );

        // Remove the duplicate of the top level menu item from the sub menu
        // to make things pretty.
        remove_submenu_page('lianamailer', 'lianamailer');

    }


    /**
     * Construct an admin page
     *
     * @return null
     */
    public function lianaMailerWPFormsCreateAdminPage() {
		$this->lianamailer_wpforms_options = get_option('lianamailer_wpforms_options');
		?>

		<div class="wrap">
		<?php
		// LianaMailer API Settings
		?>
			<h2>LianaMailer API Options for WPForms</h2>
		<?php settings_errors(); ?>
		<form method="post" action="options.php">
			<?php
			settings_fields('lianamailer_wpforms_option_group');
			do_settings_sections('lianamailer_wpforms_admin');
			submit_button();
			?>
		</form>
		</div>
        <?php
    }

    /**
     * Init a WPForms admin page
     *
     * @return null
     */
	public function lianaMailerWPFormsPageInit() {

		$page = 'lianamailer_wpforms_admin';
		$section = 'lianamailer_wpforms_section';

		// LianaMailer
		register_setting(
            'lianamailer_wpforms_option_group', // option_group
            'lianamailer_wpforms_options', // option_name
            [
                $this,
                'lianaMailerWPFormsSanitize'
            ] // sanitize_callback
        );

		add_settings_section(
            $section, // id
            '', // empty section title text
            [ $this, 'lianMailerContactForm7SectionInfo' ], // callback
            $page // page
        );

		$inputs = [
			// API UserID
			[
				'name' => 'lianamailer_wpforms_userid',
				'title' => 'LianaMailer API UserID',
				'callback' => [ $this, 'lianaMailerWPFormsUserIDCallback' ],
				'page' => $page,
				'section' => $section
			],
			// API Secret key
			[
				'name' => 'lianamailer_wpforms_secret',
				'title' => 'LianaMailer API Secret key',
				'callback' => [ $this, 'lianaMailerWPFormsSecretKeyCallback' ],
				'page' => $page,
				'section' => $section
			],
			// API URL
			[
				'name' => 'lianamailer_wpforms_url',
				'title' => 'LianaMailer API URL',
				'callback' => [ $this, 'lianaMailerWPFormsUrlCallback' ],
				'page' => $page,
				'section' => $section,
			],
			// API Realm
			[
				'name' => 'lianamailer_wpforms_realm',
				'title' => 'LianaMailer API Realm',
				'callback' => [ $this, 'lianaMailerWPFormsRealmCallback' ],
				'page' => $page,
				'section' => $section,
			],
			// Status check
			[
				'name' => 'lianamailer_wpforms_status_check',
				'title' => 'LianaMailer Connection Check',
				'callback' => [ $this, 'lianaMailerWPFormsConnectionCheckCallback' ],
				'page' => $page,
				'section' => $section
			]
		];

		$this->addInputs($inputs);

	}

	private function addInputs($inputs) {
		if(empty($inputs))
			return;

		foreach($inputs as $input) {
			try {
				add_settings_field(
					$input['name'], // id
					$input['title'], // title
					$input['callback'], // callback
					$input['page'], // page
					$input['section'], // section
					(!empty($input['options']) ? $input['options'] : null)
				);
			}
			catch (\Exception $e) {
				$this->error_messages[] = 'Oops, something went wrong: '.$e->getMessage();
			}
		}
	}

    /**
     * Basic input sanitization function
     *
     * @param string $input String to be sanitized.
     *
     * @return null
     */
    public function lianaMailerWPFormsSanitize($input) {
        $sanitary_values = [];

		// for LianaMailer inputs
		if (isset($input['lianamailer_userid'])) {
            $sanitary_values['lianamailer_userid']
                = sanitize_text_field($input['lianamailer_userid']);
        }
		if (isset($input['lianamailer_secret_key'])) {
            $sanitary_values['lianamailer_secret_key']
                = sanitize_text_field($input['lianamailer_secret_key']);
        }
		if (isset($input['lianamailer_url'])) {
            $sanitary_values['lianamailer_url']
                = sanitize_text_field($input['lianamailer_url']);
        }
		if (isset($input['lianamailer_realm'])) {
            $sanitary_values['lianamailer_realm']
                = sanitize_text_field($input['lianamailer_realm']);
        }
        return $sanitary_values;
    }

    /**
     * Empty section info
     *
     * @return null
     */
    public function lianMailerContactForm7SectionInfo($arg) {
        // Intentionally empty section here.
        // Could be used to generate info text.
    }

	/**
     * LianaMailer API URL
     *
     * @return null
     */
    public function lianaMailerWPFormsUrlCallback() {

		printf(
            '<input class="regular-text" type="text" '
            .'name="lianamailer_wpforms_options[lianamailer_url]" '
            .'id="lianamailer_url" value="%s">',
			isset($this->lianamailer_wpforms_options['lianamailer_url']) ? esc_attr($this->lianamailer_wpforms_options['lianamailer_url']) : ''
        );
    }
	/**
     * LianaMailer API Realm
     *
     * @return null
     */
    public function lianaMailerWPFormsRealmCallback() {
		// https://app.lianamailer.com
		printf(
            '<input class="regular-text" type="text" '
            .'name="lianamailer_wpforms_options[lianamailer_realm]" '
            .'id="lianamailer_realm" value="%s">',
			isset($this->lianamailer_wpforms_options['lianamailer_realm']) ? esc_attr($this->lianamailer_wpforms_options['lianamailer_realm']) : ''
        );
    }

	/**
     * LianaMailer Status check
     *
     * @return null
     */
    public function lianaMailerWPFormsConnectionCheckCallback() {

		$return = '💥Fail';

		if(!empty($this->lianamailer_wpforms_options['lianamailer_userid']) || !empty($this->lianamailer_wpforms_options['lianamailer_secret_key']) || !empty($this->lianamailer_wpforms_options['lianamailer_realm'])) {
			$rest = new Rest(
				$this->lianamailer_wpforms_options['lianamailer_userid'],		// userid
				$this->lianamailer_wpforms_options['lianamailer_secret_key'],	// user secret
				$this->lianamailer_wpforms_options['lianamailer_realm'],		// realm eg. "EUR"
				$this->lianamailer_wpforms_options['lianamailer_url']			// https://rest.lianamailer.com
			);

			$status = $rest->getStatus();
			if($status) {
				$return = '💚 OK';
			}
		}

		echo $return;

    }

	/**
     * LianaMailer UserID
     *
     * @return null
     */
    public function lianaMailerWPFormsUserIDCallback() {
        printf(
            '<input class="regular-text" type="text" '
            .'name="lianamailer_wpforms_options[lianamailer_userid]" '
            .'id="lianamailer_userid" value="%s">',
            isset($this->lianamailer_wpforms_options['lianamailer_userid']) ? esc_attr($this->lianamailer_wpforms_options['lianamailer_userid']) : ''
        );
    }

		/**
     * LianaMailer UserID
     *
     * @return null
     */
    public function lianaMailerWPFormsSecretKeyCallback() {
        printf(
            '<input class="regular-text" type="text" '
            .'name="lianamailer_wpforms_options[lianamailer_secret_key]" '
            .'id="lianamailer_secret_key" value="%s">',
			isset($this->lianamailer_wpforms_options['lianamailer_secret_key']) ? esc_attr($this->lianamailer_wpforms_options['lianamailer_secret_key']) : ''
        );
    }
}
if (is_admin()) {
    $lianaMailerWPForms = new LianaMailerWPForms();
}

