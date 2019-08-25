<?php
/**
 * BP REST: BP_REST_Attachments_Group_Cover_Endpoint class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Group Cover endpoints.
 *
 * @since 0.1.0
 */
class BP_REST_Attachments_Group_Cover_Endpoint extends WP_REST_Controller {

	use BP_REST_Attachments;

	/**
	 * Reuse some parts of the BP_REST_Groups_Endpoint class.
	 *
	 * @since 0.1.0
	 *
	 * @var BP_REST_Groups_Endpoint
	 */
	protected $groups_endpoint;

	/**
	 * Hold the group object.
	 *
	 * @since 0.1.0
	 *
	 * @var BP_Groups_Group
	 */
	protected $group;

	/**
	 * Group object type.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	protected $object = 'group';

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace       = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base       = buddypress()->groups->id;
		$this->groups_endpoint = new BP_REST_Groups_Endpoint();
	}

	/**
	 * Register the component routes.
	 *
	 * @since 0.1.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<group_id>[\d]+)/cover',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Fetch an existing group cover.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$cover = bp_get_group_cover_url( $this->group );

		if ( empty( $cover ) ) {
			return new WP_Error(
				'bp_rest_attachments_group_cover_no_image',
				__( 'Sorry, there was a problem fetching this group cover.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $cover, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a group cover is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param string            $cover    The group cover.
		 * @param WP_REST_Response  $response The response data.
		 * @param WP_REST_Request   $request  The request sent to the API.
		 */
		do_action( 'bp_rest_attachments_group_cover_get_item', $cover, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to get a group cover.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$retval      = true;
		$this->group = $this->groups_endpoint->get_group_object( $request );

		if ( ! $this->group ) {
			$retval = new WP_Error(
				'bp_rest_group_invalid_id',
				__( 'Invalid group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		/**
		 * Filter the group cover `get_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_attachments_group_cover_get_item_permissions_check', $retval, $request );
	}

	/**
	 * Upload a group cover.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$request->set_param( 'context', 'edit' );

		// Get the image file from $_FILES.
		$files = $request->get_file_params();

		if ( empty( $files ) ) {
			return new WP_Error(
				'bp_rest_attachments_group_cover_no_image_file',
				__( 'Sorry, you need an image file to upload.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		// Upload the group cover.
		$cover_url = $this->upload_cover_from_file( $files );
		if ( is_wp_error( $cover_url ) ) {
			return $cover_url;
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $cover_url, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a group cover is uploaded via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param string            $cover_url  The group cover url.
		 * @param WP_REST_Response  $response   The response data.
		 * @param WP_REST_Request   $request    The request sent to the API.
		 */
		do_action( 'bp_rest_attachments_group_cover_create_item', $cover_url, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to upload a group cover.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		$retval = $this->delete_item_permissions_check( $request );

		if ( true === $retval && bp_disable_group_cover_image_uploads() ) {
			$retval = new WP_Error(
				'bp_rest_attachments_group_cover_disabled',
				__( 'Sorry, group cover upload is disabled.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		/**
		 * Filter the group cover `create_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_attachments_group_cover_create_item_permissions_check', $retval, $request );
	}

	/**
	 * Delete an existing group cover.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$request->set_param( 'context', 'edit' );

		$cover_url = bp_get_group_cover_url( $this->group );
		$deleted   = bp_attachments_delete_file(
			array(
				'item_id'    => (int) $this->group->id,
				'object_dir' => 'groups',
				'type'       => 'cover-image',
			)
		);

		if ( ! $deleted ) {
			return new WP_Error(
				'bp_rest_attachments_group_cover_delete_failed',
				__( 'Sorry, there was a problem deleting this group cover.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		// Build the response.
		$response = new WP_REST_Response();
		$response->set_data(
			array(
				'deleted'  => true,
				'previous' => $cover_url,
			)
		);

		/**
		 * Fires after a group cover is deleted via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param BP_Groups_Group   $group     The group object.
		 * @param WP_REST_Response  $response  The response data.
		 * @param WP_REST_Request   $request   The request sent to the API.
		 */
		do_action( 'bp_rest_attachments_group_cover_delete_item', $this->group, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to delete a group cover.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		$retval = $this->get_item_permissions_check( $request );
		$args   = array();

		if ( isset( $this->group->id ) ) {
			$args = array(
				'item_id' => (int) $this->group->id,
				'object'  => 'group',
			);
		}

		if ( true === $retval && ! is_user_logged_in() ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to perform this action.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		if ( true === $retval && ! empty( $args ) && ! bp_attachments_current_user_can( 'edit_cover_image', $args ) ) {
			$retval = new WP_Error(
				'bp_rest_authorization_required',
				__( 'Sorry, you are not authorized to perform this action.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		}

		/**
		 * Filter the group cover `delete_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_attachments_group_cover_delete_item_permissions_check', $retval, $request );
	}

	/**
	 * Prepares group cover to return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param string          $cover   Group cover url.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $cover, $request ) {
		$data = array(
			'image' => $cover,
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		// @todo add prepare_links
		$response = rest_ensure_response( $data );

		/**
		 * Filter a group cover value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response  $response Response.
		 * @param WP_REST_Request   $request  Request used to generate the response.
		 */
		return apply_filters( 'bp_rest_attachments_group_cover_prepare_value', $response, $request );
	}

	/**
	 * Get the plugin schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'bp_attachments_group_cover',
			'type'       => 'object',
			'properties' => array(
				'image' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Full size of the image file.', 'buddypress' ),
					'type'        => 'string',
					'readonly'    => true,
				),
			),
		);

		/**
		 * Filters the group cover schema.
		 *
		 * @param string $schema The endpoint schema.
		 */
		return apply_filters( 'bp_rest_attachments_group_cover_schema', $this->add_additional_fields_schema( $schema ) );
	}
}
