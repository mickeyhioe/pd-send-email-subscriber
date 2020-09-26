<?php
/*
 * Plugin Name: PD Send Email Subscriber
 * Plugin URI: https://pixeldreams.com
 * Description: Plugin to send custom notification email to subscriber
 * Version: 1.0
 * Author: Pixel Dreams
 * Author URI: https://pixeldreams.com
 * License: GPL2
*/

class PD_Send_Notification_Email_Subscriber_Profile {

    public function __construct() {
        //Add Checkbox to User Profile
        add_action( 'show_user_profile',             array( $this, 'pd_additional_profile_field' ) );
        add_action( 'edit_user_profile',             array( $this, 'pd_additional_profile_field' ) );
        add_action( 'personal_options_update',       array( $this, 'pd_save_additional_profile_field' ) );
        add_action( 'edit_user_profile_update',      array( $this, 'pd_save_additional_profile_field' ) );
    }

    // Add Extra Field to User Profile
    public function pd_additional_profile_field( $user ) {

        $user_subscribe = get_the_author_meta( "subscribe_newsletter", $user->ID); 
        $checked = (isset($user_subscribe) && $user_subscribe) ? ' checked="checked"' : '';?>        
        
        <h3>Newsletter Subscription</h3>
        <table class="form-table">
        <tr>
            <th></th>
            <td>
                <label>
                <input 
                    type="checkbox" 
                    name="subscribe_newsletter" 
                    id="subscribe_newsletter" 
                    value="<?php echo esc_attr( $user_subscribe ); ?>" <?php echo $checked; ?> /><strong><?php _e("Yes, please subscribe"); ?></strong></label>                
            </td>
        </tr>
        </table><?php    
    }

    // Save Extra Field to User Profile
    public function pd_save_additional_profile_field( $user_id ) {
        if ( !current_user_can( 'edit_user', $user_id ) )
            return false;

        if ( isset($_POST['subscribe_newsletter'], $_POST['user_id'] ) ) {
            update_user_meta( $user_id, 'subscribe_newsletter', '1'); 
        } else { 
            update_user_meta( $user_id, 'subscribe_newsletter', NULL); 
        }        
    }

}

class PD_Send_Notification_Email_Subscriber_Admin_Column {

    public function __construct() {
        //Add Admin Column
        add_filter( 'manage_users_columns',          array( $this, 'add_subscribe_to_user_table' ) );
        add_filter( 'manage_users_custom_column',    array( $this, 'add_subscribe_to_user_table_row'), 10, 3 );
        add_filter( 'manage_users_sortable_columns', array( $this, 'add_subscribe_sortable_cake_column' ) );
        add_action( 'admin_footer',                  array( $this, 'add_subscribe_jquery_event' ) );
        add_action( 'wp_ajax_subscribemetasave',     array( $this, 'add_subscribe_process_ajax' ) );
    }

    // Admin Column for Users 
    public function add_subscribe_to_user_table( $column ) {
        $column['subscribe_newsletter'] = 'Subscribe';
        return $column;
    }

    // Add Checkbox for Subscribe Newsletter
    public function add_subscribe_to_user_table_row( $val, $column_name, $user_id ) {

        if( $column_name  == 'subscribe_newsletter' ) {
            return  $val.'<input type="checkbox" data-userid="'. $user_id .'" class="subscribe_checkbox" ' . checked(1, get_the_author_meta( "subscribe_newsletter", $user_id), false ) . '/><small style="display:block;color:#7ad03a"></small>';
        }
    }

    // Make the Column Sortable
    public function add_subscribe_sortable_cake_column( $columns ) {
        $columns['subscribe_newsletter'] = 'Subscribe';
        return $columns;
    }

    // Add jQuery script to website footer that allows to send AJAX request
    public function add_subscribe_jquery_event(){
    
        echo "<script>jQuery(function($){
            $('.subscribe_checkbox').click(function(){
                
                var checkbox = $(this),
                    checkbox_value = (checkbox.is(':checked') ? 1 : 0 );
                $.ajax({
                    type: 'POST',
                    data: {
                        action: 'subscribemetasave', // wp_ajax_{action} WordPress hook to process AJAX requests
                        value: checkbox_value,
                        user_id: checkbox.attr('data-userid'),
                        myajaxnonce : '" . wp_create_nonce( "activatingcheckbox" ) . "'
                    },
                    beforeSend: function( xhr ) {
                        checkbox.prop('disabled', true );
                    },
                    url: ajaxurl, // as usual, it is already predefined in /wp-admin
                    success: function(data){
                        checkbox.prop('disabled', false ).next().html(data).show().fadeOut(400);
                    }
                });
            });
        });</script>";
    
    }

    // Process our AJAX request    
    public function add_subscribe_process_ajax(){
    
        check_ajax_referer( 'activatingcheckbox', 'myajaxnonce' );
        if (update_user_meta( $_POST['user_id'], 'subscribe_newsletter', $_POST['value'])){ 
            echo 'Updated';
        }else{
            echo 'Failed';
        }
    
        die();
    }
}

class PD_Send_Notification_Email_Subscriber_Settings{

    private $options;

    public function __construct(){
        
        add_action( 'admin_menu',               array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init',               array( $this, 'page_init' ) );

        add_action( 'transition_post_status',   array( $this, 'notify_user_on_publish' ), 10, 3 );
    }

    // Admin Menu
    public function add_plugin_page(){

        // Add the menu item and page
        $page_title = 'Send Email to Subscribers';
        $menu_title = 'Send Email to Subscribers';
        $capability = 'manage_options';
        $slug = 'pd-send-email-settings';
        $callback = array( $this, 'create_admin_page' );

        add_submenu_page( 
            'options-general.php', 
            $page_title, 
            $menu_title, 
            $capability, 
            $slug, 
            $callback 
        );
    }

    // Admin Init
    public function create_admin_page() {
        // Set class property
        $this->options = get_option( 'pd_email_option' );
        ?>
        <div class="wrap">

            <h1>PD Send Email Subscriber</h1>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'pd_email_option_group' );
                do_settings_sections( 'pd-email-setting-admin' );
                submit_button('Save Settings');
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init() {        
        register_setting(
            'pd_email_option_group', // Option group
            'pd_email_option', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'setting_section_id', // ID
            'Settings', // Title
            array( $this, 'print_section_info' ), // Callback
            'pd-email-setting-admin' // Page
        );

        add_settings_field(
            'email_notification', 
            'Email Notification', 
            array( $this, 'email_notification_callback' ), 
            'pd-email-setting-admin', 
            'setting_section_id'
        );

        add_settings_field(
            'email_subject', // ID
            'Email Subject', // Title 
            array( $this, 'email_subject_callback' ), // Callback
            'pd-email-setting-admin', // Page
            'setting_section_id' // Section           
        );      

        add_settings_field(
            'email_body', 
            'Email Body', 
            array( $this, 'email_body_callback' ), 
            'pd-email-setting-admin', 
            'setting_section_id'
        );

        

        /* Preview Email 
        add_settings_field(
            'email_draft', 
            'Email Draft', 
            array( $this, 'email_draft_callback' ), 
            'pd-email-setting-admin', 
            'setting_section_id'
        );
        */
        
         
    }

    /**
     * Sanitize each setting field as needed
     */
    public function sanitize( $input ){
        $new_input = array();
        if( isset( $input['email_subject'] ) )
            $new_input['email_subject'] = sanitize_text_field( $input['email_subject'] );

        if( isset( $input['email_body'] ) )
            //$new_input['email_body'] = sanitize_text_field( htmlentities($input['email_body'] ));
            $new_input['email_body'] = wp_kses_post($input['email_body'] );

        if (isset($input['email_notification'] ) )
            $new_input['email_notification'] = $input['email_notification'];

        return $new_input;
    }

    /**
     * Print all fields
     */  
    public function print_section_info() {
        print 'Email settings to send notification to all subscribers when there is new published post';
    }

    public function email_subject_callback() {
        printf(
            '<input type="text" class="regular-text"  id="email_subject" name="pd_email_option[email_subject]" value="%s" />',
            isset( $this->options['email_subject'] ) ? esc_attr( $this->options['email_subject']) : ''
        );
    }

    public function email_body_callback() {
        $args = array(
            'textarea_name' => 'pd_email_option[email_body]'
        );

        wp_editor( 
            $this->options['email_body'],
            'email_body', 
            $args
        );
    }

    public function email_notification_callback() {        
        echo '<label><input type="checkbox" name="pd_email_option[email_notification]" id="email_notification" value="1"' . checked( 1, $this->options['email_notification'], false ) . '/>Send email to all subscribers <u>everytime</u> there is a new post being published</label>';
    }

    public function email_draft_callback() {
        ob_start();
        include("templates/email_header.php");
        echo $this->options['email_body'];
        include("templates/email_footer.php");
        $message = ob_get_contents();
        ob_end_clean();
        echo $message;
    }

    /**
     * Send email to subscribers
     */

    public function notify_user_on_publish( $new_status, $old_status, $post ) {
        if ( $new_status !== 'publish' || $old_status === 'publish' )
            return;
        if ( ! $post_type = get_post_type_object( $post->post_type ) )
            return;

        $options = get_option( 'pd_email_option');
    
        $email_notification = $options['email_notification'];
        $email_subject = $options['email_subject'];
        $email_body = $options['email_body'];
    
        $subscribers = get_users( array ( 
            'role'      => 'subscriber',
            'meta_key'  => 'subscribe_newsletter',
            'meta_value'=> 1
        ));
    
        $emails = array ();
    
        foreach ( $subscribers as $subscriber )
            $emails[] = $subscriber->user_email;
    
        $subject = "[".get_bloginfo('name')."] ";
        if($email_subject){
            $subject .= $email_subject;
        }else{
            $subject .= "New update!";
        }
    
        if($email_body){
            $body = $email_body;
        }else{
            $body = sprintf( '<p>Hey there is a new entry! See <%s></p>',
                get_permalink( $post )
            );
        }    
        
        $headers = array('Content-Type: text/html; charset=UTF-8');

        ob_start();
        include("templates/email_header.php");
        echo $body;
        include("templates/email_footer.php");
        $message = ob_get_contents();
        ob_end_clean();
    
        if($email_notification){
            wp_mail( $emails, $subject, $message, $headers );
        }
    }
    
}

new PD_Send_Notification_Email_Subscriber_Profile();
new PD_Send_Notification_Email_Subscriber_Admin_Column();
new PD_Send_Notification_Email_Subscriber_Settings();


