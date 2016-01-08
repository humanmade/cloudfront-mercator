<?php

namespace HM\Stack\Custom_Domains;
use WP_CLI_Command;
use WP_CLI;
/**
 * Manage domain syncing to HM Stack
 */
class CLI_Command extends WP_CLI_Command {

	/**
	 * Sync all domains on the network to HM Stack
	 *
	 * @subcommand sync-domains
	 */
	public function sync_domains() {
		global $wpdb;
		$suppress = $wpdb->suppress_errors();
		$mappings = $wpdb->get_col( 'SELECT DISTINCT domain FROM ' . $wpdb->dmtable );
		$wpdb->suppress_errors( $suppress );

		if ( ! $mappings ) {
			WP_CLI::error( 'No mappings found on the network.' );
		}

		$domains = get_domains_from_hm_stack();
		$new_domains = array();
		foreach ( $mappings as $mapping ) {
			$new_domains = array_merge( $new_domains, get_domain_with_alternatives( $mapping ) );
		}

		update_domains_on_hm_stack( array_merge( $domains, $new_domains ) );

		WP_CLI::success( 'Completed domain sync. Added the follwing domains: ' );
		print_r( array_diff( $new_domains, $domains ) );
	}
}
