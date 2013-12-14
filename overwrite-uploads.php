<?php
/*
Plugin Name: Overwrite Uploads
Description: Lets you choose whether or not Wordpress should overwrite files uploaded to the Media Library
Version:     1.0.2
Author:      Ian Dunn
Author URI:  http://iandunn.name
License:     GPL2
*/

/*  
 * Copyright 2011 Ian Dunn (email : ian@iandunn.name)
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as 
 * published by the Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

if ( basename( $_SERVER['SCRIPT_FILENAME'] ) == basename( __FILE__ ) )
	die( "Access denied." );

define( 'OVUP_NAME',                'Overwrite Uploads' );
define( 'OVUP_REQUIRED_PHP_VERSION', '5' );

if ( ! class_exists( 'overwriteUploads' ) ) {
	/**
	 * A Wordpress plugin that allows the user to override files uploaded to the Media Library
	 * Requires PHP5+ because of various OOP features, pass by reference, etc
	 * Requires Wordpress 3.1+ because the issue in the link referenced in nonUniqueFilename(), WP_Query() meta_query
	 *
	 * @package overwriteUploads
	 * @author  Ian Dunn <ian@iandunn.name>
	 */
	class overwriteUploads {
		// Declare variables and constants
		protected $environmentOK;
		const REQUIRED_WP_VERSION = '3.1';
		const PREFIX              = 'ovup_';
		const DEBUG_MODE          = false;

		/**
		 * Constructor
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function __construct() {
			// Initialize variables
			$this->environmentOK    = $this->checkEnvironment();

			// Register remaining actions and filters
			if ( $this->environmentOK ) {
				add_filter( 'wp_handle_upload_overrides', array( $this, 'addUniqueFilenameCallback' ) );
			}
		}

		/**
		 * Checks whether the system requirements are met
		 * file.php is only loaded by WP when necessary, so we include it to make sure we can always check the flag inside it
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 * @return bool True if system requirements are met, false if not
		 */
		protected function checkEnvironment() {
			require_once( ABSPATH . '/wp-admin/includes/file.php' );
			global $wp_version;
			$environmentOK = true;

			if ( version_compare( $wp_version, self::REQUIRED_WP_VERSION, "<" ) ) {
				wp_die( OVUP_NAME . ' requires <strong>Wordpress ' . self::REQUIRED_WP_VERSION . '</strong> or newer in order to work. Please upgrade if you would like to use this plugin.' );
				$environmentOK = false;
			}

			if ( ! defined( 'OVUP_FILTER_ADDED' ) || OVUP_FILTER_ADDED !== true ) {
				wp_die( OVUP_NAME . ' requires a new filter to be added to Wordpress. If this is a new installation or you recently upgraded Wordpress, please see the installation instructions on <a href="http://wordpress.org/extend/plugins/overwrite-uploads/installation/">the Installation page</a> for information on adding it.' );
				$environmentOK = false;
			}

			return $environmentOK;
		}

		/**
		 * Adds the callback necessary to avoid creating unique filenames
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param mixed $overrides The $overrides passed to wp_handle_upload. Either an array or boolean false if nothing was passed.
		 * @return array
		 */
		public function addUniqueFilenameCallback( $overrides ) {
			$overrides['test_form']                = false;
			$overrides['unique_filename_callback'] = array( $this, 'nonUniqueFilename' );

			return $overrides;
		}

		/**
		 * Returns the filename to be assigned by wp_handle_upload()
		 * This does the same thing that the comparable section of wp_unique_filename() does, except it doesn't postfix a number if the file already exists, which allows files to be overwritten.
		 * Requires WP 3.1 (see link below)
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 * @link   http://core.trac.wordpress.org/ticket/14627 Before WP 3.1 there was a bug where $extension didn't get passed in
		 * @param string $directory The directory the file will be stored in
		 * @param string $name      The name of the file (after being sanitized, etc)
		 * @param string $extension The file extension
		 * @return string The filename (without any postfixed numbers)
		 */
		public function nonUniqueFilename( $directory, $name, $extension ) {
			$filename = $name . strtolower( $extension );
			$this->removeOldAttachments( $filename );

			return $filename;
		}

		/**
		 * Removes the old attachment post and metadata so that there won't be multiple entries in the Media Library
		 *
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param string $filename
		 */
		function removeOldAttachments( $filename ) {
			$metaQueryParams = array(
				array(
					'key'     => '_wp_attached_file',
					'value'   => $filename,
					'compare' => 'LIKE'
				)
			);

			$params = array(
				'numberposts' => - 1,
				'meta_query'  => $metaQueryParams,
				'post_type'   => 'attachment'
			);

			$oldAttachments = get_posts( $params );

			foreach ( $oldAttachments as $post ) {
				setup_postdata( $post ); // @todo - get_the_ID() isn't working
				$oaFilename = get_post_meta( $post->ID, '_wp_attached_file', true );

				if ( basename( $oaFilename ) == $filename )
					wp_delete_attachment( $post->ID, true );
			}
			wp_reset_postdata();
		}
	} // end overwriteUploads
}

/**
 * Prints an error that the required PHP version wasn't met.
 * This has to be defined outside the class because the class can't be called if the required PHP version isn't installed.
 * Writes options to the database
 *
 * @author Ian Dunn <ian@iandunn.name>
 */
function ovup_phpOld() {
	echo '<div id="message" class="error"><p>' . OVUP_NAME . ' requires <strong>PHP ' . OVUP_REQUIRED_PHP_VERSION . '</strong> in order to work. Please ask your web host about upgrading.</p></div>';
}

// Create an instance
if ( is_admin() ) {
	if ( version_compare( PHP_VERSION, OVUP_REQUIRED_PHP_VERSION, '>=' ) ) {
		if ( class_exists( "overwriteUploads" ) )
			$ovup = new overwriteUploads();
	}
	else
		add_action( 'admin_notices', 'ovup_phpOld' );
}

?>