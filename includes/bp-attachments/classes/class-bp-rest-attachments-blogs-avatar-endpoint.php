<?php
/**
 * BP REST: BP_REST_Attachments_Blogs_Avatar_Endpoint class
 *
 * @package BuddyPress
 * @since 6.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Blog avatar endpoints.
 *
 * @since 6.0.0
 */
class BP_REST_Attachments_Blogs_Avatar_Endpoint extends WP_REST_Controller {

	use BP_REST_Attachments;

	/**
	 * Constructor.
	 *
	 * @since 6.0.0
	 */
	public function __construct() {
		$this->namespace = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base = buddypress()->blogs->id;
	}

	/**
	 * Register the component routes.
	 *
	 * @since 6.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<blog_id>[\d]+)/avatar',
			array(
				'args'   => array(
					'blog_id' => array(
						'description' => __( 'A unique numeric ID for the blog.', 'buddypress' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => $this->get_item_collection_params(),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Fetch an existing blog avatar.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$args = array();

		foreach ( array( 'full', 'thumb' ) as $type ) {
			$args[ $type ] = bp_get_blog_avatar(
				array(
					'type'    => $type,
					'blog_id' => $request['blog_id'],
					'alt'     => $request['alt'],
					'no_grav' => (bool) $request['no_grav'],
				)
			);
		}
		
		// Get the avatar object.
		$avatar = $this->get_avatar_object( $args );

		if ( ! $avatar->full && ! $avatar->thumb ) {
			return new WP_Error(
				'bp_rest_attachments_blogs_avatar_no_image',
				__( 'Sorry, there was a problem fetching the blog avatar.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $avatar, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a blog avatar is fetched via the REST API.
		 *
		 * @since 6.0.0
		 *
		 * @param string            $avatar   The avatar.
		 * @param WP_REST_Response  $response The response data.
		 * @param WP_REST_Request   $request  The request sent to the API.
		 */
		do_action( 'bp_rest_attachments_blogs_avatar_get_item', $avatar, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to get a blog avatar.
	 *
	 * @since 6.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$retval = true;
		$blogs  = bp_blogs_get_blogs(
			array(
				'include_blog_ids'  => $request['blog_id'],
				'per_page'          => 1,
				'update_meta_cache' => false,
			)
		);

		$blog = (array) $blogs['blogs'][0];

		if ( true === $retval && ! $blog instanceof BP_Blogs_Blog ) {
			$retval = new WP_Error(
				'bp_rest_blog_invalid_id',
				__( 'Invalid group ID.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}
		
		if ( true === $retval && ! buddypress()->avatar->show_avatars ) {
			$retval = new WP_Error(
				'bp_rest_attachments_blogs_avatar_disabled',
				__( 'Sorry, blog avatar upload is disabled.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		/**
		 * Filter the blog avatar `get_item` permissions check.
		 *
		 * @since 6.0.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_attachments_blogs_avatar_get_item_permissions_check', $retval, $request );
	}

	/**
	 * Prepares avatar data to return as an object.
	 *
	 * @since 6.0.0
	 *
	 * @param object          $avatar  Avatar object.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $avatar, $request ) {
		$data = array(
			'full'  => $avatar->full,
			'thumb' => $avatar->thumb,
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		// @todo add prepare_links
		$response = rest_ensure_response( $data );

		/**
		 * Filter a blog avatar value returned from the API.
		 *
		 * @since 6.0.0
		 *
		 * @param WP_REST_Response  $response Response.
		 * @param WP_REST_Request   $request  Request used to generate the response.
		 * @param object            $avatar   Avatar object.
		 */
		return apply_filters( 'bp_rest_attachments_blogs_avatar_prepare_value', $response, $request, $avatar );
	}

	/**
	 * Get the blog avatar schema, conforming to JSON Schema.
	 *
	 * @since 6.0.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bp_attachments_blogs_avatar',
			'type'       => 'object',
			'properties' => array(
				'full'  => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Full size of the image file.', 'buddypress' ),
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'thumb' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Thumb size of the image file.', 'buddypress' ),
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
			),
		);

		/**
		 * Filters the blog avatar schema.
		 *
		 * @param string $schema The endpoint schema.
		 */
		return apply_filters( 'bp_rest_attachments_blogs_avatar_schema', $this->add_additional_fields_schema( $schema ) );
	}

	/**
	 * Get the query params for the `get_item`.
	 *
	 * @since 6.0.0
	 *
	 * @return array
	 */
	public function get_item_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		// Removing unused params.
		unset( $params['search'], $params['page'], $params['per_page'] );

		$params['alt'] = array(
			'description'       => __( 'The alt attribute for the <img> element.', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['no_grav'] = array(
			'description'       => __( 'Whether to disable the default Gravatar fallback.', 'buddypress' ),
			'default'           => false,
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		/**
		 * Filters the item collection query params.
		 *
		 * @param array $params Query params.
		 */
		return apply_filters( 'bp_rest_attachments_blogs_avatar_collection_params', $params );
	}
}
