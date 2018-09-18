<?php
/**
 * Plugin Name: Modification Date Options
 * Plugin URI:
 * Description: A simple plugin to manipulate the post modification date.
 * Version: 1.0
 * Author: Daren Wesolowski
 * Author URI:
 * License:GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Copyright (C) 2018  Daren Wesolowski
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

register_uninstall_hook( __FILE__ , 'modification_date_options_uninstall' );

function modification_date_options_uninstall() {
    delete_post_meta_by_key( '_modification_date_options' );
}

function modification_date_options_init() {
	new ModificationDateOptions();
}

if ( is_admin() ) {
	add_action( 'load-post.php', 'modification_date_options_init' );
}

class ModificationDateOptions {

	function __construct() {
        $post_type = get_current_screen()->post_type;

        if ( $post_type != 'page' && $post_type != 'post' ) return;

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts_and_styles' ) );
		add_action( 'add_meta_boxes', array( $this, 'meta_box' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( $this, 'filter_handler' ), '99', 2 );
	}

    public function enqueue_admin_scripts_and_styles() {
        wp_enqueue_style('modification_date_options_css', plugins_url( 'assets/css/admin.css', __FILE__ ) );
    }

	public function meta_box( $post_type ) {
		add_meta_box( 'silent_update', __( 'Modification Date' ), array( $this, 'render_meta_box_content' ), $post_type, 'side', 'high' );
	}

	public function render_meta_box_content( $post ) {
        if ( $post->post_status == 'auto-draft' ) return;

		wp_nonce_field( 'modification_date_options', 'modification_date_options_nonce' );

		$datef = __( 'M j, Y @ G:i' );
		$date = date_i18n( $datef, the_modified_date( 'U', false, false, false ) );
		$value = get_post_meta( get_the_ID(), '_modification_date_options', true ) ?: 'modified';

        $html = '';
        $html .= '<div id="modificationdatediv">';
            $html .= '<fieldset>';
                $html .= '<legend class="screen-reader-text">Modification Date</legend>';
                    $html .= '<input type="radio" name="modification_date_options" class="modification_date" id="modification_date_modified" value="modified" '.( ( $value === 'modified' ) ? 'checked="checked"' : "" ).'>';
                    $html .= '<label for="modification_date_date_mod">Show as modified</label>';

                    $html .= '<br>';
                    $html .= '<input type="radio" name="modification_date_options" class="modification_date" id="modification_date_unmodified" value="unmodified" '.( ( $value === 'unmodified' ) ? 'checked="checked"' : "" ).'>';
                    $html .= '<label for="modification_date_not_mod">Show as not modified</label>';

                    if ( $post->post_date != $post->post_modified ) {
                        $html .= '<br>';
                        $html .= '<input type="radio" name="modification_date_options" class="modification_date" id="modification_date_existing" value="existing" '.( ( $value === 'existing' ) ? 'checked="checked"' : "" ).'>';
                        $html .= '<label for="modification_date_current_mod">Use existing modified date</label>';
                    }
            $html .= '</fieldset>';
            if ( $post->post_date != $post->post_modified ) {
                $html .= '<p id="modification_date_timestamp" class="modification_date">'.__( 'Modified on:' ).' <b>'.$date.'</b></p>';
            }
        $html .= '</div>';
        echo $html;
	}

	public function filter_handler( $data, $postarr ) {
        if ( isset( $_POST['modification_date_options'] ) && isset( $postarr['post_modified'] ) && isset( $postarr['post_modified_gmt'] ) ) {

            if ( !wp_verify_nonce( $_POST['modification_date_options_nonce'], 'modification_date_options' ) ) return false;
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return false;

            $switch = $_POST['modification_date_options'];

            switch ($switch) {
                case "modified":
                    update_post_meta( $postarr['ID'], '_modification_date_options', 'modified' );
                    break;

                case "unmodified":
                    $data['post_modified'] = $postarr['post_date'];
                    $data['post_modified_gmt'] = $postarr['post_date_gmt'];
                    update_post_meta( $postarr['ID'], '_modification_date_options', 'unmodified' );
                    break;

                case "existing":
                    $data['post_modified'] = $postarr['post_modified'];
                    $data['post_modified_gmt'] = $postarr['post_modified_gmt'];
                    update_post_meta( $postarr['ID'], '_modification_date_options', 'existing' );
                    break;
            }
        }
        return $data;
    }
}
