<?php
/**
 * Plugin Name:     Easy Digital Downloads - WarriorPlus
 * Plugin URI:      https://caffeinepressmedia.com/downloads/easy-digital-downloads-warrior-plus/
 * Description:     Adds Warrior Plus / WSO integration to Easy Digital Downloads.
 * Version:         1.0.0
 * Author:          Adam Kreiss
 * Author URI:      https://caffeinepressmedia.com
 * Text Domain:     edd-warriorplus
 *
 * @package         CPM\WarriorPlus
 * @author          Adam Kreiss
 * @copyright       Copyright (c) 2016
 */

// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

if( !class_exists( 'EDD_WarriorPlus' ) ) {

    /**
     * Main EDD_WarriorPlus class
     *
     * @since       1.0.0
     */
    class EDD_WarriorPlus {

        /**
         * @var         EDD_WarriorPlus $instance The one true EDD_WarriorPlus
         * @since       1.0.0
         */
        private static $instance;

        const DEBUG = true;

        //////////////////
        // Constants
        //////////////////

        // The query parameters in the WarriorPlus IPN we need to be able to look at
        const QPARAM_AMOUNT 		= 'WP_SALE_AMOUNT';
        const QPARAM_CUSTEMAIL 		= 'WP_BUYER_EMAIL';
        const QPARAM_CUSTNAME 		= 'WP_BUYER_NAME';
        const QPARAM_EDDID 			= 'edd_id';
        const QPARAM_IPNKEY 		= 'wplusipn';
        const QPARAM_LOGIPN 		= 'logipn';
        const QPARAM_PARENTTRANID 	= 'transaction_parent_id';
        const QPARAM_PACKAGENUM 	= 'edd_pn';
        const QPARAM_SECRETKEY 		= 'WP_SECURITYKEY';
        const QPARAM_TRANID 		= 'WP_TXNID';
        const QPARAM_TRANTYPE 		= 'WP_ACTION';

        const QVALUE_IPN 			= 'ipn';
	    const QVALUE_KEYGEN         = 'keygen';
        const QVALUE_REFUND 		= 'refund';
        const QVALUE_SALE 			= 'sale';

        //////////////////
        // Plugin Setup
        //////////////////

        /**
         * Get active instance
         *
         * @access      public
         * @since       1.0.0
         * @return      object self::$instance The one true EDD_WarriorPlus
         */
        public static function instance() {
            if( !self::$instance ) {
                self::$instance = new EDD_WarriorPlus();
                self::$instance->setup_constants();
                self::$instance->load_textdomain();
	            self::$instance->includes();
                self::$instance->hooks();
            }

            return self::$instance;
        }


        /**
         * Setup plugin constants
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function setup_constants() {
            // Plugin version
            define( 'EDD_WARRIORPLUS_VER', '1.0.0' );

            // Plugin path
            define( 'EDD_WARRIORPLUS_DIR', plugin_dir_path( __FILE__ ) );

            // Plugin URL
            define( 'EDD_WARRIORPLUS_URL', plugin_dir_url( __FILE__ ) );
        }


	    /**
	     * Included any extra necessary files.
	     *
	     * @access      private
	     * @since       1.0.0
	     * @return      void
	     */
	    private function includes() {
			require_once EDD_WARRIORPLUS_DIR . 'includes/class-cpm-license-handler.php';
	    }


        /**
         * Run action and filter hooks
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function hooks() {
            add_action('add_meta_boxes',                    array($this, 'registerMetabox'));
	        add_filter('edd_settings_emails',               array($this, 'setupEmailSettings'));
            add_filter('edd_settings_extensions',           array($this, 'setupExtensionSettings'));
	        add_filter('edd_settings_sections_emails',      array($this, 'defineWarrioPlusEmailSettingSection'));
            add_filter('edd_settings_sections_extensions',  array($this, 'defineWarrioPlusExtensionSettingSection'));
            add_filter('query_vars',                        array($this, 'addQueryVariables'));
            add_action('template_redirect',                 array($this, 'checkForIPNRequest'));

            // Handle licensing
            if( class_exists( 'CPM_License' ) ) {
                new CPM_License( __FILE__, 'Easy Digital Downloads - Warrior Plus', EDD_WARRIORPLUS_VER, 'Adam Kreiss');
            }
        }


        /**
         * Internationalization
         *
         * @access      public
         * @since       1.0.0
         * @return      void
         */
        public function load_textdomain() {
            // Set filter for language directory
            $lang_dir = EDD_WARRIORPLUS_DIR . '/languages/';
            $lang_dir = apply_filters( 'edd_plugin_name_languages_directory', $lang_dir );

            // Traditional WordPress plugin locale filter
            $locale = apply_filters( 'plugin_locale', get_locale(), 'edd-warriorplus' );
            $mofile = sprintf( '%1$s-%2$s.mo', 'edd-warriorplus', $locale );

            // Setup paths to current locale file
            $mofile_local   = $lang_dir . $mofile;
            $mofile_global  = WP_LANG_DIR . '/edd-warriorplus/' . $mofile;

            if( file_exists( $mofile_global ) ) {
                // Look in global /wp-content/languages/edd-warriorplus/ folder
                load_textdomain( 'edd-warriorplus', $mofile_global );
            } elseif( file_exists( $mofile_local ) ) {
                // Look in local /wp-content/plugins/edd-warriorplus/languages/ folder
                load_textdomain( 'edd-warriorplus', $mofile_local );
            } else {
                // Load the default language files
                load_plugin_textdomain( 'edd-warriorplus', false, $lang_dir );
            }
        }


        //////////////////
        // Public methods
        //////////////////

        /**
         * Add the query parameters we need to pay attention to to the params
         * WordPress will retrieve
         *
         * @access      public
         * @since       1.0.0
         * @param       array $public_query_vars The list of query vars WordPress will import
         * @return      array The modified query vars array that will now include query vars needed by this plugin
         */
        public function addQueryVariables($public_query_vars)
        {
            $public_query_vars[] = self::QPARAM_AMOUNT;
            $public_query_vars[] = self::QPARAM_CUSTEMAIL;
            $public_query_vars[] = self::QPARAM_CUSTNAME;
            $public_query_vars[] = self::QPARAM_EDDID;
	        $public_query_vars[] = self::QPARAM_IPNKEY;
            $public_query_vars[] = self::QPARAM_SECRETKEY;
            $public_query_vars[] = self::QPARAM_TRANID;
            $public_query_vars[] = self::QPARAM_TRANTYPE;

            return $public_query_vars;
        }


        /**
         * Determines if the incoming request is a IPN request we want to look at.  If so, the request is processed.
         *
         * @access      public
         * @since       1.0.0
         */
        public function checkForIPNRequest()
        {
            // If we find the appropriate parameter in the query then we have a match
            // and want to redirect to our custom listener
	        $this->debug("http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
	        $this->debug(print_r($_POST, true));

	        $useKeyGen = $this->isKeygenEnabled();
	        $ipnKey = get_query_var(self::QPARAM_IPNKEY);
	        $transactionType = isset($_POST[self::QPARAM_TRANTYPE]) ? $_POST[self::QPARAM_TRANTYPE] : '';

	        // If we have an IPN request, only process it if the user has NOT selected to return license keys OR this is a refund
	        // These requests are filtered because if returning a license key, we will receive two requests on a sale that are
	        // otherwise identical
            if ($ipnKey == self::QVALUE_IPN &&
                (!$useKeyGen || $transactionType == self::QVALUE_REFUND)) {
                $this->processIPNRequest(false);
            }
	        else if ($ipnKey == self::QVALUE_KEYGEN) {
		        $this->processIPNRequest(true);
	        }

	        // Stop any further processing as we've handled everything
	        exit;
        }


	    /**
	     * Define the WarriorPlus sub-tab on the Emails tab in EDD Settings
	     *
	     * @access      public
	     * @since       1.0.0
	     * @param       array $sections The existing tab sections
	     * @return      array The modified tab section array
	     */
	    public function defineWarrioPlusEmailSettingSection( $sections ) {
		    $plugin_sections = array(
			    'warriorplusemail' => __( 'WarriorPlus Notifications', 'edd-warriorplus' )
		    );

		    return array_merge( $sections, $plugin_sections );
	    }


	    /**
	     * Define the WarriorPlus sub-tab on the Extension tab in EDD Settings
	     *
	     * @access      public
	     * @since       1.0.0
	     * @param       array $sections The existing tab sections
	     * @return      array The modified tab section array
	     */
	    public function defineWarrioPlusExtensionSettingSection( $sections ) {
		    $plugin_sections = array(
			    'warriorplus' => __( 'WarriorPlus', 'edd-warriorplus' )
		    );

		    return array_merge( $sections, $plugin_sections );
	    }


        /**
         * Add the WarriorPlus Meta Box
         *
         * @since 1.0
         */
        function registerMetabox()
        {
            add_meta_box('edd_warriorplus_box', __('WarriorPlus', 'edd-warriorplus'), array($this, 'renderMetabox'), 'download', 'side', 'core');
        }


	    /**
	     * Render the WarriorPlus information meta box
	     *
	     * @since 1.0
	     */
	    public function renderMetabox()
	    {
		    global $edd_options;
		    global $post;
		    $post_ipn_url = add_query_arg(array(self::QPARAM_IPNKEY => self::QVALUE_IPN, self::QPARAM_EDDID => $post->ID), get_home_url() . '/');
		    $keygen_ipn_url = add_query_arg(array(self::QPARAM_IPNKEY => self::QVALUE_KEYGEN, self::QPARAM_EDDID => $post->ID), get_home_url() . '/');
		    ?>
		    <table class="form-table">
			    <tr>
				    <td>
					    <strong><?php _e('WarriorPlus Notification URL', 'edd-warriorplus') ?>: </strong>
					    <span style="font-size: 90%;"><?php echo $post_ipn_url; ?></span>
				    </td>
			    </tr>
			    <tr>
				    <td>
					    <strong><?php _e('Instructions', 'edd-warriorplus') ?>:</strong>
					    <?php _e('Copy the above URL into the <strong>Notification URL</strong> field in the Custom Integration section configured for this product in WarriorPlus.', 'edd-warriorplus') ?>
				    <td>
			    </tr>
			    <?php if (isset($edd_options['edd_warriorplus_generate_license']) && $edd_options['edd_warriorplus_generate_license']) { ?>
				    <tr>
					    <td>
						    <strong><?php _e('WarriorPlus Key Generation URL', 'edd-warriorplus') ?>: </strong>
						    <span style="font-size: 90%;"><?php echo $keygen_ipn_url; ?></span>
					    </td>
				    </tr>
				    <tr>
					    <td>
						    <strong><?php _e('Instructions', 'edd-warriorplus') ?>:</strong>
						    <?php _e('Copy the above URL into the <strong>Key Generation URL</strong> field in the Custom Integration section configured for this product in WarriorPlus.', 'edd-warriorplus') ?>
					    <td>
				    </tr>
		        <?php } ?>
		    </table>
		    <?php
	    }


	    /**
	     * Add settings to the Email WarriorPlus section
	     *
	     * @access      public
	     * @since       1.0.0
	     * @param       array $settings The existing EDD settings array
	     * @return      array The modified EDD settings array
	     */
	    public function setupEmailSettings( $settings ) {
		    $plugin_settings = array(
			    'warriorplusemail' => array(
				    array(
					    'id'        => 'edd_warriorplus_header',
					    'name'      => '<strong>' . __('New User Notification', 'edd-warriorplus') . '</strong>',
					    'desc'      => '',
					    'type'      => 'header',
					    'size'      => 'regular'
				    ),
				    array(
					    'id'   => 'edd_warriorplus_new_user_email_subject',
					    'name' => __('Email Subject', 'edd-warriorplus'),
					    'type' => 'text',
					    'size' => 'large',
					    'std'  => __('Enter email subject line', 'edd-warriorplus')
				    ),
				    array(
					    'id'   => 'edd_warriorplus_new_user_email_message',
					    'name' => __('Email Message', 'edd-warriorplus'),
					    'type' => 'rich_editor',
					    'desc' => __('Enter the email message you\'d like your customers to receive when a new user account is created for them. Use template tags below to customize the email.', 'edd-warriorplus') . '<br/>' .
					              '{name} - ' . __('The customer\'s name', 'edd-warriorplus') . '<br/>' .
					              '{username} - ' . __('The new username for the user account', 'edd-warriorplus') . '<br/>' .
					              '{password} - ' . __('The password for the user account', 'edd-warriorplus') . '<br/>' .
					              '{email} - ' . __('The customer\'s email account that has been used for new user account.', 'edd-warriorplus') . '<br/>' .
					              __('These emails will be sent automatically when a new user account is created in response to receiving an IPN notification from WarriorPlus.  Users are matched on the email address ' .
					                 'received from WarriorPlus.  If a user exists on your site with a matching email address then no new account will be created.', 'edd-warriorplus'),
					    'std'  => __("Hello {name},\n\n We have created a new user account for you. Log-in to your account with following information.\n\n Login URL: \n\n Username: {username} \n\n Password: {password}", 'edd-warriorplus')
				    )
			    )
		    );

		    return array_merge( $settings, $plugin_settings );
	    }


	    /**
	     * Add settings
	     *
	     * @access      public
	     * @since       1.0.0
	     * @param       array $settings The existing EDD settings array
	     * @return      array The modified EDD settings array
	     */
	    public function setupExtensionSettings( $settings ) {
		    $plugin_settings = array(
			    'warriorplus' => array(
				    array(
					    'id'        => 'edd_warriorplus_header',
					    'name'      => '<strong>' . __('WarriorPlus', 'edd-warriorplus') . '</strong>',
					    'desc'      => '',
					    'type'      => 'header',
					    'size'      => 'regular'
				    ),
				    array(
					    'id'   => 'edd_warriorplus_secret_key',
					    'name' => __('WarriorPlus Security Key', 'edd-warriorplus'),
					    'desc' => __('Enter the Secret Key for your WarriorPlus account. This value is an optional but recommended value that you can set to help verify that notifications are in fact sent by ' .
					                 'WarriorPlus for your account.', 'edd-warriorplus'),
					    'type' => 'text',
					    'size' => 'regular',
				    ),
				    array(
					    'id'   => 'edd_warriorplus_generate_license',
					    'name' => __('Show License Key In WarriorPlus', 'edd-warriorplus'),
					    'desc' => __('Check if you want license keys displayed to buyers on the WarriorPlus "Thank You For Your Purchase" page.  Note: This requires the Software Licensing plugin.  ' .
					                 'If you enable this after previously setting up notification links in WarriorPlus, you will need to add the Key Generation URL now shown on all downloads to the WarriorPlus ' .
					                 'configuration.', 'edd-warriorplus'),
					    'type' => 'checkbox'
				    ),
				    array(
					    'id'   => 'edd_warriorplus_generate_license_template_text',
					    'name' => __('License Key Template Text', 'edd-warriorplus'),
					    'desc' => __('Enter the license key text you would like displayed on WarriorPlus when a purchase is made. Use the template tags below to customize the message.', 'edd-warriorplus') . '<br />' .
					              '{licenseKey} - ' . __('The license key generated for the purchase', 'edd-warriorplus') . '<br/>' .
					              '{name} - ' . __('The customer\'s name', 'edd-warriorplus') . '<br/>' .
					              '{username} - ' . __('The new username for the user account', 'edd-warriorplus') . '<br/>' .
					              '{email} - ' . __('The customer\'s email account that has been used for new user account.', 'edd-warriorplus'),
					    'type' => 'text',
					    'size' => 'large',
					    'std'  => __('The license key for your purchase is {licenseKey}')
				    ),
				    array(
					    'id'   => 'edd_warriorplus_create_new_user',
					    'name' => __('Create New User On Purchase', 'edd-warriorplus'),
					    'desc' => __('Check if you would like a new user created when a purchase is made through WarriorPlus.  If checked, an email will automatically be sent to the user when their account is created.  This email can be customized under the Emails tab.', 'edd-warriorplus'),
					    'type' => 'checkbox'
				    ),
			    )
		    );

		    return array_merge( $settings, $plugin_settings );
	    }

        //////////////////
        // Private methods
        //////////////////

        /**
         * Utility debug method that logs to WordPress logs
         *
         * @access      private
         * @since       1.0.0
         *
         * @param       string $log The message to log
         */
        private function debug($log)
        {
            if (true === WP_DEBUG && true === self::DEBUG) {
                if (is_array($log) || is_object($log)) {
                    error_log(print_r($log, true));
                } else {
                    error_log($log);
                }
            }
        }


	    /**
	     * Function that will echo any license key associated with the download.
	     *
	     * @access      private
	     * @since       1.0.0
	     * @param       int $payment The payment the download was purchased under and the license is associated with
	     * @param       string $name The name of the customer
	     * @param       string $email The email address of the customer
	     **/
	    private function echoLicenseKey($payment, $name, $email) {
		    global $edd_options;

		    if ($this->isSLPluginActive()
		           && class_exists( 'EDD_Software_Licensing' )) {
			    $this->debug( 'Checking if need to echo license key' );

			    $licensing   = EDD_Software_Licensing::instance();
			    $licenseKeys = $licensing->get_licenses_of_purchase( $payment );

			    // Only output a license key if the purchase resulted in one
			    if ( ! empty( $licenseKeys ) && count( $licenseKeys ) > 0 ) {
				    $this->debug( 'Need to echo license key' );
				    $templateMessage = $edd_options['edd_warriorplus_generate_license_template_text'];

				    $licenseID  = $licenseKeys[0]->ID;
				    $licenseKey = $licensing->get_license_key( $licenseID );

				    $licenseMessage = $this->replaceTokens( $templateMessage, $name, $email, null, $email, $licenseKey );

				    $this->debug( 'Echoing license key: ' . $licenseMessage );
				    echo $licenseMessage;
			    }
		    }
	    }


        /**
         * Function that can be used to verify that the IPN request received is a
         * valid request
         *
         * @access      private
         * @since       1.0.0
         * @return      bool True if the ipn request verifies successfully, false otherwise.
         **/
        private function ipnVerification()
        {
            //  Get the IPN key
            global $edd_options;
            $edd_secret_key = isset($edd_options['edd_warriorplus_secret_key']) ? trim($edd_options['edd_warriorplus_secret_key']) : null;
	        $this->debug('Key: ' . $edd_secret_key);
            $request_secret_key = isset($_POST[self::QPARAM_SECRETKEY]) ? trim($_POST[self::QPARAM_SECRETKEY]) : null;

            if ($edd_secret_key != null) {
                $this->debug('Verifying secret key');
                return $edd_secret_key == $request_secret_key;
            }

            // Default to true if no secret key is specified
            return true;
        }


        /**
         * Returns true if the site has been configured to return license keys on keygen requests
         *
         * @access      private
         * @since       1.0.0
         * @return bool True if the site has been configured to return license keys on keygen requests, false otherwise.
         */
        private function isKeygenEnabled()
        {
            global $edd_options;
            return isset($edd_options['edd_warriorplus_generate_license']) ? $edd_options['edd_warriorplus_generate_license'] : false;
        }


        /**
         * Determines if the EDD Software Licensing plugin is active
         *
         * @access      private
         * @since       1.0.0
         * @return bool True if the EDD Software Licensing plugin is active, false otherwise.
         */
        private function isSLPluginActive()
        {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
            return is_plugin_active('edd-software-licensing/edd-software-licenses.php');
        }


        /**
         * Send the 'new user' message to a new customer
         *
         * @access      private
         * @since       1.0.0
         *
         * @global      array $edd_options The EDD options and settings
         * @param       string $name The name of the new user
         * @param       string $email The email address to mail to
         * @param       string $username The generated username for the new user account
         * @param       string $password The generated password for the new user account
         */
        private function mailToNewUser($name, $email, $username, $password)
        {
            global $edd_options;

            // Setup the email message
            $from_email = isset($edd_options['from_email']) ? $edd_options['from_email'] : get_option('admin_email');
            $from_name = isset($edd_options['from_name']) ? $edd_options['from_name'] : get_bloginfo('name');
            $message = isset($edd_options['edd_warriorplus_new_user_email_message']) ? $edd_options['edd_warriorplus_new_user_email_message'] : '';
            $subject = isset($edd_options['edd_warriorplus_new_user_email_subject']) ? trim($edd_options['edd_warriorplus_new_user_email_subject']) : '';

            // Replace any tokens in the email
            $message = $this->replaceTokens($message, $name, $username, $password, $email, null);

            // Send the message
            $headers = "From: " . stripslashes_deep(html_entity_decode($from_name, ENT_COMPAT, 'UTF-8')) . " <$from_email>\r\n";
            $headers .= "Reply-To: " . $from_email . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=utf-8\r\n";

            $this->debug('Sending email to ' . $email);
            wp_mail($email, $subject, $message, $headers);
        }


        /**
         * Process a response - this includes validating the the notification
         * as well as actually completing the manual purchase
         *
         * @access      private
         * @since       1.0.0
         * @param $echoLicenseKey bool Indicates that the license key should be echoed in the response
         */
        private function processIPNRequest($echoLicenseKey)
        {
            global $edd_options;

            // Trash any slashes in the WordPress POST array
            $_POST = stripslashes_deep($_POST);

            if ($this->ipnVerification()) {
                $this->debug('Processing...');
                $transactionType = isset($_POST[self::QPARAM_TRANTYPE]) ? $_POST[self::QPARAM_TRANTYPE] : '';
                $tranID = isset($_POST[self::QPARAM_TRANID]) ? trim($_POST[self::QPARAM_TRANID]) : '';
                if ($transactionType == self::QVALUE_SALE) {
                    // Populate the EDD payment object
                    $productID = get_query_var(self::QPARAM_EDDID);
                    $price = ($_POST[self::QPARAM_AMOUNT]);
                    $email = $_POST[self::QPARAM_CUSTEMAIL];
                    $name = $_POST[self::QPARAM_CUSTNAME];

	                // Before we attempt to create the payment, check for a duplicate
	                $args = array(
		                'post_type'  => 'edd_payment',
		                'meta_query' => array(
			                array(
				                'key'     => '_edd_warriorplus_tranid',
				                'value'   => $tranID,
				                'compare' => '='
			                )
		                )
	                );
	                $payments = get_posts($args);
	                if (count($payments) > 0) {
		                $this->debug('Duplicate payment received');
		                exit;
	                }

                    // Attempt to find a user to match this transaction to
                    $user = get_user_by('email', $email);

                    // If the option to create a new user is selected then check for an existing user and if there isn't one,
                    // create a new one
                    $create_new_user = isset($edd_options['edd_warriorplus_create_new_user']) ? $edd_options['edd_warriorplus_create_new_user'] : false;
                    if (!$user && $create_new_user) {
                        if (null == username_exists($email)) {

                            // Generate the password and create the user
                            $password = wp_generate_password(12, false);
                            $username = $email;
                            $new_user_id = wp_create_user($username, $password, $email);

                            // Set the nickname
                            wp_update_user(
                                array(
                                    'ID'         => $new_user_id,
                                    'nickname'   => $name,
                                    'first_name' => $name,
                                    'last_name'  => ''
                                )
                            );

                            // Set the role
                            $user = new WP_User($new_user_id);

                            // Email the user
                            $this->mailToNewUser($name, $email, $username, $password);
                        }
                    }

	                // Set the user info on the purchase (potentially with the user we just created
	                $user_info = array(
		                'email'      => $email,
		                'first_name' => $name,
		                'last_name'  => '',
		                'id'         => $user != null ? $user->ID : '',
		                'discount'   => null
	                );

	                // If variable pricing is being used on the product then match on the package number as well for tracking purposes
	                $item_options = array();
	                if ( isset( $_GET['edd_pn'] ) ) {
		                $edd_package_number = absint( $_GET['edd_pn'] );
		                $price_id           = $edd_package_number - 1;
		                $item_options       = array( array( 'price_id' => $price_id ) );
	                }

	                $cart_details[] = array(
		                'id'          => $productID,
		                'name'        => get_the_title( $productID ),
		                'item_number' => array(
			                'id'      => $productID,
			                'options' => $item_options,
		                ),
		                'price'       => $price,
		                'quantity'    => 1,
		                'tax'         => 0,
		                'in_bundle'   => 0
	                );

	                $payment_data = array(
		                'price'        => $price,
		                'user_email'   => $email,
		                'date'         => date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ),
		                'purchase_key' => strtolower( md5( uniqid() ) ),
		                'currency'     => edd_get_currency(),
		                'user_info'    => $user_info,
		                'cart_details' => $cart_details,
		                'status'       => 'pending',
		                'downloads'    => array(
			                'download' => array(
				                'id' => $productID
			                )
		                )
	                );

	                // Record the pending payment
	                $this->debug( 'Inserting payment' );
	                $payment = edd_insert_payment( $payment_data );
	                $this->debug( 'Payment ID: ' . $payment );
	                if ($payment) {
		                update_post_meta( $payment, '_edd_warriorplus_tranid', $tranID );
		                edd_set_payment_transaction_id( $payment, $tranID );
		                edd_insert_payment_note( $payment, 'WarriorPlus Transaction ID: ' . $tranID );

		                if ( get_query_var( self::QPARAM_LOGIPN ) == 1 ) {
			                edd_insert_payment_note( $payment, 'WarriorPlus POST URL: ' . print_r( $_POST, true ) );
		                }

		                edd_update_payment_status( $payment, 'publish' );

		                if ( $echoLicenseKey ) {
			                $this->echoLicenseKey( $payment, $name, $email );
		                }
	                }

                    // Empty the shopping cart
                    edd_empty_cart();
                } else if ($transactionType == self::QVALUE_REFUND) {
                    // Find the correct payment history post based on the WarriorPlus parent transaction ID
                    $parentTranID = isset($_POST[self::QPARAM_TRANID]) ? $_POST[self::QPARAM_TRANID] : null;
                    if ($parentTranID == null) {
                        return;
                    } else {
                        // Find any posts that match that (expecting only one)
                        $args = array(
                            'post_type'  => 'edd_payment',
                            'meta_query' => array(
                                array(
                                    'key'     => '_edd_warriorplus_tranid',
                                    'value'   => $parentTranID,
                                    'compare' => '='
                                )
                            )
                        );
                        $payments = get_posts($args);

                        // We shouldn't have multiple payments for a single purchase but if we do - refund them all
                        foreach ($payments as $payment_post) {
                            $paymentID = $payment_post->ID;

                            edd_insert_payment_note($paymentID, sprintf(__('WarriorPlus Payment #%s Refunded', 'edd'), $parentTranID));
                            edd_update_payment_status($paymentID, 'refunded');
                        }
                    }
                }
            }
        }


        /**
         * Replace any tokens in a text message with the provided values.
         *
         * @access private
         * @since 1.0.0
         * @param $message string The tokenized message
         * @param $name string The name of the user
         * @param $username string The WordPress username for the user
         * @param $password string The WordPress password for the user
         * @param $email string The email address of the user
         * @param $licenseKey string The license key of the purchase
         *
         * @return string The tokenized message with all tokens replaced with the provided values
         */
        private function replaceTokens($message, $name, $username, $password, $email, $licenseKey) {
            // Replace any tokens in the email
            $message = str_replace('{name}', $name, $message);
            $message = str_replace('{username}', $username, $message);
            $message = str_replace('{password}', $password, $message);
            $message = str_replace('{email}', $email, $message);
            $message = str_replace('{licenseKey}', $licenseKey, $message);
            return wpautop($message);
        }
    }
} // End if class_exists check


/**
 * The main function responsible for returning the one true EDD_WarriorPlus
 * instance to functions everywhere
 *
 * @since       1.0.0
 * @return      \EDD_WarriorPlus The one true EDD_WarriorPlus
 */
function EDD_WarriorPlus_load() {
    if( ! class_exists( 'Easy_Digital_Downloads' ) ) {
        if( ! class_exists( 'EDD_Extension_Activation' ) ) {
            require_once __DIR__ . '/includes/class-extension-activation.php';
        }

        $activation = new EDD_Extension_Activation( plugin_dir_path( __FILE__ ), basename( __FILE__ ) );
        $activation->run();

        return null;
    } else {
        return EDD_WarriorPlus::instance();
    }
}
add_action( 'plugins_loaded', 'EDD_WarriorPlus_load' );


/**
 * The activation hook is called outside of the singleton because WordPress doesn't
 * register the call from within the class, since we are preferring the plugins_loaded
 * hook for compatibility, we also can't reference a function inside the plugin class
 * for the activation function. If you need an activation function, put it here.
 *
 * @since       1.0.0
 * @return      void
 */
function edd_plugin_name_activation() {
    /* Activation functions here */
}
register_activation_hook( __FILE__, 'edd_plugin_name_activation' );