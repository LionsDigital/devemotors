<?php

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    WhatsAppMe
 * @subpackage WhatsAppMe/public
 * @author     Creame <hola@crea.me>
 */
class WhatsAppMe_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * The setings of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $settings    The current settings of this plugin.
	 */
	private $settings;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @since    2.0.0     Added visibility setting
	 * @since    2.1.0     Added message_badge
	 * @since    2.3.0     Added button_delay and whatsapp_web settings, message_delay in seconds
	 * @param    string    $plugin_name       The name of the plugin.
	 * @param    string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->settings = array(
			'show'          => false,
			'telephone'     => '',
			'mobile_only'   => false,
			'button_delay'  => 3,
			'whatsapp_web'  => false,
			'message_text'  => '',
			'message_delay' => 10,
			'message_badge' => 'no',
			'message_send'  => '',
			'position'      => 'right',
			'visibility'    => array( 'all' => 'yes' ),
		);

	}

	/**
	 * Get global settings and current post settings and prepare
	 *
	 * @since    1.0.0
	 * @since    2.0.0   Check visibility
	 * @since    2.2.0   Post settings can also change "telephone". Added 'whastapp_web' setting
	 * @since    2.3.0   Fix global $post incorrect post id on loops. WPML integration.
	 * @return   void
	 */
	public function get_settings() {

		// If use "global $post;" take first post in loop on archive pages
		$post = get_queried_object();

		$global_settings = get_option( 'whatsappme' );

		if ( is_array( $global_settings ) ) {
			// Clean unused saved settings
			$settings = array_intersect_key( $global_settings, $this->settings );
			// Merge defaults with saved settings
			$settings = array_merge( $this->settings, $settings );

			// miliseconds (<v2.3) to seconds
			if ( $settings['message_delay'] > 120 ) {
				$settings['message_delay'] = round( $settings['message_delay'] / 1000 );
			}

			// Load WPML/Polylang translated strings
			$settings['message_text'] = apply_filters( 'wpml_translate_single_string', $settings['message_text'], 'WhatsApp me', 'Call To Action' );
			$settings['message_send'] = apply_filters( 'wpml_translate_single_string', $settings['message_send'], 'WhatsApp me', 'Message' );

			// Post custom settings
			$post_settings = is_a( $post, 'WP_Post' ) ? get_post_meta( $post->ID, '_whatsappme', true ) : '';

			if ( is_array( $post_settings ) ) {
				// Move old 'hide' to new 'view' field
				if ( isset( $post_settings['hide'] ) ) {
					$post_settings['view'] = 'no';
					unset( $post_settings['hide'] );
				}

				$settings = array_merge( $settings, $post_settings );
			}

			// Prepare settings
			$settings['telephone']     = preg_replace( '/^0+|\D/', '', $settings['telephone'] );
			$settings['position']      = $settings['position'] != 'left' ? 'right' : 'left';
			$settings['mobile_only']   = $settings['mobile_only'] == 'yes';
			$settings['message_badge'] = $settings['message_text'] && $settings['message_badge'] == 'yes';
			$settings['message_send']  = $this->replace_message_variables( $settings['message_send'] );

			$settings['show'] = $settings['telephone'] != '';
			if ( $settings['show'] ) {
				$settings['show'] = isset( $settings['view'] ) ?
					$settings['view'] == 'yes' :
					$this->check_visibility( $settings['visibility'] );
			}
			unset( $settings['view'] );

			// Set true to link http://web.whatsapp.com instead http://api.whatsapp.com
			$settings['whatsapp_web'] = apply_filters( 'whatsappme_whatsapp_web', $settings['whatsapp_web'] == 'yes' );

			$this->settings = $settings;
		}

		// Apply filter to settings
		$this->settings = apply_filters( 'whatsappme_get_settings', $this->settings, $post );

		// Ensure not show if not phone
		if ( ! $this->settings['telephone'] ) {
			$this->settings['show'] = false;
		}
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 * @since    2.2.2     minified
	 */
	public function enqueue_styles() {

		if ( $this->settings['show'] ) {
			$styles = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? 'whatsappme.css' : 'whatsappme.min.css';
			wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/' . $styles, array(), $this->version, 'all' );
		}

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 * @since    2.2.2     minified
	 */
	public function enqueue_scripts() {

		if ( $this->settings['show'] ) {
			$script = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? 'whatsappme.js' : 'whatsappme.min.js';
			wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/' . $script, array( 'jquery' ), $this->version, true );
		}

	}

	/**
	 * Outputs WhatsApp button html and his settings on footer
	 *
	 * @since    1.0.0
	 */
	public function footer_html() {
		global $wp;

		if ( $this->settings['show'] ) {

			// Clean unnecessary settings on front
			$data = array_diff_key( $this->settings, array_flip( array( 'show', 'visibility', 'position' ) ) );

			$copy = apply_filters( 'whatsappme_copy', __( 'Powered by', 'creame-whatsapp-me' ) );

			$link_url = urlencode( home_url( $wp->request ) );
			$link_site = urlencode( get_bloginfo( 'name' ) );
			$link = "https://wame.chat/powered/?site=$link_site&url=$link_url";

			?>
			<div class="whatsappme <?php echo apply_filters( 'whatsappme_classes', "whatsappme--{$this->settings['position']}" ); ?>" data-settings="<?php echo esc_attr( json_encode( $data ) ); ?>">
				<div class="whatsappme__button">
					<svg class="whatsappme__button__open" viewBox="0 0 24 24"><path fill="#fff" d="M3.516 3.516c4.686-4.686 12.284-4.686 16.97 0 4.686 4.686 4.686 12.283 0 16.97a12.004 12.004 0 0 1-13.754 2.299l-5.814.735a.392.392 0 0 1-.438-.44l.748-5.788A12.002 12.002 0 0 1 3.517 3.517zm3.61 17.043l.3.158a9.846 9.846 0 0 0 11.534-1.758c3.843-3.843 3.843-10.074 0-13.918-3.843-3.843-10.075-3.843-13.918 0a9.846 9.846 0 0 0-1.747 11.554l.16.303-.51 3.942a.196.196 0 0 0 .219.22l3.961-.501zm6.534-7.003l-.933 1.164a9.843 9.843 0 0 1-3.497-3.495l1.166-.933a.792.792 0 0 0 .23-.94L9.561 6.96a.793.793 0 0 0-.924-.445 1291.6 1291.6 0 0 0-2.023.524.797.797 0 0 0-.588.88 11.754 11.754 0 0 0 10.005 10.005.797.797 0 0 0 .88-.587l.525-2.023a.793.793 0 0 0-.445-.923L14.6 13.327a.792.792 0 0 0-.94.23z"/></svg>
					<svg class="whatsappme__button__send" viewbox="0 0 400 400" fill="none" fill-rule="evenodd" stroke="#fff" stroke-linecap="round" stroke-width="33">
						<path class="wame_plain" stroke-dasharray="1096.67" stroke-dashoffset="1096.67" d="M168.83 200.504H79.218L33.04 44.284a1 1 0 0 1 1.386-1.188L365.083 199.04a1 1 0 0 1 .003 1.808L34.432 357.903a1 1 0 0 1-1.388-1.187l29.42-99.427"/>
						<path class="wame_chat" stroke-dasharray="1019.22" stroke-dashoffset="1019.22" d="M318.087 318.087c-52.982 52.982-132.708 62.922-195.725 29.82l-80.449 10.18 10.358-80.112C18.956 214.905 28.836 134.99 81.913 81.913c65.218-65.217 170.956-65.217 236.174 0 42.661 42.661 57.416 102.661 44.265 157.316"/>
					</svg>
					<?php if ($this->settings['message_badge']): ?><div class="whatsappme__badge">1</div><?php endif; ?>
				</div>
				<?php if ($this->settings['message_text']): ?>
					<div class="whatsappme__box">
						<div class="whatsappme__header">
							<svg viewBox="0 0 120 28"><path fill="#fff" fill-rule="evenodd" d="M117.2 17c0 .4-.2.7-.4 1-.1.3-.4.5-.7.7l-1 .2c-.5 0-.9 0-1.2-.2l-.7-.7a3 3 0 0 1-.4-1 5.4 5.4 0 0 1 0-2.3c0-.4.2-.7.4-1l.7-.7a2 2 0 0 1 1.1-.3 2 2 0 0 1 1.8 1l.4 1a5.3 5.3 0 0 1 0 2.3zm2.5-3c-.1-.7-.4-1.3-.8-1.7a4 4 0 0 0-1.3-1.2c-.6-.3-1.3-.4-2-.4-.6 0-1.2.1-1.7.4a3 3 0 0 0-1.2 1.1V11H110v13h2.7v-4.5c.4.4.8.8 1.3 1 .5.3 1 .4 1.6.4a4 4 0 0 0 3.2-1.5c.4-.5.7-1 .8-1.6.2-.6.3-1.2.3-1.9s0-1.3-.3-2zm-13.1 3c0 .4-.2.7-.4 1l-.7.7-1.1.2c-.4 0-.8 0-1-.2-.4-.2-.6-.4-.8-.7a3 3 0 0 1-.4-1 5.4 5.4 0 0 1 0-2.3c0-.4.2-.7.4-1 .1-.3.4-.5.7-.7a2 2 0 0 1 1-.3 2 2 0 0 1 1.9 1l.4 1a5.4 5.4 0 0 1 0 2.3zm1.7-4.7a4 4 0 0 0-3.3-1.6c-.6 0-1.2.1-1.7.4a3 3 0 0 0-1.2 1.1V11h-2.6v13h2.7v-4.5c.3.4.7.8 1.2 1 .6.3 1.1.4 1.7.4a4 4 0 0 0 3.2-1.5c.4-.5.6-1 .8-1.6.2-.6.3-1.2.3-1.9s-.1-1.3-.3-2c-.2-.6-.4-1.2-.8-1.6zm-17.5 3.2l1.7-5 1.7 5h-3.4zm.2-8.2l-5 13.4h3l1-3h5l1 3h3L94 7.3h-3zm-5.3 9.1l-.6-.8-1-.5a11.6 11.6 0 0 0-2.3-.5l-1-.3a2 2 0 0 1-.6-.3.7.7 0 0 1-.3-.6c0-.2 0-.4.2-.5l.3-.3h.5l.5-.1c.5 0 .9 0 1.2.3.4.1.6.5.6 1h2.5c0-.6-.2-1.1-.4-1.5a3 3 0 0 0-1-1 4 4 0 0 0-1.3-.5 7.7 7.7 0 0 0-3 0c-.6.1-1 .3-1.4.5l-1 1a3 3 0 0 0-.4 1.5 2 2 0 0 0 1 1.8l1 .5 1.1.3 2.2.6c.6.2.8.5.8 1l-.1.5-.4.4a2 2 0 0 1-.6.2 2.8 2.8 0 0 1-1.4 0 2 2 0 0 1-.6-.3l-.5-.5-.2-.8H77c0 .7.2 1.2.5 1.6.2.5.6.8 1 1 .4.3.9.5 1.4.6a8 8 0 0 0 3.3 0c.5 0 1-.2 1.4-.5a3 3 0 0 0 1-1c.3-.5.4-1 .4-1.6 0-.5 0-.9-.3-1.2zM74.7 8h-2.6v3h-1.7v1.7h1.7v5.8c0 .5 0 .9.2 1.2l.7.7 1 .3a7.8 7.8 0 0 0 2 0h.7v-2.1a3.4 3.4 0 0 1-.8 0l-1-.1-.2-1v-4.8h2V11h-2V8zm-7.6 9v.5l-.3.8-.7.6c-.2.2-.7.2-1.2.2h-.6l-.5-.2a1 1 0 0 1-.4-.4l-.1-.6.1-.6.4-.4.5-.3a4.8 4.8 0 0 1 1.2-.2 8.3 8.3 0 0 0 1.2-.2l.4-.3v1zm2.6 1.5v-5c0-.6 0-1.1-.3-1.5l-1-.8-1.4-.4a10.9 10.9 0 0 0-3.1 0l-1.5.6c-.4.2-.7.6-1 1a3 3 0 0 0-.5 1.5h2.7c0-.5.2-.9.5-1a2 2 0 0 1 1.3-.4h.6l.6.2.3.4.2.7c0 .3 0 .5-.3.6-.1.2-.4.3-.7.4l-1 .1a21.9 21.9 0 0 0-2.4.4l-1 .5c-.3.2-.6.5-.8.9-.2.3-.3.8-.3 1.3s.1 1 .3 1.3c.1.4.4.7.7 1l1 .4c.4.2.9.2 1.3.2a6 6 0 0 0 1.8-.2c.6-.2 1-.5 1.5-1a4 4 0 0 0 .2 1H70l-.3-1v-1.2zm-11-6.7c-.2-.4-.6-.6-1-.8-.5-.2-1-.3-1.8-.3-.5 0-1 .1-1.5.4a3 3 0 0 0-1.3 1.2v-5h-2.7v13.4H53v-5.1c0-1 .2-1.7.5-2.2.3-.4.9-.6 1.6-.6.6 0 1 .2 1.3.6.3.4.4 1 .4 1.8v5.5h2.7v-6c0-.6 0-1.2-.2-1.6 0-.5-.3-1-.5-1.3zm-14 4.7l-2.3-9.2h-2.8l-2.3 9-2.2-9h-3l3.6 13.4h3l2.2-9.2 2.3 9.2h3l3.6-13.4h-3l-2.1 9.2zm-24.5.2L18 15.6c-.3-.1-.6-.2-.8.2A20 20 0 0 1 16 17c-.2.2-.4.3-.7.1-.4-.2-1.5-.5-2.8-1.7-1-1-1.7-2-2-2.4-.1-.4 0-.5.2-.7l.5-.6.4-.6v-.6L10.4 8c-.3-.6-.6-.5-.8-.6H9c-.2 0-.6.1-.9.5C7.8 8.2 7 9 7 10.7c0 1.7 1.3 3.4 1.4 3.6.2.3 2.5 3.7 6 5.2l1.9.8c.8.2 1.6.2 2.2.1.6-.1 2-.8 2.3-1.6.3-.9.3-1.5.2-1.7l-.7-.4zM14 25.3c-2 0-4-.5-5.8-1.6l-.4-.2-4.4 1.1 1.2-4.2-.3-.5A11.5 11.5 0 0 1 22.1 5.7 11.5 11.5 0 0 1 14 25.3zM14 0A13.8 13.8 0 0 0 2 20.7L0 28l7.3-2A13.8 13.8 0 1 0 14 0z"/></svg>
							<div class="whatsappme__close"><svg viewBox="0 0 24 24"><path fill="#fff" d="M24 2.4L21.6 0 12 9.6 2.4 0 0 2.4 9.6 12 0 21.6 2.4 24l9.6-9.6 9.6 9.6 2.4-2.4-9.6-9.6L24 2.4z"/></svg></div>
						</div>
						<div class="whatsappme__message"><?php echo $this->formated_message(); ?></div>
						<?php if ($copy): ?><div class="whatsappme__copy"><?php echo $copy; ?> <a href="<?php echo $link; ?>" rel="nofollow noopener" target="_blank"><svg viewBox="0 0 72 17"><path fill="#fff" fill-rule="evenodd" d="M25.371 10.429l2.122-6.239h.045l2.054 6.239h-4.22zm32.2 2.397c-.439.495-.88.953-1.325 1.375-.797.755-1.332 1.232-1.604 1.43-.622.438-1.156.706-1.604.805-.447.1-.787.13-1.02.09a3.561 3.561 0 0 1-.7-.239c-.66-.318-1.02-.864-1.079-1.64-.058-.774.03-1.619.263-2.533.35-1.987 1.108-4.133 2.274-6.438a73.481 73.481 0 0 0-2.8 3.04c-.816.954-1.7 2.096-2.653 3.428a44.068 44.068 0 0 0-2.77 4.441c-.738 0-1.341-.159-1.808-.477-.427-.278-.748-.695-.962-1.252-.214-.556-.165-1.41.146-2.563l.204-.626c.097-.298.204-.606.32-.924.117-.318.234-.626.35-.924.117-.298.195-.507.234-.626v.06c.272-.756.603-1.56.991-2.415a56.92 56.92 0 0 1 1.4-2.832 62.832 62.832 0 0 0-3.266 3.875 61.101 61.101 0 0 0-2.945 3.995 57.072 57.072 0 0 0-2.886 4.71c-.387 0-.736-.044-1.048-.131l.195.545h-3.72l-1.23-3.786h-6.093L23.158 17h-3.605l6.16-17h3.674l4.357 12.16c.389-1.35.97-2.736 1.74-4.16a41.336 41.336 0 0 0 2.013-4.232.465.465 0 0 0 .058-.18c0-.039.02-.098.058-.178.04-.08.078-.199.117-.358.039-.159.097-.337.175-.536.039-.12.078-.219.117-.298a.465.465 0 0 0 .058-.18c.078-.277.175-.575.292-.893.116-.318.194-.597.233-.835V.25c-.039-.04-.039-.08 0-.119l.233-.12c.117-.039.292.02.525.18.156.08.292.179.408.298.272.199.564.427.875.685.311.259.583.557.816.895a2.9 2.9 0 0 1 .467 1.043c.078.358.039.735-.117 1.133a8.127 8.127 0 0 1-.35.775c0 .08-.038.159-.116.238a2.93 2.93 0 0 1-.175.298 7.05 7.05 0 0 0-.35.656c-.039.04-.058.07-.058.09 0 .02-.02.05-.059.089a61.988 61.988 0 0 1-1.633 2.385c-.544.755-.913 1.35-1.108 1.788a79.39 79.39 0 0 1 3.5-4.233 101.59 101.59 0 0 1 3.12-3.398C45.651 1.82 46.612.986 47.468.43c.739.278 1.341.596 1.808.954.428.318.768.676 1.02 1.073.253.398.244.835-.029 1.312l-1.4 2.325a36.928 36.928 0 0 0-1.749 3.279 53.748 53.748 0 0 1 1.633-1.848 46.815 46.815 0 0 1 4.024-3.875c.7-.597 1.38-1.113 2.041-1.55.739.278 1.341.596 1.808.953.428.318.768.676 1.02 1.073.253.398.243.835-.029 1.312-.155.318-.408.795-.758 1.43a152.853 152.853 0 0 0-2.04 3.846 97.87 97.87 0 0 0-.467.924c-.35.835-.632 1.55-.846 2.146-.214.597-.282.934-.204 1.014a.63.63 0 0 0 .291-.06c.234-.119.564-.348.992-.685.428-.338.875-.736 1.341-1.193.467-.457.914-.914 1.341-1.37.217-.232.409-.45.575-.657a15.4 15.4 0 0 1 .957-2.514c.34-.696.708-1.333 1.108-1.91.399-.576.778-1.044 1.137-1.402a19.553 19.553 0 0 1 1.796-1.7 32.727 32.727 0 0 1 1.497-1.164 8.821 8.821 0 0 1 1.317-.835C66.292.989 66.83.83 67.269.83c.32 0 .649.11.988.328.34.22.649.478.928.776.28.299.519.607.718.925.2.318.3.557.3.716.04.597-.06 1.253-.3 1.97a7.14 7.14 0 0 1-1.107 2.058 8.534 8.534 0 0 1-1.826 1.76 6.522 6.522 0 0 1-2.395 1.074c-.2.08-.36.06-.48-.06a.644.644 0 0 1-.179-.477c0-.358.14-.616.42-.776.837-.318 1.536-.735 2.095-1.253.559-.517.998-1.034 1.317-1.551.4-.597.699-1.213.898-1.85 0-.199-.09-.308-.27-.328a4.173 4.173 0 0 0-.448-.03 4.83 4.83 0 0 0-1.318.597c-.399.239-.848.577-1.347 1.014-.499.438-1.028 1.015-1.586 1.73-.918 1.154-1.587 2.298-2.006 3.432-.42 1.134-.629 1.979-.629 2.536 0 .915.19 1.482.569 1.7.38.22.728.329 1.048.329.638 0 1.347-.15 2.125-.448a16.248 16.248 0 0 0 2.305-1.104 30.05 30.05 0 0 0 2.126-1.342 27.256 27.256 0 0 0 1.646-1.224c.08-.04.18-.1.3-.179l.24-.12a.54.54 0 0 1 .239-.059c.08 0 .16.02.24.06.08.04.119.16.119.358 0 .239-.08.457-.24.656a19.115 19.115 0 0 1-2.245 1.82 35.445 35.445 0 0 1-2.185 1.403c-.759.437-1.497.855-2.215 1.253a8.461 8.461 0 0 1-1.647.387c-.499.06-.968.09-1.407.09-.998 0-1.796-.16-2.395-.477-.599-.319-1.048-.706-1.347-1.164a4.113 4.113 0 0 1-.599-1.372c-.1-.457-.15-.843-.15-1.161zm-42.354-1.111L17.887 0h3.514L17.02 17h-3.56L10.7 5.428h-.046L7.94 17H4.312L0 0h3.582L6.16 11.571h.045L9.035 0h3.354l2.783 11.715h.045z"/></svg></a></div><?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}

	}


	/**
	 * Format raw message text for html output.
	 * Also apply styles transformations like WhatsApp app.
	 *
	 * @since    1.3.0
	 * @since    2.3.0      apply replace_message_variables
	 * @return   string     message formated string
	 */
	public function formated_message() {

		$replacements = apply_filters( 'whatsappme_message_replacements', array(
			'/_(\S[^_]*\S)_/mu'    => '<em>$1</em>',
			'/\*(\S[^\*]*\S)\*/mu' => '<strong>$1</strong>',
			'/~(\S[^~]*\S)~/mu'    => '<del>$1</del>',
		) );

		$replacements_keys = array_keys( $replacements );

		// Split text into lines and apply replacements line by line
		$lines = explode( "\n", $this->settings['message_text'] );
		foreach ($lines as $key => $line) {
			$lines[$key] = preg_replace( $replacements_keys, $replacements, esc_html( $line ) );
		}

		return $this->replace_message_variables( implode( '<br>', $lines ) );

	}

	/**
	 * Format message send, replace vars.
	 *
	 * @since    1.4.0
	 * @since    2.3.0      renamed from formated_message_send to replace_message_variables
	 * @return   string     message formated string
	 */
	public function replace_message_variables( $string ) {
		global $wp;

		$replacements = apply_filters( 'whatsappme_message_send_replacements', array(
			'/\{SITE\}/i' => get_bloginfo( 'name' ),
			'/\{URL\}/i'  => home_url( $wp->request ),
			'/\{TITLE\}/i'=> $this->get_title(),
		) );

		return preg_replace( array_keys( $replacements ), $replacements, $string );

	}

	/**
	 * Get current page title
	 *
	 * @since    1.4.0
	 * @return   string     message formated string
	 */
	public function get_title() {

		if ( is_home() || is_singular() ) {
			$title = single_post_title( '', false );
		} elseif ( is_tax() ) {
			$title = single_term_title( '', false );
		} elseif ( function_exists( 'wp_get_document_title' ) ) {
			$title  = wp_get_document_title();

			// Try to remove sitename from $title for cleaner title
			$sep   = apply_filters( 'document_title_separator', '-' );
			$site  = get_bloginfo( 'name', 'display' );
			$title = str_replace( esc_html( convert_chars( wptexturize( " $sep " . $site ) ) ), '', $title);
		} else {
			$title = get_bloginfo( 'name' );
		}

		return apply_filters( 'whatsappme_get_title', $title );

	}

	/**
	 * Check visibility on current page
	 *
	 * @since    2.0.0
	 * @param    array       $options    array of visibility settings
	 * @return   boolean     is visible or not on current page
	 */
	public function check_visibility($options) {

		$global = isset( $options['all'] ) ? $options['all'] == 'yes' : true;

		// Check front page
		if ( is_front_page() ) {
			return isset( $options['front_page'] ) ? $options['front_page'] == 'yes' : $global;
		}

		// Check blog page
		if ( is_home() ) {
			return isset( $options['blog_page'] ) ? $options['blog_page'] == 'yes' : $global;
		}

		// Check 404 page
		if ( is_404() ) {
			return isset( $options['404_page'] ) ? $options['404_page'] == 'yes' : $global;
		}

		// Check WooCommerce
		if ( class_exists( 'WooCommerce' ) ) {
			$woo = isset( $options['woocommerce'] ) ? $options['woocommerce'] == 'yes' : $global;

			// Product page
			if ( is_product() ) {
				return isset( $options['product'] ) ? $options['product'] == 'yes' : $woo;
			}

			// Cart page
			if ( is_cart() ) {
				return isset( $options['cart'] ) ? $options['cart'] == 'yes' : $woo;
			}

			// Checkout page
			if ( is_checkout() ) {
				return isset( $options['checkout'] ) ? $options['checkout'] == 'yes' : $woo;
			}

			// Customer account pages
			if ( is_account_page() ) {
				return isset( $options['account_page'] ) ? $options['account_page'] == 'yes': $woo;
			}

			if ( is_woocommerce() ) {
				return $woo;
			}
		}

		// Check Custom Post Types
		if ( is_array( $options ) ) {
			foreach ( $options as $cpt => $view ) {
				if ( substr( $cpt, 0, 4 ) == 'cpt_' ) {
					$cpt = substr( $cpt, 4 );
					if ( is_singular( $cpt ) || is_post_type_archive( $cpt ) ) {
						return $view == 'yes';
					}
				}
			}
		}

		// Search results
		if ( is_search() ) {
			return isset( $options['search'] ) ? $options['search'] == 'yes' : $global;
		}

		// Check archives
		if ( is_archive() ) {

			// Date archive
			if ( isset( $options['date'] ) && is_date() ) {
				return $options['date'] == 'yes';
			}

			// Author archive
			if ( isset( $options['author'] ) && is_author() ) {
				return $options['author'] == 'yes';
			}

			return isset( $options['archive'] ) ? $options['archive'] == 'yes' : $global;
		}

		// Check singular
		if ( is_singular() ) {

			// Page
			if ( isset( $options['page'] ) && is_page() ) {
				return $options['page'] == 'yes';
			}

			// Post (or other custom posts)
			if ( isset( $options['post'] ) && is_single() ) {
				return $options['post'] == 'yes';
			}

			return isset( $options['singular'] ) ? $options['singular'] == 'yes' : $global;
		}

		return $global;
	}

	/**
	 * Hide on Elementor preview mode.
	 * Set 'show' false when is editing on Elementor
	 *
	 * @since    2.2.3
	 * @param    object      /Elementor/Preview instance
	 */
	public function elementor_preview_disable($elementor_preview) {

		$this->settings['show'] = apply_filters( 'whatsappme_elementor_preview_show', false );

	}
}
