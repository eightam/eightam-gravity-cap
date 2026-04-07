<?php
/**
 * Cap CAPTCHA field type for Gravity Forms.
 *
 * @package GravityCap
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Registers a "Cap CAPTCHA" field that renders a proof-of-work widget
 * and validates the resulting token server-side.
 */
class GF_Field_Cap extends GF_Field {

	/**
	 * Field type identifier.
	 *
	 * @var string
	 */
	public $type = 'cap_captcha';

	/**
	 * Title shown in the form editor.
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'Cap CAPTCHA', 'gravity-cap' );
	}

	/**
	 * Description shown in the form editor.
	 *
	 * @return string
	 */
	public function get_form_editor_field_description() {
		return esc_attr__( 'Adds a proof-of-work CAPTCHA to protect your form from spam.', 'gravity-cap' );
	}

	/**
	 * Icon in the form editor sidebar.
	 *
	 * @return string
	 */
	public function get_form_editor_field_icon() {
		return 'gform-icon--recaptcha';
	}

	/**
	 * Place the field in the Advanced Fields group.
	 *
	 * @return array
	 */
	public function get_form_editor_button() {
		return array(
			'group' => 'advanced_fields',
			'text'  => $this->get_form_editor_field_title(),
		);
	}

	/**
	 * Which field settings tabs to show in the editor.
	 *
	 * @return array
	 */
	public function get_form_editor_field_settings() {
		return array(
			'label_setting',
			'label_placement_setting',
			'description_setting',
			'css_class_setting',
			'error_message_setting',
			'conditional_logic_field_setting',
		);
	}

	/**
	 * Set default properties — hide the field label by default.
	 *
	 * @return array
	 */
	public function get_field_defaults() {
		return array(
			'label'          => 'CAPTCHA',
			'labelPlacement' => 'hidden_label',
		);
	}

	/**
	 * Render the field on the frontend or a placeholder in the editor.
	 *
	 * @param array  $form  The form object.
	 * @param string $value Current value.
	 * @param mixed  $entry The entry object.
	 * @return string
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {
		$field_id = sprintf( 'field_%d_%d', (int) rgar( $form, 'id' ), $this->id );

		// In the form editor or entry detail, show a placeholder.
		if ( $this->is_form_editor() || $this->is_entry_detail() ) {
			return sprintf(
				'<div class="ginput_container ginput_container_cap_captcha" id="%s">
					<div style="border:1px solid #ccc;border-radius:4px;padding:12px 16px;display:inline-flex;align-items:center;gap:8px;background:#fafafa;">
						<span style="display:inline-block;width:18px;height:18px;border:2px solid #999;border-radius:3px;"></span>
						<span style="color:#666;">%s</span>
					</div>
				</div>',
				esc_attr( $field_id ),
				esc_html__( 'Cap CAPTCHA Widget', 'gravity-cap' )
			);
		}

		// Frontend rendering.
		$server_url = egcap_get_setting( 'cap_server_url' );
		$site_key   = egcap_get_setting( 'cap_site_key' );

		if ( empty( $server_url ) || empty( $site_key ) ) {
			GFCommon::log_error( 'GF_Field_Cap: Cap server URL or site key is not configured.' );
			return '';
		}

		$endpoint = trailingslashit( trailingslashit( $server_url ) . $site_key );

		$i18n_attrs = array(
			'data-cap-i18n-initial-state'        => esc_attr__( 'Verify you are human', 'gravity-cap' ),
			'data-cap-i18n-verifying-label'      => esc_attr__( 'Verifying...', 'gravity-cap' ),
			'data-cap-i18n-solved-label'         => esc_attr__( 'Verified', 'gravity-cap' ),
			'data-cap-i18n-error-label'          => esc_attr__( 'Verification error', 'gravity-cap' ),
			'data-cap-i18n-troubleshooting-label' => esc_attr__( 'Having trouble?', 'gravity-cap' ),
			'data-cap-i18n-wasm-disabled'        => esc_attr__( 'Please enable JavaScript to continue.', 'gravity-cap' ),
			'data-cap-i18n-verify-aria-label'    => esc_attr__( 'Click to verify you are human', 'gravity-cap' ),
			'data-cap-i18n-verifying-aria-label'  => esc_attr__( 'Verification in progress', 'gravity-cap' ),
			'data-cap-i18n-verified-aria-label'   => esc_attr__( 'Successfully verified', 'gravity-cap' ),
			'data-cap-i18n-error-aria-label'      => esc_attr__( 'Verification failed', 'gravity-cap' ),
		);

		$attr_string = '';
		foreach ( $i18n_attrs as $attr => $val ) {
			$attr_string .= sprintf( ' %s="%s"', $attr, $val );
		}

		return sprintf(
			'<div class="ginput_container ginput_container_cap_captcha" id="%s"><cap-widget data-cap-api-endpoint="%s" style="--cap-widget-width:100%%"%s></cap-widget></div>',
			esc_attr( $field_id ),
			esc_url( $endpoint ),
			$attr_string
		);
	}

	/**
	 * Prevent GF from flagging the field as "required but empty".
	 *
	 * The real token lives in $_POST['cap-token'], not in the field's own input.
	 *
	 * @param int $form_id The form ID.
	 * @return bool
	 */
	public function is_value_submission_empty( $form_id ) {
		return false;
	}

	/**
	 * Validate the Cap token server-side.
	 *
	 * @param string|array $value The field value (unused — token comes from cap-token POST param).
	 * @param array        $form  The form object.
	 */
	public function validate( $value, $form ) {
		// Multi-page guard: only validate on the page where this field lives.
		$current_page = GFFormDisplay::get_current_page( rgar( $form, 'id' ) );
		if ( $current_page > 0 && (int) $current_page !== (int) $this->pageNumber ) {
			return;
		}

		$token = rgpost( 'cap-token' );

		if ( empty( $token ) ) {
			$this->failed_validation  = true;
			$this->validation_message = $this->errorMessage
				? $this->errorMessage
				: esc_html__( 'Please complete the CAPTCHA verification.', 'gravity-cap' );
			return;
		}

		$server_url = egcap_get_setting( 'cap_server_url' );
		$site_key   = egcap_get_setting( 'cap_site_key' );
		$secret     = egcap_get_setting( 'cap_secret' );

		if ( empty( $server_url ) || empty( $site_key ) || empty( $secret ) ) {
			$this->failed_validation  = true;
			$this->validation_message = esc_html__( 'CAPTCHA verification is not configured. Please contact the site administrator.', 'gravity-cap' );
			GFCommon::log_error( 'GF_Field_Cap: Missing Cap server URL, site key, or API secret in settings.' );
			return;
		}

		$verify_url = trailingslashit( trailingslashit( $server_url ) . $site_key ) . 'siteverify';

		$response = wp_remote_post( $verify_url, array(
			'timeout' => 10,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array(
				'secret'   => $secret,
				'response' => $token,
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			$this->failed_validation  = true;
			$this->validation_message = esc_html__( 'CAPTCHA verification failed due to a network error. Please try again.', 'gravity-cap' );
			GFCommon::log_error( 'GF_Field_Cap: wp_remote_post failed — ' . $response->get_error_message() );
			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body ) || empty( $body['success'] ) ) {
			$this->failed_validation  = true;
			$this->validation_message = esc_html__( 'CAPTCHA verification failed. Please try again.', 'gravity-cap' );
			$error_detail             = isset( $body['error'] ) ? $body['error'] : 'unknown';
			GFCommon::log_error( 'GF_Field_Cap: siteverify returned failure — ' . $error_detail );
			return;
		}

		GFCommon::log_debug( 'GF_Field_Cap: Token verified successfully.' );
	}

	/**
	 * Don't save any value to the entry — like the native CAPTCHA field.
	 *
	 * @param mixed  $value      The field value.
	 * @param array  $form       The form object.
	 * @param string $input_name The input name.
	 * @param int    $lead_id    The entry ID.
	 * @param array  $lead       The entry object.
	 * @return string
	 */
	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {
		return '';
	}

	/**
	 * Display in entry detail view.
	 *
	 * @param mixed  $value    The field value.
	 * @param string $currency Currency code.
	 * @param bool   $use_text Whether to use text.
	 * @param string $format   Output format.
	 * @param string $media    Media type.
	 * @return string
	 */
	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		return esc_html__( 'Verified', 'gravity-cap' );
	}

	/**
	 * Display in entry list view.
	 *
	 * @param mixed  $value    The field value.
	 * @param array  $entry    The entry object.
	 * @param string $field_id The field ID.
	 * @param array  $columns  The columns.
	 * @param array  $form     The form object.
	 * @return string
	 */
	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		return esc_html__( 'Verified', 'gravity-cap' );
	}
}
