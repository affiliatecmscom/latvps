<?php
/**
 * Plugin Name: REST Batch Guard (LATVPS)
 * Description: Chặn endpoint /batch/v1 của REST API với request chưa đăng nhập (vá phòng thủ cho lớp tấn công wp2shell qua batch + customize_changeset).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		if ( null !== $result ) {
			return $result;
		}
		if ( 0 === strpos( (string) $request->get_route(), '/batch/v1' ) && ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', 'Authentication required.', array( 'status' => 401 ) );
		}
		return $result;
	},
	1,
	3
);
