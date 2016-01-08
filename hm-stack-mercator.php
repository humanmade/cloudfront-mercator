<?php

/**
 * Plugin Name: HM Stack Mercator Integration
 * Description: For multisite with domain mapping, auto sync the domains to HM Stack hosting
 * Author: Joe Hoyle | Human made
 *
 */

namespace HM\Stack\Mercator_Integration;
use Mercator\Mapping;
use WP_Error;

// disable if we don't have access to the current environment's name
if ( ! defined( 'HM_ENV' ) || ! defined( 'HM_STACK_URL' ) || ! HM_STACK_URL ) {
	return;
}

add_action( 'mercator.mapping.created', __NAMESPACE__ . '\\mercator_mapping_created' );
add_action( 'mercator.mapping.updated', __NAMESPACE__ . '\\mercator_mapping_updated', 10, 2 );
add_action( 'mercator.mapping.deleted', __NAMESPACE__ . '\\mercator_mapping_deleted' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/inc/class-cli-command.php';
	\WP_CLI::add_command( 'hm-stack-mercator', __NAMESPACE__ . '\\CLI_Command' );
}
/**
 * When a mapping is added via mercator, we want to add it to the domains list.
 *
 * @param Mercator\Mapping
 */
function mercator_mapping_created( Mapping $mapping ) {
	$domains = get_domains_from_hm_stack();
	$domains = array_merge( $domains, get_domain_with_alternatives( $mapping->get_domain() ) );
	update_domains_on_hm_stack( $domains );
}

/**
 * When a mapping is updated via mercator, we want to add it to the domains list.
 *
 * @param Mercator\Mapping
 */
function mercator_mapping_updated( Mapping $mapping, Mapping $old_mapping ) {
	if ( $mapping->get_domain() === $old_mapping->get_domain() ) {
		return;
	}

	$domains = get_domains_from_hm_stack();
	$domains = array_diff( $domains, get_domain_with_alternatives( $old_mapping->get_domain() ) );
	$domains = array_merge( $domains, get_domain_with_alternatives( $mapping->get_domain() ) );
	update_domains_on_hm_stack( $domains );
}

/**
 * When a mapping is deleted via mercator, we want to remove it from the domains list.
 *
 * @param Mercator\Mapping
 */
function mercator_mapping_deleted( Mapping $mapping ) {

	$domains = get_domains_from_hm_stack();
	$domains = array_diff( $domains, get_domain_with_alternatives( $mapping->get_domain() ) );
	update_domains_on_hm_stack( $domains );
}

/**
 * Get all the domains from the HM Stack Application
 * @return string[]|WP_Error
 */
function get_domains_from_hm_stack() {
	$request = wp_remote_get( get_hm_stack_url(), array(
		'timeout' => 10,
	) );

	if ( is_wp_error( $request ) ) {
		return $request;
	}

	$body = json_decode( wp_remote_retrieve_body( $request ), true );
	return $body['domains'];
}

function update_domains_on_hm_stack( array $domains ) {

	wp_remote_post( get_hm_stack_url(), array(
		'timeout' => 1,
		'body' => array(
			'domains' => array_unique( $domains ),
		),
 	) );
}

function get_hm_stack_url() {
	return esc_url( trailingslashit( HM_STACK_URL ) . 'api/stack/applications/' . HM_ENV );
}

/**
 * Get all the domain with alternatives such as www.
 *
 * @param  string $domain
 * @return string[]
 */
function get_domain_with_alternatives( $domain ) {
	// Grab both WWW and no-WWW
	if ( strpos( $domain, 'www.' ) === 0 ) {
		$www = $domain;
		$nowww = substr( $domain, 4 );
	}
	else {
		$nowww = $domain;
		$www = 'www.' . $domain;
	}

	return array( $nowww, $www );
}