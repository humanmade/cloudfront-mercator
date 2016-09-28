<?php

/**
 * Plugin Name: CloudFront Mercator Integration
 * Description: For multisite with domain mapping, auto sync the domains to CloudFront
 * Author: Joe Hoyle | Human made
 *
 */

namespace HM\CloudFront\Mercator_Integration;
use Mercator\Mapping;
use WP_Error;
use Exception;

add_action( 'mercator.mapping.created', __NAMESPACE__ . '\\mercator_mapping_created' );
add_action( 'mercator.mapping.updated', __NAMESPACE__ . '\\mercator_mapping_updated', 10, 2 );
add_action( 'mercator.mapping.deleted', __NAMESPACE__ . '\\mercator_mapping_deleted' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/inc/class-cli-command.php';
	\WP_CLI::add_command( 'cloudfront-mercator', __NAMESPACE__ . '\\CLI_Command' );
}
/**
 * When a mapping is added via mercator, we want to add it to the domains list.
 *
 * @param Mercator\Mapping
 */
function mercator_mapping_created( Mapping $mapping ) {
	$domains = get_domains_from_cloudfront();
	$domains = array_merge( $domains, get_domain_with_alternatives( $mapping->get_domain() ) );
	update_domains_on_cloudfront( $domains );
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

	$domains = get_domains_from_cloudfront();
	$domains = array_diff( $domains, get_domain_with_alternatives( $old_mapping->get_domain() ) );
	$domains = array_merge( $domains, get_domain_with_alternatives( $mapping->get_domain() ) );
	update_domains_on_cloudfront( $domains );
}

/**
 * When a mapping is deleted via mercator, we want to remove it from the domains list.
 *
 * @param Mercator\Mapping
 */
function mercator_mapping_deleted( Mapping $mapping ) {

	$domains = get_domains_from_cloudfront();
	$domains = array_diff( $domains, get_domain_with_alternatives( $mapping->get_domain() ) );
	update_domains_on_cloudfront( $domains );
}

/**
 * Get all the domains from the CloudFront Distribution
 * @return string[]|WP_Error
 */
function get_domains_from_cloudfront() {
	$domains = get_cloudfront_distribution_config()['DistributionConfig']['Aliases']['Items'];

	if ( ! $domains ) {
		return array();
	}

	return $domains;
}

function update_domains_on_cloudfront( array $domains ) {
	$distribution = get_cloudfront_distribution_config();
	$config = $distribution['DistributionConfig'];
	$config['Aliases']['Quantity'] = count( $domains );
	$config['Aliases']['Items'] = $domains;
	$condig['CallerReference'] = rand( 1, 100 );

	try {
		get_aws_client()->updateDistribution( array(
			'Id' => CLOUDFRONT_MERCATOR_DISTRIBUTION_ID,
			'DistributionConfig' => $config,
			'IfMatch' => $distribution['ETag'],
		) );
	} catch ( Exception $e ) {
		trigger_error( sprintf( 'Mercator domain failed to be pushed to CloudFront, error %s (%s)', $e->getMessage(), $e->getCode() ), E_USER_WARNING );
	}
}

function get_cloudfront_distribution_config() {
	return get_aws_client()->getDistributionConfig( array(
		'Id' => CLOUDFRONT_MERCATOR_DISTRIBUTION_ID,
	) );
}

function get_aws_client() {
	if ( ! class_exists( 'Aws\CloudFront\CloudFrontClient' ) ) {
		include_once( __DIR__ . '/lib/aws/aws-autoloader.php' );
	}

	$cloudfront = new \Aws\CloudFront\CloudFrontClient( array(
		'version' => 'latest',
		'region'  => CLOUDFRONT_MERCATOR_AWS_REGION,
		'credentials' => array(
			'key' => CLOUDFRONT_MERCATOR_AWS_KEY,
			'secret' => CLOUDFRONT_MERCATOR_AWS_SECRET,
		),
	));

	return $cloudfront;
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
	} else {
		$nowww = $domain;
		$www = 'www.' . $domain;
	}

	return array( $nowww, $www );
}
