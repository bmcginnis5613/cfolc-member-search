<?php
/**
 * Plugin Name: CFOLC Member Search
 * Description: Search, filter and export member data by various attributes.
 * Version: 1.0.3
 * Requires PHP: 7.4
 * Author: FirstTracks Marketing
 * Author URI: https://firsttracksmarketing.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CEN_EAR_XPROFILE_FIRST_NAME_FIELD_ID', 1 );
define( 'CEN_EAR_XPROFILE_LAST_NAME_FIELD_ID', 2 );
define( 'CEN_EAR_XPROFILE_TITLE_FIELD_ID', 4 );
define( 'CEN_EAR_XPROFILE_COMPANY_FIELD_ID', 5 );
define( 'CEN_EAR_XPROFILE_PHONE_FIELD_ID', 6 );
define( 'CEN_EAR_RESULTS_PER_PAGE', 50 );
define( 'CEN_EAR_ONBOARDING_FORM_ID', 40 );

/**
 * Add the member search beneath WordPress's Users menu.
 */
function cen_ear_register_member_search_page() {
	add_users_page(
		__( 'Member Search', 'member-search' ),
		__( 'Member Search', 'member-search' ),
		cen_ear_get_required_capability(),
		'member-search',
		'cen_ear_render_member_search_page'
	);
}
add_action( 'admin_menu', 'cen_ear_register_member_search_page', 20 );
add_action( 'admin_post_cen_ear_export_members', 'cen_ear_export_members' );

/**
 * Return the capability required to search and export members.
 *
 * @return string
 */
function cen_ear_get_required_capability() {
	$capability = sanitize_key( (string) apply_filters( 'cen_ear_required_capability', 'list_users' ) );

	return '' !== $capability ? $capability : 'list_users';
}

/**
 * Return the member-search admin URL.
 *
 * @param array<string,mixed> $args Optional query arguments.
 * @return string
 */
function cen_ear_get_admin_page_url( $args = array() ) {
	$url = admin_url( 'users.php?page=member-search' );

	return empty( $args ) ? $url : add_query_arg( $args, $url );
}

/**
 * Return the fields users can display in the matches preview.
 *
 * @param bool $include_event_rsvp_status Whether RSVP Status is available for the current search.
 * @return array<string,array{label:string,type:string}>
 */
function cen_ear_get_preview_columns( $include_event_rsvp_status = true ) {
	$columns = array(
		'wp_user_id' => array( 'label' => 'WP User ID', 'type' => 'user_id' ),
		'ym_id'      => array( 'label' => 'YM ID', 'type' => 'text' ),
		'first_name' => array( 'label' => 'First Name', 'type' => 'text' ),
		'last_name'  => array( 'label' => 'Last Name', 'type' => 'text' ),
		'title'      => array( 'label' => 'Job Title', 'type' => 'text' ),
		'company'    => array( 'label' => 'Company Name', 'type' => 'text' ),
		'email'      => array( 'label' => 'Email', 'type' => 'email' ),
		'phone'      => array( 'label' => 'Phone Number', 'type' => 'phone' ),
	);

	foreach ( cen_ear_get_export_profile_fields() as $key => $profile_field ) {
		if ( ! isset( $columns[ $key ] ) ) {
			$columns[ $key ] = array(
				'label' => (string) $profile_field['header'],
				'type'  => 'text',
			);
		}
	}

	$columns['membership_plans']            = array( 'label' => 'Membership Plan', 'type' => 'list' );
	$columns['membership_plan_expirations'] = array( 'label' => 'Membership Plan Expiration', 'type' => 'list' );
	$columns['bb_groups']                   = array( 'label' => 'BB Groups', 'type' => 'list' );
	$columns['join_date']                   = array( 'label' => 'Join Date', 'type' => 'text' );
	$columns['last_active']                 = array( 'label' => 'BB Last Active', 'type' => 'text' );
	$columns['completed_onboarding']        = array( 'label' => 'Completed Onboarding', 'type' => 'text' );
	$columns['bb_profile_type']              = array( 'label' => 'BB Profile Type', 'type' => 'text' );
	$columns['bb_status']                    = array( 'label' => 'BB Status', 'type' => 'text' );
	$columns['forum_discussion_emails']      = array( 'label' => 'Forum Discussion Emails', 'type' => 'text' );
	if ( $include_event_rsvp_status ) {
		$columns['event_rsvp_status'] = array( 'label' => 'RSVP Status', 'type' => 'list' );
	}

	return (array) apply_filters( 'cen_ear_preview_columns', $columns, $include_event_rsvp_status );
}

/**
 * Return the fields shown before a user customizes their preview.
 *
 * @return string[]
 */
function cen_ear_get_default_preview_fields() {
	return (array) apply_filters(
		'cen_ear_default_preview_fields',
		array( 'wp_user_id', 'first_name', 'last_name', 'title', 'company', 'email', 'phone', 'bb_groups', 'last_active', 'completed_onboarding', 'bb_status', 'forum_discussion_emails', 'event_rsvp_status' )
	);
}

/**
 * Return the browser cookie name for the current WordPress user.
 *
 * @return string
 */
function cen_ear_get_preview_fields_cookie_name() {
	return 'cen_ear_preview_fields_' . get_current_user_id();
}

/**
 * Return this browser's saved preview fields, falling back to the defaults.
 *
 * @return string[]
 */
function cen_ear_get_selected_preview_fields() {
	$allowed     = array_keys( cen_ear_get_preview_columns( true ) );
	$cookie_name = cen_ear_get_preview_fields_cookie_name();
	$saved       = isset( $_COOKIE[ $cookie_name ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
	$fields  = '' !== $saved ? explode( ',', $saved ) : cen_ear_get_default_preview_fields();
	$fields  = array_values( array_intersect( array_map( 'sanitize_key', $fields ), $allowed ) );

	return ! empty( $fields ) ? $fields : array_values( array_intersect( cen_ear_get_default_preview_fields(), $allowed ) );
}

/**
 * Load Events Calendar events for the Event value picker.
 *
 * @return array<int,string>|WP_Error
 */
function cen_ear_get_event_choices() {
	if ( ! post_type_exists( 'tribe_events' ) ) {
		return new WP_Error( 'missing_events_calendar', __( 'The Events Calendar must be active before Event criteria can be used.', 'member-search' ) );
	}

	$events  = get_posts(
		array(
			'post_type'      => 'tribe_events',
			'post_status'    => array( 'publish', 'future', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'meta_value',
			'meta_key'       => '_EventStartDate', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'order'          => 'DESC',
		)
	);
	$choices = array();

	foreach ( $events as $event ) {
		$label      = get_the_title( $event );
		$start_date = get_post_meta( $event->ID, '_EventStartDate', true );
		if ( $start_date ) {
			$timestamp = strtotime( $start_date );
			if ( $timestamp ) {
				$label .= ' — ' . wp_date( get_option( 'date_format' ), $timestamp );
			}
		}
		$choices[ (int) $event->ID ] = cen_ear_decode_text( $label );
	}

	return $choices;
}

/**
 * Return the additional BuddyBoss Extended Profile fields used by member
 * search and Excel reports.
 *
 * Choice array keys are the exact values stored by BuddyBoss. Labels are what
 * the criteria picker displays.
 *
 * @return array<string,array<string,mixed>>
 */
function cen_ear_get_additional_profile_fields() {
	return (array) apply_filters(
		'cen_ear_additional_xprofile_fields',
		array(
			'industry' => array(
				'id'          => 23,
				'label'       => 'Industry',
				'search_type' => 'choice',
				'choices'     => array_combine(
					array(
						'Advertising/Marketing/Sales',
						'Agriculture/Forestry/Farming',
						'Business Services/Legal',
						'Construction/Engineering/Mining',
						'Education/Government/Non-Profit',
						'Energy/Utilities/Oil/Gas',
						'Entertainment/Gaming/Sports/Travel',
						'Finance/Insurance',
						'Healthcare/Pharmaceutical/Biotechnology',
						'Manufacturing',
						'Media/Publishing/Printing',
						'Real Estate/Property Management',
						'Restaurants/Food/Beverage/Tobacco',
						'Retail/Wholesale/Distribution',
						'Technology/Telecommunications',
						'Transportation',
						'Other',
					),
					array(
						'Advertising/Marketing/Sales',
						'Agriculture/Forestry/Farming',
						'Business Services/Legal',
						'Construction/Engineering/Mining',
						'Education/Government/Non-Profit',
						'Energy/Utilities/Oil/Gas',
						'Entertainment/Gaming/Sports/Travel',
						'Finance/Insurance',
						'Healthcare/Pharmaceutical/Biotechnology',
						'Manufacturing',
						'Media/Publishing/Printing',
						'Real Estate/Property Management',
						'Restaurants/Food/Beverage/Tobacco',
						'Retail/Wholesale/Distribution',
						'Technology/Telecommunications',
						'Transportation',
						'Other',
					)
				),
				'width'       => 38,
			),
			'company_type' => array(
				'id'          => 233,
				'label'       => 'Company Type',
				'search_type' => 'choice',
				'choices'     => array(
					'Not for Profit' => 'Not for Profit',
					'Privately Held' => 'Privately Held',
					'Public Company' => 'Public Company',
					'Venture Backed' => 'Venture Backed',
					'Other'          => 'Other',
					'Unknown'        => 'Unknown',
				),
				'width'       => 22,
			),
			'revenue_range' => array(
				'id'          => 7,
				'label'       => 'Revenue Range',
				'search_type' => 'choice',
				'choices'     => array(
					'Less than $5M'   => 'Less than $5M',
					'$5 - $9.9M'      => '$5 - $9.9M',
					'$10 - $49.9M'    => '$10 - $49.9M',
					'$50 - $99.9M'    => '$50 - $99.9M',
					'$100 - $499.9M'  => '$100 - $499.9M',
					'$500 - $999.9M'  => '$500 - $999.9M',
					'$1B - $9.9B'     => '$1B - $9.9B',
					'$10B+'            => '$10B+',
				),
				'width'       => 22,
			),
			'employee_count' => array(
				'id'          => 16,
				'label'       => 'Employee Count',
				'search_type' => 'choice',
				'choices'     => array(
					'Less than 25' => 'Less than 25',
					'25-99'        => '25-99',
					'100-999'      => '100-999',
					'1,000-4,999'  => '1,000-4,999',
					'5,000-9,999'  => '5,000-9,999',
					'10,000+'      => '10,000+',
				),
				'width'       => 20,
			),
			'areas_of_expertise' => array(
				'id'          => 182,
				'label'       => 'Areas of Expertise',
				'search_type' => 'choice',
				'choices'     => array_combine(
					array(
						'Attaining a Board Seat',
						'Building High Performance Teams',
						'Capital and Debt Strategies',
						'Cash Flow',
						'CEO/CFO Partnership',
						'Crisis Leadership',
						'Cybersecurity',
						'Digital Transformation',
						'Economics',
						'Executive Presence',
						'Gen AI',
						'M&A Strategies',
						'Multi-Generational Workforce',
						'Navigating Change',
						'Personal Brand',
						'Presenting to Boards',
						'Strategic Decision Making',
						'Supply Chain',
						'Talent Engagement & Retention',
						'Tariffs & Trade Wars',
					),
					array(
						'Attaining a Board Seat',
						'Building High Performance Teams',
						'Capital and Debt Strategies',
						'Cash Flow',
						'CEO/CFO Partnership',
						'Crisis Leadership',
						'Cybersecurity',
						'Digital Transformation',
						'Economics',
						'Executive Presence',
						'Gen AI',
						'M&A Strategies',
						'Multi-Generational Workforce',
						'Navigating Change',
						'Personal Brand',
						'Presenting to Boards',
						'Strategic Decision Making',
						'Supply Chain',
						'Talent Engagement & Retention',
						'Tariffs & Trade Wars',
					)
				),
				'width'       => 50,
			),
			'linkedin_profile_url' => array(
				'id'          => 223,
				'label'       => 'LinkedIn Profile URL',
				'search_type' => false,
				'width'       => 42,
			),
			'interested_in_speaking' => array(
				'id'          => 226,
				'label'       => 'Interested in speaking opportunities',
				'search_type' => 'choice',
				'choices'     => array( 'Interested in speaking opportunities' => 'Yes' ),
				'output'      => 'yes_if_not_empty',
				'width'       => 22,
			),
			'city' => array(
				'id'          => 230,
				'label'       => 'City',
				'search_type' => 'text',
				'width'       => 22,
			),
			'state' => array(
				'id'          => 231,
				'label'       => 'State',
				'search_type' => 'text',
				'width'       => 16,
			),
		)
	);
}

/**
 * Load WooCommerce Memberships plans for the search value picker.
 *
 * @return array<int,string>|WP_Error
 */
function cen_ear_get_woocommerce_membership_plan_choices() {
	if ( ! function_exists( 'wc_memberships_get_membership_plans' ) ) {
		return new WP_Error( 'missing_woocommerce_memberships', __( 'WooCommerce Memberships must be active before Membership Plan criteria can be used.', 'member-search' ) );
	}

	$choices = array();
	foreach ( (array) wc_memberships_get_membership_plans() as $plan ) {
		$plan_id = is_object( $plan ) && method_exists( $plan, 'get_id' ) ? absint( $plan->get_id() ) : 0;
		$name    = is_object( $plan ) && method_exists( $plan, 'get_name' ) ? cen_ear_decode_text( $plan->get_name() ) : '';
		if ( $plan_id && '' !== $name ) {
			$choices[ $plan_id ] = $name;
		}
	}

	natcasesort( $choices );

	return $choices;
}

/**
 * Load all BuddyBoss groups for the BB Groups value picker.
 *
 * @return array<int,string>|WP_Error
 */
function cen_ear_get_buddyboss_group_choices() {
	if ( ! function_exists( 'groups_get_groups' ) ) {
		return new WP_Error( 'missing_buddyboss_groups', __( 'BuddyBoss Groups must be active before BB Groups criteria can be used.', 'member-search' ) );
	}

	$results = groups_get_groups(
		array(
			'per_page'          => 9999,
			'page'              => 1,
			'show_hidden'       => true,
			'orderby'           => 'name',
			'order'             => 'ASC',
			'populate_extras'   => false,
			'update_meta_cache' => false,
		)
	);
	$choices = array();
	foreach ( isset( $results['groups'] ) ? (array) $results['groups'] : array() as $group ) {
		if ( is_object( $group ) && ! empty( $group->id ) && isset( $group->name ) ) {
			$choices[ absint( $group->id ) ] = cen_ear_decode_text( $group->name );
		}
	}

	return $choices;
}

/**
 * Return a readable label for a BuddyBoss profile type object.
 *
 * @param mixed  $type Profile type object, array, or slug.
 * @param string $fallback_slug Fallback profile type slug.
 * @return string
 */
function cen_ear_get_buddyboss_profile_type_label( $type, $fallback_slug = '' ) {
	$labels = is_object( $type ) && isset( $type->labels )
		? $type->labels
		: ( is_array( $type ) && isset( $type['labels'] ) ? $type['labels'] : array() );
	foreach ( array( 'singular_name', 'name' ) as $label_key ) {
		$label = is_object( $labels ) && isset( $labels->{$label_key} )
			? $labels->{$label_key}
			: ( is_array( $labels ) && isset( $labels[ $label_key ] ) ? $labels[ $label_key ] : '' );
		if ( is_scalar( $label ) && '' !== trim( (string) $label ) ) {
			return cen_ear_decode_text( $label );
		}
	}

	$slug = $fallback_slug;
	if ( is_object( $type ) && ! empty( $type->name ) ) {
		$slug = (string) $type->name;
	} elseif ( is_array( $type ) && ! empty( $type['name'] ) ) {
		$slug = (string) $type['name'];
	} elseif ( is_scalar( $type ) && '' !== (string) $type ) {
		$slug = (string) $type;
	}

	return ucwords( str_replace( array( '-', '_' ), ' ', sanitize_key( $slug ) ) );
}

/**
 * Load all registered BuddyBoss profile types for the search value picker.
 *
 * @return array<string,string>|WP_Error
 */
function cen_ear_get_buddyboss_profile_type_choices() {
	if ( ! function_exists( 'bp_get_member_types' ) ) {
		return new WP_Error( 'missing_buddyboss_profile_types', __( 'BuddyBoss Profile Types must be active before BB Profile Type criteria can be used.', 'member-search' ) );
	}

	static $cached_choices = null;
	if ( null !== $cached_choices ) {
		return $cached_choices;
	}

	$choices = array();
	foreach ( (array) bp_get_member_types( array(), 'objects' ) as $key => $type ) {
		$slug = '';
		if ( is_object( $type ) && ! empty( $type->name ) ) {
			$slug = sanitize_key( $type->name );
		} elseif ( is_array( $type ) && ! empty( $type['name'] ) ) {
			$slug = sanitize_key( $type['name'] );
		} elseif ( is_scalar( $type ) ) {
			$slug = sanitize_key( (string) $type );
		} elseif ( is_string( $key ) ) {
			$slug = sanitize_key( $key );
		}
		if ( '' !== $slug ) {
			$choices[ $slug ] = cen_ear_get_buddyboss_profile_type_label( $type, $slug );
		}
	}
	if ( ! empty( $choices ) ) {
		natcasesort( $choices );
	}

	$cached_choices = $choices;

	return $cached_choices;
}

/**
 * Whether a BuddyBoss profile type should be omitted from Event results.
 *
 * @param int $user_id WordPress user ID.
 * @return bool
 */
function cen_ear_is_excluded_event_user( $user_id ) {
	if ( ! function_exists( 'bp_get_member_type' ) ) {
		return false;
	}

	$excluded = (array) apply_filters( 'cen_ear_excluded_event_profile_types', array( 'staff', 'admin', 'administrator', 'facilitator', 'facilitators' ) );
	$excluded = array_map( 'sanitize_title', $excluded );
	$types    = (array) bp_get_member_type( $user_id, false );

	foreach ( $types as $type ) {
		$names = array( sanitize_title( $type ) );
		if ( function_exists( 'bp_get_member_type_object' ) ) {
			$object = bp_get_member_type_object( $type );
			if ( $object && ! empty( $object->labels ) ) {
				$names[] = sanitize_title( isset( $object->labels['name'] ) ? $object->labels['name'] : '' );
				$names[] = sanitize_title( isset( $object->labels['singular_name'] ) ? $object->labels['singular_name'] : '' );
			}
		}
		if ( array_intersect( array_filter( $names ), $excluded ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Return the first populated value from an Event Tickets attendee record.
 *
 * @param array    $attendee Attendee data.
 * @param string[] $keys     Candidate keys.
 * @return string
 */
function cen_ear_get_attendee_value( $attendee, $keys ) {
	foreach ( $keys as $key ) {
		if ( isset( $attendee[ $key ] ) && is_scalar( $attendee[ $key ] ) && '' !== trim( (string) $attendee[ $key ] ) ) {
			return cen_ear_decode_text( $attendee[ $key ] );
		}
	}

	return '';
}

/**
 * Normalize an attendee field label or key for reliable comparisons.
 *
 * @param string $value Field label or key.
 * @return string
 */
function cen_ear_normalize_attendee_field_key( $value ) {
	return str_replace( '_', '-', sanitize_title( (string) $value ) );
}

/**
 * Find a custom attendee field value in Event Tickets metadata.
 *
 * Event Tickets versions and ticket providers expose attendee fields in a few
 * different shapes. This supports associative slug/value arrays as well as
 * label/value records without depending on a provider-specific class.
 *
 * @param mixed    $metadata Attendee metadata.
 * @param string[] $aliases  Accepted field labels or slugs.
 * @return string
 */
function cen_ear_find_attendee_meta_value( $metadata, $aliases ) {
	$metadata = maybe_unserialize( $metadata );
	if ( is_object( $metadata ) ) {
		$metadata = get_object_vars( $metadata );
	}
	if ( ! is_array( $metadata ) ) {
		return '';
	}

	$aliases = array_map( 'cen_ear_normalize_attendee_field_key', (array) $aliases );
	$label_keys = array( 'label', 'name', 'slug', 'key', 'field', 'field_name' );
	$value_keys = array( 'value', 'answer', 'response', 'field_value' );
	foreach ( $label_keys as $label_key ) {
		if ( ! isset( $metadata[ $label_key ] ) || ! is_scalar( $metadata[ $label_key ] ) ) {
			continue;
		}
		if ( ! in_array( cen_ear_normalize_attendee_field_key( $metadata[ $label_key ] ), $aliases, true ) ) {
			continue;
		}
		foreach ( $value_keys as $value_key ) {
			if ( isset( $metadata[ $value_key ] ) && is_scalar( $metadata[ $value_key ] ) ) {
				return cen_ear_decode_text( $metadata[ $value_key ] );
			}
		}
	}

	foreach ( $metadata as $key => $value ) {
		if ( is_scalar( $value ) && in_array( cen_ear_normalize_attendee_field_key( $key ), $aliases, true ) ) {
			return cen_ear_decode_text( $value );
		}
	}

	foreach ( $metadata as $value ) {
		$match = cen_ear_find_attendee_meta_value( $value, $aliases );
		if ( '' !== $match ) {
			return $match;
		}
	}

	return '';
}

/**
 * Return the requested Event Tickets attendee registration field.
 *
 * @param array    $attendee Attendee data.
 * @param string[] $aliases  Accepted field labels or slugs.
 * @return string
 */
function cen_ear_get_attendee_meta_value( $attendee, $aliases ) {
	$metadata = array();
	foreach ( array( 'attendee_meta', 'attendee_meta_fields', 'meta', 'custom_fields' ) as $metadata_key ) {
		if ( isset( $attendee[ $metadata_key ] ) ) {
			$metadata[ $metadata_key ] = $attendee[ $metadata_key ];
		}
	}
	foreach ( (array) $aliases as $alias ) {
		foreach ( $attendee as $key => $candidate ) {
			if ( cen_ear_normalize_attendee_field_key( $key ) === cen_ear_normalize_attendee_field_key( $alias ) ) {
				$metadata[ $key ] = $candidate;
			}
		}
	}
	$value = cen_ear_find_attendee_meta_value( $metadata, $aliases );

	return is_scalar( $value ) ? cen_ear_decode_text( $value ) : '';
}

/**
 * Normalize an Event Tickets attendee record to the RSVP labels used by the UI.
 *
 * @param array $attendee Attendee data.
 * @return string going, not-going, or cancelled.
 */
function cen_ear_get_attendee_rsvp_status( $attendee ) {
	$status = isset( $attendee['order_status'] ) ? sanitize_key( $attendee['order_status'] ) : '';
	if ( in_array( $status, array( 'cancelled', 'canceled', 'wc-cancelled' ), true ) ) {
		return 'cancelled';
	}
	if ( in_array( $status, array( 'no', 'not-going', 'failed', 'refunded', 'trash', 'wc-failed', 'wc-refunded' ), true ) ) {
		return 'not-going';
	}

	return 'going';
}

/**
 * Return the display label for a normalized Event Tickets RSVP status.
 *
 * @param string $status Normalized RSVP status.
 * @return string
 */
function cen_ear_get_attendee_rsvp_status_label( $status ) {
	if ( 'cancelled' === $status ) {
		return __( 'Cancelled', 'member-search' );
	}
	if ( 'not-going' === $status ) {
		return __( 'Not Going', 'member-search' );
	}

	return __( 'Going', 'member-search' );
}

/**
 * Split an accountless attendee's name into first and last name values.
 *
 * @param string $name Attendee name.
 * @return array{first_name:string,last_name:string}
 */
function cen_ear_split_attendee_name( $name ) {
	$name = cen_ear_decode_text( $name );
	if ( false !== strpos( $name, ',' ) ) {
		$parts = array_map( 'trim', explode( ',', $name, 2 ) );

		return array(
			'first_name' => isset( $parts[1] ) ? $parts[1] : '',
			'last_name'  => isset( $parts[0] ) ? $parts[0] : '',
		);
	}

	$parts = preg_split( '/\s+/', $name, 2 );

	return array(
		'first_name' => isset( $parts[0] ) ? $parts[0] : '',
		'last_name'  => isset( $parts[1] ) ? $parts[1] : '',
	);
}

/**
 * Return account-linked users and accountless guests with selected RSVP statuses.
 *
 * @param int[] $event_ids Event IDs.
 * @param string[]  $rsvp_statuses RSVP status slugs.
 * @param bool|null $checked_in Require checked-in records when true, unchecked records when false, or either when null.
 * @return array{user_ids:int[],guests:array<string,array<string,string>>,user_statuses:array<int,string[]>,user_attendee_fields:array<int,array<string,string>>}|WP_Error
 */
function cen_ear_get_event_attendee_records( $event_ids, $rsvp_statuses = array( 'going', 'not-going', 'cancelled' ), $checked_in = null ) {
	if ( ! class_exists( 'Tribe__Tickets__Tickets' ) ) {
		return new WP_Error( 'missing_event_tickets', __( 'Event Tickets must be active before Event attendees can be searched.', 'member-search' ) );
	}

	static $cache = array();
	$event_ids    = array_values( array_unique( array_filter( array_map( 'absint', (array) $event_ids ) ) ) );
	$rsvp_statuses = array_values( array_intersect( array( 'going', 'not-going', 'cancelled' ), array_map( 'sanitize_key', (array) $rsvp_statuses ) ) );
	if ( empty( $rsvp_statuses ) ) {
		$rsvp_statuses = array( 'going', 'not-going', 'cancelled' );
	}
	sort( $event_ids, SORT_NUMERIC );
	$cache_key = implode( ',', $event_ids ) . '|' . implode( ',', $rsvp_statuses ) . '|' . ( null === $checked_in ? 'any' : ( $checked_in ? 'in' : 'out' ) );
	if ( isset( $cache[ $cache_key ] ) ) {
		return $cache[ $cache_key ];
	}

	$user_ids = array();
	$guests   = array();
	$user_statuses = array();
	$user_attendee_fields = array();
	foreach ( $event_ids as $event_id ) {
		$attendees = Tribe__Tickets__Tickets::get_event_attendees( absint( $event_id ) );
		foreach ( (array) $attendees as $attendee ) {
			$attendee = (array) $attendee;
			$is_checked_in = isset( $attendee['check_in'] ) && 1 === absint( $attendee['check_in'] );
			if ( null !== $checked_in && (bool) $checked_in !== $is_checked_in ) {
				continue;
			}
			$rsvp_status = cen_ear_get_attendee_rsvp_status( $attendee );
			if ( ! in_array( $rsvp_status, $rsvp_statuses, true ) ) {
				continue;
			}
			$rsvp_label = cen_ear_get_attendee_rsvp_status_label( $rsvp_status );
			$attendee_fields = array(
				'title'   => cen_ear_get_attendee_meta_value( $attendee, array( 'Job Title', 'job_title' ) ),
				'company' => cen_ear_get_attendee_meta_value( $attendee, array( 'Company Name', 'company_name', 'company', 'organization' ) ),
				'phone'   => cen_ear_get_attendee_meta_value( $attendee, array( 'Mobile Phone', 'mobile_phone', 'mobile', 'phone', 'telephone' ) ),
			);

			$user_id = ! empty( $attendee['user_id'] ) ? absint( $attendee['user_id'] ) : 0;
			if ( ! $user_id ) {
				$email = ! empty( $attendee['holder_email'] ) ? sanitize_email( $attendee['holder_email'] ) : sanitize_email( isset( $attendee['purchaser_email'] ) ? $attendee['purchaser_email'] : '' );
				$user  = $email ? get_user_by( 'email', $email ) : false;
				$user_id = $user ? (int) $user->ID : 0;
			}
			if ( $user_id ) {
				$user_ids[] = $user_id;
				$user_statuses[ $user_id ][] = $rsvp_label;
				if ( ! isset( $user_attendee_fields[ $user_id ] ) ) {
					$user_attendee_fields[ $user_id ] = array( 'title' => '', 'company' => '', 'phone' => '' );
				}
				foreach ( $attendee_fields as $field_key => $field_value ) {
					if ( '' !== $field_value && '' === $user_attendee_fields[ $user_id ][ $field_key ] ) {
						$user_attendee_fields[ $user_id ][ $field_key ] = $field_value;
					}
				}
				continue;
			}

			$email = sanitize_email( cen_ear_get_attendee_value( $attendee, array( 'holder_email', 'purchaser_email' ) ) );
			$name  = cen_ear_get_attendee_value( $attendee, array( 'holder_name', 'purchaser_name' ) );
			if ( '' === $email && '' === $name ) {
				continue;
			}

			$name_parts = cen_ear_split_attendee_name( $name );
			$name_key   = strtolower( $name_parts['last_name'] . '|' . $name_parts['first_name'] );
			$guest_key  = '' !== $email ? strtolower( $email ) . '|' . $name_key : $name_key;
			$guests[ $guest_key ] = array(
				'first_name' => $name_parts['first_name'],
				'last_name'  => $name_parts['last_name'],
				'title'      => $attendee_fields['title'],
				'company'    => $attendee_fields['company'],
				'email'      => $email,
				'phone'      => $attendee_fields['phone'],
				'wp_user_id' => __( 'Guest', 'member-search' ),
				'bb_status'  => '',
				'bb_groups'  => '',
				'event_rsvp_status' => $rsvp_label,
			);
		}
	}

	$cache[ $cache_key ] = array(
		'user_ids' => array_values( array_unique( $user_ids ) ),
		'guests'   => $guests,
		'user_statuses' => array_map(
			static function ( $statuses ) {
				return array_values( array_unique( $statuses ) );
			},
			$user_statuses
		),
		'user_attendee_fields' => $user_attendee_fields,
	);

	return $cache[ $cache_key ];
}

/**
 * Return WordPress users registered for any selected event, regardless of RSVP status.
 *
 * @param int[] $event_ids Event IDs.
 * @return int[]|WP_Error
 */
function cen_ear_get_event_attendee_user_ids( $event_ids ) {
	$records = cen_ear_get_event_attendee_records( $event_ids );

	return is_wp_error( $records ) ? $records : $records['user_ids'];
}

/**
 * Determine whether an attendee record was created by the RSVP provider.
 *
 * @param array $attendee Attendee data.
 * @return bool
 */
function cen_ear_is_rsvp_attendee( $attendee ) {
	foreach ( array( 'provider', 'provider_slug', 'ticket_provider', 'attendee_source', 'ticket_type' ) as $key ) {
		if ( ! isset( $attendee[ $key ] ) ) {
			continue;
		}
		$value = is_object( $attendee[ $key ] ) ? get_class( $attendee[ $key ] ) : (string) $attendee[ $key ];
		if ( false !== stripos( $value, 'rsvp' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Build one attendance-report row per Event Tickets/RSVP attendee.
 *
 * @param int  $event_id Event ID.
 * @param bool $include_suspended Whether suspended BuddyBoss profiles may be included.
 * @return array<int,array<string,string>>|WP_Error
 */
function cen_ear_get_attendance_report_rows( $event_id, $include_suspended = false ) {
	if ( ! class_exists( 'Tribe__Tickets__Tickets' ) ) {
		return new WP_Error( 'missing_event_tickets', __( 'Event Tickets must be active before attendance reports can be exported.', 'member-search' ) );
	}

	$field_ids = cen_ear_get_profile_field_ids();
	$rows      = array();
	foreach ( (array) Tribe__Tickets__Tickets::get_event_attendees( absint( $event_id ) ) as $attendee ) {
		$attendee = (array) $attendee;
		$user_id  = ! empty( $attendee['user_id'] ) ? absint( $attendee['user_id'] ) : 0;
		$email    = sanitize_email( cen_ear_get_attendee_value( $attendee, array( 'holder_email', 'purchaser_email' ) ) );
		if ( ! $user_id && $email ) {
			$user    = get_user_by( 'email', $email );
			$user_id = $user ? (int) $user->ID : 0;
		}
		if ( ! $include_suspended && $user_id && cen_ear_is_user_suspended( $user_id ) ) {
			continue;
		}
		$user = $user_id ? get_userdata( $user_id ) : false;
		if ( '' === $email && $user ) {
			$email = sanitize_email( $user->user_email );
		}

		$name = cen_ear_get_attendee_value( $attendee, array( 'holder_name', 'purchaser_name' ) );
		if ( '' === $name && $user ) {
			$first_name = function_exists( 'xprofile_get_field_data' ) ? cen_ear_get_xprofile_field( $user_id, $field_ids['first_name'] ) : '';
			$last_name  = function_exists( 'xprofile_get_field_data' ) ? cen_ear_get_xprofile_field( $user_id, $field_ids['last_name'] ) : '';
			$name       = trim( $first_name . ' ' . $last_name );
			$name       = '' !== $name ? $name : cen_ear_decode_text( $user->display_name );
		}

		$phone   = cen_ear_get_attendee_meta_value( $attendee, array( 'Mobile Phone', 'mobile_phone', 'mobile', 'phone', 'telephone' ) );
		$title   = cen_ear_get_attendee_meta_value( $attendee, array( 'Job Title', 'job_title' ) );
		$company = cen_ear_get_attendee_meta_value( $attendee, array( 'Company Name', 'company_name', 'company', 'organization' ) );
		if ( $user_id && function_exists( 'xprofile_get_field_data' ) ) {
			$phone   = '' !== $phone ? $phone : cen_ear_get_xprofile_field( $user_id, CEN_EAR_XPROFILE_PHONE_FIELD_ID );
			$title   = '' !== $title ? $title : cen_ear_get_xprofile_field( $user_id, $field_ids['title'] );
			$company = '' !== $company ? $company : cen_ear_get_xprofile_field( $user_id, $field_ids['company'] );
		}

		$is_rsvp         = cen_ear_is_rsvp_attendee( $attendee );
		$attendee_status = cen_ear_get_attendee_meta_value( $attendee, array( 'Attendee Status', 'attendee_status', 'status' ) );
		$attendee_id     = isset( $attendee['attendee_id'] ) ? absint( $attendee['attendee_id'] ) : 0;
		$rsvp_date       = $attendee_id ? get_post_time( 'n/j/Y g:i A', false, $attendee_id, true ) : '';
		if ( $is_rsvp ) {
			$attendee_status = $user_id ? cen_ear_get_buddyboss_profile_types( $user_id ) : '';
		}

		$row = array(
			'check_in_status' => ! empty( $attendee['check_in'] ) ? __( 'Yes', 'member-search' ) : __( 'No', 'member-search' ),
			'phone'           => $phone,
			'title'           => $title,
			'company'         => $company,
			'cpe_option'      => cen_ear_get_attendee_meta_value( $attendee, array( 'CPE Option', 'cpe_option', 'cpe' ) ),
			'attendee_status' => $attendee_status,
			'invited_by'      => $is_rsvp ? '' : cen_ear_get_attendee_meta_value( $attendee, array( 'Who can we thank for inviting you?', 'who_can_we_thank_for_inviting_you', 'invited_by' ) ),
			'speaker_question' => $is_rsvp ? '' : cen_ear_get_attendee_meta_value( $attendee, array( 'Do you have a question for the speaker?', 'do_you_have_a_question_for_the_speaker', 'speaker_question' ) ),
			'rsvp_date'         => $rsvp_date,
			'event_rsvp_status' => cen_ear_get_attendee_rsvp_status_label( cen_ear_get_attendee_rsvp_status( $attendee ) ),
			'name'             => $name,
			'email'            => $email,
		);
		$row_key = $user_id
			? 'user:' . $user_id
			: 'guest:' . strtolower( trim( $email ) . '|' . trim( $name ) );
		if ( 'guest:|' === $row_key ) {
			$row_key = 'attendee:' . ( isset( $attendee['attendee_id'] ) ? absint( $attendee['attendee_id'] ) : count( $rows ) );
		}
		if ( ! isset( $rows[ $row_key ] ) ) {
			$rows[ $row_key ] = $row;
			continue;
		}
		if ( __( 'Yes', 'member-search' ) === $row['check_in_status'] ) {
			$rows[ $row_key ]['check_in_status'] = $row['check_in_status'];
		}
		foreach ( $row as $field_key => $field_value ) {
			if ( '' !== $field_value && empty( $rows[ $row_key ][ $field_key ] ) ) {
				$rows[ $row_key ][ $field_key ] = $field_value;
			}
		}
	}

	return array_values( $rows );
}

/**
 * Return the event IDs relevant to submitted criteria, or every event when an
 * RSVP status is searched without a separate Event criterion.
 *
 * @param array $rules Validated criteria.
 * @param array $attributes Attribute registry.
 * @return int[]
 */
function cen_ear_get_criteria_event_ids( $rules, $attributes ) {
	$event_ids = array();
	$has_rsvp_status_rule = false;
	foreach ( (array) $rules as $rule ) {
		$definition = isset( $attributes[ $rule['attribute'] ] ) ? $attributes[ $rule['attribute'] ] : array();
		$query_type = isset( $definition['query_type'] ) ? $definition['query_type'] : '';
		if ( 'event_attendance' === $query_type && in_array( $rule['operator'], array( 'is', 'attended' ), true ) ) {
			$event_ids = array_merge( $event_ids, array_map( 'absint', $rule['values'] ) );
		} elseif ( 'event_rsvp_status' === $query_type ) {
			$has_rsvp_status_rule = true;
		}
	}
	if ( empty( $event_ids ) && $has_rsvp_status_rule && isset( $attributes['event']['choices'] ) ) {
		$event_ids = array_keys( $attributes['event']['choices'] );
	}

	return array_values( array_unique( array_filter( array_map( 'absint', $event_ids ) ) ) );
}

/**
 * Return accountless guests when every criterion is a positive Event search.
 *
 * @param array<int,array<string,mixed>>   $rules      Validated criteria.
 * @param array<string,array<string,mixed>> $attributes Attribute registry.
 * @return array<int,array<string,string>>|WP_Error
 */
function cen_ear_get_matching_event_guests( $rules, $attributes ) {
	$event_ids = array();
	$statuses  = array( 'going', 'not-going', 'cancelled' );
	$checked_in = null;
	$has_event_rule = false;
	foreach ( $rules as $rule ) {
		$definition = isset( $attributes[ $rule['attribute'] ] ) ? $attributes[ $rule['attribute'] ] : array();
		$query_type = isset( $definition['query_type'] ) ? $definition['query_type'] : '';
		$is_positive_event_rule = 'event_attendance' === $query_type && in_array( $rule['operator'], array( 'is', 'attended' ), true );
		$is_positive_status_rule = 'event_rsvp_status' === $query_type && 'is' === $rule['operator'];
		if ( ! $is_positive_event_rule && ! $is_positive_status_rule ) {
			return array();
		}
		$has_event_rule = true;
		if ( 'event_attendance' === $query_type ) {
			$event_ids = array_merge( $event_ids, array_map( 'absint', $rule['values'] ) );
			if ( 'attended' === $rule['operator'] ) {
				$checked_in = true;
			}
		} else {
			$statuses = $rule['values'];
		}
	}
	if ( ! $has_event_rule ) {
		return array();
	}
	if ( empty( $event_ids ) && isset( $attributes['event']['choices'] ) ) {
		$event_ids = array_keys( $attributes['event']['choices'] );
	}
	$records = cen_ear_get_event_attendee_records( $event_ids, $statuses, $checked_in );
	if ( is_wp_error( $records ) ) {
		return $records;
	}

	$guests = array_values( $records['guests'] );
	usort(
		$guests,
		static function ( $left, $right ) {
			$last_name = strcasecmp( $left['last_name'], $right['last_name'] );

			return 0 !== $last_name ? $last_name : strcasecmp( $left['first_name'], $right['first_name'] );
		}
	);

	return $guests;
}

/**
 * Define the attributes available to the reusable criteria builder.
 *
 * New attributes can be registered through the cen_ear_search_attributes filter.
 *
 * @param array<int,string>|WP_Error $event_choices Events Calendar choices.
 * @return array<string,array<string,mixed>>
 */

function cen_ear_get_search_attributes( $event_choices ) {
	$choice_operators = array(
		'is'           => __( 'Is', 'member-search' ),
		'is_not'       => __( 'Is not', 'member-search' ),
		'is_empty'     => __( 'Is empty', 'member-search' ),
		'is_not_empty' => __( 'Is not empty', 'member-search' ),
	);
	$text_operators = array(
		'is'               => __( 'Is', 'member-search' ),
		'is_not'           => __( 'Is not', 'member-search' ),
		'contains'         => __( 'Contains', 'member-search' ),
		'does_not_contain' => __( 'Does not contain', 'member-search' ),
		'is_empty'         => __( 'Is empty', 'member-search' ),
		'is_not_empty'     => __( 'Is not empty', 'member-search' ),
	);
	$date_operators = array(
		'is'                => __( 'Is', 'member-search' ),
		'is_not'            => __( 'Is not', 'member-search' ),
		'is_empty'          => __( 'Is empty', 'member-search' ),
		'is_not_empty'      => __( 'Is not empty', 'member-search' ),
		'between_inclusive' => __( 'Between and including', 'member-search' ),
		'on_or_before'      => __( 'On or before', 'member-search' ),
		'on_or_after'       => __( 'On or after', 'member-search' ),
	);
	$attributes = array(
		'attendance_report' => array(
			'label'          => __( 'Attendance Report', 'member-search' ),
			'type'           => 'single_choice',
			'criterion_type' => 'direct',
			'query_type'     => 'event_attendance',
			'choices'        => is_wp_error( $event_choices ) ? array() : $event_choices,
			'operators'      => array( 'is' => __( 'Is', 'member-search' ) ),
			'hide_operator'  => true,
			'available'      => ! is_wp_error( $event_choices ) && class_exists( 'Tribe__Tickets__Tickets' ),
		),
		'event' => array(
			'label'     => __( 'Event', 'member-search' ),
			'type'      => 'multi_choice',
			'criterion_type' => 'direct',
			'query_type' => 'event_attendance',
			'choices'   => is_wp_error( $event_choices ) ? array() : $event_choices,
			'operators' => array(
				'is'       => __( 'Is RSVPed', 'member-search' ),
				'is_not'   => __( 'Is not RSVPed', 'member-search' ),
				'attended' => __( 'Attended', 'member-search' ),
			),
			'available' => ! is_wp_error( $event_choices ),
		),
		'event_rsvp_status' => array(
			'label'          => __( 'RSVP Status', 'member-search' ),
			'type'           => 'multi_choice',
			'criterion_type' => 'attribute',
			'query_type'     => 'event_rsvp_status',
			'choices'        => array(
				'going'     => __( 'Going', 'member-search' ),
				'not-going' => __( 'Not Going', 'member-search' ),
				'cancelled' => __( 'Cancelled', 'member-search' ),
			),
			'operators'      => array(
				'is'     => __( 'Is', 'member-search' ),
				'is_not' => __( 'Is not', 'member-search' ),
			),
			'available'      => ! is_wp_error( $event_choices ) && class_exists( 'Tribe__Tickets__Tickets' ),
		),
	);

	$text_fields = array(
		'first_name'      => array( 'First Name', 'xprofile_text', CEN_EAR_XPROFILE_FIRST_NAME_FIELD_ID ),
		'last_name'       => array( 'Last Name', 'xprofile_text', CEN_EAR_XPROFILE_LAST_NAME_FIELD_ID ),
		'title'           => array( 'Job Title', 'xprofile_text', CEN_EAR_XPROFILE_TITLE_FIELD_ID ),
		'company'         => array( 'Company Name', 'xprofile_text', CEN_EAR_XPROFILE_COMPANY_FIELD_ID ),
		'email'           => array( 'Email', 'user_field_text', 'user_email' ),
		'wp_user_id'      => array( 'WP User ID', 'user_field_text', 'ID' ),
		'phone'           => array( 'Phone Number', 'xprofile_text', CEN_EAR_XPROFILE_PHONE_FIELD_ID ),
	);
	foreach ( $text_fields as $key => $field_definition ) {
		$attribute = array(
			'label'          => $field_definition[0],
			'type'           => 'multi_text',
			'criterion_type' => 'attribute',
			'query_type'     => $field_definition[1],
			'choices'        => array(),
			'operators'      => $text_operators,
			'available'      => 'xprofile_text' !== $field_definition[1] || function_exists( 'xprofile_get_field_data' ),
		);
		if ( 'xprofile_text' === $field_definition[1] ) {
			$attribute['xprofile_field_id'] = $field_definition[2];
		} elseif ( 'user_meta_text' === $field_definition[1] ) {
			$attribute['meta_key'] = $field_definition[2];
		} else {
			$attribute['user_field'] = $field_definition[2];
		}
		$attributes[ $key ] = $attribute;
	}

	foreach ( cen_ear_get_additional_profile_fields() as $key => $profile_field ) {
		if ( empty( $profile_field['search_type'] ) ) {
			continue;
		}
		if ( 'choice' === $profile_field['search_type'] ) {
			$attributes[ $key ] = array(
				'label'             => $profile_field['label'],
				'type'              => 'multi_choice',
				'criterion_type'    => 'attribute',
				'query_type'        => 'xprofile_choice',
				'choices'           => isset( $profile_field['choices'] ) ? (array) $profile_field['choices'] : array(),
				'operators'         => $choice_operators,
				'available'         => function_exists( 'xprofile_get_field_data' ),
				'xprofile_field_id' => absint( $profile_field['id'] ),
			);
			continue;
		}

		$attributes[ $key ] = array(
			'label'             => $profile_field['label'],
			'type'              => 'multi_text',
			'criterion_type'    => 'attribute',
			'query_type'        => 'xprofile_text',
			'choices'           => array(),
			'operators'         => $text_operators,
			'available'         => function_exists( 'xprofile_get_field_data' ),
			'xprofile_field_id' => absint( $profile_field['id'] ),
		);
	}

	$date_fields = array(
		'join_date'   => array( 'Join Date', 'user_field_date', 'user_registered' ),
		'last_active' => array( 'BB Last Active', 'buddyboss_last_active', '' ),
	);
	foreach ( $date_fields as $key => $field_definition ) {
		$attribute = array(
			'label'          => $field_definition[0],
			'type'           => 'date',
			'criterion_type' => 'attribute',
			'query_type'     => $field_definition[1],
			'choices'        => array(),
			'operators'      => $date_operators,
			'available'      => ( 'xprofile_date' !== $field_definition[1] || function_exists( 'xprofile_get_field_data' ) ) && ( 'buddyboss_last_active' !== $field_definition[1] || function_exists( 'bp_get_user_last_activity' ) ),
		);
		if ( 'user_field_date' === $field_definition[1] ) {
			$attribute['user_field'] = $field_definition[2];
		} elseif ( 'user_meta_date' === $field_definition[1] ) {
			$attribute['meta_key'] = $field_definition[2];
		} elseif ( 'xprofile_date' === $field_definition[1] ) {
			$attribute['xprofile_field_id'] = $field_definition[2];
		}
		$attributes[ $key ] = $attribute;
	}

	$attributes['completed_onboarding'] = array(
		'label'          => 'Completed Onboarding',
		'type'           => 'single_choice',
		'criterion_type' => 'attribute',
		'query_type'     => 'gravity_forms_onboarding',
		'choices'        => array(
			'yes' => __( 'Yes', 'member-search' ),
			'no'  => __( 'No', 'member-search' ),
		),
		'operators'      => array( 'is' => __( 'Is', 'member-search' ) ),
		'available'      => class_exists( 'GFForms' ) || class_exists( 'GFAPI' ),
	);
	$attributes['forum_discussion_emails'] = array(
		'label'          => 'Forum Discussion Emails',
		'type'           => 'single_choice',
		'criterion_type' => 'attribute',
		'query_type'     => 'buddyboss_forum_discussion_emails',
		'choices'        => array(
			'subscribed'   => __( 'Subscribed', 'member-search' ),
			'unsubscribed' => __( 'Unsubscribed', 'member-search' ),
		),
		'operators'      => array(
			'is'     => __( 'Is', 'member-search' ),
			'is_not' => __( 'Is not', 'member-search' ),
		),
		'available'      => true,
	);

	$membership_plan_choices = cen_ear_get_woocommerce_membership_plan_choices();
	$attributes['membership_plan'] = array(
		'label'          => 'Membership Plan',
		'type'           => 'multi_choice',
		'criterion_type' => 'attribute',
		'query_type'     => 'woocommerce_membership_plan',
		'choices'        => is_array( $membership_plan_choices ) ? $membership_plan_choices : array(),
		'operators'      => $choice_operators,
		'available'      => is_array( $membership_plan_choices ) && ! empty( $membership_plan_choices ),
	);
	$attributes['membership_plan_expiration'] = array(
		'label'          => 'Membership Plan Expiration',
		'type'           => 'date',
		'criterion_type' => 'attribute',
		'query_type'     => 'woocommerce_membership_expiration',
		'choices'        => array(),
		'operators'      => $date_operators,
		'available'      => function_exists( 'wc_memberships_get_user_memberships' ),
	);

	$buddyboss_group_choices        = cen_ear_get_buddyboss_group_choices();
	$buddyboss_profile_type_choices = cen_ear_get_buddyboss_profile_type_choices();
	$attributes['bb_status'] = array(
		'label'          => 'BB Status',
		'type'           => 'multi_choice',
		'criterion_type' => 'attribute',
		'query_type'     => 'buddyboss_status',
		'choices'        => array(
			'active'    => __( 'Active', 'member-search' ),
			'suspended' => __( 'Suspended', 'member-search' ),
		),
		'operators'      => $choice_operators,
		'available'      => function_exists( 'bp_moderation_is_user_suspended' ) || function_exists( 'bp_is_user_spammer' ),
	);
	$attributes['bb_profile_type'] = array(
		'label'          => 'BB Profile Type',
		'type'           => 'multi_choice',
		'criterion_type' => 'attribute',
		'query_type'     => 'buddyboss_profile_type',
		'choices'        => is_array( $buddyboss_profile_type_choices ) ? $buddyboss_profile_type_choices : array(),
		'operators'      => $choice_operators,
		'available'      => is_array( $buddyboss_profile_type_choices ) && ! empty( $buddyboss_profile_type_choices ),
	);
	$attributes['bb_groups'] = array(
		'label'          => 'BB Groups',
		'type'           => 'multi_choice',
		'criterion_type' => 'attribute',
		'query_type'     => 'buddyboss_group',
		'choices'        => is_array( $buddyboss_group_choices ) ? $buddyboss_group_choices : array(),
		'operators'      => $choice_operators,
		'available'      => is_array( $buddyboss_group_choices ) && ! is_wp_error( $buddyboss_group_choices ),
	);

	// Retain the original filter argument positions for existing integrations.
	return (array) apply_filters( 'cen_ear_search_attributes', $attributes, null, $event_choices, null );
}

/**
 * Read zero or more reusable criteria rows from a request.
 *
 * @param array<mixed> $source Request data.
 * @return array<int,array{attribute:string,operator:string,values:string[]}>
 */
function cen_ear_get_criteria_rules( $source ) {
	$rules = array();
	if ( isset( $source['criteria'] ) && is_array( $source['criteria'] ) ) {
		foreach ( array_slice( (array) wp_unslash( $source['criteria'] ), 0, 25 ) as $submitted_rule ) {
			if ( ! is_array( $submitted_rule ) ) {
				continue;
			}
			$values = isset( $submitted_rule['values'] ) ? (array) $submitted_rule['values'] : array();
			$values = array_values( array_filter( array_map( 'sanitize_text_field', array_filter( $values, 'is_scalar' ) ) ) );
			$rules[] = array(
				'attribute' => isset( $submitted_rule['attribute'] ) && is_scalar( $submitted_rule['attribute'] ) ? sanitize_key( (string) $submitted_rule['attribute'] ) : '',
				'operator'  => isset( $submitted_rule['operator'] ) && is_scalar( $submitted_rule['operator'] ) ? sanitize_key( (string) $submitted_rule['operator'] ) : '',
				'values'    => $values,
			);
		}
		return $rules;
	}

	return $rules;
}

/**
 * Validate criteria against the registered attribute definitions.
 *
 * @param array<int,array<string,mixed>> $rules Submitted rules.
 * @param array<string,array<string,mixed>> $attributes Attribute registry.
 * @return array<int,array{attribute:string,operator:string,values:string[]}>|WP_Error
 */
function cen_ear_validate_criteria_rules( $rules, $attributes ) {
	$validated = array();
	foreach ( $rules as $rule ) {
		$attribute = isset( $rule['attribute'] ) ? sanitize_key( $rule['attribute'] ) : '';
		$operator  = isset( $rule['operator'] ) ? sanitize_key( $rule['operator'] ) : '';
		$values    = isset( $rule['values'] ) ? array_values( array_map( 'strval', (array) $rule['values'] ) ) : array();

		if ( ! isset( $attributes[ $attribute ] ) || empty( $attributes[ $attribute ]['available'] ) ) {
			return new WP_Error( 'unsupported_attribute', __( 'A submitted search attribute is not available.', 'member-search' ) );
		}
		if ( ! isset( $attributes[ $attribute ]['operators'][ $operator ] ) ) {
			return new WP_Error( 'unsupported_operator', __( 'A submitted search condition is not supported.', 'member-search' ) );
		}
		if ( isset( $attributes[ $attribute ]['value_normalizer'] ) && 'digits' === $attributes[ $attribute ]['value_normalizer'] ) {
			$values = array_map(
				static function ( $value ) {
					return preg_replace( '/\D+/', '', $value );
				},
				$values
			);
			$values = array_values(
				array_unique(
					array_filter(
						$values,
						static function ( $value ) {
							return '' !== $value;
						}
					)
				)
			);
		}
		$requires_values = ! in_array( $operator, array( 'is_empty', 'is_not_empty' ), true );
		if ( $requires_values && empty( $values ) ) {
			return new WP_Error( 'missing_value', __( 'Select at least one value for every criterion.', 'member-search' ) );
		}
		if ( 'date' === $attributes[ $attribute ]['type'] && $requires_values ) {
			$expected_values = 'between_inclusive' === $operator ? 2 : 1;
			if ( $expected_values !== count( $values ) ) {
				return new WP_Error( 'invalid_date_count', __( 'Select every required date for each date criterion.', 'member-search' ) );
			}
			foreach ( $values as $value ) {
				if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $date_parts ) || ! checkdate( (int) $date_parts[2], (int) $date_parts[3], (int) $date_parts[1] ) ) {
					return new WP_Error( 'invalid_date', __( 'A submitted search date is invalid.', 'member-search' ) );
				}
			}
			if ( 'between_inclusive' === $operator && $values[0] > $values[1] ) {
				return new WP_Error( 'invalid_date_range', __( 'The start date must be on or before the end date.', 'member-search' ) );
			}
		}
		if ( 'single_choice' === $attributes[ $attribute ]['type'] && 1 !== count( $values ) ) {
			return new WP_Error( 'invalid_value_count', __( 'Select one event for the attendance report.', 'member-search' ) );
		}
		foreach ( $requires_values ? $values : array() as $value ) {
			if ( ! in_array( $attributes[ $attribute ]['type'], array( 'multi_text', 'date' ), true ) && ! array_key_exists( $value, (array) $attributes[ $attribute ]['choices'] ) ) {
				return new WP_Error( 'invalid_value', __( 'A selected value is no longer available.', 'member-search' ) );
			}
		}
		if ( ! $requires_values ) {
			$values = array();
		}
		$validated[] = compact( 'attribute', 'operator', 'values' );
	}
	$attendance_rules = array_filter(
		$validated,
		static function ( $rule ) {
			return 'attendance_report' === $rule['attribute'];
		}
	);
	if ( ! empty( $attendance_rules ) && 1 !== count( $validated ) ) {
		return new WP_Error( 'attendance_report_combined', __( 'Attendance Report must be used by itself.', 'member-search' ) );
	}

	return $validated;
}

/**
 * Return equivalent text forms that may exist in WordPress/BuddyBoss storage.
 *
 * Older or imported profile values may contain straight, typographic, encoded,
 * or double-encoded apostrophes. Searching all equivalent forms lets a value
 * such as O'Brien find O’Brien, O&#039;Brien, or O&amp;#039;Brien.
 *
 * @param string[] $values Submitted search values.
 * @return string[]
 */
function cen_ear_get_text_search_variants( $values ) {
	$variants = array();
	foreach ( (array) $values as $value ) {
		$value    = cen_ear_decode_text( $value );
		$straight = str_replace( array( '’', '‘' ), "'", $value );
		$forms    = array(
			$value,
			$straight,
			str_replace( "'", "\\'", $straight ),
			str_replace( "'", '’', $straight ),
			str_replace( "'", '&#039;', $straight ),
			str_replace( "'", '&#39;', $straight ),
			str_replace( "'", '&apos;', $straight ),
			str_replace( "'", '&#8217;', $straight ),
			str_replace( "'", '&rsquo;', $straight ),
		);
		foreach ( $forms as $form ) {
			if ( '' === $form ) {
				continue;
			}
			$variants[] = $form;
			if ( false !== strpos( $form, '&' ) ) {
				$variants[] = str_replace( '&', '&amp;', $form );
			}
		}
	}

	return array_values( array_unique( $variants ) );
}

/**
 * Return user IDs whose BuddyBoss text profile field matches a comparison.
 *
 * @param int      $field_id  BuddyBoss Extended Profile field ID.
 * @param string   $comparison Supported comparison: exact, contains, or nonempty.
 * @param string[] $values    Text values to compare with OR logic.
 * @return int[]
 */
function cen_ear_get_xprofile_text_match_ids( $field_id, $comparison, $values = array() ) {
	global $wpdb;
	if ( in_array( $comparison, array( 'exact', 'contains' ), true ) && empty( $values ) ) {
		return array();
	}
	if ( in_array( $comparison, array( 'exact', 'contains' ), true ) ) {
		$values = cen_ear_get_text_search_variants( $values );
	}

	$bp = function_exists( 'buddypress' ) ? buddypress() : null;
	$table_name = is_object( $bp ) && isset( $bp->profile->table_name_data )
		? $bp->profile->table_name_data
		: $wpdb->prefix . 'bp_xprofile_data';
	$params = array( absint( $field_id ) );

	if ( 'exact' === $comparison ) {
		$placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
		$sql          = "SELECT DISTINCT user_id FROM {$table_name} WHERE field_id = %d AND value IN ({$placeholders})";
		$params       = array_merge( $params, $values );
	} elseif ( 'contains' === $comparison ) {
		$clauses = array();
		foreach ( $values as $value ) {
			$clauses[] = 'value LIKE %s';
			$params[]  = '%' . $wpdb->esc_like( $value ) . '%';
		}
		$sql = "SELECT DISTINCT user_id FROM {$table_name} WHERE field_id = %d AND (" . implode( ' OR ', $clauses ) . ')';
	} else {
		$sql = "SELECT DISTINCT user_id FROM {$table_name} WHERE field_id = %d AND value <> ''";
	}

	// The table name is supplied by BuddyBoss; all values are prepared separately.
	$query = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

	return array_values( array_filter( array_map( 'absint', $wpdb->get_col( $query ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Return user IDs whose BuddyBoss choice field contains a selected value.
 *
 * BuddyBoss stores select-box values as plain text and checkbox values as
 * serialized arrays. Exact matching therefore checks both representations.
 *
 * @param int      $field_id  BuddyBoss Extended Profile field ID.
 * @param string   $comparison Supported comparison: exact or nonempty.
 * @param string[] $values    Stored choice values to compare with OR logic.
 * @return int[]
 */
function cen_ear_get_xprofile_choice_match_ids( $field_id, $comparison, $values = array() ) {
	global $wpdb;
	if ( 'exact' !== $comparison ) {
		return cen_ear_get_xprofile_text_match_ids( $field_id, $comparison, $values );
	}
	if ( empty( $values ) ) {
		return array();
	}

	$bp         = function_exists( 'buddypress' ) ? buddypress() : null;
	$table_name = is_object( $bp ) && isset( $bp->profile->table_name_data )
		? $bp->profile->table_name_data
		: $wpdb->prefix . 'bp_xprofile_data';
	$params     = array( absint( $field_id ) );
	$clauses    = array();
	foreach ( cen_ear_get_text_search_variants( $values ) as $value ) {
		$clauses[] = '(value = %s OR value LIKE %s)';
		$params[]  = $value;
		$params[]  = '%:"' . $wpdb->esc_like( $value ) . '";%';
	}

	$sql   = "SELECT DISTINCT user_id FROM {$table_name} WHERE field_id = %d AND (" . implode( ' OR ', $clauses ) . ')';
	$query = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

	return array_values( array_filter( array_map( 'absint', $wpdb->get_col( $query ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Return user IDs whose user-meta text value matches a comparison.
 *
 * @param string   $meta_key   User meta key.
 * @param string   $comparison Supported comparison: exact, contains, or nonempty.
 * @param string[] $values     Text values to compare with OR logic.
 * @return int[]
 */
function cen_ear_get_user_meta_text_match_ids( $meta_key, $comparison, $values = array() ) {
	global $wpdb;
	if ( in_array( $comparison, array( 'exact', 'contains' ), true ) && empty( $values ) ) {
		return array();
	}

	$params = array( $meta_key );
	if ( 'exact' === $comparison ) {
		$placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
		$sql          = "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value IN ({$placeholders})";
		$params       = array_merge( $params, $values );
	} elseif ( 'contains' === $comparison ) {
		$clauses = array();
		foreach ( $values as $value ) {
			$clauses[] = 'meta_value LIKE %s';
			$params[]  = '%' . $wpdb->esc_like( $value ) . '%';
		}
		$sql = "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND (" . implode( ' OR ', $clauses ) . ')';
	} else {
		$sql = "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value <> ''";
	}

	$query = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

	return array_values( array_filter( array_map( 'absint', $wpdb->get_col( $query ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Return user IDs whose whitelisted WordPress user field matches a comparison.
 *
 * @param string   $user_field WordPress users-table field.
 * @param string   $comparison Supported comparison: exact, contains, or nonempty.
 * @param string[] $values     Text values to compare with OR logic.
 * @return int[]
 */
function cen_ear_get_user_field_text_match_ids( $user_field, $comparison, $values = array() ) {
	global $wpdb;
	$expressions = array(
		'ID'         => 'CAST(ID AS CHAR)',
		'user_email' => 'user_email',
	);
	if ( ! isset( $expressions[ $user_field ] ) || ( in_array( $comparison, array( 'exact', 'contains' ), true ) && empty( $values ) ) ) {
		return array();
	}

	$expression = $expressions[ $user_field ];
	$params     = array();
	if ( 'exact' === $comparison ) {
		$placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
		$sql          = "SELECT ID FROM {$wpdb->users} WHERE {$expression} IN ({$placeholders})";
		$params       = $values;
	} elseif ( 'contains' === $comparison ) {
		$clauses = array();
		foreach ( $values as $value ) {
			$clauses[] = $expression . ' LIKE %s';
			$params[]  = '%' . $wpdb->esc_like( $value ) . '%';
		}
		$sql = "SELECT ID FROM {$wpdb->users} WHERE " . implode( ' OR ', $clauses );
	} else {
		$sql = "SELECT ID FROM {$wpdb->users} WHERE {$expression} <> ''";
	}

	$query = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

	return array_values( array_filter( array_map( 'absint', $wpdb->get_col( $query ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Build a SQL expression that converts supported stored date formats to a date.
 *
 * @param string $column Trusted SQL column name.
 * @return string
 */
function cen_ear_get_normalized_date_sql( $column ) {
	return "DATE(COALESCE(
		STR_TO_DATE(NULLIF({$column}, ''), '__CEN_DATE_TIME__'),
		STR_TO_DATE(NULLIF({$column}, ''), '__CEN_DATE_DASH__'),
		STR_TO_DATE(NULLIF({$column}, ''), '__CEN_DATE_COMPACT__'),
		STR_TO_DATE(NULLIF({$column}, ''), '__CEN_DATE_US_SLASH__'),
		STR_TO_DATE(NULLIF({$column}, ''), '__CEN_DATE_US_DASH__')
	))";
}

/**
 * Return user IDs whose BuddyBoss last-active date matches in Eastern Time.
 *
 * @param string   $comparison Supported comparison.
 * @param string[] $values     Date values in YYYY-MM-DD format.
 * @return int[]
 */
function cen_ear_get_buddyboss_last_active_match_ids( $comparison, $values = array() ) {
	global $wpdb;

	$bp         = function_exists( 'buddypress' ) ? buddypress() : null;
	$table_name = is_object( $bp ) && isset( $bp->members->table_name_last_activity ) ? $bp->members->table_name_last_activity : $wpdb->prefix . 'bp_activity';
	$component  = is_object( $bp ) && isset( $bp->members->id ) ? $bp->members->id : 'members';
	$sql        = "SELECT user_id, MAX(date_recorded) AS last_active FROM {$table_name} WHERE component = %s AND type = %s GROUP BY user_id";
	$query      = $wpdb->prepare( $sql, $component, 'last_activity' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$matches    = array();

	foreach ( (array) $wpdb->get_results( $query, ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$user_id = isset( $row['user_id'] ) ? absint( $row['user_id'] ) : 0;
		$date    = isset( $row['last_active'] ) ? cen_ear_format_buddyboss_last_active( $row['last_active'], 'Y-m-d' ) : '';
		if ( ! $user_id || '' === $date ) {
			continue;
		}

		$is_match = 'nonempty' === $comparison;
		if ( 'exact' === $comparison ) {
			$is_match = isset( $values[0] ) && $date === $values[0];
		} elseif ( 'between' === $comparison ) {
			$is_match = isset( $values[0], $values[1] ) && $date >= $values[0] && $date <= $values[1];
		} elseif ( 'on_or_before' === $comparison ) {
			$is_match = isset( $values[0] ) && $date <= $values[0];
		} elseif ( 'on_or_after' === $comparison ) {
			$is_match = isset( $values[0] ) && $date >= $values[0];
		}
		if ( $is_match ) {
			$matches[] = $user_id;
		}
	}

	return $matches;
}

/**
 * Return user IDs whose normalized date field matches a comparison.
 *
 * @param array<string,mixed> $definition Date attribute definition.
 * @param string              $comparison Supported comparison.
 * @param string[]            $values Date values in YYYY-MM-DD format.
 * @return int[]
 */
function cen_ear_get_date_match_ids( $definition, $comparison, $values = array() ) {
	global $wpdb;

	$query_type = isset( $definition['query_type'] ) ? $definition['query_type'] : '';
	if ( 'buddyboss_last_active' === $query_type ) {
		return cen_ear_get_buddyboss_last_active_match_ids( $comparison, $values );
	}
	$params     = array();
	if ( 'user_field_date' === $query_type && 'user_registered' === $definition['user_field'] ) {
		$table_name = $wpdb->users;
		$id_column  = 'ID';
		$column     = 'user_registered';
		$base_where = '1=1';
	} elseif ( 'user_meta_date' === $query_type ) {
		$table_name = $wpdb->usermeta;
		$id_column  = 'user_id';
		$column     = 'meta_value';
		$base_where = 'meta_key = %s';
		$params[]   = $definition['meta_key'];
	} elseif ( 'xprofile_date' === $query_type ) {
		$bp         = function_exists( 'buddypress' ) ? buddypress() : null;
		$table_name = is_object( $bp ) && isset( $bp->profile->table_name_data ) ? $bp->profile->table_name_data : $wpdb->prefix . 'bp_xprofile_data';
		$id_column  = 'user_id';
		$column     = 'value';
		$base_where = 'field_id = %d';
		$params[]   = absint( $definition['xprofile_field_id'] );
	} elseif ( 'woocommerce_membership_expiration' === $query_type ) {
		$table_name = "{$wpdb->posts} cen_membership INNER JOIN {$wpdb->postmeta} cen_expiration ON cen_expiration.post_id = cen_membership.ID";
		$id_column  = 'cen_membership.post_author';
		$column     = 'cen_expiration.meta_value';
		$base_where = "cen_membership.post_type = 'wc_user_membership' AND cen_membership.post_status <> 'trash' AND cen_expiration.meta_key = '_end_date'";
		$plan_ids   = isset( $definition['membership_plan_ids'] ) ? array_values( array_filter( array_map( 'absint', (array) $definition['membership_plan_ids'] ) ) ) : array();
		if ( ! empty( $plan_ids ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $plan_ids ), '%d' ) );
			$base_where  .= " AND cen_membership.post_parent IN ({$placeholders})";
			$params       = array_merge( $params, $plan_ids );
		}
	} else {
		return array();
	}

	$date_expression = cen_ear_get_normalized_date_sql( $column );
	if ( 'exact' === $comparison ) {
		$date_where = $date_expression . ' = %s';
		$params[]   = $values[0];
	} elseif ( 'between' === $comparison ) {
		$date_where = $date_expression . ' BETWEEN %s AND %s';
		$params[]   = $values[0];
		$params[]   = $values[1];
	} elseif ( 'on_or_before' === $comparison ) {
		$date_where = $date_expression . ' <= %s';
		$params[]   = $values[0];
	} elseif ( 'on_or_after' === $comparison ) {
		$date_where = $date_expression . ' >= %s';
		$params[]   = $values[0];
	} else {
		$date_where = $date_expression . ' IS NOT NULL';
	}

	$sql   = "SELECT DISTINCT {$id_column} FROM {$table_name} WHERE {$base_where} AND {$date_where}";
	$query = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	$query = strtr(
		$query,
		array(
			'__CEN_DATE_TIME__'     => '%Y-%m-%d %H:%i:%s',
			'__CEN_DATE_DASH__'     => '%Y-%m-%d',
			'__CEN_DATE_COMPACT__'  => '%Y%m%d',
			'__CEN_DATE_US_SLASH__' => '%m/%d/%Y',
			'__CEN_DATE_US_DASH__'  => '%m-%d-%Y',
		)
	);

	return array_values( array_filter( array_map( 'absint', $wpdb->get_col( $query ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Return user IDs assigned to selected WooCommerce Memberships plans.
 *
 * @param string $comparison Supported comparison: exact or nonempty.
 * @param int[]  $values Membership plan post IDs.
 * @return int[]
 */
function cen_ear_get_woocommerce_membership_plan_match_ids( $comparison, $values = array() ) {
	global $wpdb;

	$sql    = "SELECT DISTINCT post_author FROM {$wpdb->posts} WHERE post_type = 'wc_user_membership' AND post_status <> 'trash' AND post_author > 0";
	$params = array();
	if ( 'exact' === $comparison ) {
		$plan_ids = array_values( array_filter( array_map( 'absint', $values ) ) );
		if ( empty( $plan_ids ) ) {
			return array();
		}
		$placeholders = implode( ', ', array_fill( 0, count( $plan_ids ), '%d' ) );
		$sql         .= " AND post_parent IN ({$placeholders})";
		$params       = $plan_ids;
	}

	$query = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

	return array_values( array_filter( array_map( 'absint', $wpdb->get_col( $query ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Return user IDs whose BuddyBoss moderation status matches selected values.
 *
 * @param string   $comparison Supported comparison: exact or nonempty.
 * @param string[] $values Selected status values.
 * @return int[]
 */
function cen_ear_get_buddyboss_status_match_ids( $comparison, $values = array() ) {
	$user_ids = array_values( array_filter( array_map( 'absint', get_users( array( 'fields' => 'ID' ) ) ) ) );
	if ( 'nonempty' === $comparison ) {
		return $user_ids;
	}

	$values = array_map( 'strtolower', array_map( 'strval', $values ) );
	return array_values(
		array_filter(
			$user_ids,
			static function ( $user_id ) use ( $values ) {
				$status = strtolower( cen_ear_get_buddyboss_status( $user_id ) );

				return in_array( $status, $values, true );
			}
		)
	);
}

/**
 * Return user IDs whose forum-discussion email preference matches a selected
 * status. An unset BuddyBoss preference is treated as subscribed.
 *
 * @param string[] $values Selected statuses.
 * @return int[]
 */
function cen_ear_get_forum_discussion_email_match_ids( $values = array() ) {
	$user_ids = array_values( array_filter( array_map( 'absint', get_users( array( 'fields' => 'ID' ) ) ) ) );
	$values   = array_values( array_filter( array_map( 'sanitize_key', (array) $values ) ) );
	if ( function_exists( 'update_meta_cache' ) ) {
		update_meta_cache( 'user', $user_ids );
	}

	return array_values(
		array_filter(
			$user_ids,
			static function ( $user_id ) use ( $values ) {
				$status = sanitize_key( cen_ear_get_forum_discussion_email_status( $user_id ) );

				return in_array( $status, $values, true );
			}
		)
	);
}

/**
 * Return user IDs assigned to any selected BuddyBoss profile type.
 *
 * @param string   $comparison Supported comparison: exact or nonempty.
 * @param string[] $values Selected profile type slugs.
 * @return int[]
 */
function cen_ear_get_buddyboss_profile_type_match_ids( $comparison, $values = array() ) {
	if ( ! function_exists( 'bp_get_member_type' ) ) {
		return array();
	}

	$user_ids = array_values( array_filter( array_map( 'absint', get_users( array( 'fields' => 'ID' ) ) ) ) );
	$values   = array_values( array_filter( array_map( 'sanitize_key', (array) $values ) ) );

	return array_values(
		array_filter(
			$user_ids,
			static function ( $user_id ) use ( $comparison, $values ) {
				$profile_types = array_values( array_filter( array_map( 'sanitize_key', (array) bp_get_member_type( $user_id, false ) ) ) );
				if ( 'nonempty' === $comparison ) {
					return ! empty( $profile_types );
				}

				return ! empty( array_intersect( $profile_types, $values ) );
			}
		)
	);
}

/**
 * Return user IDs belonging to any selected confirmed BuddyBoss group.
 *
 * @param string   $comparison Supported comparison: exact or nonempty.
 * @param string[] $values Selected group IDs.
 * @return int[]
 */
function cen_ear_get_buddyboss_group_match_ids( $comparison, $values = array() ) {
	global $wpdb;

	$bp         = function_exists( 'buddypress' ) ? buddypress() : null;
	$table_name = is_object( $bp ) && isset( $bp->groups->table_name_members ) ? $bp->groups->table_name_members : $wpdb->prefix . 'bp_groups_members';
	$sql        = "SELECT DISTINCT user_id FROM {$table_name} WHERE is_confirmed = 1 AND is_banned = 0";
	$params     = array();
	if ( 'exact' === $comparison ) {
		$group_ids = array_values( array_filter( array_map( 'absint', $values ) ) );
		if ( empty( $group_ids ) ) {
			return array();
		}
		$placeholders = implode( ', ', array_fill( 0, count( $group_ids ), '%d' ) );
		$sql         .= " AND group_id IN ({$placeholders})";
		$params       = $group_ids;
	}

	$query = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

	return array_values( array_filter( array_map( 'absint', $wpdb->get_col( $query ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Query users using AND between criteria rows and OR between values in a row.
 *
 * @param array<int,array{attribute:string,operator:string,values:string[]}> $rules Validated rules.
 * @param array<string,array<string,mixed>> $attributes Attribute registry.
 * @param int $page One-based page, or zero for every result.
 * @param bool $include_suspended Whether suspended BuddyBoss profiles may match.
 * @return array{ids:int[],guests:array<int,array<string,string>>,total:int,max_pages:int}|WP_Error
 */
function cen_ear_query_criteria_rules( $rules, $attributes, $page = 1, $include_suspended = false ) {
	$page          = absint( $page );
	$include_ids   = null;
	$exclude_ids   = array();
	$meta_queries  = array( 'relation' => 'AND' );
	$event_statuses = array( 'going', 'not-going', 'cancelled' );
	$event_scope_ids = array();
	$membership_plan_contexts = array();
	foreach ( $rules as $context_rule ) {
		$context_definition = isset( $attributes[ $context_rule['attribute'] ] ) ? $attributes[ $context_rule['attribute'] ] : array();
		$context_type = isset( $context_definition['query_type'] ) ? $context_definition['query_type'] : '';
		if ( 'event_rsvp_status' === $context_type && 'is' === $context_rule['operator'] ) {
			$event_statuses = $context_rule['values'];
		} elseif ( 'event_attendance' === $context_type && in_array( $context_rule['operator'], array( 'is', 'attended' ), true ) ) {
			$event_scope_ids = array_merge( $event_scope_ids, array_map( 'absint', $context_rule['values'] ) );
		} elseif ( 'woocommerce_membership_plan' === $context_type && 'is' === $context_rule['operator'] ) {
			$membership_plan_contexts[] = array_values( array_filter( array_map( 'absint', $context_rule['values'] ) ) );
		}
	}
	$membership_expiration_plan_ids = 1 === count( $membership_plan_contexts ) ? $membership_plan_contexts[0] : array();
	if ( empty( $event_scope_ids ) && isset( $attributes['event']['choices'] ) ) {
		$event_scope_ids = array_keys( $attributes['event']['choices'] );
	}

	foreach ( $rules as $rule ) {
		$definition = $attributes[ $rule['attribute'] ];
		$query_type = isset( $definition['query_type'] ) ? $definition['query_type'] : '';
		if ( 'gravity_forms_onboarding' === $query_type ) {
			$completed_ids = cen_ear_get_completed_onboarding_user_ids();
			if ( 'yes' === $rule['values'][0] ) {
				$include_ids = null === $include_ids ? $completed_ids : array_values( array_intersect( $include_ids, $completed_ids ) );
			} else {
				$exclude_ids = array_merge( $exclude_ids, $completed_ids );
			}
			continue;
		}
		if ( 'woocommerce_membership_plan' === $query_type ) {
			$is_positive = in_array( $rule['operator'], array( 'is', 'is_not_empty' ), true );
			$comparison  = in_array( $rule['operator'], array( 'is', 'is_not' ), true ) ? 'exact' : 'nonempty';
			$matching_ids = cen_ear_get_woocommerce_membership_plan_match_ids( $comparison, $rule['values'] );
			if ( $is_positive ) {
				$include_ids = null === $include_ids ? $matching_ids : array_values( array_intersect( $include_ids, $matching_ids ) );
			} else {
				$exclude_ids = array_merge( $exclude_ids, $matching_ids );
			}
			continue;
		}
		if ( in_array( $query_type, array( 'buddyboss_status', 'buddyboss_forum_discussion_emails', 'buddyboss_profile_type', 'buddyboss_group' ), true ) ) {
			$is_positive = in_array( $rule['operator'], array( 'is', 'is_not_empty' ), true );
			$comparison  = in_array( $rule['operator'], array( 'is', 'is_not' ), true ) ? 'exact' : 'nonempty';
			if ( 'buddyboss_status' === $query_type ) {
				$matching_ids = cen_ear_get_buddyboss_status_match_ids( $comparison, $rule['values'] );
			} elseif ( 'buddyboss_forum_discussion_emails' === $query_type ) {
				$matching_ids = cen_ear_get_forum_discussion_email_match_ids( $rule['values'] );
			} elseif ( 'buddyboss_profile_type' === $query_type ) {
				$matching_ids = cen_ear_get_buddyboss_profile_type_match_ids( $comparison, $rule['values'] );
			} else {
				$matching_ids = cen_ear_get_buddyboss_group_match_ids( $comparison, $rule['values'] );
			}
			if ( $is_positive ) {
				$include_ids = null === $include_ids ? $matching_ids : array_values( array_intersect( $include_ids, $matching_ids ) );
			} else {
				$exclude_ids = array_merge( $exclude_ids, $matching_ids );
			}
			continue;
		}
		if ( in_array( $query_type, array( 'user_field_date', 'user_meta_date', 'xprofile_date', 'buddyboss_last_active', 'woocommerce_membership_expiration' ), true ) ) {
			$is_positive = ! in_array( $rule['operator'], array( 'is_not', 'is_empty' ), true );
			if ( in_array( $rule['operator'], array( 'is', 'is_not' ), true ) ) {
				$comparison = 'exact';
			} elseif ( 'between_inclusive' === $rule['operator'] ) {
				$comparison = 'between';
			} elseif ( 'on_or_before' === $rule['operator'] ) {
				$comparison = 'on_or_before';
			} elseif ( 'on_or_after' === $rule['operator'] ) {
				$comparison = 'on_or_after';
			} else {
				$comparison = 'nonempty';
			}
			$date_definition = $definition;
			if ( 'woocommerce_membership_expiration' === $query_type && ! empty( $membership_expiration_plan_ids ) ) {
				$date_definition['membership_plan_ids'] = $membership_expiration_plan_ids;
			}
			$matching_ids = cen_ear_get_date_match_ids( $date_definition, $comparison, $rule['values'] );
			if ( $is_positive ) {
				$include_ids = null === $include_ids ? $matching_ids : array_values( array_intersect( $include_ids, $matching_ids ) );
			} else {
				$exclude_ids = array_merge( $exclude_ids, $matching_ids );
			}
			continue;
		}
		if ( in_array( $query_type, array( 'xprofile_text', 'xprofile_choice', 'user_meta_text', 'user_field_text' ), true ) ) {
			$is_positive = in_array( $rule['operator'], array( 'is', 'contains', 'is_not_empty' ), true );
			if ( in_array( $rule['operator'], array( 'is', 'is_not' ), true ) ) {
				$comparison = 'exact';
			} elseif ( in_array( $rule['operator'], array( 'contains', 'does_not_contain' ), true ) ) {
				$comparison = 'contains';
			} else {
				$comparison = 'nonempty';
			}
			if ( 'xprofile_choice' === $query_type ) {
				$matching_ids = cen_ear_get_xprofile_choice_match_ids( $definition['xprofile_field_id'], $comparison, $rule['values'] );
			} elseif ( 'xprofile_text' === $query_type ) {
				$matching_ids = cen_ear_get_xprofile_text_match_ids( $definition['xprofile_field_id'], $comparison, $rule['values'] );
			} elseif ( 'user_meta_text' === $query_type ) {
				$matching_ids = cen_ear_get_user_meta_text_match_ids( $definition['meta_key'], $comparison, $rule['values'] );
			} else {
				$matching_ids = cen_ear_get_user_field_text_match_ids( $definition['user_field'], $comparison, $rule['values'] );
			}
			if ( $is_positive ) {
				$include_ids = null === $include_ids ? $matching_ids : array_values( array_intersect( $include_ids, $matching_ids ) );
			} else {
				$exclude_ids = array_merge( $exclude_ids, $matching_ids );
			}
			continue;
		}
		if ( in_array( $query_type, array( 'user_meta_choice', 'user_meta_multi_choice' ), true ) ) {
			if ( 'is_empty' === $rule['operator'] ) {
				$meta_queries[] = array(
					'relation' => 'OR',
					array( 'key' => $definition['meta_key'], 'compare' => 'NOT EXISTS' ),
					array( 'key' => $definition['meta_key'], 'value' => '', 'compare' => '=' ),
					array( 'key' => $definition['meta_key'], 'value' => 'a:0:{}', 'compare' => '=' ),
				);
				continue;
			}
			if ( 'is_not_empty' === $rule['operator'] ) {
				$meta_queries[] = array(
					'relation' => 'AND',
					array( 'key' => $definition['meta_key'], 'compare' => 'EXISTS' ),
					array( 'key' => $definition['meta_key'], 'value' => '', 'compare' => '!=' ),
					array( 'key' => $definition['meta_key'], 'value' => 'a:0:{}', 'compare' => '!=' ),
				);
				continue;
			}
			if ( 'user_meta_multi_choice' === $query_type ) {
				$choice_query = array( 'relation' => 'is_not' === $rule['operator'] ? 'AND' : 'OR' );
				foreach ( $rule['values'] as $value ) {
					$choice_query[] = array(
						'key'     => $definition['meta_key'],
						'value'   => '"' . $value . '"',
						'compare' => 'is_not' === $rule['operator'] ? 'NOT LIKE' : 'LIKE',
					);
				}
				$meta_queries[] = $choice_query;
				continue;
			}
			$meta_queries[] = array(
				'key'     => $definition['meta_key'],
				'value'   => $rule['values'],
				'compare' => 'is_not' === $rule['operator'] ? 'NOT IN' : 'IN',
			);
			continue;
		}

		if ( 'event_attendance' === $query_type ) {
			$is_positive_event = in_array( $rule['operator'], array( 'is', 'attended', 'is_not_empty' ), true );
			$event_values = in_array( $rule['operator'], array( 'is_empty', 'is_not_empty' ), true ) ? array_keys( $definition['choices'] ) : $rule['values'];
			$event_records = cen_ear_get_event_attendee_records(
				array_map( 'absint', $event_values ),
				$event_statuses,
				'attended' === $rule['operator'] ? true : null
			);
			$attendee_ids = is_wp_error( $event_records ) ? $event_records : $event_records['user_ids'];
			if ( is_wp_error( $attendee_ids ) ) {
				return $attendee_ids;
			}
			if ( $is_positive_event ) {
				$include_ids = null === $include_ids ? $attendee_ids : array_values( array_intersect( $include_ids, $attendee_ids ) );
			} else {
				$exclude_ids = array_merge( $exclude_ids, $attendee_ids );
			}
			continue;
		}
		if ( 'event_rsvp_status' === $query_type ) {
			$status_records = cen_ear_get_event_attendee_records( $event_scope_ids, $rule['values'] );
			if ( is_wp_error( $status_records ) ) {
				return $status_records;
			}
			if ( 'is' === $rule['operator'] ) {
				$include_ids = null === $include_ids ? $status_records['user_ids'] : array_values( array_intersect( $include_ids, $status_records['user_ids'] ) );
			} else {
				$exclude_ids = array_merge( $exclude_ids, $status_records['user_ids'] );
			}
		}
	}

	$guest_rows = cen_ear_get_matching_event_guests( $rules, $attributes );
	if ( is_wp_error( $guest_rows ) ) {
		return $guest_rows;
	}
	if ( ! $include_suspended && ( function_exists( 'bp_moderation_is_user_suspended' ) || function_exists( 'bp_is_user_spammer' ) ) ) {
		$exclude_ids = array_merge(
			$exclude_ids,
			cen_ear_get_buddyboss_status_match_ids( 'exact', array( 'suspended' ) )
		);
	}

	// WP_User_Query does not reliably apply `exclude` when an `include` list is
	// also present. Resolve the AND logic here so a user matched by a positive
	// criterion cannot survive a negative criterion such as "not registered".
	$exclude_ids = array_values( array_unique( array_filter( array_map( 'absint', $exclude_ids ) ) ) );
	if ( is_array( $include_ids ) && ! empty( $exclude_ids ) ) {
		$include_ids = array_values( array_diff( $include_ids, $exclude_ids ) );
		$exclude_ids = array();
	}

	if ( is_array( $include_ids ) && empty( $include_ids ) && empty( $guest_rows ) ) {
		return array( 'ids' => array(), 'guests' => array(), 'total' => 0, 'max_pages' => 0 );
	}
	$skip_user_query = is_array( $include_ids ) && empty( $include_ids );

	$args = array(
		'fields'      => 'ID',
		'orderby'     => 'display_name',
		'order'       => 'ASC',
		'count_total' => 0 !== $page,
	);
	if ( count( $meta_queries ) > 1 ) {
		$args['meta_query'] = $meta_queries; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	}
	if ( is_array( $include_ids ) && ! empty( $include_ids ) ) {
		$args['include'] = array_values( array_unique( array_map( 'absint', $include_ids ) ) );
	}
	if ( ! empty( $exclude_ids ) ) {
		$args['exclude'] = array_values( array_unique( array_map( 'absint', $exclude_ids ) ) );
	}
	if ( 0 === $page ) {
		$args['number'] = -1;
	} else {
		$args['number'] = CEN_EAR_RESULTS_PER_PAGE;
		$args['offset'] = ( $page - 1 ) * CEN_EAR_RESULTS_PER_PAGE;
	}

	if ( $skip_user_query ) {
		$ids        = array();
		$user_total = 0;
	} else {
		$query      = new WP_User_Query( $args );
		$ids        = array_values( array_filter( array_map( 'absint', $query->get_results() ) ) );
		$user_total = 0 === $page ? count( $ids ) : absint( $query->get_total() );
	}

	$guest_total = count( $guest_rows );
	if ( 0 === $page ) {
		$page_guests = $guest_rows;
	} else {
		$page_offset = ( $page - 1 ) * CEN_EAR_RESULTS_PER_PAGE;
		$guest_offset = max( 0, $page_offset - $user_total );
		$guest_limit  = max( 0, CEN_EAR_RESULTS_PER_PAGE - count( $ids ) );
		$page_guests  = array_slice( $guest_rows, $guest_offset, $guest_limit );
	}
	$total = $user_total + $guest_total;

	return array(
		'ids'       => $ids,
		'guests'    => $page_guests,
		'total'     => $total,
		'max_pages' => 0 === $page ? ( $total ? 1 : 0 ) : (int) ceil( $total / CEN_EAR_RESULTS_PER_PAGE ),
	);
}

/**
 * Render the data-driven criteria builder.
 *
 * @param array<string,array<string,mixed>> $attributes Attribute registry.
 * @param array<int,array<string,mixed>> $rules Current criteria.
 * @param bool $include_suspended Whether the suspended-profile option is selected.
 */
function cen_ear_render_criteria_builder( $attributes, $rules, $include_suspended = false ) {
	$client_attributes = array();
	foreach ( $attributes as $key => $attribute ) {
		if ( empty( $attribute['available'] ) ) {
			continue;
		}
		$client_choices = array();
		foreach ( (array) $attribute['choices'] as $choice_value => $choice_label ) {
			// Send ordered pairs rather than a JSON object. JavaScript sorts
			// integer-like object keys (event IDs) numerically, which otherwise
			// discards the newest-to-oldest order supplied by the event query.
			$client_choices[] = array( (string) $choice_value, (string) $choice_label );
		}
		$client_attributes[ $key ] = array(
			'label'     => (string) $attribute['label'],
			'type'      => (string) $attribute['type'],
			'criterion_type' => isset( $attribute['criterion_type'] ) ? (string) $attribute['criterion_type'] : 'attribute',
			'operators' => (array) $attribute['operators'],
			'choices'   => $client_choices,
			'hide_operator' => ! empty( $attribute['hide_operator'] ),
		);
	}
	$direct_attributes  = array();
	$generic_attributes = array();
	foreach ( $client_attributes as $key => $attribute ) {
		if ( 'attribute' === $attribute['criterion_type'] ) {
			$generic_attributes[ $key ] = $attribute;
		} else {
			$direct_attributes[ $key ] = $attribute;
		}
	}
	// Keep the registry order so related fields remain together (notably
	// First Name immediately followed by Last Name).
	$client_attributes = array_merge( $direct_attributes, $generic_attributes );

	$add_options          = array();
	$has_attribute_fields = false;
	foreach ( $client_attributes as $key => $attribute ) {
		if ( 'attribute' === $attribute['criterion_type'] ) {
			$has_attribute_fields = true;
		} else {
			$add_options[ $key ] = $attribute['label'];
		}
	}
	if ( $has_attribute_fields ) {
		$add_options['attribute'] = __( 'Attribute', 'member-search' );
	}
	?>
	<style>
		.cen-ear-builder.cen-ear-builder-v2 { display: block; }
		.cen-ear-criteria-list { display: grid; gap: 28px; padding-right: 70px; }
		.cen-ear-rule { position: relative; display: grid; grid-template-columns: minmax(180px, 1fr) minmax(180px, .85fr) minmax(280px, 2fr) 40px; gap: 10px; align-items: start; }
		.cen-ear-rule.cen-ear-rule-no-condition { grid-template-columns: minmax(180px, 1fr) minmax(280px, 2.85fr) 40px; }
		.cen-ear-rule.cen-ear-rule-generic { grid-template-columns: minmax(140px, .75fr) minmax(180px, 1fr) minmax(180px, .85fr) minmax(260px, 1.65fr) 40px; }
		.cen-ear-rule-remove { position: relative; z-index: 2; display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; margin-top: 24px; padding: 0; color: #b32d2e; font-size: 0; line-height: 0; background: #fff; border: 1px solid #b32d2e; border-radius: 2px; cursor: pointer; }
		.cen-ear-rule-remove::before, .cen-ear-rule-remove::after { content: ""; position: absolute; top: 50%; left: 50%; width: 16px; height: 2px; background: currentColor; border-radius: 1px; transform-origin: center; }
		.cen-ear-rule-remove::before { transform: translate(-50%, -50%) rotate(45deg); }
		.cen-ear-rule-remove::after { transform: translate(-50%, -50%) rotate(-45deg); }
		.cen-ear-rule-remove:hover, .cen-ear-rule-remove:focus { color: #fff; background: #b32d2e; outline: none; }
		.cen-ear-rule-connector { position: absolute; top: 44px; right: -60px; width: 110px; height: calc(100% + 28px); pointer-events: none; }
		.cen-ear-rule:last-child .cen-ear-rule-connector { display: none; }
		.cen-ear-rule-connector::before { content: ""; position: absolute; inset: 0; background: linear-gradient(#8c8f94, #8c8f94) left top / 98px 2px no-repeat, linear-gradient(#8c8f94, #8c8f94) left bottom / 98px 2px no-repeat, linear-gradient(#8c8f94, #8c8f94) right 12px top / 2px 100% no-repeat; }
		.cen-ear-rule-connector-label { position: absolute; z-index: 1; top: 50%; right: -2px; padding: 2px 5px; color: #fff; font-size: 11px; font-weight: 700; line-height: 16px; background: #646970; border-radius: 3px; transform: translateY(-50%); }
		.cen-ear-no-value { box-sizing: border-box; display: flex; align-items: center; min-height: 40px; padding: 0 12px; color: #646970; background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 2px; }
		.cen-ear-searchable-select > .cen-ear-single-trigger > span { display: block; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		.cen-ear-searchable-select > .cen-ear-single-trigger::after { flex: 0 0 7px; }
		.cen-ear-searchable-menu { width: min(480px, calc(100vw - 40px)); max-height: 420px; }
		.cen-ear-searchable-menu .cen-ear-picker-option { padding: 12px 16px; font-size: 14px; line-height: 1.35; }
		.cen-ear-searchable-menu .cen-ear-single-search { box-sizing: border-box; position: sticky; z-index: 2; top: 0; width: calc(100% - 24px) !important; height: 38px; min-height: 38px; margin: 10px 12px !important; padding: 5px 10px; background: #fff; border: 1px solid #8c8f94; border-radius: 3px; box-shadow: none; }
		.cen-ear-searchable-menu .cen-ear-single-search:focus { border-color: #2271b1; outline: none; box-shadow: 0 0 0 1px #2271b1; }
		.cen-ear-date-control { position: relative; }
		.cen-ear-date-values { display: grid; gap: 8px; }
		.cen-ear-date-values.cen-ear-date-range { grid-template-columns: repeat(2, minmax(130px, 1fr)); }
		.cen-ear-date-input { box-sizing: border-box; width: 100%; height: 40px; min-height: 40px; margin: 0; padding: 0 10px; background: #fff; border: 1px solid #8c8f94; border-radius: 2px; }
		.cen-ear-date-input:focus { border-color: #2271b1; outline: none; box-shadow: 0 0 0 1px #2271b1; }
		.cen-ear-date-control.cen-ear-invalid .cen-ear-date-input:invalid, .cen-ear-date-control.cen-ear-invalid .cen-ear-date-input:not(:valid) { border-color: #d63638; box-shadow: 0 0 0 1px #d63638; }
		.cen-ear-date-error { margin: 5px 0 0; color: #d63638; font-size: 12px; }
		.cen-ear-suspended-option { display: flex; gap: 8px; align-items: flex-start; max-width: 720px; margin: 0 0 8px; padding: 10px 12px; color: #7a4b00; background: #fff8e5; border-left: 4px solid #dba617; }
		.cen-ear-suspended-option input { flex: 0 0 auto; margin-top: 2px; }
		.cen-ear-suspended-option label { font-weight: 600; }
		.cen-ear-add-criteria { margin-top: 8px; }
		.cen-ear-add-criteria .cen-ear-single-select { width: 220px; }
		.cen-ear-builder-v2 .cen-ear-actions { margin-top: 16px; }
		@media (max-width: 782px) { .cen-ear-criteria-list { padding-right: 0; } .cen-ear-rule, .cen-ear-rule.cen-ear-rule-generic { grid-template-columns: 1fr; } .cen-ear-rule-remove { justify-self: start; margin-top: 0; } .cen-ear-rule-connector { display: none; } }
	</style>
	<form id="cen-ear-search-form-v2" method="get" action="<?php echo esc_url( admin_url( 'users.php' ) ); ?>">
		<input type="hidden" name="page" value="member-search">
		<input type="hidden" name="cen_ear_search" value="1">
		<div class="cen-ear-builder cen-ear-builder-v2">
			<div class="cen-ear-suspended-option">
				<input type="checkbox" id="cen-ear-include-suspended" name="include_suspended" value="1" <?php checked( $include_suspended ); ?>>
				<label for="cen-ear-include-suspended"><?php echo esc_html__( '⚠️ Include suspended profiles. Unchecked searches Active profiles only.', 'member-search' ); ?></label>
			</div>
			<div id="cen-ear-criteria-list" class="cen-ear-criteria-list"></div>
			<div class="cen-ear-add-criteria">
				<label class="screen-reader-text" for="cen-ear-add-criteria"><?php echo esc_html__( 'Add criteria', 'member-search' ); ?></label>
				<div id="cen-ear-add-criteria-wrapper" class="cen-ear-single-select cen-ear-enhanced">
					<select id="cen-ear-add-criteria">
						<option value=""><?php echo esc_html__( 'Add criteria', 'member-search' ); ?></option>
						<?php foreach ( $add_options as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
					</select>
					<button type="button" id="cen-ear-add-criteria-trigger" class="cen-ear-single-trigger" aria-haspopup="listbox" aria-expanded="false"><span><?php echo esc_html__( 'Add criteria', 'member-search' ); ?></span></button>
					<div id="cen-ear-add-criteria-menu" class="cen-ear-picker-menu cen-ear-single-menu" role="listbox" hidden>
						<?php foreach ( $add_options as $key => $label ) : ?><button type="button" class="cen-ear-picker-option" role="option" data-value="<?php echo esc_attr( $key ); ?>" aria-selected="false"><?php echo esc_html( $label ); ?></button><?php endforeach; ?>
					</div>
				</div>
			</div>
			<div class="cen-ear-actions">
				<button type="submit" class="button cen-ear-search-button"><?php echo esc_html__( 'Search', 'member-search' ); ?></button>
				<a class="button cen-ear-clear-button" href="<?php echo esc_url( cen_ear_get_admin_page_url() ); ?>"><?php echo esc_html__( 'Clear', 'member-search' ); ?></a>
			</div>
		</div>
	</form>
	<script>
		(function () {
			var form = document.getElementById('cen-ear-search-form-v2');
			var list = document.getElementById('cen-ear-criteria-list');
			var addSelect = document.getElementById('cen-ear-add-criteria');
			var includeSuspended = document.getElementById('cen-ear-include-suspended');
			var attributes = <?php echo wp_json_encode( $client_attributes ); ?>;
			var initialRules = <?php echo wp_json_encode( array_values( $rules ) ); ?>;
			var noMatchesText = <?php echo wp_json_encode( __( 'No matches.', 'member-search' ) ); ?>;
			var allSelectedText = <?php echo wp_json_encode( __( 'All values have been selected.', 'member-search' ) ); ?>;
			var missingValueText = <?php echo wp_json_encode( __( 'Select at least one value.', 'member-search' ) ); ?>;
			var missingDateText = <?php echo wp_json_encode( __( 'Select every required date.', 'member-search' ) ); ?>;
			var invalidDateRangeText = <?php echo wp_json_encode( __( 'The start date must be on or before the end date.', 'member-search' ) ); ?>;
			var removeText = <?php echo wp_json_encode( __( 'Remove', 'member-search' ) ); ?>;
			var initializing = true;
			if (!form || !list || !addSelect) { return; }

			function markResultsStale() {
				if (initializing) { return; }
				var exportForm = document.getElementById('cen-ear-export-form');
				if (exportForm) { exportForm.hidden = true; }
			}
			if (includeSuspended) { includeSuspended.addEventListener('change', markResultsStale); }

			function updateAddCriteriaAvailability() {
				var wrapper = document.getElementById('cen-ear-add-criteria-wrapper');
				var trigger = document.getElementById('cen-ear-add-criteria-trigger');
				var hasAttendanceReport = Array.prototype.some.call(list.querySelectorAll('.cen-ear-attribute-select'), function (select) {
					return 'attendance_report' === select.value;
				});
				if (wrapper && wrapper.parentElement) { wrapper.parentElement.hidden = hasAttendanceReport; }
				if (trigger) { trigger.disabled = hasAttendanceReport; }
				addSelect.disabled = hasAttendanceReport;
			}

			function entries(object) {
				if (Array.isArray(object)) { return object; }
				return Object.keys(object || {}).map(function (key) { return [key, object[key]]; });
			}

			function field(labelText) {
				var container = document.createElement('div');
				var label = document.createElement('span');
				container.className = 'cen-ear-field';
				label.className = 'cen-ear-field-label';
				label.textContent = labelText;
				container.appendChild(label);
				return container;
			}

			function singleSelect(options, selectedValue, ariaLabel, onChange, searchable) {
				var wrapper = document.createElement('div');
				var select = document.createElement('select');
				var trigger = document.createElement('button');
				var triggerText = document.createElement('span');
				var menu = document.createElement('div');
				var searchInput = null;
				var empty = null;
				wrapper.className = 'cen-ear-single-select cen-ear-enhanced';
				select.setAttribute('aria-label', ariaLabel);
				trigger.type = 'button';
				trigger.className = 'cen-ear-single-trigger';
				trigger.setAttribute('aria-label', ariaLabel);
				trigger.setAttribute('aria-haspopup', 'listbox');
				trigger.setAttribute('aria-expanded', 'false');
				menu.className = 'cen-ear-picker-menu cen-ear-single-menu';
				menu.setAttribute('role', 'listbox');
				menu.hidden = true;
				if (searchable) {
					wrapper.classList.add('cen-ear-searchable-select');
					menu.classList.add('cen-ear-searchable-menu');
					searchInput = document.createElement('input');
					empty = document.createElement('p');
					searchInput.type = 'text';
					searchInput.className = 'cen-ear-single-search';
					searchInput.placeholder = <?php echo wp_json_encode( __( 'Search attributes', 'member-search' ) ); ?>;
					searchInput.setAttribute('autocomplete', 'off');
					searchInput.setAttribute('aria-label', <?php echo wp_json_encode( __( 'Search attributes', 'member-search' ) ); ?>);
					empty.className = 'cen-ear-picker-empty';
					empty.textContent = noMatchesText;
					empty.hidden = true;
					menu.appendChild(searchInput);
				}

				function filterOptions() {
					if (!searchInput) { return; }
					var query = searchInput.value.trim().toLowerCase();
					var matches = 0;
					menu.querySelectorAll('.cen-ear-picker-option').forEach(function (option) {
						var matchesQuery = !query || -1 !== option.textContent.toLowerCase().indexOf(query);
						option.hidden = !matchesQuery;
						if (matchesQuery) { matches += 1; }
					});
					empty.hidden = matches > 0;
				}
				function close() {
					menu.hidden = true; trigger.setAttribute('aria-expanded', 'false');
					if (searchInput) { searchInput.value = ''; filterOptions(); }
				}
				function choose(value, notify) {
					select.value = value;
					menu.querySelectorAll('.cen-ear-picker-option').forEach(function (option) {
						var active = option.getAttribute('data-value') === value;
						option.setAttribute('aria-selected', active ? 'true' : 'false');
						if (active) { triggerText.textContent = option.textContent; }
					});
					if (notify && onChange) { onChange(value); }
				}

				options.forEach(function (optionData) {
					var option = document.createElement('option');
					var menuOption = document.createElement('button');
					option.value = optionData[0];
					option.textContent = optionData[1];
					select.appendChild(option);
					menuOption.type = 'button';
					menuOption.className = 'cen-ear-picker-option';
					menuOption.setAttribute('role', 'option');
					menuOption.setAttribute('data-value', optionData[0]);
					menuOption.textContent = optionData[1];
					menuOption.addEventListener('click', function () { choose(optionData[0], true); close(); trigger.focus(); });
					menu.appendChild(menuOption);
				});
				if (empty) {
					menu.appendChild(empty);
					searchInput.addEventListener('input', filterOptions);
					searchInput.addEventListener('keydown', function (event) {
						var first = menu.querySelector('.cen-ear-picker-option:not([hidden])');
						if ('Enter' === event.key && first) { event.preventDefault(); first.click(); }
						else if ('Escape' === event.key) { event.preventDefault(); close(); trigger.focus(); }
						else if ('ArrowDown' === event.key && first) { event.preventDefault(); first.focus(); }
					});
				}
				trigger.appendChild(triggerText);
				trigger.addEventListener('click', function () {
					var opening = menu.hidden;
					document.querySelectorAll('.cen-ear-single-menu').forEach(function (other) {
						other.hidden = true;
						var otherTrigger = other.parentElement.querySelector('.cen-ear-single-trigger');
						if (otherTrigger) { otherTrigger.setAttribute('aria-expanded', 'false'); }
					});
					menu.hidden = !opening;
					trigger.setAttribute('aria-expanded', opening ? 'true' : 'false');
					if (opening && searchInput) {
						searchInput.value = ''; filterOptions(); searchInput.focus();
					}
				});
				select.addEventListener('change', function () { choose(select.value, true); });
				document.addEventListener('click', function (event) { if (!wrapper.contains(event.target)) { close(); } });
				wrapper.appendChild(select);
				wrapper.appendChild(trigger);
				wrapper.appendChild(menu);
				choose(selectedValue || options[0][0], false);
				return { element: wrapper, select: select };
			}

			function multiPicker(attribute, selectedValues) {
				var control = document.createElement('div');
				var chips = document.createElement('div');
				var input = document.createElement('input');
				var menu = document.createElement('div');
				var empty = document.createElement('p');
				var error = document.createElement('p');
				control.className = 'cen-ear-multi-select cen-ear-enhanced';
				chips.className = 'cen-ear-chips';
				input.type = 'text';
				input.className = 'cen-ear-picker-search';
				input.setAttribute('role', 'combobox');
				input.setAttribute('autocomplete', 'off');
				input.setAttribute('aria-label', 'Value');
				input.setAttribute('aria-expanded', 'false');
				menu.className = 'cen-ear-picker-menu';
				menu.setAttribute('role', 'listbox');
				menu.hidden = true;
				empty.className = 'cen-ear-picker-empty';
				empty.hidden = true;
				error.className = 'cen-ear-picker-error';
				error.textContent = missingValueText;
				error.hidden = true;

				entries(attribute.choices).forEach(function (choice) {
					var option = document.createElement('button');
					option.type = 'button';
					option.className = 'cen-ear-picker-option';
					option.setAttribute('data-value', choice[0]);
					option.setAttribute('aria-selected', 'false');
					option.textContent = choice[1];
					menu.appendChild(option);
				});
				menu.appendChild(empty);
				chips.appendChild(input);
				control.appendChild(chips);
				control.appendChild(menu);
				control.appendChild(error);

				function optionFor(value) {
					return Array.prototype.find.call(menu.querySelectorAll('.cen-ear-picker-option'), function (option) { return option.getAttribute('data-value') === String(value); }) || null;
				}
				function filter() {
					var query = input.value.trim().toLowerCase();
					var available = 0;
					menu.querySelectorAll('.cen-ear-picker-option').forEach(function (option) {
						var hide = 'true' === option.getAttribute('aria-selected') || (query && -1 === option.textContent.toLowerCase().indexOf(query));
						option.hidden = !!hide;
						if (!hide) { available += 1; }
					});
					empty.textContent = query ? noMatchesText : allSelectedText;
					empty.hidden = available > 0;
				}
				function open() { filter(); menu.hidden = false; input.setAttribute('aria-expanded', 'true'); }
				function close() { menu.hidden = true; input.setAttribute('aria-expanded', 'false'); }
				function update() {
					filter();
					if (chips.querySelector('.cen-ear-value-input')) {
						control.classList.remove('cen-ear-invalid'); error.hidden = true; input.removeAttribute('aria-invalid');
					}
				}
				function removeChip(chip) {
					var option = optionFor(chip.getAttribute('data-value'));
					if (option) { option.setAttribute('aria-selected', 'false'); }
					chip.remove(); update(); renumber(); markResultsStale();
				}
				function addChip(value) {
					var option = optionFor(value);
					if (!option || 'true' === option.getAttribute('aria-selected')) { return; }
					var chip = document.createElement('span');
					var label = document.createElement('span');
					var remove = document.createElement('button');
					var hidden = document.createElement('input');
					chip.className = 'cen-ear-chip'; chip.setAttribute('data-value', String(value));
					label.className = 'cen-ear-chip-label'; label.textContent = option.textContent;
					remove.type = 'button'; remove.className = 'cen-ear-chip-remove'; remove.textContent = '\u00d7'; remove.setAttribute('aria-label', removeText + ' ' + option.textContent);
					hidden.type = 'hidden'; hidden.className = 'cen-ear-value-input'; hidden.value = String(value);
					remove.addEventListener('click', function () { removeChip(chip); input.focus(); });
					chip.appendChild(label); chip.appendChild(remove); chip.appendChild(hidden); chips.insertBefore(chip, input);
					input.value = '';
					option.setAttribute('aria-selected', 'true'); update(); renumber(); markResultsStale();
				}
				menu.querySelectorAll('.cen-ear-picker-option').forEach(function (option) {
					option.addEventListener('click', function () { addChip(option.getAttribute('data-value')); open(); input.focus(); });
				});
				control.addEventListener('click', function (event) { if (!event.target.closest('.cen-ear-chip-remove') && !event.target.closest('.cen-ear-picker-menu')) { open(); input.focus(); } });
				input.addEventListener('input', open);
				input.addEventListener('keydown', function (event) {
					var first = menu.querySelector('.cen-ear-picker-option:not([hidden])');
					if ('Enter' === event.key) { event.preventDefault(); if (first) { addChip(first.getAttribute('data-value')); open(); } }
					else if ('Escape' === event.key) { close(); }
					else if ('Backspace' === event.key && !input.value) { var selected = chips.querySelectorAll('.cen-ear-chip'); if (selected.length) { removeChip(selected[selected.length - 1]); } }
				});
				document.addEventListener('click', function (event) { if (!control.contains(event.target)) { close(); } });
				(selectedValues || []).forEach(addChip);

				return {
					element: control,
					inputs: function () { return chips.querySelectorAll('.cen-ear-value-input'); },
					validate: function () {
						var valid = !!chips.querySelector('.cen-ear-value-input');
						control.classList.toggle('cen-ear-invalid', !valid); error.hidden = valid;
						if (!valid) { input.setAttribute('aria-invalid', 'true'); } else { input.removeAttribute('aria-invalid'); }
						return valid;
					}
				};
			}

			function singleChoicePicker(attribute, selectedValues) {
				var selectedValue = selectedValues && selectedValues.length ? String(selectedValues[0]) : '';
				var options = [['', <?php echo wp_json_encode( __( 'Select an event', 'member-search' ) ); ?>]].concat(entries(attribute.choices));
				var control = singleSelect(options, selectedValue, 'Value', function () { markResultsStale(); }, true);
				control.select.classList.add('cen-ear-value-input');
				return {
					element: control.element,
					validate: function () {
						var valid = '' !== control.select.value;
						control.select.setAttribute('aria-invalid', valid ? 'false' : 'true');
						return valid;
					}
				};
			}

			function multiTextPicker(selectedValues) {
				var control = document.createElement('div');
				var chips = document.createElement('div');
				var input = document.createElement('input');
				var error = document.createElement('p');
				control.className = 'cen-ear-multi-select cen-ear-text-values';
				chips.className = 'cen-ear-chips';
				input.type = 'text';
				input.className = 'cen-ear-picker-search';
				input.maxLength = 200;
				input.placeholder = <?php echo wp_json_encode( __( 'Add another', 'member-search' ) ); ?>;
				input.setAttribute('autocomplete', 'off');
				input.setAttribute('aria-label', 'Value');
				error.className = 'cen-ear-picker-error';
				error.textContent = missingValueText;
				error.hidden = true;
				chips.appendChild(input);
				control.appendChild(chips);
				control.appendChild(error);

				function update() {
					var hasValues = !!chips.querySelector('.cen-ear-value-input');
					if (hasValues) {
						control.classList.remove('cen-ear-invalid'); error.hidden = true; input.removeAttribute('aria-invalid');
					}
				}
				function removeChip(chip) {
					chip.remove(); update(); renumber(); markResultsStale(); input.focus();
				}
				function addChip(rawValue) {
					var value = String(rawValue || '').trim();
					if (!value) { return false; }
					var duplicate = Array.prototype.some.call(chips.querySelectorAll('.cen-ear-value-input'), function (hidden) {
						return hidden.value.toLowerCase() === value.toLowerCase();
					});
					if (duplicate) { input.value = ''; return false; }
					var chip = document.createElement('span');
					var label = document.createElement('span');
					var remove = document.createElement('button');
					var hidden = document.createElement('input');
					chip.className = 'cen-ear-chip'; chip.setAttribute('data-value', value);
					label.className = 'cen-ear-chip-label'; label.textContent = value;
					remove.type = 'button'; remove.className = 'cen-ear-chip-remove'; remove.textContent = '\u00d7'; remove.setAttribute('aria-label', removeText + ' ' + value);
					hidden.type = 'hidden'; hidden.className = 'cen-ear-value-input'; hidden.value = value;
					remove.addEventListener('click', function () { removeChip(chip); });
					chip.appendChild(label); chip.appendChild(remove); chip.appendChild(hidden); chips.insertBefore(chip, input);
					input.value = ''; update(); renumber(); markResultsStale();
					return true;
				}

				control.addEventListener('click', function () { input.focus(); });
				input.addEventListener('keydown', function (event) {
					if ('Enter' === event.key) { event.preventDefault(); addChip(input.value); }
					else if ('Backspace' === event.key && !input.value) {
						var selected = chips.querySelectorAll('.cen-ear-chip');
						if (selected.length) { removeChip(selected[selected.length - 1]); }
					}
				});
				(selectedValues || []).forEach(addChip);

				return {
					element: control,
					validate: function () {
						addChip(input.value);
						var valid = !!chips.querySelector('.cen-ear-value-input');
						control.classList.toggle('cen-ear-invalid', !valid); error.hidden = valid;
						if (!valid) { input.setAttribute('aria-invalid', 'true'); } else { input.removeAttribute('aria-invalid'); }
						return valid;
					}
				};
			}

			function datePicker(operatorKey, selectedValues) {
				var control = document.createElement('div');
				var values = document.createElement('div');
				var error = document.createElement('p');
				var inputCount = 'between_inclusive' === operatorKey ? 2 : 1;
				control.className = 'cen-ear-date-control';
				values.className = 'cen-ear-date-values' + (2 === inputCount ? ' cen-ear-date-range' : '');
				error.className = 'cen-ear-date-error';
				error.textContent = missingDateText;
				error.hidden = true;

				for (var index = 0; index < inputCount; index += 1) {
					var input = document.createElement('input');
					input.type = 'date';
					input.className = 'cen-ear-date-input cen-ear-value-input';
					input.required = true;
					input.setAttribute('aria-label', 2 === inputCount ? (0 === index ? 'Start date' : 'End date') : 'Date');
					input.value = selectedValues && selectedValues[index] ? selectedValues[index] : '';
					input.addEventListener('change', function () {
						control.classList.remove('cen-ear-invalid'); error.hidden = true; markResultsStale();
					});
					values.appendChild(input);
				}
				control.appendChild(values);
				control.appendChild(error);

				return {
					element: control,
					validate: function () {
						var inputs = values.querySelectorAll('.cen-ear-date-input');
						var complete = Array.prototype.every.call(inputs, function (input) { return !!input.value; });
						var rangeValid = 2 !== inputs.length || !complete || inputs[0].value <= inputs[1].value;
						var valid = complete && rangeValid;
						error.textContent = rangeValid ? missingDateText : invalidDateRangeText;
						control.classList.toggle('cen-ear-invalid', !valid); error.hidden = valid;
						if (!valid) {
							var missing = values.querySelector('.cen-ear-date-input:invalid');
							if (missing) { missing.focus(); }
						}
						return valid;
					}
				};
			}

			function renumber() {
				list.querySelectorAll('.cen-ear-rule').forEach(function (row, index) {
					row.querySelector('.cen-ear-attribute-select').name = 'criteria[' + index + '][attribute]';
					row.querySelector('.cen-ear-operator-select').name = 'criteria[' + index + '][operator]';
					row.querySelectorAll('.cen-ear-value-input').forEach(function (input) { input.name = 'criteria[' + index + '][values][]'; });
				});
			}

			function createRule(rule, replaceRow) {
				var hasEventRule = 'event' === rule.attribute || Array.prototype.some.call(list.querySelectorAll('.cen-ear-attribute-select'), function (select) {
					return 'event' === select.value;
				});
				var genericAttributes = entries(attributes).filter(function (entry) {
					return 'attribute' === entry[1].criterion_type && ('event_rsvp_status' !== entry[0] || hasEventRule);
				});
				var directAttributes = entries(attributes).filter(function (entry) { return 'attribute' !== entry[1].criterion_type; });
				var topLevelOptions = directAttributes.map(function (entry) { return [entry[0], entry[1].label]; });
				if (genericAttributes.length) { topLevelOptions.push(['attribute', <?php echo wp_json_encode( __( 'Attribute', 'member-search' ) ); ?>]); }
				var attributeKey = attributes[rule.attribute] ? rule.attribute : (genericAttributes.length ? genericAttributes[0][0] : directAttributes[0][0]);
				var attribute = attributes[attributeKey];
				var isGeneric = 'attribute' === attribute.criterion_type;
				var operatorKey = attribute.operators[rule.operator] ? rule.operator : Object.keys(attribute.operators)[0];
				var row = document.createElement('div');
				var typeField = field(isGeneric ? <?php echo wp_json_encode( __( 'Criteria', 'member-search' ) ); ?> : <?php echo wp_json_encode( __( 'Attribute', 'member-search' ) ); ?>);
				var attributeField = isGeneric ? field(<?php echo wp_json_encode( __( 'Attribute', 'member-search' ) ); ?>) : null;
				var operatorField = field(<?php echo wp_json_encode( __( 'Condition', 'member-search' ) ); ?>);
				var valueField = field(<?php echo wp_json_encode( __( 'Value', 'member-search' ) ); ?>);
				var remove = document.createElement('button');
				var connector = document.createElement('span');
				var connectorLabel = document.createElement('span');
				var requiresValue = -1 === ['is_empty', 'is_not_empty'].indexOf(operatorKey);
				var picker;
				if (requiresValue) {
					if ('date' === attribute.type) {
						picker = datePicker(operatorKey, rule.values || []);
					} else {
						picker = 'single_choice' === attribute.type ? singleChoicePicker(attribute, rule.values || []) : ('multi_text' === attribute.type ? multiTextPicker(rule.values || []) : multiPicker(attribute, rule.values || []));
					}
				} else {
					var noValue = document.createElement('div');
					noValue.className = 'cen-ear-no-value';
					noValue.textContent = <?php echo wp_json_encode( __( 'No value required', 'member-search' ) ); ?>;
					picker = { element: noValue, validate: function () { return true; } };
				}
				row.className = 'cen-ear-rule' + (isGeneric ? ' cen-ear-rule-generic' : '');
				var typeSelect = singleSelect(topLevelOptions, isGeneric ? 'attribute' : attributeKey, 'Criteria', function (newType) {
					var nextAttribute = 'attribute' === newType ? genericAttributes[0][0] : newType;
					createRule({ attribute: nextAttribute, operator: Object.keys(attributes[nextAttribute].operators)[0], values: [] }, row);
				});
				if (!isGeneric) { typeSelect.select.className = 'cen-ear-attribute-select'; }
				var attributeSelect = null;
				if (isGeneric) {
					attributeSelect = singleSelect(genericAttributes.map(function (entry) { return [entry[0], entry[1].label]; }), attributeKey, 'Attribute', function (newAttribute) {
						createRule({ attribute: newAttribute, operator: Object.keys(attributes[newAttribute].operators)[0], values: [] }, row);
					}, true);
					attributeSelect.select.className = 'cen-ear-attribute-select';
				}
				var operatorSelect = singleSelect(entries(attribute.operators), operatorKey, 'Condition', function (newOperator) {
					createRule({ attribute: attributeKey, operator: newOperator, values: [] }, row);
				});
				operatorSelect.select.className = 'cen-ear-operator-select';
				typeField.appendChild(typeSelect.element);
				if (isGeneric) { attributeField.appendChild(attributeSelect.element); }
				operatorField.appendChild(operatorSelect.element);
				if (attribute.hide_operator) { operatorField.hidden = true; row.classList.add('cen-ear-rule-no-condition'); }
				valueField.appendChild(picker.element);
				remove.type = 'button'; remove.className = 'cen-ear-rule-remove'; remove.innerHTML = '&times;'; remove.setAttribute('aria-label', <?php echo wp_json_encode( __( 'Remove criterion', 'member-search' ) ); ?>);
				remove.addEventListener('click', function () { row.remove(); renumber(); updateAddCriteriaAvailability(); markResultsStale(); });
				connector.className = 'cen-ear-rule-connector'; connector.setAttribute('aria-hidden', 'true');
				connectorLabel.className = 'cen-ear-rule-connector-label'; connectorLabel.textContent = <?php echo wp_json_encode( __( 'AND', 'member-search' ) ); ?>;
				connector.appendChild(connectorLabel);
				row.appendChild(typeField);
				if (isGeneric) { row.appendChild(attributeField); }
				row.appendChild(operatorField); row.appendChild(valueField); row.appendChild(remove); row.appendChild(connector);
				row.cenEarValidate = picker.validate;
				if (replaceRow) { replaceRow.replaceWith(row); } else { list.appendChild(row); }
				if ('attendance_report' === attributeKey) {
					list.querySelectorAll('.cen-ear-rule').forEach(function (otherRow) { if (otherRow !== row) { otherRow.remove(); } });
				}
				renumber();
				updateAddCriteriaAvailability();
				markResultsStale();
			}

			initialRules.forEach(function (rule) { if (attributes[rule.attribute]) { createRule(rule); } });
			initializing = false;
			var addWrapper = document.getElementById('cen-ear-add-criteria-wrapper');
			var addTrigger = document.getElementById('cen-ear-add-criteria-trigger');
			var addMenu = document.getElementById('cen-ear-add-criteria-menu');
			function closeAddMenu() {
				if (addMenu && addTrigger) { addMenu.hidden = true; addTrigger.setAttribute('aria-expanded', 'false'); }
			}
			if (addWrapper && addTrigger && addMenu) {
				addTrigger.addEventListener('click', function () {
					var opening = addMenu.hidden;
					document.querySelectorAll('.cen-ear-single-menu').forEach(function (menu) {
						menu.hidden = true;
						var trigger = menu.parentElement.querySelector('.cen-ear-single-trigger');
						if (trigger) { trigger.setAttribute('aria-expanded', 'false'); }
					});
					addMenu.hidden = !opening;
					addTrigger.setAttribute('aria-expanded', opening ? 'true' : 'false');
				});
				addMenu.querySelectorAll('.cen-ear-picker-option').forEach(function (option) {
					option.addEventListener('click', function () {
						addSelect.value = option.getAttribute('data-value');
						addSelect.dispatchEvent(new Event('change', { bubbles: true }));
						closeAddMenu();
						addTrigger.focus();
					});
				});
				document.addEventListener('click', function (event) { if (!addWrapper.contains(event.target)) { closeAddMenu(); } });
			}
			addSelect.addEventListener('change', function () {
				var selectedAttribute = addSelect.value;
				if ('attribute' === selectedAttribute) {
					var firstGeneric = entries(attributes).find(function (entry) { return 'attribute' === entry[1].criterion_type; });
					selectedAttribute = firstGeneric ? firstGeneric[0] : '';
				}
				if (attributes[selectedAttribute]) { createRule({ attribute: selectedAttribute, operator: Object.keys(attributes[selectedAttribute].operators)[0], values: [] }); }
				addSelect.value = '';
				if (addTrigger) { addTrigger.querySelector('span').textContent = <?php echo wp_json_encode( __( 'Add criteria', 'member-search' ) ); ?>; }
				if (addMenu) { addMenu.querySelectorAll('.cen-ear-picker-option').forEach(function (option) { option.setAttribute('aria-selected', 'false'); }); }
			});
			form.addEventListener('submit', function (event) {
				var valid = true;
				list.querySelectorAll('.cen-ear-rule').forEach(function (row) { if (!row.cenEarValidate()) { valid = false; } });
				if (!valid) { event.preventDefault(); }
			});
		}());
	</script>
	<?php
}

/**
 * Render the member attribute search dashboard.
 */
function cen_ear_render_member_search_page() {
	if ( ! current_user_can( cen_ear_get_required_capability() ) ) {
		wp_die(
			esc_html__( 'You are not allowed to search or export members.', 'member-search' ),
			esc_html__( 'Access denied', 'member-search' ),
			array( 'response' => 403 )
		);
	}

	$event_choices = cen_ear_get_event_choices();
	$attributes   = cen_ear_get_search_attributes( $event_choices );
	$rules        = cen_ear_get_criteria_rules( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$current_page = isset( $_GET['results_page'] ) ? max( 1, absint( $_GET['results_page'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$include_suspended = isset( $_GET['include_suspended'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['include_suspended'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$search       = null;
	$rows         = array();
	$search_error = null;
	$report_event_ids = array();

	$validated = cen_ear_validate_criteria_rules( $rules, $attributes );
	if ( is_wp_error( $validated ) ) {
		$search_error = $validated;
	} else {
		$rules  = $validated;
		$search = cen_ear_query_criteria_rules( $validated, $attributes, $current_page, $include_suspended );
		if ( is_wp_error( $search ) ) {
			$search_error = $search;
			$search       = null;
		} else {
			$report_event_ids = cen_ear_get_criteria_event_ids( $rules, $attributes );
			$rows = array_merge(
				cen_ear_get_member_report_rows( $search['ids'], true, $report_event_ids ),
				isset( $search['guests'] ) ? $search['guests'] : array()
			);
		}
	}
	$preview_columns        = cen_ear_get_preview_columns( ! empty( $report_event_ids ) );
	$selected_preview_fields = cen_ear_get_selected_preview_fields();
	if ( empty( array_intersect( $selected_preview_fields, array_keys( $preview_columns ) ) ) ) {
		$available_defaults      = array_intersect( cen_ear_get_default_preview_fields(), array_keys( $preview_columns ) );
		$selected_preview_fields = array_values( array_unique( array_merge( $selected_preview_fields, array_slice( $available_defaults, 0, 1 ) ) ) );
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'Member Search', 'member-search' ); ?></h1>

			<?php if ( $search_error ) : ?>
				<?php cen_ear_render_notice( $search_error->get_error_message(), 'error' ); ?>
			<?php endif; ?>

			<style>
				.cen-ear-builder { display: grid; grid-template-columns: minmax(180px, 1fr) minmax(140px, .7fr) minmax(260px, 2fr); gap: 10px; align-items: end; max-width: 1100px; margin: 20px 0; padding: 18px; background: #fff; border: 1px solid #c3c4c7; }
				.cen-ear-field label, .cen-ear-field-label { display: block; margin-bottom: 6px; font-weight: 600; }
				.cen-ear-field select { box-sizing: border-box; width: 100%; height: 40px; min-height: 40px; }
				.cen-ear-single-select { position: relative; }
				.cen-ear-single-trigger { box-sizing: border-box; display: none; align-items: center; justify-content: space-between; width: 100%; height: 40px; padding: 0 14px; color: #1d2327; font-size: 14px; text-align: left; background: #fff; border: 1px solid #8c8f94; border-radius: 2px; cursor: pointer; }
				.cen-ear-single-trigger::after { content: ""; width: 7px; height: 7px; margin: 0 4px 4px 14px; border-right: 2px solid #1d2327; border-bottom: 2px solid #1d2327; transform: rotate(45deg); }
				.cen-ear-single-trigger[aria-expanded="true"] { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; }
				.cen-ear-single-trigger[aria-expanded="true"]::after { margin-top: 5px; margin-bottom: 0; transform: rotate(225deg); }
				.cen-ear-single-select.cen-ear-enhanced > select { display: none; }
				.cen-ear-single-select.cen-ear-enhanced > .cen-ear-single-trigger { display: flex; }
				.cen-ear-multi-select { box-sizing: border-box; position: relative; min-height: 40px; padding: 0 8px; background: #fff; border: 1px solid #8c8f94; border-radius: 2px; cursor: text; }
				.cen-ear-multi-select:focus-within { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; }
				.cen-ear-multi-select.cen-ear-invalid { border-color: #d63638; box-shadow: 0 0 0 1px #d63638; }
				.cen-ear-chips { box-sizing: border-box; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; width: 100%; min-width: 0; min-height: 38px; }
				.cen-ear-chips:has(.cen-ear-chip) { padding-top: 5px; }
				.cen-ear-chip { box-sizing: border-box; display: inline-flex; gap: 7px; align-items: center; max-width: 100%; min-width: 0; padding: 4px 7px 4px 9px; background: #e2e4e7; border-radius: 3px; }
				.cen-ear-chip + .cen-ear-chip { padding-left: 0; }
				.cen-ear-chip + .cen-ear-chip::before { content: "or"; align-self: stretch; display: inline-flex; align-items: center; padding: 0 6px; color: #fff; background: #8c8f94; border-radius: 3px 0 0 3px; }
				.cen-ear-chip-label { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
				.cen-ear-chip-remove { flex: 0 0 22px; width: 22px; height: 22px; padding: 0; color: #50575e; font-size: 20px; line-height: 18px; background: transparent; border: 0; border-radius: 2px; cursor: pointer; }
				.cen-ear-chip-remove:hover, .cen-ear-chip-remove:focus { color: #fff; background: #646970; outline: none; }
				.cen-ear-multi-select input.cen-ear-picker-search { box-sizing: border-box; flex: 1 1 140px; min-width: 80px; width: auto; height: 38px !important; min-height: 38px !important; max-height: 38px !important; margin: 0 !important; padding: 0 4px !important; color: #1d2327; font-size: 14px; line-height: 38px !important; background: transparent; border: 0 !important; outline: 0; box-shadow: none !important; }
				.cen-ear-picker-menu { position: absolute; z-index: 100000; top: calc(100% + 5px); left: -1px; width: calc(100% + 2px); max-height: 360px; overflow-y: auto; background: #fff; border: 1px solid #8c8f94; box-shadow: 0 8px 20px rgba(0, 0, 0, .22); }
				.cen-ear-picker-menu[hidden] { display: none; }
				.cen-ear-picker-option { display: block; width: 100%; padding: 15px 18px; color: #1d2327; font-size: 15px; text-align: left; background: #fff; border: 0; border-bottom: 1px solid #f0f0f1; cursor: pointer; }
				.cen-ear-picker-option[hidden], .cen-ear-picker-empty[hidden], .cen-ear-picker-error[hidden] { display: none; }
				.cen-ear-picker-option:last-child { border-bottom: 0; }
				.cen-ear-picker-option[aria-selected="true"] { font-weight: 600; background: #e2e4e7; }
				.cen-ear-picker-option:hover, .cen-ear-picker-option:focus { color: #fff; background: #2271b1; outline: none; }
				.cen-ear-picker-empty { margin: 0; padding: 15px 18px; color: #646970; }
				.cen-ear-picker-error { position: absolute; inset: 0; display: flex; align-items: center; justify-content: flex-start; margin: 0; padding: 0 12px; color: #d63638; text-align: left; pointer-events: none; }
				.cen-ear-picker-menu { display: none; }
				.cen-ear-enhanced .cen-ear-picker-menu:not([hidden]) { display: block; }
				.cen-ear-event-rule { display: contents; }
				.cen-ear-event-rule[hidden] { display: none; }
				.cen-ear-event-remove { grid-column: 1 / -1; justify-self: start; margin-top: -2px; }
				.cen-ear-actions { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: 10px; justify-content: flex-start; margin-top: 8px; }
				.cen-ear-actions .button { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; padding: 0 18px; color: #fff; }
				.cen-ear-actions .cen-ear-add-criterion { color: #2271b1; background: #f6f7f7; border-color: #2271b1; }
				.cen-ear-actions .cen-ear-add-criterion:hover, .cen-ear-actions .cen-ear-add-criterion:focus { color: #135e96; background: #f0f6fc; border-color: #135e96; }
				.cen-ear-actions .cen-ear-search-button { background: #218739; border-color: #176b2c; }
				.cen-ear-actions .cen-ear-search-button:hover, .cen-ear-actions .cen-ear-search-button:focus { color: #fff; background: #176b2c; border-color: #105321; }
				.cen-ear-actions .cen-ear-clear-button { background: #d63638; border-color: #b32d2e; }
				.cen-ear-actions .cen-ear-clear-button:hover, .cen-ear-actions .cen-ear-clear-button:focus { color: #fff; background: #b32d2e; border-color: #8f2424; }
				.cen-ear-event-remove { color: #b32d2e; }
				.cen-ear-results-header { display: flex; gap: 16px; align-items: center; justify-content: space-between; width: 100%; margin-top: 26px; }
				.cen-ear-results-header h2 { margin: 0; }
				.cen-ear-results-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
				.cen-ear-preview-modal { box-sizing: border-box; width: 560px; max-width: calc(100vw - 40px); max-height: calc(100vh - 80px); padding: 0; overflow: hidden; background: #fff; border: 0; border-radius: 4px; box-shadow: 0 20px 60px rgba(0, 0, 0, .35); }
				.cen-ear-preview-modal::backdrop { background: rgba(0, 0, 0, .58); }
				.cen-ear-preview-modal-header { display: flex; gap: 20px; align-items: center; justify-content: space-between; padding: 18px 20px; border-bottom: 1px solid #dcdcde; }
				.cen-ear-preview-modal-header h2 { margin: 0; font-size: 20px; }
				.cen-ear-preview-modal-close { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; padding: 0; color: #50575e; font-size: 0; line-height: 0; background: transparent; border: 0; border-radius: 3px; cursor: pointer; }
				.cen-ear-preview-modal-close::before, .cen-ear-preview-modal-close::after { content: ""; position: absolute; top: 50%; left: 50%; width: 18px; height: 2px; background: currentColor; border-radius: 1px; transform-origin: center; }
				.cen-ear-preview-modal-close::before { transform: translate(-50%, -50%) rotate(45deg); }
				.cen-ear-preview-modal-close::after { transform: translate(-50%, -50%) rotate(-45deg); }
				.cen-ear-preview-modal-close:hover, .cen-ear-preview-modal-close:focus { color: #fff; background: #b32d2e; outline: none; }
				.cen-ear-preview-fields-panel { display: grid; grid-template-columns: repeat(2, minmax(180px, 1fr)); gap: 10px 20px; max-height: calc(100vh - 230px); padding: 20px; overflow-y: auto; }
				.cen-ear-preview-fields-panel label { display: flex; gap: 7px; align-items: flex-start; }
				.cen-ear-preview-fields-panel input { flex: 0 0 auto; margin-top: 1px; }
				.cen-ear-preview-fields-error { grid-column: 1 / -1; margin: 4px 0 0; color: #d63638; }
				.cen-ear-preview-modal-footer { display: flex; justify-content: flex-end; padding: 14px 20px; background: #f6f7f7; border-top: 1px solid #dcdcde; }
				.cen-ear-results-scroll { width: 100%; margin-top: 12px; overflow-x: auto; }
				.cen-ear-results { width: 100%; min-width: 900px; }
				.cen-ear-results [hidden] { display: none; }
				.cen-ear-pagination { width: 100%; margin: 24px 0; text-align: center; }
				.cen-ear-pagination > .page-numbers { display: inline-flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin: 0; padding: 0; list-style: none; }
				.cen-ear-pagination > .page-numbers > li { margin: 0; }
				.cen-ear-pagination .page-numbers a, .cen-ear-pagination .page-numbers span { box-sizing: border-box; display: inline-flex; align-items: center; justify-content: center; min-width: 44px; height: 44px; padding: 0 14px; color: #2271b1; font-size: 16px; font-weight: 600; text-decoration: none; background: #fff; border: 1px solid #c3c4c7; border-radius: 3px; }
				.cen-ear-pagination .page-numbers a:hover, .cen-ear-pagination .page-numbers a:focus { color: #fff; background: #2271b1; border-color: #2271b1; }
				.cen-ear-pagination .page-numbers .current { color: #fff; background: #1d2327; border-color: #1d2327; }
				.cen-ear-pagination .page-numbers .dots { color: #646970; background: transparent; border-color: transparent; }
				@media (max-width: 782px) { .cen-ear-builder { grid-template-columns: 1fr; } .cen-ear-results-header { align-items: flex-start; flex-direction: column; } .cen-ear-preview-fields-panel { grid-template-columns: 1fr; } }
			</style>

			<?php cen_ear_render_criteria_builder( $attributes, $rules, $include_suspended ); ?>

			<?php if ( is_array( $search ) ) : ?>
				<div class="cen-ear-results-header">
					<h2>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of matching members. */
								_n( '%s match', '%s matches', $search['total'], 'member-search' ),
								number_format_i18n( $search['total'] )
							)
						);
						?>
					</h2>
					<?php if ( $search['total'] > 0 ) : ?>
						<div class="cen-ear-results-actions">
							<button type="button" id="cen-ear-preview-modal-open" class="button"><?php echo esc_html__( 'Preview Fields', 'member-search' ); ?></button>
							<dialog id="cen-ear-preview-modal" class="cen-ear-preview-modal" aria-labelledby="cen-ear-preview-modal-title">
								<div class="cen-ear-preview-modal-header">
									<h2 id="cen-ear-preview-modal-title"><?php echo esc_html__( 'Preview Fields', 'member-search' ); ?></h2>
									<button type="button" id="cen-ear-preview-modal-close" class="cen-ear-preview-modal-close" aria-label="<?php echo esc_attr__( 'Close', 'member-search' ); ?>"></button>
								</div>
								<div class="cen-ear-preview-fields-panel">
									<?php foreach ( $preview_columns as $field_key => $preview_column ) : ?>
										<label>
											<input type="checkbox" class="cen-ear-preview-field-toggle" value="<?php echo esc_attr( $field_key ); ?>" <?php checked( in_array( $field_key, $selected_preview_fields, true ) ); ?>>
											<span><?php echo esc_html( $preview_column['label'] ); ?></span>
										</label>
									<?php endforeach; ?>
									<p id="cen-ear-preview-fields-error" class="cen-ear-preview-fields-error" hidden><?php echo esc_html__( 'Select at least one preview field.', 'member-search' ); ?></p>
								</div>
								<div class="cen-ear-preview-modal-footer">
									<button type="button" id="cen-ear-preview-fields-save" class="button button-primary"><?php echo esc_html__( 'Save', 'member-search' ); ?></button>
								</div>
							</dialog>
						<form id="cen-ear-export-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="cen_ear_export_members">
							<?php if ( $include_suspended ) : ?><input type="hidden" name="include_suspended" value="1"><?php endif; ?>
							<?php foreach ( $rules as $rule_index => $rule ) : ?>
								<input type="hidden" name="criteria[<?php echo esc_attr( $rule_index ); ?>][attribute]" value="<?php echo esc_attr( $rule['attribute'] ); ?>">
								<input type="hidden" name="criteria[<?php echo esc_attr( $rule_index ); ?>][operator]" value="<?php echo esc_attr( $rule['operator'] ); ?>">
								<?php foreach ( $rule['values'] as $value ) : ?><input type="hidden" name="criteria[<?php echo esc_attr( $rule_index ); ?>][values][]" value="<?php echo esc_attr( $value ); ?>"><?php endforeach; ?>
							<?php endforeach; ?>
							<?php wp_nonce_field( 'cen_ear_export_members' ); ?>
							<button type="submit" class="button button-primary"><?php echo esc_html__( 'Download Excel File', 'member-search' ); ?></button>
						</form>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( empty( $rows ) ) : ?>
					<?php cen_ear_render_notice( __( 'No members matched this search.', 'member-search' ), 'warning' ); ?>
				<?php else : ?>
					<div class="cen-ear-results-scroll">
						<table class="widefat striped cen-ear-results">
							<thead><tr>
								<?php foreach ( $preview_columns as $field_key => $preview_column ) : ?>
									<th data-cen-ear-field="<?php echo esc_attr( $field_key ); ?>"<?php echo in_array( $field_key, $selected_preview_fields, true ) ? '' : ' hidden'; ?>><?php echo esc_html( $preview_column['label'] ); ?></th>
								<?php endforeach; ?>
							</tr></thead>
							<tbody>
								<?php foreach ( $rows as $row ) : ?>
									<tr>
										<?php foreach ( $preview_columns as $field_key => $preview_column ) : ?>
											<?php $field_value = isset( $row[ $field_key ] ) ? (string) $row[ $field_key ] : ''; ?>
											<td data-cen-ear-field="<?php echo esc_attr( $field_key ); ?>"<?php echo in_array( $field_key, $selected_preview_fields, true ) ? '' : ' hidden'; ?>>
												<?php if ( 'user_id' === $preview_column['type'] ) : ?>
													<?php $wp_user_id = absint( $field_value ); ?>
													<?php $edit_user_url = $wp_user_id ? get_edit_user_link( $wp_user_id ) : ''; ?>
													<?php if ( $edit_user_url ) : ?>
														<a href="<?php echo esc_url( $edit_user_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $wp_user_id ); ?></a>
													<?php else : ?>
														<?php echo esc_html( $field_value ); ?>
													<?php endif; ?>
												<?php elseif ( 'email' === $preview_column['type'] ) : ?>
													<?php if ( '' !== $field_value ) : ?><a href="<?php echo esc_url( 'mailto:' . $field_value ); ?>"><?php echo esc_html( $field_value ); ?></a><?php endif; ?>
												<?php elseif ( 'phone' === $preview_column['type'] ) : ?>
													<?php
													$phone_display = cen_ear_format_phone_for_export( $field_value );
													$phone_number  = preg_replace( '/\D+/', '', $field_value );
													$phone_link    = 0 === strpos( $phone_display, '+' ) ? '+' . $phone_number : $phone_number;
													?>
													<?php if ( '' !== $phone_number ) : ?><a href="<?php echo esc_attr( 'tel:' . $phone_link ); ?>"><?php echo esc_html( $phone_display ); ?></a><?php endif; ?>
												<?php elseif ( 'list' === $preview_column['type'] ) : ?>
													<?php echo esc_html( str_replace( '||', ', ', $field_value ) ); ?>
												<?php else : ?>
													<?php echo esc_html( $field_value ); ?>
												<?php endif; ?>
											</td>
										<?php endforeach; ?>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<script>
						(function () {
							var toggles = Array.prototype.slice.call(document.querySelectorAll('.cen-ear-preview-field-toggle'));
							var selectedFields = <?php echo wp_json_encode( array_values( $selected_preview_fields ) ); ?>;
							var cookieName = <?php echo wp_json_encode( cen_ear_get_preview_fields_cookie_name() ); ?>;
							var modal = document.getElementById('cen-ear-preview-modal');
							var openButton = document.getElementById('cen-ear-preview-modal-open');
							var closeButton = document.getElementById('cen-ear-preview-modal-close');
							var saveButton = document.getElementById('cen-ear-preview-fields-save');
							var error = document.getElementById('cen-ear-preview-fields-error');
							if (!toggles.length || !modal || !openButton || !closeButton || !saveButton) { return; }

							function setColumnVisibility(field, visible) {
								document.querySelectorAll('[data-cen-ear-field]').forEach(function (cell) {
									if (cell.getAttribute('data-cen-ear-field') === field) { cell.hidden = !visible; }
								});
							}

							function saveCookie() {
								var cookie = cookieName + '=' + encodeURIComponent(selectedFields.join(',')) + '; path=/; max-age=31536000; SameSite=Lax';
								if ('https:' === window.location.protocol) { cookie += '; Secure'; }
								document.cookie = cookie;
							}

							function resetModal() {
								toggles.forEach(function (toggle) { toggle.checked = -1 !== selectedFields.indexOf(toggle.value); });
								if (error) { error.hidden = true; }
							}

							function closeModal() {
								resetModal();
								modal.close();
							}

							openButton.addEventListener('click', function () {
								resetModal();
								modal.showModal();
							});
							closeButton.addEventListener('click', closeModal);
							modal.addEventListener('cancel', function () { resetModal(); });
							modal.addEventListener('click', function (event) {
								if (event.target !== modal) { return; }
								var bounds = modal.getBoundingClientRect();
								var outside = event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom;
								if (outside) { closeModal(); }
							});
							toggles.forEach(function (toggle) {
								toggle.addEventListener('change', function () { if (error) { error.hidden = true; } });
							});
							saveButton.addEventListener('click', function () {
								var checkedFields = toggles.filter(function (toggle) { return toggle.checked; }).map(function (toggle) { return toggle.value; });
								if (!checkedFields.length) {
									if (error) { error.hidden = false; }
									return;
								}
								var availableFields = toggles.map(function (toggle) { return toggle.value; });
								selectedFields = selectedFields.filter(function (field) { return -1 === availableFields.indexOf(field); }).concat(checkedFields);
								toggles.forEach(function (toggle) { setColumnVisibility(toggle.value, toggle.checked); });
								saveCookie();
								if (error) { error.hidden = true; }
								modal.close();
							});
						}());
					</script>

					<?php if ( $search['max_pages'] > 1 ) : ?>
						<nav class="cen-ear-pagination" aria-label="<?php echo esc_attr__( 'Member results pagination', 'member-search' ); ?>">
							<?php
						$pagination_args = array( 'cen_ear_search' => 1, 'criteria' => $rules, 'results_page' => 999999999 );
						if ( $include_suspended ) {
							$pagination_args['include_suspended'] = 1;
						}
						$pagination_url = cen_ear_get_admin_page_url( $pagination_args );
							echo wp_kses_post(
								paginate_links(
									array(
										'base'      => str_replace( '999999999', '%#%', $pagination_url ),
										'format'    => '',
										'current'   => $current_page,
										'total'     => $search['max_pages'],
										'type'      => 'list',
										'prev_text' => __( '&laquo; Previous', 'member-search' ),
										'next_text' => __( 'Next &raquo;', 'member-search' ),
									)
								)
							);
							?>
						</nav>
					<?php endif; ?>
				<?php endif; ?>
			<?php endif; ?>
	</div>
	<?php
}

/**
 * Convert a stored profile value to plain text.
 *
 * @param mixed $value Stored value.
 * @return string
 */
function cen_ear_decode_text( $value ) {
	$value = (string) $value;

	// Some providers return text that has already been entity-encoded before
	// WordPress encodes it again (for example, &amp;#8217;). Decode each layer so
	// pickers display the intended punctuation instead of an entity string.
	for ( $decode_pass = 0; $decode_pass < 3; $decode_pass++ ) {
		// html_entity_decode() is required here because wp_specialchars_decode()
		// does not decode numeric entities such as &#8217;.
		$decoded = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( $decoded === $value ) {
			break;
		}
		$value = $decoded;
	}

	// Normalize legacy/imported values that stored an SQL-style escape before
	// an apostrophe. WordPress handles SQL escaping separately, so the slash is
	// data rather than punctuation and should not be displayed to users.
	$value = str_replace( "\\'", "'", $value );

	$value = wp_strip_all_tags( $value );

	return trim( $value );
}

/**
 * Return a plain-text BuddyBoss/BuddyPress Extended Profile field value.
 *
 * @param int $user_id  WordPress user ID.
 * @param int $field_id Extended Profile field ID.
 * @return string
 */
function cen_ear_get_xprofile_field( $user_id, $field_id ) {
	if ( ! function_exists( 'xprofile_get_field_data' ) ) {
		return '';
	}

	$value = xprofile_get_field_data( absint( $field_id ), absint( $user_id ), 'comma' );
	if ( is_wp_error( $value ) ) {
		return '';
	}
	if ( is_array( $value ) ) {
		$values = array_map( 'cen_ear_decode_text', array_filter( $value, 'is_scalar' ) );

		return implode( '||', array_values( array_filter( $values, 'strlen' ) ) );
	}

	return cen_ear_decode_text( $value );
}

/**
 * Convert a value read directly from the Extended Profile data table to the
 * same comma-separated text returned by xprofile_get_field_data().
 *
 * @param mixed $value Stored Extended Profile value.
 * @return string
 */
function cen_ear_normalize_stored_xprofile_value( $value ) {
	$value = function_exists( 'maybe_unserialize' ) ? maybe_unserialize( $value ) : $value;
	if ( is_array( $value ) ) {
		$values = array_map( 'cen_ear_decode_text', array_filter( $value, 'is_scalar' ) );

		return implode( ', ', array_values( array_filter( $values, 'strlen' ) ) );
	}
	if ( is_object( $value ) || ! is_scalar( $value ) ) {
		return '';
	}

	return cen_ear_decode_text( $value );
}

/**
 * Bulk-load Extended Profile values for all requested users and fields.
 *
 * @param int[] $user_ids  WordPress user IDs.
 * @param int[] $field_ids Extended Profile field IDs.
 * @return array<int,array<int,string>> Values keyed by user ID and field ID.
 */
function cen_ear_get_bulk_xprofile_fields( $user_ids, $field_ids ) {
	global $wpdb;

	$user_ids  = array_values( array_unique( array_filter( array_map( 'absint', (array) $user_ids ) ) ) );
	$field_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $field_ids ) ) ) );
	if ( empty( $user_ids ) || empty( $field_ids ) || ! function_exists( 'xprofile_get_field_data' ) ) {
		return array();
	}

	$bp         = function_exists( 'buddypress' ) ? buddypress() : null;
	$table_name = is_object( $bp ) && isset( $bp->profile->table_name_data )
		? $bp->profile->table_name_data
		: $wpdb->prefix . 'bp_xprofile_data';
	$values     = array();
	$field_placeholders = implode( ', ', array_fill( 0, count( $field_ids ), '%d' ) );
	foreach ( array_chunk( $user_ids, 500 ) as $user_id_chunk ) {
		$user_placeholders = implode( ', ', array_fill( 0, count( $user_id_chunk ), '%d' ) );
		$sql = "SELECT user_id, field_id, value FROM {$table_name} WHERE user_id IN ({$user_placeholders}) AND field_id IN ({$field_placeholders})";
		$query = $wpdb->prepare( $sql, array_merge( $user_id_chunk, $field_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		foreach ( (array) $wpdb->get_results( $query, ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$user_id  = isset( $row['user_id'] ) ? absint( $row['user_id'] ) : 0;
			$field_id = isset( $row['field_id'] ) ? absint( $row['field_id'] ) : 0;
			if ( $user_id && $field_id ) {
				$values[ $user_id ][ $field_id ] = cen_ear_normalize_stored_xprofile_value( isset( $row['value'] ) ? $row['value'] : '' );
			}
		}
	}

	return $values;
}

/**
 * Return a plain-text WordPress user-meta value.
 *
 * @param int    $user_id WordPress user ID.
 * @param string $key     User-meta key.
 * @return string
 */
function cen_ear_get_user_meta_text( $user_id, $key ) {
	$value = get_user_meta( absint( $user_id ), $key, true );
	if ( is_array( $value ) || is_object( $value ) ) {
		return '';
	}

	return cen_ear_decode_text( $value );
}

/**
 * Return the user's BuddyBoss email preference for new discussions in
 * subscribed forums.
 *
 * Modern BuddyBoss versions store this preference under the registered
 * bb_forums_subscribed_discussion notification type. Use BuddyBoss's own
 * resolver so user, site-wide, and default settings are all respected. The
 * legacy user-meta key remains as a compatibility fallback.
 *
 * @param int $user_id WordPress user ID.
 * @return string
 */
function cen_ear_get_forum_discussion_email_status( $user_id ) {
	$user_id = absint( $user_id );
	if ( function_exists( 'bb_is_notification_enabled' ) ) {
		return bb_is_notification_enabled( $user_id, 'bb_forums_subscribed_discussion', 'email' ) ? 'Subscribed' : 'Unsubscribed';
	}

	$preference = get_user_meta( $user_id, 'notification_forums_following_topic', true );

	return 'no' === strtolower( trim( (string) $preference ) ) ? 'Unsubscribed' : 'Subscribed';
}

/**
 * Check every BuddyBoss state that makes a member profile inactive.
 *
 * BuddyBoss moderation suspensions and BuddyPress spammer flags are stored and
 * checked separately. Treat either state as suspended in this report.
 *
 * @param int $user_id WordPress user ID.
 * @return bool
 */
function cen_ear_is_user_suspended( $user_id ) {
	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return false;
	}
	if ( function_exists( 'bp_moderation_is_user_suspended' ) && bp_moderation_is_user_suspended( $user_id ) ) {
		return true;
	}
	if ( function_exists( 'bp_is_user_spammer' ) ) {
		return (bool) bp_is_user_spammer( $user_id );
	}

	// Match BuddyBoss's fallback spammer check when its helper is unavailable.
	$user = get_userdata( $user_id );

	return $user && ( ! empty( $user->spam ) || 1 === (int) $user->user_status );
}

/**
 * Return the user's BuddyBoss moderation status for display and export.
 *
 * @param int $user_id WordPress user ID.
 * @return string
 */
function cen_ear_get_buddyboss_status( $user_id ) {
	return cen_ear_is_user_suspended( $user_id ) ? 'Suspended' : 'Active';
}

/**
 * Return the readable BuddyBoss profile types assigned to a user.
 *
 * @param int $user_id WordPress user ID.
 * @return string Profile type labels separated by ||.
 */
function cen_ear_get_buddyboss_profile_types( $user_id ) {
	if ( ! function_exists( 'bp_get_member_type' ) ) {
		return '';
	}

	$choices = cen_ear_get_buddyboss_profile_type_choices();
	$choices = is_array( $choices ) ? $choices : array();
	$labels  = array();
	foreach ( (array) bp_get_member_type( absint( $user_id ), false ) as $profile_type ) {
		$slug = sanitize_key( $profile_type );
		if ( '' === $slug ) {
			continue;
		}
		if ( isset( $choices[ $slug ] ) ) {
			$labels[] = $choices[ $slug ];
			continue;
		}
		$type     = function_exists( 'bp_get_member_type_object' ) ? bp_get_member_type_object( $slug ) : $slug;
		$labels[] = cen_ear_get_buddyboss_profile_type_label( $type, $slug );
	}
	$labels = array_values( array_unique( array_filter( $labels ) ) );
	natcasesort( $labels );

	return implode( '||', $labels );
}

/**
 * Return the names of all confirmed BuddyBoss groups for a user.
 *
 * Member, moderator, and organizer memberships are included. Group names are
 * sorted alphabetically and separated by || for spreadsheet export.
 *
 * @param int $user_id WordPress user ID.
 * @return string
 */
function cen_ear_get_buddyboss_groups( $user_id ) {
	if ( ! function_exists( 'bp_get_user_groups' ) || ! function_exists( 'groups_get_groups' ) ) {
		return '';
	}

	$memberships = bp_get_user_groups(
		absint( $user_id ),
		array(
			'is_admin' => null,
			'is_mod'   => null,
		)
	);
	$group_ids   = array_map( 'absint', array_keys( (array) $memberships ) );
	$group_ids   = array_values( array_filter( $group_ids ) );
	if ( empty( $group_ids ) ) {
		return '';
	}

	$results = groups_get_groups(
		array(
			'include'     => $group_ids,
			'per_page'    => count( $group_ids ),
			'show_hidden' => true,
			'orderby'     => 'name',
			'order'       => 'ASC',
		)
	);
	$names   = array();
	foreach ( isset( $results['groups'] ) ? (array) $results['groups'] : array() as $group ) {
		if ( is_object( $group ) && isset( $group->name ) ) {
			$name = cen_ear_decode_text( $group->name );
			if ( '' !== $name ) {
				$names[] = $name;
			}
		}
	}

	return implode( '||', array_values( array_unique( $names ) ) );
}

/**
 * Bulk-load confirmed BuddyBoss group names for multiple users.
 *
 * @param int[] $user_ids WordPress user IDs.
 * @return array<int,string> Group names keyed by user ID.
 */
function cen_ear_get_bulk_buddyboss_groups( $user_ids ) {
	global $wpdb;

	$user_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $user_ids ) ) ) );
	if ( empty( $user_ids ) || ! function_exists( 'groups_get_groups' ) ) {
		return array();
	}
	$bp = function_exists( 'buddypress' ) ? buddypress() : null;
	$members_table = is_object( $bp ) && isset( $bp->groups->table_name_members ) ? $bp->groups->table_name_members : $wpdb->prefix . 'bp_groups_members';
	$groups_table  = is_object( $bp ) && isset( $bp->groups->table_name ) ? $bp->groups->table_name : $wpdb->prefix . 'bp_groups';
	$names_by_user = array();

	foreach ( array_chunk( $user_ids, 500 ) as $user_id_chunk ) {
		$placeholders = implode( ', ', array_fill( 0, count( $user_id_chunk ), '%d' ) );
		$sql = "SELECT memberships.user_id, group_data.name
			FROM {$members_table} memberships
			INNER JOIN {$groups_table} group_data ON group_data.id = memberships.group_id
			WHERE memberships.is_confirmed = 1 AND memberships.is_banned = 0
			AND memberships.user_id IN ({$placeholders})
			ORDER BY memberships.user_id ASC, group_data.name ASC";
		$query = $wpdb->prepare( $sql, $user_id_chunk ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		foreach ( (array) $wpdb->get_results( $query, ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$user_id = isset( $row['user_id'] ) ? absint( $row['user_id'] ) : 0;
			$name    = isset( $row['name'] ) ? cen_ear_decode_text( $row['name'] ) : '';
			if ( $user_id && '' !== $name ) {
				$names_by_user[ $user_id ][ $name ] = $name;
			}
		}
	}

	foreach ( $names_by_user as $user_id => $names ) {
		$names = array_values( $names );
		natcasesort( $names );
		$names_by_user[ $user_id ] = implode( '||', $names );
	}

	return $names_by_user;
}

/**
 * Bulk-load readable BuddyBoss profile types for multiple users.
 *
 * @param int[] $user_ids WordPress user IDs.
 * @return array<int,string> Profile type labels keyed by user ID.
 */
function cen_ear_get_bulk_buddyboss_profile_types( $user_ids ) {
	global $wpdb;

	$user_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $user_ids ) ) ) );
	if ( empty( $user_ids ) || ! function_exists( 'bp_get_member_type' ) ) {
		return array();
	}
	$taxonomy       = function_exists( 'bp_get_member_type_tax_name' ) ? bp_get_member_type_tax_name() : 'bp_member_type';
	$choices        = cen_ear_get_buddyboss_profile_type_choices();
	$choices        = is_array( $choices ) ? $choices : array();
	$labels_by_user = array();

	foreach ( array_chunk( $user_ids, 500 ) as $user_id_chunk ) {
		$placeholders = implode( ', ', array_fill( 0, count( $user_id_chunk ), '%d' ) );
		$sql = "SELECT relationships.object_id AS user_id, terms.slug
			FROM {$wpdb->term_relationships} relationships
			INNER JOIN {$wpdb->term_taxonomy} taxonomies ON taxonomies.term_taxonomy_id = relationships.term_taxonomy_id
			INNER JOIN {$wpdb->terms} terms ON terms.term_id = taxonomies.term_id
			WHERE taxonomies.taxonomy = %s AND relationships.object_id IN ({$placeholders})";
		$query = $wpdb->prepare( $sql, array_merge( array( $taxonomy ), $user_id_chunk ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		foreach ( (array) $wpdb->get_results( $query, ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$user_id = isset( $row['user_id'] ) ? absint( $row['user_id'] ) : 0;
			$slug    = isset( $row['slug'] ) ? sanitize_key( $row['slug'] ) : '';
			if ( ! $user_id || '' === $slug ) {
				continue;
			}
			$type  = function_exists( 'bp_get_member_type_object' ) ? bp_get_member_type_object( $slug ) : $slug;
			$label = isset( $choices[ $slug ] ) ? $choices[ $slug ] : cen_ear_get_buddyboss_profile_type_label( $type, $slug );
			if ( '' !== $label ) {
				$labels_by_user[ $user_id ][ $label ] = $label;
			}
		}
	}

	foreach ( $labels_by_user as $user_id => $labels ) {
		$labels = array_values( $labels );
		natcasesort( $labels );
		$labels_by_user[ $user_id ] = implode( '||', $labels );
	}

	return $labels_by_user;
}

/**
 * Return the user's WordPress registration date without leading zeros.
 *
 * @param int $user_id WordPress user ID.
 * @return string
 */
function cen_ear_get_registration_date( $user_id ) {
	$user = get_userdata( absint( $user_id ) );
	if ( ! $user || empty( $user->user_registered ) ) {
		return '';
	}

	return (string) mysql2date( 'n/j/Y', $user->user_registered, false );
}

/**
 * Convert a BuddyBoss UTC last-active timestamp to Eastern Time.
 *
 * @param mixed  $value  BuddyBoss date_recorded value.
 * @param string $format PHP date format.
 * @return string
 */
function cen_ear_format_buddyboss_last_active( $value, $format = 'n/j/Y g:i A' ) {
	$value  = trim( (string) $value );
	$date   = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new DateTimeZone( 'UTC' ) );
	$errors = DateTimeImmutable::getLastErrors();
	if ( false === $date || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ) {
		return '';
	}

	return $date->setTimezone( new DateTimeZone( 'America/New_York' ) )->format( $format );
}

/**
 * Bulk-load BuddyBoss last-active dates for report rows.
 *
 * @param int[] $user_ids WordPress user IDs.
 * @return array<int,string> Dates keyed by user ID.
 */
function cen_ear_get_bulk_buddyboss_last_active( $user_ids ) {
	global $wpdb;

	$user_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $user_ids ) ) ) );
	if ( empty( $user_ids ) || ! function_exists( 'bp_get_user_last_activity' ) ) {
		return array();
	}

	$bp         = function_exists( 'buddypress' ) ? buddypress() : null;
	$table_name = is_object( $bp ) && isset( $bp->members->table_name_last_activity ) ? $bp->members->table_name_last_activity : $wpdb->prefix . 'bp_activity';
	$component  = is_object( $bp ) && isset( $bp->members->id ) ? $bp->members->id : 'members';
	$dates      = array();
	foreach ( array_chunk( $user_ids, 500 ) as $user_id_chunk ) {
		$placeholders = implode( ', ', array_fill( 0, count( $user_id_chunk ), '%d' ) );
		$params       = array_merge( array( $component, 'last_activity' ), $user_id_chunk );
		$sql          = "SELECT user_id, MAX(date_recorded) AS last_active FROM {$table_name}
			WHERE component = %s AND type = %s AND user_id IN ({$placeholders}) GROUP BY user_id";
		$query        = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		foreach ( (array) $wpdb->get_results( $query, ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$user_id = isset( $row['user_id'] ) ? absint( $row['user_id'] ) : 0;
			if ( $user_id && ! empty( $row['last_active'] ) ) {
				$dates[ $user_id ] = cen_ear_format_buddyboss_last_active( $row['last_active'] );
			}
		}
	}

	return $dates;
}

/**
 * Return every user ID with an active entry for the onboarding form.
 *
 * @return int[] WordPress user IDs.
 */
function cen_ear_get_completed_onboarding_user_ids() {
	global $wpdb;

	$form_id = absint( apply_filters( 'cen_ear_onboarding_form_id', CEN_EAR_ONBOARDING_FORM_ID ) );
	if ( ! $form_id || ( ! class_exists( 'GFForms' ) && ! class_exists( 'GFAPI' ) ) ) {
		return array();
	}

	$table_name = class_exists( 'GFFormsModel' ) && is_callable( array( 'GFFormsModel', 'get_entry_table_name' ) )
		? GFFormsModel::get_entry_table_name()
		: $wpdb->prefix . 'gf_entry';
	$sql = $wpdb->prepare(
		"SELECT DISTINCT created_by FROM {$table_name} WHERE form_id = %d AND status = %s AND created_by > 0",
		$form_id,
		'active'
	); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	return array_values( array_unique( array_filter( array_map( 'absint', (array) $wpdb->get_col( $sql ) ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Return users who have submitted the configured Gravity Forms onboarding form.
 *
 * Gravity Forms stores the submitting WordPress user ID in created_by. Only
 * active entries count as completed onboarding; trashed and spam entries do not.
 *
 * @param int[] $user_ids WordPress user IDs.
 * @return array<int,bool> Completion flags keyed by user ID.
 */
function cen_ear_get_bulk_completed_onboarding( $user_ids ) {
	global $wpdb;

	$user_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $user_ids ) ) ) );
	$form_id  = absint( apply_filters( 'cen_ear_onboarding_form_id', CEN_EAR_ONBOARDING_FORM_ID ) );
	if ( empty( $user_ids ) || ! $form_id || ( ! class_exists( 'GFForms' ) && ! class_exists( 'GFAPI' ) ) ) {
		return array();
	}

	$table_name = class_exists( 'GFFormsModel' ) && is_callable( array( 'GFFormsModel', 'get_entry_table_name' ) )
		? GFFormsModel::get_entry_table_name()
		: $wpdb->prefix . 'gf_entry';
	$completed  = array();
	foreach ( array_chunk( $user_ids, 500 ) as $user_id_chunk ) {
		$placeholders = implode( ', ', array_fill( 0, count( $user_id_chunk ), '%d' ) );
		$params       = array_merge( array( $form_id, 'active' ), $user_id_chunk );
		$sql          = "SELECT DISTINCT created_by FROM {$table_name} WHERE form_id = %d AND status = %s AND created_by IN ({$placeholders})";
		$query        = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		foreach ( (array) $wpdb->get_col( $query ) as $user_id ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$user_id = absint( $user_id );
			if ( $user_id ) {
				$completed[ $user_id ] = true;
			}
		}
	}

	return $completed;
}

/**
 * Format a WooCommerce Memberships end date without leading zeros.
 *
 * Unlimited memberships store 0 and export with a blank expiration.
 *
 * @param mixed $value Stored membership end date.
 * @return string
 */
function cen_ear_format_membership_expiration( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value || '0' === $value ) {
		return '';
	}
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $value, $parts ) ) {
		return '';
	}
	if ( ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
		return '';
	}

	return (int) $parts[2] . '/' . (int) $parts[3] . '/' . $parts[1];
}

/**
 * Bulk-load WooCommerce Memberships plans for report export.
 *
 * @param int[] $user_ids WordPress user IDs.
 * @return array<int,array<int,array{name:string,expiration:string,id:int}>> Memberships keyed by user ID.
 */
function cen_ear_get_bulk_woocommerce_memberships_for_report( $user_ids ) {
	global $wpdb;

	$user_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $user_ids ) ) ) );
	if ( ! function_exists( 'wc_memberships_get_user_memberships' ) ) {
		return array();
	}

	$records_by_user = array();
	foreach ( array_chunk( $user_ids, 500 ) as $user_id_chunk ) {
		$placeholders = implode( ', ', array_fill( 0, count( $user_id_chunk ), '%d' ) );
		$sql = "SELECT memberships.ID AS membership_id, memberships.post_author AS user_id,
			plans.post_title AS plan_name, expiration.meta_value AS end_date
			FROM {$wpdb->posts} memberships
			INNER JOIN {$wpdb->posts} plans ON plans.ID = memberships.post_parent
			LEFT JOIN {$wpdb->postmeta} expiration ON expiration.post_id = memberships.ID AND expiration.meta_key = '_end_date'
			WHERE memberships.post_type = 'wc_user_membership' AND memberships.post_status <> 'trash'
			AND memberships.post_author IN ({$placeholders})
			ORDER BY memberships.post_author ASC, plans.post_title ASC, memberships.ID ASC";
		$query = $wpdb->prepare( $sql, $user_id_chunk ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		foreach ( (array) $wpdb->get_results( $query, ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$user_id       = isset( $row['user_id'] ) ? absint( $row['user_id'] ) : 0;
			$membership_id = isset( $row['membership_id'] ) ? absint( $row['membership_id'] ) : 0;
			$name          = isset( $row['plan_name'] ) ? cen_ear_decode_text( $row['plan_name'] ) : '';
			if ( ! $user_id || ! $membership_id || '' === $name ) {
				continue;
			}
			$records_by_user[ $user_id ][] = array(
				'name'       => $name,
				'expiration' => cen_ear_format_membership_expiration( isset( $row['end_date'] ) ? $row['end_date'] : '' ),
				'id'         => $membership_id,
			);
		}
	}
	foreach ( $records_by_user as $user_id => $records ) {
		usort(
			$records,
			static function ( $left, $right ) {
				$name_order = strnatcasecmp( $left['name'], $right['name'] );

				return 0 !== $name_order ? $name_order : $left['id'] - $right['id'];
			}
		);
		$records_by_user[ $user_id ] = $records;
	}

	return $records_by_user;
}

/**
 * Return all WooCommerce Memberships plans held by a user for report export.
 *
 * @param int $user_id WordPress user ID.
 * @return array<int,array{name:string,expiration:string,id:int}>
 */
function cen_ear_get_woocommerce_memberships_for_report( $user_id ) {
	$user_id         = absint( $user_id );
	$records_by_user = cen_ear_get_bulk_woocommerce_memberships_for_report( array( $user_id ) );

	return isset( $records_by_user[ $user_id ] ) ? $records_by_user[ $user_id ] : array();
}

/**
 * Return the configured BuddyBoss Extended Profile field IDs.
 *
 * @return array{first_name:int,last_name:int,title:int,company:int}
 */
function cen_ear_get_profile_field_ids() {
	return apply_filters(
		'cen_ear_profile_field_ids',
		array(
			'first_name' => CEN_EAR_XPROFILE_FIRST_NAME_FIELD_ID,
			'last_name'  => CEN_EAR_XPROFILE_LAST_NAME_FIELD_ID,
			'title'      => CEN_EAR_XPROFILE_TITLE_FIELD_ID,
			'company'    => CEN_EAR_XPROFILE_COMPANY_FIELD_ID,
		)
	);
}

/**
 * Return the BuddyBoss Extended Profile fields included only in Excel exports.
 *
 * @return array<string,array{id:int,header:string,width:int}>
 */
function cen_ear_get_export_profile_fields() {
	$fields = array(
		'phone' => array( 'id' => CEN_EAR_XPROFILE_PHONE_FIELD_ID, 'header' => 'Phone Number', 'width' => 22, 'format' => 'phone' ),
	);
	foreach ( cen_ear_get_additional_profile_fields() as $key => $profile_field ) {
		$fields[ $key ] = array(
			'id'     => absint( $profile_field['id'] ),
			'header' => $profile_field['label'],
			'width'  => absint( $profile_field['width'] ),
		);
		if ( ! empty( $profile_field['output'] ) ) {
			$fields[ $key ]['output'] = $profile_field['output'];
		}
	}

	return apply_filters(
		'cen_ear_export_xprofile_fields',
		$fields
	);
}

/**
 * Build report rows for the selected WordPress members.
 *
 * BuddyBoss profile values are used when available. WordPress first
 * and last names provide a fallback when Extended Profiles is unavailable or
 * the corresponding profile value is empty.
 *
 * @param int[] $user_ids              WordPress user IDs.
 * @param bool  $include_export_fields Whether to load export-only profile fields.
 * @param int[] $event_ids Event IDs used to derive RSVP status.
 * @return array<int,array<string,string>>
 */
function cen_ear_get_member_report_rows( $user_ids, $include_export_fields = false, $event_ids = array() ) {
	$rows                  = array();
	$user_ids              = array_values( array_unique( array_filter( array_map( 'absint', (array) $user_ids ) ) ) );
	$field_ids             = cen_ear_get_profile_field_ids();
	$export_profile_fields = $include_export_fields ? cen_ear_get_export_profile_fields() : array();
	$xprofile_field_ids    = array_merge( array_values( $field_ids ), array( CEN_EAR_XPROFILE_PHONE_FIELD_ID ) );
	foreach ( $export_profile_fields as $profile_field ) {
		$xprofile_field_ids[] = absint( $profile_field['id'] );
	}
	if ( function_exists( 'cache_users' ) ) {
		cache_users( $user_ids );
	}
	if ( function_exists( 'update_meta_cache' ) ) {
		update_meta_cache( 'user', $user_ids );
	}
	$xprofile_values       = cen_ear_get_bulk_xprofile_fields( $user_ids, $xprofile_field_ids );
	$groups_by_user        = cen_ear_get_bulk_buddyboss_groups( $user_ids );
	$profile_types_by_user = $include_export_fields ? cen_ear_get_bulk_buddyboss_profile_types( $user_ids ) : array();
	$memberships_by_user   = $include_export_fields ? cen_ear_get_bulk_woocommerce_memberships_for_report( $user_ids ) : array();
	$last_active_by_user   = cen_ear_get_bulk_buddyboss_last_active( $user_ids );
	$onboarding_by_user    = $include_export_fields ? cen_ear_get_bulk_completed_onboarding( $user_ids ) : array();
	$get_profile_value     = static function ( $user_id, $field_id ) use ( $xprofile_values ) {
		return isset( $xprofile_values[ $user_id ][ $field_id ] ) ? $xprofile_values[ $user_id ][ $field_id ] : '';
	};
	$event_statuses        = array();
	$event_attendee_fields = array();
	if ( ! empty( $event_ids ) ) {
		$event_records = cen_ear_get_event_attendee_records( $event_ids, array( 'going', 'not-going', 'cancelled' ) );
		if ( ! is_wp_error( $event_records ) ) {
			$event_statuses = $event_records['user_statuses'];
			$event_attendee_fields = isset( $event_records['user_attendee_fields'] ) ? $event_records['user_attendee_fields'] : array();
		}
	}

	foreach ( $user_ids as $user_id ) {
		$user = $user_id ? get_userdata( $user_id ) : false;
		if ( ! $user ) {
			continue;
		}

		$first_name = $get_profile_value( $user_id, $field_ids['first_name'] );
		$last_name  = $get_profile_value( $user_id, $field_ids['last_name'] );
		$first_name = '' !== $first_name ? $first_name : cen_ear_get_user_meta_text( $user_id, 'first_name' );
		$last_name  = '' !== $last_name ? $last_name : cen_ear_get_user_meta_text( $user_id, 'last_name' );

		if ( '' === $first_name && '' === $last_name ) {
			$name_parts = preg_split( '/\s+/', cen_ear_decode_text( $user->display_name ), 2 );
			$first_name = isset( $name_parts[0] ) ? $name_parts[0] : '';
			$last_name  = isset( $name_parts[1] ) ? $name_parts[1] : '';
		}

		$profile_title   = $get_profile_value( $user_id, $field_ids['title'] );
		$profile_company = $get_profile_value( $user_id, $field_ids['company'] );
		$profile_phone   = $get_profile_value( $user_id, CEN_EAR_XPROFILE_PHONE_FIELD_ID );
		$ticket_fields   = isset( $event_attendee_fields[ $user_id ] ) ? $event_attendee_fields[ $user_id ] : array();

		$row = array(
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'title'      => ! empty( $ticket_fields['title'] ) ? $ticket_fields['title'] : $profile_title,
			'company'    => ! empty( $ticket_fields['company'] ) ? $ticket_fields['company'] : $profile_company,
			'email'      => sanitize_email( $user->user_email ),
			'phone'      => ! empty( $ticket_fields['phone'] ) ? $ticket_fields['phone'] : $profile_phone,
			'join_date'  => ! empty( $user->user_registered ) ? (string) mysql2date( 'n/j/Y', $user->user_registered, false ) : '',
			'last_active' => isset( $last_active_by_user[ $user_id ] ) ? $last_active_by_user[ $user_id ] : '',
			'wp_user_id' => (string) $user_id,
			'bb_status'  => cen_ear_get_buddyboss_status( $user_id ),
			'bb_groups'  => isset( $groups_by_user[ $user_id ] ) ? $groups_by_user[ $user_id ] : '',
			'forum_discussion_emails' => cen_ear_get_forum_discussion_email_status( $user_id ),
		);
		if ( ! empty( $event_ids ) ) {
			$row['event_rsvp_status'] = isset( $event_statuses[ $user_id ] ) ? implode( '||', $event_statuses[ $user_id ] ) : '';
		}

		if ( $include_export_fields ) {
			$row['ym_id']           = cen_ear_get_user_meta_text( $user_id, 'ym_id' );
			$row['bb_profile_type'] = isset( $profile_types_by_user[ $user_id ] ) ? $profile_types_by_user[ $user_id ] : '';
			$row['completed_onboarding'] = isset( $onboarding_by_user[ $user_id ] ) ? 'Yes' : 'No';
			foreach ( $export_profile_fields as $key => $profile_field ) {
				if ( 'phone' === $key && ! empty( $ticket_fields['phone'] ) ) {
					$value = $ticket_fields['phone'];
				} else {
					$value = $get_profile_value( $user_id, $profile_field['id'] );
				}
				$row[ $key ] = isset( $profile_field['output'] ) && 'yes_if_not_empty' === $profile_field['output']
					? ( '' !== $value ? 'Yes' : '' )
					: $value;
			}
			$membership_names       = array();
			$membership_expirations = array();
			foreach ( isset( $memberships_by_user[ $user_id ] ) ? $memberships_by_user[ $user_id ] : array() as $membership ) {
				$membership_names[]       = $membership['name'];
				if ( '' !== $membership['expiration'] ) {
					$membership_expirations[] = $membership['expiration'];
				}
			}
			$row['membership_plans']            = implode( '||', $membership_names );
			$row['membership_plan_expirations'] = implode( '||', $membership_expirations );
		}

		$rows[] = $row;
	}

	return $rows;
}

/**
 * Check whether all required PhpSpreadsheet classes are available.
 *
 * @param string[] $classes Required class names.
 * @return bool
 */
function cen_ear_phpspreadsheet_classes_available( $classes ) {
	foreach ( (array) $classes as $class ) {
		if ( ! class_exists( $class ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Detect a partially loaded PhpSpreadsheet namespace before mixing vendors.
 *
 * @return bool
 */
function cen_ear_is_phpspreadsheet_namespace_loaded() {
	$symbols = array(
		'PhpOffice\\PhpSpreadsheet\\Spreadsheet',
		'PhpOffice\\PhpSpreadsheet\\IOFactory',
		'PhpOffice\\PhpSpreadsheet\\Style\\Style',
		'PhpOffice\\PhpSpreadsheet\\IComparable',
	);

	foreach ( $symbols as $symbol ) {
		if ( class_exists( $symbol, false ) || interface_exists( $symbol, false ) || trait_exists( $symbol, false ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Load this plugin's bundled copy of PhpSpreadsheet.
 *
 * @param string[] $required_classes Required class names.
 * @return true|WP_Error
 */
function cen_ear_load_phpspreadsheet_library( $required_classes ) {
	$autoload_path = plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

	if ( ! file_exists( $autoload_path ) ) {
		return new WP_Error(
			'missing_phpspreadsheet',
			__( 'The bundled PhpSpreadsheet library is missing. Reinstall the CFOLC Member Search plugin.', 'member-search' )
		);
	}

	require_once $autoload_path;

	if ( ! cen_ear_phpspreadsheet_classes_available( $required_classes ) ) {
		$message = cen_ear_is_phpspreadsheet_namespace_loaded()
			? __( 'Another plugin has already loaded an incompatible copy of PhpSpreadsheet. Please update or temporarily disable the conflicting plugin before exporting Excel files.', 'member-search' )
			: __( 'The bundled PhpSpreadsheet library could not be loaded. Reinstall the CFOLC Member Search plugin.', 'member-search' );

		return new WP_Error(
			'phpspreadsheet_load_failed',
			$message
		);
	}

	return true;
}

/**
 * Set an Excel cell as text so profile data cannot become a formula.
 *
 * @param object $sheet      PhpSpreadsheet worksheet.
 * @param string $coordinate Cell coordinate.
 * @param string $value      Cell value.
 */
function cen_ear_set_excel_text( $sheet, $coordinate, $value ) {
	$sheet->setCellValueExplicit(
		$coordinate,
		(string) $value,
		\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
	);
}

/**
 * Format a phone number for export while preserving unrecognized input.
 *
 * Ten-digit numbers and eleven-digit numbers beginning with the North American
 * country code 1 use the familiar North American display format. Recognized
 * international numbers use +<country code> <national number>. Incomplete and
 * otherwise unrecognized values are returned unchanged.
 *
 * @param mixed $value Stored phone value.
 * @return string
 */
function cen_ear_format_phone_for_export( $value ) {
	$original = trim( (string) $value );
	$digits   = preg_replace( '/\D+/', '', $original );
	$compact  = preg_replace( '/\s+/', '', $original );

	if ( preg_match( '/[a-z]/i', $original ) ) {
		return $original;
	}

	$international = 0 === strpos( $compact, '+' );
	if ( 0 === strpos( $digits, '011' ) ) {
		$digits        = substr( $digits, 3 );
		$international = true;
	} elseif ( 0 === strpos( $digits, '00' ) ) {
		$digits        = substr( $digits, 2 );
		$international = true;
	}

	if ( 11 === strlen( $digits ) && '1' === $digits[0] ) {
		$digits = substr( $digits, 1 );
	}
	if ( 10 === strlen( $digits ) && preg_match( '/^[2-9]\d{2}[2-9]\d{6}$/', $digits ) ) {
		return sprintf(
			'(%s) %s-%s',
			substr( $digits, 0, 3 ),
			substr( $digits, 3, 3 ),
			substr( $digits, 6, 4 )
		);
	}

	// A number longer than a North American number is also an international
	// candidate when it begins with a recognized calling code.
	$international = $international || strlen( $digits ) > 11;
	if ( ! $international || strlen( $digits ) < 7 || strlen( $digits ) > 15 ) {
		return $original;
	}

	$calling_codes = array_flip(
		explode(
			' ',
			'1 7 20 27 30 31 32 33 34 36 39 40 41 43 44 45 46 47 48 49 51 52 53 54 55 56 57 58 60 61 62 63 64 65 66 81 82 84 86 90 91 92 93 94 95 98 211 212 213 216 218 220 221 222 223 224 225 226 227 228 229 230 231 232 233 234 235 236 237 238 239 240 241 242 243 244 245 246 247 248 249 250 251 252 253 254 255 256 257 258 260 261 262 263 264 265 266 267 268 269 290 291 297 298 299 350 351 352 353 354 355 356 357 358 359 370 371 372 373 374 375 376 377 378 379 380 381 382 383 385 386 387 389 420 421 423 500 501 502 503 504 505 506 507 508 509 590 591 592 593 594 595 596 597 598 599 670 672 673 674 675 676 677 678 679 680 681 682 683 685 686 687 688 689 690 691 692 800 808 850 852 853 855 856 870 878 880 881 882 883 886 888 960 961 962 963 964 965 966 967 968 970 971 972 973 974 975 976 977 979 991'
		)
	);
	$calling_code = '';
	foreach ( array( 1, 2, 3 ) as $length ) {
		$candidate = substr( $digits, 0, $length );
		if ( isset( $calling_codes[ $candidate ] ) ) {
			$calling_code = $candidate;
			break;
		}
	}
	if ( '' === $calling_code || strlen( $digits ) - strlen( $calling_code ) < 4 ) {
		return $original;
	}

	return '+' . $calling_code . ' ' . substr( $digits, strlen( $calling_code ) );
}

/**
 * Set an Excel cell to a numeric US currency value with no decimal places.
 *
 * @param object $sheet      PhpSpreadsheet worksheet.
 * @param string $coordinate Cell coordinate.
 * @param mixed  $value      Stored currency value.
 */
function cen_ear_set_excel_currency( $sheet, $coordinate, $value ) {
	$original    = trim( (string) $value );
	$is_negative = 1 === preg_match( '/^\(.*\)$/', $original );
	$number      = preg_replace( '/[^0-9.\-]/', '', $original );

	if ( $is_negative && false === strpos( $number, '-' ) ) {
		$number = '-' . $number;
	}
	if ( '' === $number || ! is_numeric( $number ) ) {
		cen_ear_set_excel_text( $sheet, $coordinate, $original );
		return;
	}

	$sheet->setCellValueExplicit(
		$coordinate,
		(float) $number,
		\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
	);
	$sheet->getStyle( $coordinate )->getNumberFormat()->setFormatCode( '$#,##0;[Red]-$#,##0' );
}

/**
 * Set an Excel cell to a numeric value with thousands separators and no decimals.
 *
 * @param object $sheet      PhpSpreadsheet worksheet.
 * @param string $coordinate Cell coordinate.
 * @param mixed  $value      Stored numeric value.
 */
function cen_ear_set_excel_integer( $sheet, $coordinate, $value ) {
	$original = trim( (string) $value );
	$number   = preg_replace( '/[^0-9.\-]/', '', $original );

	if ( '' === $number || ! is_numeric( $number ) ) {
		cen_ear_set_excel_text( $sheet, $coordinate, $original );
		return;
	}

	$sheet->setCellValueExplicit(
		$coordinate,
		(float) $number,
		\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
	);
	$sheet->getStyle( $coordinate )->getNumberFormat()->setFormatCode( '#,##0' );
}

/**
 * Set an Excel cell to a whole number without thousands separators.
 *
 * @param object $sheet      PhpSpreadsheet worksheet.
 * @param string $coordinate Cell coordinate.
 * @param mixed  $value      Stored numeric value.
 */
function cen_ear_set_excel_id( $sheet, $coordinate, $value ) {
	$original = trim( (string) $value );

	if ( '' === $original || ! preg_match( '/^\d+$/', $original ) ) {
		cen_ear_set_excel_text( $sheet, $coordinate, $original );
		return;
	}

	$sheet->setCellValueExplicit(
		$coordinate,
		(float) $original,
		\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
	);
	$sheet->getStyle( $coordinate )->getNumberFormat()->setFormatCode( '0' );
}

/**
 * Set an Excel cell to a date value when given a valid month/day/year date.
 *
 * @param object $sheet      PhpSpreadsheet worksheet.
 * @param string $coordinate Cell coordinate.
 * @param mixed  $value      Stored date value.
 */
function cen_ear_set_excel_date( $sheet, $coordinate, $value ) {
	$original = trim( (string) $value );

	if ( ! preg_match( '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $original, $parts ) || ! checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[3] ) ) {
		cen_ear_set_excel_text( $sheet, $coordinate, $original );
		return;
	}

	$date = new DateTimeImmutable( sprintf( '%04d-%02d-%02d', $parts[3], $parts[1], $parts[2] ), new DateTimeZone( 'UTC' ) );
	$sheet->setCellValueExplicit(
		$coordinate,
		\PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel( $date ),
		\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
	);
	$sheet->getStyle( $coordinate )->getNumberFormat()->setFormatCode( 'm/d/yyyy' );
}

/**
 * Set an Excel cell to a sortable date and time value.
 *
 * @param object $sheet      PhpSpreadsheet worksheet.
 * @param string $coordinate Cell coordinate.
 * @param mixed  $value      Stored date and time value.
 */
function cen_ear_set_excel_datetime( $sheet, $coordinate, $value ) {
	$original = trim( (string) $value );
	$date     = DateTimeImmutable::createFromFormat( '!n/j/Y g:i A', $original, new DateTimeZone( 'UTC' ) );
	$errors   = DateTimeImmutable::getLastErrors();

	if ( false === $date || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ) {
		cen_ear_set_excel_text( $sheet, $coordinate, $original );
		return;
	}

	$sheet->setCellValueExplicit(
		$coordinate,
		\PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel( $date ),
		\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
	);
	$sheet->getStyle( $coordinate )->getNumberFormat()->setFormatCode( 'm/d/yyyy h:mm AM/PM' );
}

/**
 * Convert a one-based column number to an Excel column name.
 *
 * @param int $index One-based column number.
 * @return string
 */
function cen_ear_get_excel_column_name( $index ) {
	$index  = absint( $index );
	$column = '';

	while ( $index > 0 ) {
		$index--;
		$column = chr( 65 + ( $index % 26 ) ) . $column;
		$index  = (int) floor( $index / 26 );
	}

	return $column;
}

/**
 * Build and stream the member-search Excel workbook.
 *
 * @param array $members           Matching member or attendee rows.
 * @param bool  $attendance_report Whether to render attendance-report columns.
 */
function cen_ear_stream_member_workbook( $members, $attendance_report = false ) {
	$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
	$spreadsheet->getProperties()
		->setCreator( 'CEN' )
		->setTitle( $attendance_report ? 'Attendance Report' : 'Member Search Report' )
		->setSubject( $attendance_report ? 'Event attendance and registration data' : 'Member profile data matching selected search criteria' );

	$sheet = $spreadsheet->getActiveSheet();
	$sheet->setTitle( 'Sheet1' );
	$sheet->setShowGridlines( false );
	$sheet->freezePane( 'A2' );
	$sheet->getDefaultRowDimension()->setRowHeight( 14 );
	$include_event_rsvp_status = false;
	$include_membership_columns = false;
	foreach ( (array) $members as $member ) {
		if ( array_key_exists( 'event_rsvp_status', (array) $member ) ) {
			$include_event_rsvp_status = true;
		}
		if ( ! $attendance_report && array_key_exists( 'membership_plans', (array) $member ) ) {
			$include_membership_columns = true;
		}
	}
	if ( ! $attendance_report && function_exists( 'wc_memberships_get_user_memberships' ) ) {
		$include_membership_columns = true;
	}

	$export_columns = $attendance_report ? array(
		array( 'header' => 'Attendee Type', 'key' => 'attendee_status', 'width' => 24 ),
		array( 'header' => 'Check in', 'key' => 'check_in_status', 'width' => 14 ),
		array( 'header' => 'Name', 'key' => 'name', 'width' => 30 ),
		array( 'header' => 'Company Name', 'key' => 'company', 'width' => 36 ),
		array( 'header' => 'Job Title', 'key' => 'title', 'width' => 34 ),
		array( 'header' => 'Email', 'key' => 'email', 'width' => 34 ),
		array( 'header' => 'Do you have a question for the speaker?', 'key' => 'speaker_question', 'width' => 48 ),
		array( 'header' => 'Who can we thank for inviting you?', 'key' => 'invited_by', 'width' => 38 ),
		array( 'header' => 'CPE Option', 'key' => 'cpe_option', 'width' => 22 ),
		array( 'header' => 'RSVP Date', 'key' => 'rsvp_date', 'width' => 22, 'format' => 'datetime' ),
		array( 'header' => 'RSVP Status', 'key' => 'event_rsvp_status', 'width' => 20 ),
		array( 'header' => 'Phone Number', 'key' => 'phone', 'width' => 22, 'format' => 'phone' ),
	) : array(
		array( 'header' => 'WP User ID', 'key' => 'wp_user_id', 'width' => 12, 'format' => 'id' ),
		array( 'header' => 'YM ID', 'key' => 'ym_id', 'width' => 14, 'format' => 'id' ),
		array( 'header' => 'First Name', 'key' => 'first_name', 'width' => 20 ),
		array( 'header' => 'Last Name', 'key' => 'last_name', 'width' => 20 ),
		array( 'header' => 'Job Title', 'key' => 'title', 'width' => 34 ),
		array( 'header' => 'Company Name', 'key' => 'company', 'width' => 36 ),
		array( 'header' => 'Email', 'key' => 'email', 'width' => 34 ),
	);
	foreach ( $attendance_report ? array() : cen_ear_get_export_profile_fields() as $key => $profile_field ) {
		$export_columns[] = array(
			'header' => $profile_field['header'],
			'key'    => $key,
			'width'  => $profile_field['width'],
			'format' => isset( $profile_field['format'] ) ? $profile_field['format'] : 'text',
		);
	}
	if ( ! $attendance_report && $include_membership_columns ) {
		$export_columns[] = array( 'header' => 'Membership Plan', 'key' => 'membership_plans', 'width' => 40 );
		$export_columns[] = array( 'header' => 'Membership Plan Expiration', 'key' => 'membership_plan_expirations', 'width' => 28, 'format' => 'date' );
	}
	if ( ! $attendance_report ) {
		$export_columns = array_merge(
		$export_columns,
		array(
			array( 'header' => 'BB Groups', 'key' => 'bb_groups', 'width' => 40, 'wrap' => false ),
			array( 'header' => 'Join Date', 'key' => 'join_date', 'width' => 14, 'format' => 'date' ),
			array( 'header' => 'BB Last Active', 'key' => 'last_active', 'width' => 22, 'format' => 'datetime' ),
			array( 'header' => 'Completed Onboarding', 'key' => 'completed_onboarding', 'width' => 22 ),
			array( 'header' => 'BB Profile Type', 'key' => 'bb_profile_type', 'width' => 24 ),
			array( 'header' => 'BB Status', 'key' => 'bb_status', 'width' => 14 ),
			array( 'header' => 'Forum Discussion Emails', 'key' => 'forum_discussion_emails', 'width' => 26 ),
		)
		);
	}
	if ( ! $attendance_report && $include_event_rsvp_status ) {
		$export_columns[] = array( 'header' => 'RSVP Status', 'key' => 'event_rsvp_status', 'width' => 20 );
	}

	$columns = array();
	foreach ( $export_columns as $index => $export_column ) {
		$columns[] = cen_ear_get_excel_column_name( $index + 1 );
	}
	foreach ( $export_columns as $index => $export_column ) {
		cen_ear_set_excel_text( $sheet, $columns[ $index ] . '1', $export_column['header'] );
		$sheet->getColumnDimension( $columns[ $index ] )->setWidth( $export_column['width'] );
	}
	$last_column = $columns[ count( $export_columns ) - 1 ];

	$row = 2;
	foreach ( $members as $member ) {
		foreach ( $export_columns as $index => $export_column ) {
			$value = isset( $member[ $export_column['key'] ] ) ? $member[ $export_column['key'] ] : '';
			$coordinate = $columns[ $index ] . $row;
			if ( isset( $export_column['format'] ) && 'phone' === $export_column['format'] ) {
				cen_ear_set_excel_text( $sheet, $coordinate, cen_ear_format_phone_for_export( $value ) );
			} elseif ( isset( $export_column['format'] ) && 'currency' === $export_column['format'] ) {
				cen_ear_set_excel_currency( $sheet, $coordinate, $value );
			} elseif ( isset( $export_column['format'] ) && 'integer' === $export_column['format'] ) {
				cen_ear_set_excel_integer( $sheet, $coordinate, $value );
			} elseif ( isset( $export_column['format'] ) && 'id' === $export_column['format'] ) {
				cen_ear_set_excel_id( $sheet, $coordinate, $value );
			} elseif ( isset( $export_column['format'] ) && 'date' === $export_column['format'] ) {
				cen_ear_set_excel_date( $sheet, $coordinate, $value );
			} elseif ( isset( $export_column['format'] ) && 'datetime' === $export_column['format'] ) {
				cen_ear_set_excel_datetime( $sheet, $coordinate, $value );
			} else {
				cen_ear_set_excel_text( $sheet, $coordinate, $value );
			}
		}
		$sheet->getRowDimension( $row )->setRowHeight( 14 );
		$row++;
	}

	$last_row = max( 1, $row - 1 );
	// Keep the final visible report field from also being Excel's terminal used
	// column. Some Excel versions do not paint that final filtered column until
	// its width is manually changed. A hidden populated helper column forces the
	// initial render while leaving the visible report unchanged.
	$render_helper_column = cen_ear_get_excel_column_name( count( $export_columns ) + 1 );
	cen_ear_set_excel_text( $sheet, $render_helper_column . '1', ' ' );
	if ( $last_row > 1 ) {
		cen_ear_set_excel_text( $sheet, $render_helper_column . $last_row, ' ' );
	}
	$sheet->getColumnDimension( $render_helper_column )->setVisible( false );

	$header_style = array(
		'font'      => array(
			'bold'  => true,
			'color' => array( 'rgb' => 'FFFFFF' ),
		),
		'fill'      => array(
			'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
			'startColor' => array( 'rgb' => '1F4E78' ),
		),
		'alignment' => array(
			'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
			'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
			'wrapText'   => true,
		),
	);
	$sheet->getStyle( 'A1:' . $last_column . '1' )->applyFromArray( $header_style );
	// Excel can initially fail to paint the final filtered heading when its
	// wrapped text is constrained to a fixed-height row. Keep this short label
	// on one line so it is visible without requiring a manual column resize.
	$sheet->getStyle( $last_column . '1' )->getAlignment()->setWrapText( false );
	$sheet->getRowDimension( 1 )->setRowHeight( 14 );

	if ( $last_row > 1 ) {
		$sheet->getStyle( 'A2:' . $last_column . $last_row )->getAlignment()
			->setVertical( \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP )
			->setWrapText( true );
		$sheet->getStyle( 'A1:' . $last_column . $last_row )->getBorders()->getAllBorders()
			->setBorderStyle( \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN )
			->getColor()->setRGB( 'D9E2F3' );
	}
	foreach ( $export_columns as $index => $export_column ) {
		if ( isset( $export_column['wrap'] ) && false === $export_column['wrap'] ) {
			$sheet->getStyle( $columns[ $index ] . '1:' . $columns[ $index ] . $last_row )->getAlignment()->setWrapText( false );
		}
	}

	$sheet->setAutoFilter( 'A1:' . $last_column . $last_row );
	$sheet->getPageSetup()
		->setOrientation( \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE )
		->setFitToWidth( 1 )
		->setFitToHeight( 0 );
	$sheet->getPageMargins()->setTop( 0.4 )->setRight( 0.4 )->setBottom( 0.4 )->setLeft( 0.4 );

	if ( headers_sent() ) {
		$spreadsheet->disconnectWorksheets();
		wp_die( esc_html__( 'The Excel file could not be sent because output had already started.', 'member-search' ) );
	}

	nocache_headers();
	header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
	header( 'Content-Disposition: attachment; filename="' . ( $attendance_report ? 'attendance-report-' : 'search-report-' ) . time() . '.xlsx"' );
	header( 'Cache-Control: max-age=0' );

	$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
	$writer->save( 'php://output' );
	$spreadsheet->disconnectWorksheets();
	exit;
}

/**
 * Export all members matching the submitted, validated criteria.
 */
function cen_ear_export_members() {
	if ( ! current_user_can( cen_ear_get_required_capability() ) ) {
		wp_die(
			esc_html__( 'You are not allowed to search or export members.', 'member-search' ),
			esc_html__( 'Access denied', 'member-search' ),
			array( 'response' => 403 )
		);
	}

	check_admin_referer( 'cen_ear_export_members' );

	$event_choices = cen_ear_get_event_choices();
	$attributes    = cen_ear_get_search_attributes( $event_choices );
	$rules         = cen_ear_validate_criteria_rules( cen_ear_get_criteria_rules( $_POST ), $attributes );
	$include_suspended = isset( $_POST['include_suspended'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['include_suspended'] ) );
	if ( is_wp_error( $rules ) ) {
		wp_die(
			esc_html( $rules->get_error_message() ),
			esc_html__( 'Invalid member search', 'member-search' ),
			array( 'back_link' => true )
		);
	}

	$search = cen_ear_query_criteria_rules( $rules, $attributes, 0, $include_suspended );
	if ( is_wp_error( $search ) ) {
		wp_die( esc_html( $search->get_error_message() ) );
	}
	if ( empty( $search['ids'] ) && empty( $search['guests'] ) ) {
		wp_safe_redirect(
			cen_ear_get_admin_page_url(
				array(
					'cen_ear_search'    => 1,
					'criteria'          => $rules,
					'include_suspended' => $include_suspended ? 1 : 0,
				)
			)
		);
		exit;
	}

	$loaded = cen_ear_load_phpspreadsheet_library(
		array(
			'PhpOffice\\PhpSpreadsheet\\Spreadsheet',
			'PhpOffice\\PhpSpreadsheet\\Cell\\DataType',
			'PhpOffice\\PhpSpreadsheet\\Style\\Alignment',
			'PhpOffice\\PhpSpreadsheet\\Style\\Border',
			'PhpOffice\\PhpSpreadsheet\\Style\\Fill',
			'PhpOffice\\PhpSpreadsheet\\Shared\\Date',
			'PhpOffice\\PhpSpreadsheet\\Worksheet\\PageSetup',
			'PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx',
		)
	);
	if ( is_wp_error( $loaded ) ) {
		wp_die( esc_html( $loaded->get_error_message() ) );
	}

	if ( 1 === count( $rules ) && 'attendance_report' === $rules[0]['attribute'] ) {
		$attendance_rows = cen_ear_get_attendance_report_rows( absint( $rules[0]['values'][0] ), $include_suspended );
		if ( is_wp_error( $attendance_rows ) ) {
			wp_die( esc_html( $attendance_rows->get_error_message() ) );
		}
		if ( empty( $attendance_rows ) ) {
			wp_die( esc_html__( 'No attendees were found for this event.', 'member-search' ) );
		}
		cen_ear_stream_member_workbook( $attendance_rows, true );
	}

	$members = array_merge(
		cen_ear_get_member_report_rows( $search['ids'], true, cen_ear_get_criteria_event_ids( $rules, $attributes ) ),
		isset( $search['guests'] ) ? $search['guests'] : array()
	);
	if ( empty( $members ) ) {
		wp_die( esc_html__( 'The matching attendees could not be loaded.', 'member-search' ) );
	}

	cen_ear_stream_member_workbook( $members );
}

/**
 * Render a standard WordPress admin notice.
 *
 * @param string $message Notice text.
 * @param string $type    Notice type: error, warning, success, or info.
 */
function cen_ear_render_notice( $message, $type = 'info' ) {
	$allowed_types = array( 'error', 'warning', 'success', 'info' );
	$type          = in_array( $type, $allowed_types, true ) ? $type : 'info';

	printf(
		'<div class="notice notice-%1$s inline"><p>%2$s</p></div>',
		esc_attr( $type ),
		esc_html( $message )
	);
}
