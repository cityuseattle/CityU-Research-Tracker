<?php
/**
 * Research Review Portal - User Management System
 * Enhanced WordPress user integration with custom roles and capabilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RRP_User_Management {

	/**
	 * Custom roles for the Research Review Portal
	 */
	const ROLES = array(
		'rrp_student'     => array(
			'name'         => 'Research Student',
			'capabilities' => array(
				'read'                    => true,
				'rrp_submit_research'     => true,
				'rrp_view_own_submissions' => true,
				'rrp_edit_own_submissions' => true,
			),
		),
		'rrp_reviewer'    => array(
			'name'         => 'Research Reviewer', 
			'capabilities' => array(
				'read'                        => true,
				'rrp_review_submissions'      => true,
				'rrp_view_assigned_submissions' => true,
				'rrp_provide_feedback'        => true,
				'rrp_view_review_dashboard'   => true,
			),
		),
		'rrp_coordinator' => array(
			'name'         => 'Research Coordinator',
			'capabilities' => array(
				'read'                         => true,
				'rrp_assign_reviewers'         => true,
				'rrp_manage_workflow'          => true,
				'rrp_view_all_submissions'     => true,
				'rrp_edit_any_submission'      => true,
				'rrp_skip_review_stages'       => true,
				'rrp_manage_deadlines'         => true,
			),
		),
		'rrp_admin'       => array(
			'name'         => 'Research Administrator',
			'capabilities' => array(
				'read'                           => true,
				'rrp_full_admin_access'          => true,
				'rrp_manage_users'               => true,
				'rrp_manage_system_config'       => true,
				'rrp_view_analytics'             => true,
				'rrp_manage_reviewers'           => true,
				'rrp_system_configuration'       => true,
				'rrp_export_data'                => true,
				'rrp_bulk_operations'            => true,
			),
		),
		'rrp_faculty'     => array(
			'name'         => 'Research Faculty',
			'capabilities' => array(
				'read'                          => true,
				'rrp_review_submissions'        => true,
				'rrp_view_assigned_submissions' => true,
				'rrp_provide_feedback'          => true,
				'rrp_view_review_dashboard'     => true,
				'rrp_view_all_submissions'      => true,
			),
		),
		'rrp_public'      => array(
			'name'         => 'Public Submitter',
			'capabilities' => array(
				'read'                      => true,
				'rrp_submit_research'       => true,
				'rrp_view_own_submissions'  => true,
				'rrp_edit_own_submissions'  => true,
			),
		),
	);

	/**
	 * Custom capabilities for granular permission control
	 */
	const CAPABILITIES = array(
		// Student/Submitter capabilities
		'rrp_submit_research'          => 'Submit research documents',
		'rrp_view_own_submissions'     => 'View own submissions',  
		'rrp_edit_own_submissions'     => 'Edit own submissions',
		
		// Reviewer capabilities
		'rrp_review_submissions'       => 'Review assigned submissions',
		'rrp_view_assigned_submissions' => 'View assigned submissions',
		'rrp_provide_feedback'         => 'Provide review feedback',
		'rrp_view_review_dashboard'    => 'Access reviewer dashboard',
		
		// Coordinator capabilities  
		'rrp_assign_reviewers'         => 'Assign reviewers to submissions',
		'rrp_manage_workflow'          => 'Manage review workflow',
		'rrp_view_all_submissions'     => 'View all submissions',
		'rrp_edit_any_submission'      => 'Edit any submission',
		'rrp_skip_review_stages'       => 'Skip review stages',
		'rrp_manage_deadlines'         => 'Manage review deadlines',
		
		// Administrator capabilities
		'rrp_full_admin_access'        => 'Full administrative access',
		'rrp_manage_users'             => 'Manage portal users',
		'rrp_manage_system_config'     => 'Manage system configuration',
		'rrp_view_analytics'           => 'View system analytics',
		'rrp_manage_reviewers'         => 'Manage reviewer assignments',
		'rrp_system_configuration'     => 'Configure system settings',
		'rrp_export_data'              => 'Export system data',
		'rrp_bulk_operations'          => 'Perform bulk operations',
	);

	/**
	 * Return custom roles stored in config.json.
	 * Each entry: [ 'slug' => '...', 'name' => '...', 'color' => '...' ]
	 */
	public static function get_custom_roles() {
		if ( ! defined( 'RRP_DATA_DIR' ) ) {
			return array();
		}
		$path = RRP_DATA_DIR . 'config.json';
		if ( ! file_exists( $path ) ) {
			return array();
		}
		$raw = file_get_contents( $path );
		if ( substr( $raw, 0, 3 ) === "\xEF\xBB\xBF" ) {
			$raw = substr( $raw, 3 );
		}
		$data = json_decode( $raw, true );
		return ( is_array( $data ) && isset( $data['customRoles'] ) && is_array( $data['customRoles'] ) )
			? $data['customRoles']
			: array();
	}

	/**
	 * Initialize user management system
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'create_roles' ) );
		add_action( 'show_user_profile', array( __CLASS__, 'add_profile_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'add_profile_fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile_fields' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'wp_ajax_rrp_bulk_import_users', array( __CLASS__, 'bulk_import_users' ) );
	}

	/**
	 * Create custom roles and capabilities
	 */
	public static function create_roles() {
		// Create any missing core roles (idempotent)
		foreach ( self::ROLES as $role_slug => $role_data ) {
			if ( ! get_role( $role_slug ) ) {
				add_role( $role_slug, $role_data['name'], $role_data['capabilities'] );
			}
		}
		// Register any custom roles stored in config.json
		foreach ( self::get_custom_roles() as $custom ) {
			$slug = isset( $custom['slug'] ) ? sanitize_key( (string) $custom['slug'] ) : '';
			$name = isset( $custom['name'] ) ? (string) $custom['name'] : $slug;
			if ( $slug && ! get_role( $slug ) ) {
				add_role( $slug, $name, array( 'read' => true ) );
			}
		}

		// Add capabilities to administrator role
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			foreach ( self::CAPABILITIES as $cap_slug => $cap_desc ) {
				if ( method_exists( $admin_role, 'add_cap' ) ) {
					$admin_role->add_cap( $cap_slug );
				}
			}
		}
	}

	/**
	 * Remove custom roles (for plugin deactivation)
	 */
	public static function remove_roles() {
		foreach ( self::ROLES as $role_slug => $role_data ) {
			remove_role( $role_slug );
		}
	}

	/**
	 * Add custom profile fields for research portal users
	 */
	public static function add_profile_fields( $user ) {
		$department = get_user_meta( $user->ID, 'rrp_department', true );
		$expertise = get_user_meta( $user->ID, 'rrp_expertise', true );
		$submission_types = get_user_meta( $user->ID, 'rrp_submission_types', true );
		$reviewer_id = get_user_meta( $user->ID, 'rrp_reviewer_id', true );
		$phone = get_user_meta( $user->ID, 'rrp_phone', true );
		$office_location = get_user_meta( $user->ID, 'rrp_office_location', true );
		?>
		<h2><?php esc_html_e( 'Research Portal Information', 'research-review-portal' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="rrp_department"><?php esc_html_e( 'Department/Unit', 'research-review-portal' ); ?></label></th>
				<td>
					<select name="rrp_department" id="rrp_department" class="regular-text">
						<option value=""><?php esc_html_e( 'Select Department...', 'research-review-portal' ); ?></option>
						<option value="computer-science" <?php selected( $department, 'computer-science' ); ?>><?php esc_html_e( 'Computer Science', 'research-review-portal' ); ?></option>
						<option value="cybersecurity" <?php selected( $department, 'cybersecurity' ); ?>><?php esc_html_e( 'Cybersecurity', 'research-review-portal' ); ?></option>
						<option value="information-technology" <?php selected( $department, 'information-technology' ); ?>><?php esc_html_e( 'Information Technology', 'research-review-portal' ); ?></option>
						<option value="business" <?php selected( $department, 'business' ); ?>><?php esc_html_e( 'Business', 'research-review-portal' ); ?></option>
						<option value="engineering" <?php selected( $department, 'engineering' ); ?>><?php esc_html_e( 'Engineering', 'research-review-portal' ); ?></option>
						<option value="other" <?php selected( $department, 'other' ); ?>><?php esc_html_e( 'Other', 'research-review-portal' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="rrp_expertise"><?php esc_html_e( 'Research Expertise Areas', 'research-review-portal' ); ?></label></th>
				<td>
					<textarea name="rrp_expertise" id="rrp_expertise" class="regular-text" rows="3" placeholder="<?php esc_attr_e( 'List your research areas and expertise (one per line)', 'research-review-portal' ); ?>"><?php echo esc_textarea( $expertise ); ?></textarea>
					<p class="description"><?php esc_html_e( 'List your research expertise areas, one per line. This helps with reviewer assignment.', 'research-review-portal' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="rrp_submission_types"><?php esc_html_e( 'Review Submission Types', 'research-review-portal' ); ?></label></th>
				<td>
					<?php
					$types = is_array( $submission_types ) ? $submission_types : array();
					$available_types = array(
						'conference' => 'Conference Papers',
						'publication' => 'Publications',  
						'student-project' => 'Student Projects',
						'grant' => 'Grant Proposals'
					);
					foreach ( $available_types as $type_key => $type_label ) :
					?>
						<label>
							<input type="checkbox" name="rrp_submission_types[]" value="<?php echo esc_attr( $type_key ); ?>" <?php checked( in_array( $type_key, $types, true ) ); ?>>
							<?php echo esc_html( $type_label ); ?>
						</label><br>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Select which types of submissions this user can review (for reviewers).', 'research-review-portal' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="rrp_reviewer_id"><?php esc_html_e( 'Reviewer ID', 'research-review-portal' ); ?></label></th>
				<td>
					<input type="text" name="rrp_reviewer_id" id="rrp_reviewer_id" value="<?php echo esc_attr( $reviewer_id ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., r1, r2, r3...', 'research-review-portal' ); ?>">
					<p class="description"><?php esc_html_e( 'Unique reviewer identifier (for linking with existing review data).', 'research-review-portal' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="rrp_phone"><?php esc_html_e( 'Phone Number', 'research-review-portal' ); ?></label></th>
				<td>
					<input type="tel" name="rrp_phone" id="rrp_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th><label for="rrp_office_location"><?php esc_html_e( 'Office Location', 'research-review-portal' ); ?></label></th>
				<td>
					<input type="text" name="rrp_office_location" id="rrp_office_location" value="<?php echo esc_attr( $office_location ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Building, Room #', 'research-review-portal' ); ?>">
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save custom profile fields
	 */
	public static function save_profile_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		update_user_meta( $user_id, 'rrp_department', sanitize_text_field( $_POST['rrp_department'] ?? '' ) );
		update_user_meta( $user_id, 'rrp_expertise', sanitize_textarea_field( $_POST['rrp_expertise'] ?? '' ) );
		update_user_meta( $user_id, 'rrp_phone', sanitize_text_field( $_POST['rrp_phone'] ?? '' ) );
		update_user_meta( $user_id, 'rrp_office_location', sanitize_text_field( $_POST['rrp_office_location'] ?? '' ) );
		update_user_meta( $user_id, 'rrp_reviewer_id', sanitize_text_field( $_POST['rrp_reviewer_id'] ?? '' ) );
		
		$submission_types = isset( $_POST['rrp_submission_types'] ) && is_array( $_POST['rrp_submission_types'] ) 
			? array_map( 'sanitize_text_field', $_POST['rrp_submission_types'] ) 
			: array();
		update_user_meta( $user_id, 'rrp_submission_types', $submission_types );
	}

	/**
	 * Add admin menu for user management
	 */
	public static function add_admin_menu() {
		add_submenu_page(
			'users.php',
			__( 'Research Portal Users', 'research-review-portal' ),
			__( 'Research Portal', 'research-review-portal' ),
			'manage_options',
			'rrp-users',
			array( __CLASS__, 'admin_page' )
		);
	}

	/**
	 * Admin page for user management
	 */
	public static function admin_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Research Portal User Management', 'research-review-portal' ); ?></h1>
			
			<div class="rrp-admin-stats">
				<h2><?php esc_html_e( 'User Statistics', 'research-review-portal' ); ?></h2>
				<?php self::display_user_stats(); ?>
			</div>
			
			<div class="rrp-bulk-import">
				<h2><?php esc_html_e( 'Bulk User Import', 'research-review-portal' ); ?></h2>
				<?php self::display_bulk_import_form(); ?>
			</div>
			
			<div class="rrp-user-list">
				<h2><?php esc_html_e( 'Portal Users', 'research-review-portal' ); ?></h2>
				<?php self::display_user_list(); ?>
			</div>
		</div>
		<style>
		.rrp-admin-stats .stats-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 1rem;
			margin: 1rem 0;
		}
		.stat-card {
			background: #f5f5f5;
			padding: 1rem;
			border-radius: 4px;
			text-align: center;
		}
		.stat-number {
			font-size: 2rem;
			font-weight: bold;
			color: #0066aa;
		}
		.rrp-bulk-import textarea {
			width: 100%;
			height: 200px;
		}
		</style>
		<?php
	}

	/**
	 * Display user statistics
	 */
	private static function display_user_stats() {
		$stats   = array();
		$all_ids = array();
		foreach ( self::ROLES as $role_slug => $role_data ) {
			$users = get_users( array( 'role' => $role_slug ) );
			$stats[ $role_data['name'] ] = count( $users );
			foreach ( $users as $u ) {
				$all_ids[ $u->ID ] = true; // dedup multi-role users
			}
		}
		$total_unique = count( $all_ids );
		?>
		<div class="stats-grid">
			<?php foreach ( $stats as $role_name => $count ) : ?>
				<div class="stat-card">
					<div class="stat-number"><?php echo esc_html( $count ); ?></div>
					<div class="stat-label"><?php echo esc_html( $role_name ); ?></div>
				</div>
			<?php endforeach; ?>
			<div class="stat-card" style="border:2px solid #0066aa;">
				<div class="stat-number"><?php echo esc_html( $total_unique ); ?></div>
				<div class="stat-label">Total Unique Users</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Display bulk import form
	 */
	private static function display_bulk_import_form() {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
			<?php wp_nonce_field( 'rrp_bulk_import', 'rrp_nonce' ); ?>
			<input type="hidden" name="action" value="rrp_bulk_import_users">
			
			<p><?php esc_html_e( 'Import users in CSV format:', 'research-review-portal' ); ?></p>
			<p><strong><?php esc_html_e( 'Format:', 'research-review-portal' ); ?></strong> username,email,first_name,last_name,role,department,expertise</p>
			<p><strong><?php esc_html_e( 'Roles:', 'research-review-portal' ); ?></strong> rrp_student, rrp_reviewer, rrp_coordinator, rrp_admin</p>
			
			<textarea name="rrp_import_data" placeholder="<?php esc_attr_e( 'jsmith,john.smith@cityu.edu,John,Smith,rrp_reviewer,computer-science,"AI, Machine Learning"', 'research-review-portal' ); ?>" required></textarea>
			
			<p>
				<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Import Users', 'research-review-portal' ); ?>">
			</p>
		</form>
		<?php
	}

	/**
	 * Display current portal users
	 */
	private static function display_user_list() {
		$portal_users = array();
		$seen_ids     = array();
		foreach ( self::ROLES as $role_slug => $role_data ) {
			$users = get_users( array( 'role' => $role_slug ) );
			foreach ( $users as $u ) {
				if ( ! isset( $seen_ids[ $u->ID ] ) ) {
					$seen_ids[ $u->ID ] = true;
					$portal_users[]     = $u;
				}
			}
		}
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'research-review-portal' ); ?></th>
					<th><?php esc_html_e( 'Email', 'research-review-portal' ); ?></th>
					<th><?php esc_html_e( 'Role', 'research-review-portal' ); ?></th>
					<th><?php esc_html_e( 'Department', 'research-review-portal' ); ?></th>
					<th><?php esc_html_e( 'Reviewer ID', 'research-review-portal' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'research-review-portal' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $portal_users as $user ) : ?>
					<?php
					$department  = get_user_meta( $user->ID, 'rrp_department', true );
					$reviewer_id = get_user_meta( $user->ID, 'rrp_reviewer_id', true );
					// Show all portal roles the user holds (multi-role aware)
					$matched_roles = array_intersect_key( self::ROLES, array_flip( $user->roles ) );
					$role_name = ! empty( $matched_roles )
						? implode( ', ', array_column( array_values( $matched_roles ), 'name' ) )
						: implode( ', ', $user->roles );
					?>
					<tr>
						<td><?php echo esc_html( $user->display_name ); ?></td>
						<td><?php echo esc_html( $user->user_email ); ?></td>
						<td><?php echo esc_html( $role_name ); ?></td>
						<td><?php echo esc_html( $department ); ?></td>
						<td><?php echo esc_html( $reviewer_id ); ?></td>
						<td>
							<a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>" class="button button-small">
								<?php esc_html_e( 'Edit', 'research-review-portal' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( empty( $portal_users ) ) : ?>
					<tr>
						<td colspan="6"><?php esc_html_e( 'No portal users found.', 'research-review-portal' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Handle bulk user import via AJAX
	 */
	public static function bulk_import_users() {
		check_ajax_referer( 'rrp_bulk_import', 'rrp_nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Insufficient permissions', 'research-review-portal' ) );
		}

		$import_data = sanitize_textarea_field( $_POST['rrp_import_data'] ?? '' );
		$lines = array_filter( explode( "\n", $import_data ) );
		$imported = 0;
		$errors = array();

		foreach ( $lines as $line ) {
			$data = str_getcsv( trim( $line ) );
			if ( count( $data ) < 5 ) {
				$errors[] = sprintf( __( 'Invalid format in line: %s', 'research-review-portal' ), $line );
				continue;
			}

			$username = sanitize_user( $data[0] );
			$email = sanitize_email( $data[1] );
			$first_name = sanitize_text_field( $data[2] );
			$last_name = sanitize_text_field( $data[3] );
			$role = sanitize_text_field( $data[4] );
			$department = sanitize_text_field( $data[5] ?? '' );
			$expertise = sanitize_textarea_field( $data[6] ?? '' );

			if ( ! in_array( $role, array_keys( self::ROLES ), true ) ) {
				$errors[] = sprintf( __( 'Invalid role "%s" for user %s', 'research-review-portal' ), $role, $username );
				continue;
			}

			$user_data = array(
				'user_login' => $username,
				'user_email' => $email,
				'first_name' => $first_name,
				'last_name' => $last_name,
				'display_name' => trim( $first_name . ' ' . $last_name ),
				'user_pass' => wp_generate_password(),
				'role' => $role,
			);

			$user_id = wp_insert_user( $user_data );
			if ( is_wp_error( $user_id ) ) {
				$errors[] = sprintf( __( 'Failed to create user %s: %s', 'research-review-portal' ), $username, $user_id->get_error_message() );
				continue;
			}

			// Set custom meta fields
			if ( $department ) {
				update_user_meta( $user_id, 'rrp_department', $department );
			}
			if ( $expertise ) {
				update_user_meta( $user_id, 'rrp_expertise', $expertise );
			}

			$imported++;
		}

		$message = sprintf( __( 'Successfully imported %d users.', 'research-review-portal' ), $imported );
		if ( ! empty( $errors ) ) {
			$message .= ' ' . sprintf( __( '%d errors occurred:', 'research-review-portal' ), count( $errors ) );
			$message .= '<ul><li>' . implode( '</li><li>', $errors ) . '</li></ul>';
		}

		wp_redirect( add_query_arg( array(
			'page' => 'rrp-users',
			'message' => urlencode( $message ),
		), admin_url( 'users.php' ) ) );
		exit;
	}

	/**
	 * Check if user has specific capability
	 */
	public static function user_can( $capability, $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		return user_can( $user_id, $capability );
	}

	/**
	 * Get user's research portal role
	 */
	public static function get_user_portal_role( $user_id = null ) {
		// Returns the first matched role slug for backward compatibility.
		// Prefer get_user_portal_roles() for multi-role aware code.
		$roles = self::get_user_portal_roles( $user_id );
		return $roles ? $roles[0] : false;
	}

	/**
	 * Get all portal role slugs assigned to a user.
	 * Returns an array such as ['rrp_student', 'rrp_reviewer'] for multi-role users.
	 *
	 * @param int|null $user_id  Defaults to the current user.
	 * @return string[]  Array of role slugs (may be empty).
	 */
	public static function get_user_portal_roles( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array();
		}
		$all_slugs = array_keys( self::ROLES );
		foreach ( self::get_custom_roles() as $c ) {
			if ( ! empty( $c['slug'] ) ) {
				$all_slugs[] = sanitize_key( (string) $c['slug'] );
			}
		}
		return array_values( array_intersect( $all_slugs, $user->roles ) );
	}

	/**
	 * Return display labels for every portal role a user holds.
	 * Used by research-review-portal.php to populate window.RRP.userRoles.
	 *
	 * Example: user with rrp_student + rrp_reviewer → ['Student', 'Reviewer']
	 *
	 * @param string[] $user_roles  Array of WP role slugs from $current_user->roles.
	 * @return string[]  Ordered array of display labels.
	 */
	public static function get_role_labels( array $user_roles ) {
		$map = array(
			'rrp_student'     => 'Student',
			'rrp_reviewer'    => 'Reviewer',
			'rrp_faculty'     => 'Faculty',
			'rrp_coordinator' => 'Coordinator',
			'rrp_admin'       => 'Admin',
			'rrp_public'      => 'Public',
			'administrator'   => 'Admin',
		);
		// Include custom role labels
		foreach ( self::get_custom_roles() as $c ) {
			if ( ! empty( $c['slug'] ) && ! empty( $c['name'] ) ) {
				$map[ sanitize_key( (string) $c['slug'] ) ] = (string) $c['name'];
			}
		}
		$labels = array();
		foreach ( $map as $slug => $label ) {
			if ( in_array( $slug, $user_roles, true ) && ! in_array( $label, $labels, true ) ) {
				$labels[] = $label;
			}
		}
		return $labels;
	}

	/**
	 * Get users by portal role
	 */
	public static function get_users_by_role( $role ) {
		if ( ! array_key_exists( $role, self::ROLES ) ) {
			return array();
		}

		return get_users( array( 'role' => $role ) );
	}

	/**
	 * Get reviewers with their expertise and submission types
	 */
	public static function get_available_reviewers( $submission_type = null ) {
		$reviewers = self::get_users_by_role( 'rrp_reviewer' );
		$available = array();

		foreach ( $reviewers as $reviewer ) {
			$submission_types = get_user_meta( $reviewer->ID, 'rrp_submission_types', true );
			
			// If submission type filter is provided, check if reviewer handles it
			if ( $submission_type && is_array( $submission_types ) && ! in_array( $submission_type, $submission_types, true ) ) {
				continue;
			}

			$available[] = array(
				'id' => get_user_meta( $reviewer->ID, 'rrp_reviewer_id', true ) ?: 'wp_' . $reviewer->ID,
				'user_id' => $reviewer->ID,
				'name' => $reviewer->display_name,
				'email' => $reviewer->user_email,
				'department' => get_user_meta( $reviewer->ID, 'rrp_department', true ),
				'expertise' => get_user_meta( $reviewer->ID, 'rrp_expertise', true ),
				'submission_types' => $submission_types ?: array(),
			);
		}

		return $available;
	}

	/**
	 * Sync existing reviewer data with WordPress users
	 */
	public static function sync_existing_reviewers() {
		$reviewers_data = Portal_Data::read_reviewers();
		$synced = 0;

		foreach ( $reviewers_data as $reviewer ) {
			// Check if user exists by email
			$user = get_user_by( 'email', $reviewer['email'] );
			
			if ( ! $user ) {
				// Create new user
				$user_data = array(
					'user_login' => sanitize_user( $reviewer['id'] ),
					'user_email' => $reviewer['email'],
					'display_name' => $reviewer['name'],
					'user_pass' => wp_generate_password(),
					'role' => 'rrp_reviewer',
				);

				$user_id = wp_insert_user( $user_data );
				if ( is_wp_error( $user_id ) ) {
					continue;
				}
				$user = get_userdata( $user_id );
			}

			// Update meta fields
			update_user_meta( $user->ID, 'rrp_reviewer_id', $reviewer['id'] );
			update_user_meta( $user->ID, 'rrp_submission_types', $reviewer['submissionTypes'] );
			
			// Ensure user has reviewer role
			if ( ! in_array( 'rrp_reviewer', $user->roles, true ) ) {
				$user->add_role( 'rrp_reviewer' );
			}

			$synced++;
		}

		return $synced;
	}
}

// Initialize user management only if plugin is active
if ( defined( 'RRP_PLUGIN_DIR' ) ) {
	RRP_User_Management::init();

	// Hook for plugin activation
	register_activation_hook( RRP_PLUGIN_DIR . 'research-review-portal.php', array( 'RRP_User_Management', 'create_roles' ) );
	
	// Hook for plugin deactivation  
	register_deactivation_hook( RRP_PLUGIN_DIR . 'research-review-portal.php', array( 'RRP_User_Management', 'remove_roles' ) );
}