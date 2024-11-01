<?php
/**
 * Plugin Name: Wp Dashboard Widgets Disable
 * Plugin URI:  http://wordpress.org/plugins/wp-dashboard-widgets-disable
 * Description: Control visibility of dashboard widgets with the easy to use interface, just select the widgets you'd like to hide.
 * Tags:        hide dashboard widgets, dashboard widgets, wp dashboard widgets disable, hide admin-bar based on user roles, hide admin-bar based on user capabilities
 * Version:     1.0.0
 * Author:      P. Roy
 * Author URI:  https://www.proy.info
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wp-dashboard-widgets-disable
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) { die; }

class WPDashboardWidgets {

    protected $plugin_name;
    protected $version;
    public $nonceName = 'wpdwd_options';
    public $hiddenItemsOptionName = 'wpdwd_settings';
	protected $dashboard_widgets_option = 'rplus_wp_widget_disable_dashboard_option';

    public function __construct() {

        $this->plugin_name = 'wp-dashboard-widgets-disable';
        $this->version = '1.0.0';
        //$this->load_dependencies();
        //$this->load_options();

        add_action( 'admin_init', array( $this, 'register_setting' ) );

        add_action( 'admin_menu', array( $this, 'addMenuPages' ) );
        add_action( 'network_admin_menu', [ $this, 'addMenuPages' ] );
        //add_action( 'network_admin_edit_wp-widget-disable', [ $this, 'save_network_options' ] );
        //add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10, 1 );

        // Get and disable the dashboard widgets.
		//add_action( 'load-index.php', [ $this, 'disable_dashboard_widgets_with_remote_requests' ] );
		add_action( 'wp_dashboard_setup', [ $this, 'disable_dashboard_widgets' ], 100 );
		//add_action( 'wp_network_dashboard_setup', [ $this, 'disable_dashboard_widgets' ], 100 );
    }

    /**
	 * Sanitize dashboard widgets user input.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input Dashboards widgets to disable.
	 *
	 * @return array
	 */
	public function sanitize_dashboard_widgets( $input ) {
		// If there are settings errors the input was already sanitized.
		// See https://core.trac.wordpress.org/ticket/21989.
		/* if ( $this->has_settings_errors() ) {
			return $input;
		} */

		// Create our array for storing the validated options.
		$output  = [];
		$message = null;

		if ( empty( $input ) ) {
			$message = __( 'All dashboard widgets are enabled again.', 'wp-widget-disable' );
		} else {
			// Loop through each of the incoming options.
			foreach ( array_keys( $input ) as $key ) {
				// Check to see if the current option has a value. If so, process it.
				if ( isset( $input[ $key ] ) ) {
					// Strip all HTML and PHP tags and properly handle quoted strings.
					$output[ $key ] = wp_strip_all_tags( stripslashes( $input[ $key ] ) );
				}
			}

			$output_count = count( $output );
			if ( 1 === $output_count ) {
				$message = __( 'Settings saved. One dashboard widget disabled.', 'wp-widget-disable' );
			} else {
				$message = sprintf(
					/* translators: %d: number of disabled widgets */
					_n(
						'Settings saved. %d dashboard widget disabled.',
						'Settings saved. %d dashboard widgets disabled.',
						number_format_i18n( $output_count ),
						'wp-widget-disable'
					),
					$output_count
				);
			}
		}

		if ( $message ) {
			add_settings_error(
				'wp-widget-disable',
				'settings_updated',
				$message,
				'updated'
			);
		}

		return $output;
	}

    /**
	 * Register the settings.
	 *
	 * @since 1.0.0
	 */
    public function register_setting() {
        if ( is_admin() ) {
            ob_start();// this is require to resolve redirect issue
            add_action( 'admin_head', array( $this, 'ckbCheckToggle' ) );
        }
    }

    public function ckbCheckToggle() {
        ?>
        <script>
            (function($) {

                $(function() {

                    $("#ckbCheckAlldw").click(function () {
                        $(".checkwdBoxClass").prop('checked', $(this).prop('checked'));
                    });

                    $(".checkwdBoxClass").change(function(){
                        if (!$(this).prop("checked")){
                            $("#ckbCheckAlldw").prop("checked",false);
                        }else{
                            var allcheckd = true;
                            $('.checkwdBoxClass').each(function() {
                                if (!$(this).prop("checked")){
                                    allcheckd = false;
                                }
                            });
                            if(allcheckd === true) $("#ckbCheckAlldw").prop("checked",true);
                        }
                    });

                });
            })(jQuery);
        </script>
        <?php
    }

    public function addMenuPages()  {

        add_options_page(
            __('Dashboard Widgets Disable', $this->plugin_name),
            __('Dashboard Widgets Disable', $this->plugin_name),
            'manage_options',
            $this->plugin_name . '_options',
            array(
                $this,
                'settingsPage'
            )
        );

        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(&$this, 'plugin_settings_link'), 10, 2 );

    }

    public function plugin_settings_link($links, $file) {
        $settings_link = '<a href="options-general.php?page='.$this->plugin_name . '_options">' . __('Settings', $this->plugin_name) . '</a>';
        array_unshift($links, $settings_link); // before other links
        return $links;
    }

    /**
	 * Get the default dashboard widgets.
	 *
	 * @return array Sidebar widgets.
	 */
	protected function get_default_dashboard_widgets() {
		global $wp_meta_boxes;

		$screen = is_network_admin() ? 'dashboard-network' : 'dashboard';
		$action = is_network_admin() ? 'wp_network_dashboard_setup' : 'wp_dashboard_setup';

		$current_screen = get_current_screen();

		if ( ! isset( $wp_meta_boxes[ $screen ] ) || ! is_array( $wp_meta_boxes[ $screen ] ) ) {
			require_once ABSPATH . '/wp-admin/includes/dashboard.php';

			set_current_screen( $screen );

			remove_action( $action, [ $this, 'disable_dashboard_widgets' ], 100 );

			wp_dashboard_setup();

			if ( is_callable( [ 'Antispam_Bee', 'add_dashboard_chart' ] ) ) {
				Antispam_Bee::add_dashboard_chart();
			}

			add_action( $action, [ $this, 'disable_dashboard_widgets' ], 100 );
		}

		if ( isset( $wp_meta_boxes[ $screen ][0] ) ) {
			unset( $wp_meta_boxes[ $screen ][0] );
		}

		$widgets = [];

		if ( isset( $wp_meta_boxes[ $screen ] ) ) {
			$widgets = $wp_meta_boxes[ $screen ];
		}

		set_current_screen( $current_screen );

		/**
		 * Filters the available dashboard widgets.
		 *
		 * @param array $widgets The globally available dashboard widgets.
		 */
		return apply_filters( 'wp_widget_disable_default_dashboard_widgets', $widgets );
	}

    /**
	 * Retrieves the value of the option depending on current admin screen.
	 *
	 * @since 2.0.0
	 *
	 * @return array List of disabled widget IDs.
	 */
	protected function get_disabled_dashboard_widgets() {
		$widgets = (array) get_option( $this->hiddenItemsOptionName, [] );

		if ( is_network_admin() ) {
			$widgets = (array) get_site_option( $this->dashboard_widgets_option, [] );
		}

		return $widgets;
	}


    /**
	 * Disable dashboard widgets.
	 *
	 * Gets the list of disabled dashboard widgets and
	 * disables them for you in WordPress.
	 *
	 * @since 1.0.0
	 */
	public function disable_dashboard_widgets() {
		$widgets = $this->get_disabled_dashboard_widgets();

		//print_r($widgets);

		if ( ! $widgets ) {
			return;
		}

		foreach ( $widgets as $widget_id => $meta_box ) {
			if ( 'dashboard_welcome_panel' === $widget_id ) {
				remove_action( 'welcome_panel', 'wp_welcome_panel' );

				continue;
			}

			if ( 'try_gutenberg_panel' === $widget_id ) {
				remove_action( 'try_gutenberg_panel', 'wp_try_gutenberg_panel' );

				continue;
			}

			if ( 'dashboard_browser_nag' === $widget_id || 'dashboard_php_nag' === $widget_id ) {
				// Handled by ::disable_dashboard_widgets_with_remote_requests().

				continue;
			}

			remove_meta_box( $widget_id, get_current_screen()->base, $meta_box );
		}
	}


    public function settingsPage() {

		$this->saveSettings();

        $widgets = $this->get_default_dashboard_widgets();
		$wp_version = get_bloginfo( 'version' );

		$flat_widgets = [];

		foreach ( $widgets as $context => $priority ) {
			foreach ( $priority as $data ) {
				foreach ( $data as $id => $widget ) {
					if ( ! $widget ) {
						continue;
					}

					$widget['title']          = isset( $widget['title'] ) ? $widget['title'] : '';
					$widget['title_stripped'] = wp_strip_all_tags( $widget['title'] );
					$widget['context']        = $context;

					$flat_widgets[ $id ] = $widget;
				}
			}
		}

		$widgets = wp_list_sort( $flat_widgets, [ 'title_stripped' => 'ASC' ], null, true );
		//print_r($widgets);
		if ( ! $widgets ) {
			printf(
				'<p>%s</p>',
				__( 'Oops, we could not retrieve the dashboard widgets! Maybe there is another plugin already managing them?', 'wp-widget-disable' )
			);
			return;
		}

		$get_disabled_dashboard_widgets = $this->get_disabled_dashboard_widgets();
        //print_r($get_disabled_dashboard_widgets);
        ?>
        <style>

            .wrap td, .wrap th { text-align: left; }
            .form-table-wpdw{ padding: 10px; margin-bottom: 20px; }
            .form-table-wpdw th { padding: 5px; border-bottom: 1px solid #DFDFDF; }
            .form-table-wpdw td  { padding: 5px; border-bottom: 1px solid #DFDFDF; }
            .form-table-wpdw tr:last-child td  { border-bottom: 0;}
            ul.wpdashboardMetaboxs { column-count: 3; padding: 5px 0; list-style: none; font-weight:normal; }

        </style>
        <div class="wrap">
            <h2><?php echo __('Wp Dashboard Widgets Disable');?></h2>
            <p>This plugin will help to control dashboard widgets visibility. Check a widget name to hide</p>
            <form action="<?php echo esc_attr(admin_url('options-general.php?page=wp-dashboard-widgets-disable_options')); ?>" method="post">
                <?php wp_nonce_field($this->nonceName, $this->nonceName, true, true); ?>
                <table class="form-table-wpdw">
                <tbody>
					<tr>
                        <th scope="row"><label for="disableforall"><?php echo __('Select to hide all<br>Dashboard Widgets');?></label></th>
                        <td>
                            <?php
                                echo '<label class="allLabel"><input name="disableForAll" id="ckbCheckAlldw" value="yes" type="checkbox" class="">Select All</label>';
                            ?>
                            </td><td></td>
                    </tr>
					<tr>
                         <th scope="row"><label for="disableforall"><?php echo __('Dashboard Widgets');?></label></th>
                        <th>
                            <ul class="wpdashboardMetaboxs">
                                <?php
									if ( ! is_network_admin() ) {
										echo '<li><label class="metaboxLabel"><input name="dashboardMetaboxs[dashboard_welcome_panel]" '.checked( array_key_exists( 'dashboard_welcome_panel', $get_disabled_dashboard_widgets ), true, false ).' type="checkbox" value="normal" class="regular-checkbox checkwdBoxClass">'.wp_kses( 'Welcome Panel', [ 'span' => [ 'class' => true ] ] ).'</label></li>';
									}
									if ( version_compare( $wp_version, '5.1.0', '>=' ) ) {
										echo '<li><label class="metaboxLabel"><input name="dashboardMetaboxs[dashboard_php_nag]" '.checked( array_key_exists( 'dashboard_php_nag', $get_disabled_dashboard_widgets ), true, false ).' type="checkbox" value="normal" class="regular-checkbox checkwdBoxClass">'.wp_kses( 'PHP Update Required', [ 'span' => [ 'class' => true ] ] ).'</label></li>';
									}

									if (version_compare( $wp_version, '4.9.8-RC1', '>=' ) && version_compare( $wp_version, '5.0-alpha-43807', '<' )) {
										echo '<li><label class="metaboxLabel"><input name="dashboardMetaboxs[try_gutenberg_panel]" '.checked( array_key_exists( 'try_gutenberg_panel', $get_disabled_dashboard_widgets ), true, false ).' type="checkbox" value="normal" class="regular-checkbox checkwdBoxClass">'.wp_kses( 'Try Gutenberg Callout', [ 'span' => [ 'class' => true ] ] ).'</label></li>';
									}

									foreach ( $widgets as $key => $widget ) {
                                        echo '<li><label class="metaboxLabel"><input name="dashboardMetaboxs['.esc_attr( $key ).']" '.checked( array_key_exists( $key, $get_disabled_dashboard_widgets ), true, false ).' type="checkbox" value="'.esc_attr( $widget['context'] ).'" class="regular-checkbox checkwdBoxClass">'.wp_kses( $widget['title'], [ 'span' => [ 'class' => true ] ] ).'</label></li>';
                                    }

                                ?>
                            </ul>
                        </th><td></td>
                    </tr>

                    <?php //} ?>

                </tbody>
                </table>
                <input type="submit" class="button button-primary" value="<?php esc_html_e('SAVE CHANGES', $this->pluginName); ?>"/>
                <hr>
                <?php echo esc_html_e('This Plugin Developed by ',$pluginName);?><a href="https://www.proy.info" target="_blank">P. Roy</a>
            </form>
        </div>
        <?php

    }

    private function saveSettings() {
        global $menu;

        if (!isset($_POST[$this->nonceName])) {
            return false;
        }

        $verify = check_admin_referer($this->nonceName, $this->nonceName);

        //$_POST  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

		$data = [];

		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_POST['dashboardMetaboxs'] ) ) {
			$data = $this->sanitize_dashboard_widgets( $_POST['dashboardMetaboxs'] );
		}
		// phpcs:enable

		update_site_option( $this->hiddenItemsOptionName, $data );

        //print_r($_POST); exit;
        //$dashboardMetaboxs =      array_map( 'esc_attr', $_POST['dashboardMetaboxs'] );

        // we'll redirect to same page when saved to see results.
        // redirection will be done with js, due to headers error when done with wp_redirect
        //$adminPageUrl = admin_url('options-general.php?page='.$this->pluginName.'_options&saved='.$savedSuccess);
        //wp_safe_redirect( $adminPageUrl ); exit;
        //ob_end_clean();
        wp_safe_redirect( add_query_arg('updated', 'true', wp_get_referer() ) );
    }
}

new WPDashboardWidgets();
