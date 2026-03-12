<?php
/**
 * Plugin Name: Research Review Portal
 * Description: Research submission and review workflow for conferences, publications, student projects, and grants. Port of the CityU Research Review Portal for WordPress.
 * Version: 1.0.0
 * Author: CityU C4CYI
 * License: MIT
 * Text Domain: research-review-portal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RRP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RRP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RRP_DATA_DIR', RRP_PLUGIN_DIR . 'data/' );
define( 'RRP_UPLOADS_DIR', RRP_DATA_DIR . 'uploads/' );

require_once RRP_PLUGIN_DIR . 'includes/class-portal-data.php';
require_once RRP_PLUGIN_DIR . 'includes/class-portal-rest.php';
require_once RRP_PLUGIN_DIR . 'includes/class-user-management.php';
require_once RRP_PLUGIN_DIR . 'includes/class-process-documentation.php';

class Research_Review_Portal {

	public static function init() {
		add_shortcode( 'research_review_portal', array( __CLASS__, 'shortcode_portal' ) );
	}

	public static function shortcode_portal( $atts ) {
		do_action( 'rrp_portal_enqueue' );
		wp_enqueue_style(
			'research-review-portal',
			RRP_PLUGIN_URL . 'assets/portal.css',
			array(),
			'1.0.0'
		);
		wp_enqueue_script(
			'research-review-portal',
			RRP_PLUGIN_URL . 'assets/portal.js',
			array(),
			'1.0.0',
			true
		);

		$logged_in = is_user_logged_in();
		$login_url = wp_login_url( get_permalink() );
		$logout_url = wp_logout_url( get_permalink() );
		$current_user = wp_get_current_user();
		$user_name = $current_user->exists() ? ( $current_user->display_name ?: $current_user->user_login ) : '';
		$user_roles = $current_user->exists() ? $current_user->roles : array();
		$role_label = '';
		if ( in_array( 'rrp_student', $user_roles, true ) ) {
			$role_label = 'Student';
		} elseif ( in_array( 'rrp_reviewer', $user_roles, true ) ) {
			$role_label = 'Reviewer';
		} elseif ( in_array( 'rrp_coordinator', $user_roles, true ) ) {
			$role_label = 'Coordinator';
		} elseif ( in_array( 'rrp_admin', $user_roles, true ) || in_array( 'administrator', $user_roles, true ) ) {
			$role_label = 'Admin';
		}

		wp_add_inline_script( 'research-review-portal', sprintf(
			'window.RRP = { restBase: %s, nonce: %s, isLoggedIn: %s, loginUrl: %s, logoutUrl: %s, userName: %s, userRole: %s };',
			wp_json_encode( rest_url( 'research-portal/v1' ) ),
			wp_json_encode( wp_create_nonce( 'wp_rest' ) ),
			wp_json_encode( $logged_in ),
			wp_json_encode( $login_url ),
			wp_json_encode( $logout_url ),
			wp_json_encode( $user_name ),
			wp_json_encode( $role_label )
		), 'before' );

		ob_start();

		if ( ! $logged_in ) {
			?>
			<div id="research-review-portal" class="rrp-portal">
				<div class="rrp-notice">
					<h1>Research Submission Process</h1>
					<p>Welcome to the Research Review Portal. Please review the submission process in detail below.</p>
					<p><a href="<?php echo esc_url( $login_url ); ?>" class="rrp-btn">Log in to submit and access dashboard</a></p>
				</div>
				<div class="rrp-process-docs-container">
					<?php echo do_shortcode( '[rrp_process_documentation type="all" style="compact"]' ); ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		?>
		<div id="research-review-portal" class="rrp-portal" data-rest-base="<?php echo esc_attr( rest_url( 'research-portal/v1' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
			<div class="rrp-loading">Loading portal…</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

	add_action( 'init', array( 'Research_Review_Portal', 'init' ) );
	add_action( 'rest_api_init', array( 'Portal_REST', 'register_routes' ) );

	add_action( 'rrp_daily_report', array( 'Portal_REST', 'scheduled_report' ) );

	register_activation_hook( __FILE__, function() {
		if ( ! wp_next_scheduled( 'rrp_daily_report' ) ) {
			wp_schedule_event( time(), 'daily', 'rrp_daily_report' );
		}
	} );

	register_deactivation_hook( __FILE__, function() {
		wp_clear_scheduled_hook( 'rrp_daily_report' );
	} );
