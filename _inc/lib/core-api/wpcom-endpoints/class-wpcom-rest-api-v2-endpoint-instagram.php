<?php
/**
 * REST API endpoint for the Instagram connections.
 *
 * @package Jetpack
 * @since 8.5.0
 */

use Automattic\Jetpack\Connection\Client;

/**
 * Instagram connections helper API.
 *
 * @since 8.5
 */
class WPCOM_REST_API_V2_Endpoint_Instagram extends WP_REST_Controller {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'wpcom/v2';
		$this->rest_base = 'instagram';

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the route.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/connect-url',
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_instagram_connect_url' ),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/delete-access-token',
			array(
				'args'     => array(
					'access_token' => array(
						'description' => __( 'An Instagram Keyring access token.', 'jetpack' ),
						'type'        => 'string',
						'required'    => true,
					),
				),
				'methods'  => WP_REST_Server::DELETABLE,
				'callback' => array( $this, 'delete_instagram_access_token' ),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/gallery',
			array(
				'args'     => array(
					'access_token' => array(
						'description' => __( 'An Instagram Keyring access token.', 'jetpack' ),
						'type'        => 'string',
						'required'    => true,
					),
					'count'        => array(
						'description'       => __( 'How many Instagram posts?', 'jetpack' ),
						'type'              => 'int',
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_instagram_gallery' ),
			)
		);
	}

	/**
	 * Get the Instagram connect URL.
	 *
	 * @return mixed
	 */
	public function get_instagram_connect_url() {
		$site_id = $this->get_site_id();
		if ( is_wp_error( $site_id ) ) {
			return $site_id;
		}

		$path     = sprintf( '/sites/%d/external-services', $site_id );
		$response = Client::wpcom_json_api_request_as_user( $path );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body        = json_decode( wp_remote_retrieve_body( $response ) );
		$connect_url = $body->services->instagram->connect_URL;

		return $connect_url;
	}

	/**
	 * Delete a stored Instagram access token.

	 * @param  Object $request - request passed from WP.
	 * @return mixed
	 */
	public function delete_instagram_access_token( $request ) {
		$site_id = $this->get_site_id();
		if ( is_wp_error( $site_id ) ) {
			return $site_id;
		}

		$path     = sprintf( '/sites/%d/instagram/%s', $site_id, $request['access_token'] );
		$response = Client::wpcom_json_api_request_as_blog(
			$path,
			2,
			array(
				'headers' => array( 'content-type' => 'application/json' ),
				'method'  => 'DELETE',
			),
			null,
			'wpcom'
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$response_code = wp_remote_retrieve_response_code( $response );

		return $response_code;
	}

	/**
	 * Get the Instagram Gallery.
	 *
	 * @param  Object $request - request passed from WP.
	 * @return mixed
	 */
	public function get_instagram_gallery( $request ) {
		$site_id = $this->get_site_id();
		if ( is_wp_error( $site_id ) ) {
			return $site_id;
		}

		$path     = sprintf( '/sites/%d/instagram/%s?count=%d', $site_id, $request['access_token'], (int) $request['count'] );
		$response = Client::wpcom_json_api_request_as_blog(
			$path,
			2,
			array( 'headers' => array( 'content-type' => 'application/json' ) ),
			null,
			'wpcom'
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ) );

		return $body;
	}

	/**
	 * Get the WPCOM or self-hosted site ID.
	 *
	 * @return mixed
	 */
	private function get_site_id() {
		$is_wpcom = ( defined( 'IS_WPCOM' ) && IS_WPCOM );
		$site_id  = $is_wpcom ? get_current_blog_id() : Jetpack_Options::get_option( 'id' );
		if ( ! $site_id ) {
			return new WP_Error(
				'unavailable_site_id',
				__( 'Sorry, something is wrong with your Jetpack connection.', 'jetpack' ),
				403
			);
		}
		return (int) $site_id;
	}
}

wpcom_rest_api_v2_load_plugin( 'WPCOM_REST_API_V2_Endpoint_Instagram' );
