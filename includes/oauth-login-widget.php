<?php

class Discord_Oauth_Login_Widget extends WP_Widget {

    public $oauthUrl = '';

    /**
     * Sets up the widgets name etc
     */
    public function __construct() {
        $widget_ops = array( 
            'classname' => 'discord-login-widget',
            'description' => 'Login with Discord oAuth button',
        );
        parent::__construct( 'login_widget', 'Discord oAuth Login Button', $widget_ops );
        
        $client_id = get_option( 'client_id' );
        $client_secret = get_option( 'client_secret' );
        $scopes = get_option( 'oauth_scopes' );

        $qs = http_build_query(array(
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'scope' => $scopes,
            'response_type' => 'code',
            'redirect_uri' => site_url( 'wp-json/discord-sso-sync/callback', 'http' )
        ));

        $this->oauthUrl = 'https://discordapp.com/api/oauth2/authorize?' . $qs;
    }

    /**
     * Outputs the content of the widget
     *
     * @param array $args
     * @param array $instance
     */
    public function widget( $args, $instance ) {
        echo $args['before_widget'];
        if ( ! empty( $instance['title'] ) ) {
            echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
        }

            if ( is_user_logged_in() ) { 
                $user = wp_get_current_user();
                echo '<p class="login-status-text">Logged in as '. $user->display_name . ' ' . '<a href="' . wp_logout_url()  . '">Logout?</a></p>'; 
            } else { ?>
                <a class="discord-login-btn" href="<?php echo $this->oauthUrl; ?>">
                    <i class="fab fa-discord"></i> Sign in with Discord
                </a>
            <?php
            }
        ?>
        
        <?php
        echo $args['after_widget'];
    }

    /**
     * Outputs the options form on admin
     *
     * @param array $instance The widget options
     */
    public function form( $instance ) {
        // outputs the options form on admin
    }

    /**
     * Processing widget options on save
     *
     * @param array $new_instance The new options
     * @param array $old_instance The previous options
     *
     * @return array
     */
    public function update( $new_instance, $old_instance ) {
        // processes widget options to be saved
    }
}