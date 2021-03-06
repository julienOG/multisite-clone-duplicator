<?php

if( !class_exists( 'MUCD_Admin' ) ) {

    require_once MUCD_COMPLETE_PATH . '/lib/duplicate.php';

    class MUCD_Admin {

        /**
         * Register hooks used on admin side by the plugin
         */
        public static function hooks() {
            // Network admin case
            if (is_network_admin()) {
                add_action( 'network_admin_menu', array( __CLASS__, 'network_menu_add_duplicate' ) );
            }
            add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
        }

        /**
         * Do some actions at the beginning of an admin script
         */
        public static function admin_init() {
            // Hook to rows on network sites listing
            add_filter( 'manage_sites_action_links', array( __CLASS__, 'add_site_row_action' ), 10, 2 );
            // Network admin bar
            add_action('admin_bar_menu', array( __CLASS__, 'admin_network_menu_bar' ) , 300 );
            // Network setting page
            add_action('wpmu_options', array( __CLASS__, 'admin_network_option_page' ) );
            // Save Network setting page
            add_action('wpmuadminedit', array( __CLASS__, 'save_admin_network_option_page' ) );
        }

        /**
         * Adds 'Duplicate' entry to network admin-bar
         * @since 0.2.0
         * @param  WP_Admin_Bar $wp_admin_bar
         */
        public static function admin_network_menu_bar($wp_admin_bar ) {

            if ( current_user_can( 'manage_sites' ) ) {

                $wp_admin_bar->add_menu( array(
                    'parent' => 'network-admin',
                    'id'     => 'network-admin-duplicate',
                    'title'  => MUCD_NETWORK_MENU_DUPLICATION,
                    'href'   => network_admin_url('sites.php?page='. MUCD_SLUG_NETWORK_ACTION),
                ) ); 

                foreach ( (array) $wp_admin_bar->user->blogs as $blog ) {

                    if(MUCD_Functions::is_duplicable($blog->userblog_id)) {
                            $menu_id  = 'blog-' . $blog->userblog_id;
                            $wp_admin_bar->add_menu( array(
                                'parent' => $menu_id,
                                'id'     => $menu_id . '-duplicate',
                                'title'  => MUCD_NETWORK_MENU_DUPLICATE,
                                'href'   => network_admin_url('sites.php?page='. MUCD_SLUG_NETWORK_ACTION .'&amp;id=' . $blog->userblog_id),
                            ) );             
                    }

                }
            }

        }

        /**
         * Adds row action 'Duplicate' on site list
         * @since 0.2.0
         * @param array $actions
         * @param int $blog_id
         */
        public static function add_site_row_action( $actions, $blog_id ) {
            if (MUCD_Functions::is_duplicable($blog_id)) {
                $actions = array_merge( $actions, array(
                    'duplicate_link' => '<a href="'. network_admin_url('sites.php?page='. MUCD_SLUG_NETWORK_ACTION .'&amp;id=' . $blog_id).'">'. MUCD_NETWORK_MENU_DUPLICATE.'</a>'
                ));
            }
         
            return $actions;
        }
       
       /**
        * Adds 'Duplication' entry in sites menu
        * @since 0.2.0
        * @return [type] [description]
        */
        public static function network_menu_add_duplicate() {
            add_submenu_page( 'sites.php', MUCD_NETWORK_PAGE_DUPLICATE_TITLE, MUCD_NETWORK_MENU_DUPLICATE, 'manage_sites', MUCD_SLUG_NETWORK_ACTION, array( __CLASS__, 'network_page_admin_duplicate_site' ) );
        }
  
        /**
         * Check result from Duplication page / print the page
         * @since 0.2.0
         */
        public static function network_page_admin_duplicate_site() {
            global $current_site;

            // Capabilities test
            if( !current_user_can( 'manage_sites' ) ) {
                wp_die(MUCD_GAL_ERROR_CAPABILITIES);
            }

            // Getting Sites
            $site_list = MUCD_Functions::get_site_list();

            // Form Data
            $data = array(
                'source'        => (isset($_GET['id']))?$_GET['id']:0,
                'domain'        => '',
                'title'         => '',
                'email'         => '',
                'copy_files'    => 'yes',
                'keep_users'    => 'no',
                'log'           => 'no',
                'log-path'      => '',
                'advanced'      => 'hide-advanced-options'
            );

            // Manage Form Post
            if ( isset($_REQUEST['action']) && MUCD_SLUG_ACTION_DUPLICATE == $_REQUEST['action'] && !empty($_POST) ) {

                $data = MUCD_Admin::check_form($data);

                if (isset($data['error']) ) {
                    $form_message['error'] = $data['error']->get_error_message();
                }
                else {
                    $form_message = MUCD_Duplicate::duplicate_site($data);
                }
            }

            // Load template if at least one Site is available
            if( $site_list ) {

                $select_site_list = MUCD_Admin::select_site_list($site_list, $data['source']);

                MUCD_Admin::enqueue_script_network_duplicate();
                require_once MUCD_COMPLETE_PATH . '/template/network_admin_duplicate_site.php';
            }
            else {
                return new WP_Error( 'mucd_error', MUCD_GAL_ERROR_NO_SITE );
            }

            MUCD_Duplicate::close_log();

        }

        /**
         * Get select box with duplicable site list
         * @since 0.2.0
         * @param  array $site_list all the sites
         * @param  id $current_blog_id parameters
         * @return string the output
         */
        public static function select_site_list($site_list, $current_blog_id=null) {
            $output = '';

            if(count($site_list) == 1) {
                $blog_id = $site_list[0]['blog_id'];
            }
            else if(isset($current_blog_id) && MUCD_Functions::value_in_array($current_blog_id, $site_list, 'blog_id') && MUCD_Functions::is_duplicable($current_blog_id) ) {
                 $blog_id = $current_blog_id;
            }

            $output .= '<select name="site[source]">';
            foreach( $site_list as $site ) {
                if(isset($blog_id) && $site['blog_id']==$blog_id) {
                    $output .= '    <option selected value="'.$site['blog_id'].'">' . substr($site['domain'] . $site['path'], 0, -1) . '</option>';
                }
                else {
                $output .= '    <option value="'.$site['blog_id'].'">' . substr($site['domain'] . $site['path'], 0, -1) . '</option>';
                }
            }
            $output .= '</select>';

            $output .= '&emsp;<a href="'. network_admin_url("settings.php#mucd_duplication") .'" title="'.MUCD_NETWORK_PAGE_DUPLICATE_TOOLTIP.'">?</a>';

            return $output;
        }

        /**
         * Print log-error box
         * @since 0.2.0
         */
        public static function log_error_message() {
                $log_dir = MUCD_Duplicate::log_dir();
                echo '<div id="message" class="error">';
                echo '    <p>';
                if($log_dir=="") {
                     echo MUCD_LOG_ERROR;
                }
                else {
                    echo MUCD_CANT_WRITE_LOG . ' <strong>'. $log_dir .'</strong><br />';
                    echo MUCD_CHANGE_RIGHTS_LOG . '<br /><code>chmod 777 '. $log_dir .'</code>';                
                }
                echo '    </p>';
                echo '</div>';
        }

        /**
         * Print result message box error / updated
         * @since 0.2.0
         * @param  array $form_message messages to print
         */
        public static function result_message($form_message) {
            if(isset($form_message['error']) ) {
                echo '<div id="message" class="error">';
                echo '    <p>' . $form_message['error'] . '</p>';
                echo '</div>';
            }
            else {
                echo '<div id="message" class="updated">';
                echo '  <p>';
                echo '      <strong>' . $form_message['msg'] . ' : ' . '</strong>';
                switch_to_blog($form_message['site_id']);
                $user = get_current_user_id();
                echo '      <a href="' . get_dashboard_url($user) . '">' . MUCD_NETWORK_PAGE_DUPLICATE_DASHBOARD . '</a> - ';
                echo '      <a href="' . get_site_url() . '">' . MUCD_NETWORK_PAGE_DUPLICATE_VISIT . '</a> - '; 
                echo '      <a href="' . admin_url( 'customize.php' ) . '">' .MUCD_NETWORK_CUSTOMIZE . '</a>'; 
                if( $log_url = MUCD_Duplicate::log_url() ) {
                    echo ' - <a href="' . $log_url . '">' . MUCD_NETWORK_PAGE_DUPLICATE_VIEW_LOG . '</a>';
                }
                restore_current_blog();
                echo '  </p>';
                echo '</div>';
            }
        }



        /**
         * Enqueue scripts for Duplication page
         * @since 0.2.0
         */
        public static function enqueue_script_network_duplicate() {
            // Enqueue script for user suggest on mail input
            wp_enqueue_script( 'user-suggest' );
            // Enqueue script for advanced options and enable / disable log path text input
            wp_enqueue_script( 'mucd-duplicate', MUCD_URL . '/js/network_admin_duplicate_site.js' );       
        }

        /**
         * Enqueue scripts and style for Network Settings page
         * @since 0.2.0
         */
        public static function enqueue_script_network_settings() {
            // Enqueue script for network settings page
            wp_enqueue_script( 'mucd-duplicate', MUCD_URL . '/js/network_admin_settings.js' );
            // Enqueue style for network settings page
            wp_enqueue_style( 'mucd-duplicate-css', MUCD_URL . '/css/network_admin_settings.css' );     
        }

        /**
         * Duplication form validation
         * @since 0.2.0
         * @param  array $init_data default data
         * @return array $data validated data, or errors
         */
        public static function check_form($init_data) {

            $data = $init_data;
            $data['copy_files'] = 'no';                
            $data['keep_users'] = 'no';                 
            $data['log'] = 'no';                 

            // Check referer and nonce
            if(check_admin_referer( MUCD_DOMAIN )) {

                global $current_site;

                $error = array();

                // Merge $data / $_POST['site'] to get Posted data and fill form
                $data = array_merge($data, $_POST['site']);

                // format and check source
                $data['from_site_id'] = $data['source'];
                if ( empty( $data['from_site_id'] ) ) {
                    $error[] = new WP_Error( 'mucd_error', MUCD_NETWORK_PAGE_DUPLICATE_MISSING_FIELDS );
                }

                $domain = '';
                if ( preg_match( '|^([a-zA-Z0-9-])+$|', $data['domain'] ) )
                    $domain = strtolower( $data['domain'] );

                // If not a subdomain install, make sure the domain isn't a reserved word
                if ( ! is_subdomain_install() ) {
                    /** This filter is documented in wp-includes/ms-functions.php */
                    $subdirectory_reserved_names = apply_filters( 'subdirectory_reserved_names', array( 'page', 'comments', 'blog', 'files', 'feed' ) );
                    if ( in_array( $domain, $subdirectory_reserved_names ) ) {
                        $error[] = new WP_Error( 'mucd_error', sprintf( MUCD_NETWORK_PAGE_DUPLICATE_DOMAIN_ERROR_RESERVED_WORDS , implode( '</code>, <code>', $subdirectory_reserved_names ) ) );
                    }
                }

                if (empty( $domain)) {
                    $error[] = new WP_Error( 'mucd_error', MUCD_NETWORK_PAGE_DUPLICATE_DOMAIN_ERROR_REQUIRE );
                }
                if ( is_subdomain_install() ) {
                    $newdomain = $domain . '.' . preg_replace( '|^www\.|', '', $current_site->domain );
                    $path      = $current_site->path;
                } else {
                    $newdomain = $current_site->domain;
                    $path      = $current_site->path . $domain . '/';
                }

                // format and check title
                if ( empty( $data['title'] ) ) {
                    $error[] = new WP_Error( 'mucd_error', MUCD_NETWORK_PAGE_DUPLICATE_TITLE_ERROR_REQUIRE );
                }

                // format and check email admin
                if ( empty( $data['email'] ) ) {
                    $error[] = new WP_Error( 'mucd_error', MUCD_NETWORK_PAGE_DUPLICATE_EMAIL_MISSING );
                }
                $valid_mail = sanitize_email( $data['email'] );
                if (is_email( $valid_mail ) ){
                    $data['email'] = $valid_mail;
                }
                else {
                    $error[] = new WP_Error( 'mucd_error', MUCD_NETWORK_PAGE_DUPLICATE_EMAIL_ERROR_FORMAT );
                }

                $data['domain'] = $domain;
                $data['newdomain'] = $newdomain;
                $data['path'] = $path;

                if(isset($data['log']) && $data['log']=='yes' && (!isset($data['log-path']) || $data['log-path'] == "" || !MUCD_Functions::valid_path($data['log-path']) ) ) {
                    $error[] = new WP_Error( 'mucd_error', MUCD_NETWORK_PAGE_DUPLICATE_VIEW_LOG_PATH_EMPTY );
                }

                if(isset($error[0])) {
                    $data['error'] = $error[0];
                }
            }

            else {
                $data['error'] = MUCD_GAL_ERROR_CAPABILITIES;
            }

            return $data;
        }

       /**
         * Save duplication options on network settings page
         * @since 0.2.0
         */
        public static function save_admin_network_option_page() {


            if ( isset( $_POST['duplicables'] ) && $_POST['duplicables']=='all' ){
                check_admin_referer( 'siteoptions' );
                update_site_option( 'mucd_duplicables', 'all' );
            }

            else if ( isset( $_POST['duplicables-list'] ) ){
                check_admin_referer( 'siteoptions' );
                update_site_option( 'mucd_duplicables', 'selected' );
                MUCD_Option::set_duplicable_option($_POST['duplicables-list']);
            }

        }

        /**
         * Print duplication options on network settings page
         * @since 0.2.0
         */
        public static function admin_network_option_page() {

        MUCD_Admin::enqueue_script_network_settings();

        ?>
        <h3><?php echo MUCD_NETWORK_MENU_DUPLICATION; ?></h3>
                <table id="mucd_duplication" class="form-table">
                    <tr>
                        <th scope="row"><?php echo MUCD_NETWORK_SETTINGS_DUPLICABLE_WEBSITES; ?></th>
                        <td>
                        <label><input <?php checked( get_site_option( 'mucd_duplicables', 'all' ), 'all' ); ?> type="radio" id="radio-duplicables-all" name="duplicables" value="all"><?php echo MUCD_NETWORK_SETTINGS_DUPLICABLE_ALL; ?></label><br><br>
                        <label><input <?php checked( get_site_option( 'mucd_duplicables', 'all' ), 'selected' ); ?> type="radio" id="radio-duplicables-selected" name="duplicables" value="selected"><?php echo MUCD_NETWORK_SETTINGS_DUPLICABLE_SELECTED; ?></label><br><br>

                
                <?php
                $network_blogs = wp_get_sites();
                echo '<div class="multiselect" id="site-select-box">';
                //echo '<select id="site-select-box" name="site[source]">';
                foreach( $network_blogs as $blog ) {
                    //echo '    <option value="'.$blog['blog_id'].'">' . substr($blog['domain'] . $blog['path'], 0, -1) . '</option>';
                    echo '    <label><input ' . checked(get_blog_option( $blog['blog_id'], 'mucd_duplicable', "no"), 'yes', false) . ' class="duplicables-list" type="checkbox" name="duplicables-list[]" value="'.$blog['blog_id'].'" />' . substr($blog['domain'] . $blog['path'], 0, -1) . '</label>';
                }
                //echo '</select>';
                echo '</div>';
                ?>
                

                        </td>
                    </tr>
                </table>
            <?php
        }

    }
}