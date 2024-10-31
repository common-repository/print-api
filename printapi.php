<?php
/*
Plugin Name:  Print API
Description:  Verkoop je eigen boek of ontwerp met print-on-demand!
Version:      1.0.4
Author:       Print API
Author URI:   https://www.printapi.nl
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program. If not, see <ttp://www.gnu.org/licenses/.
*/

defined( 'ABSPATH' ) or die( 'No direct access allowed.' );

// ----------------------------------------------------------------------------
// The API endpoint used by this plugin is public. However, it is undocumented
// and subject to change without prior notice (only if such a change would not
// break existing unmodified plugin installations, of course).
// ----------------------------------------------------------------------------

define( 'PRINT_API_UPLOAD_BASE_URL', 'https://retail.printapi.nl/api/upload/' );

// ======================
// Shortcode & MCE plugin
// ======================

add_shortcode( 'printapi', 'printapi_shortcode' );

add_action( 'admin_init', 'printapi_action_admin_init' );
add_action( 'wp_enqueue_scripts', 'printapi_action_enqueue_scripts' );

/**
 * Called to output a print button.
 *
 * @param object $upload Upload object from the API endpoint.
 * @param string $cssClasses Optional extra CSS classes.
 *
 * @return string The print button HTML.
 */
function printapi_button_html( $upload, $cssClasses = '' ) {

  $stamp = 'printapi-button--' . $upload->code;

  // Apply custom colors:

  $style = '.' . $stamp . '       .printapi-button__c2a { background-color:' . $upload->primaryColor . ';      }';
  $style .= '.' . $stamp . ':hover .printapi-button__c2a { background-color:' . $upload->primaryHoverColor . '; }';

  // Generate the HTML:

  return '<style>' . $style . '</style>'
		 . ' <div class="printapi-button ' . esc_attr( $stamp ) . ' ' . esc_attr( $cssClasses ) . '">'
		 . '     <a title="' . esc_attr( $upload->title ) . '" href="' . esc_attr( $upload->links->cart ) . '">'
		 . '         <img src="' . esc_attr( $upload->links->thumbnail ) . '" alt="Voorbeeld" class="printapi-button__thumbnail" />'
		 . '         <div class="printapi-button__text">'
		 . '             <span class="printapi-button__title">' . esc_html( $upload->title ) . '</span>'
		 . '             <span class="printapi-button__price">Vanaf € ' . esc_html( $upload->prices->min ) . '</span>'
		 . '             <span class="printapi-button__c2a">Bestel print</span>'
		 . '         </div>'
		 . '     </a>'
		 . ' </div>';
}

/**
 * Called instead of printapi_button_html when an error occurs.
 *
 * @param string $message The error message.
 *
 * @return string The print button error HTML.
 */
function printapi_button_error_html( $message ) {

  return '<div class="printapi-button printapi-button--error">'
		 . '    ' . esc_html( $message )
		 . ' </div>';
}

/**
 * Called to transform a Print API shortcode into HTML.
 *
 * @param array $atts The shortcode attributes.
 * @param string $content The shortcode content if any. Currently ignored.
 */
function printapi_shortcode( $atts, $content = null ) {

  if ( ! isset( $atts['code'] ) ) {
	return;
  }

  // Request the upload data:

  $url      = PRINT_API_UPLOAD_BASE_URL . urlencode( $atts['code'] );
  $response = wp_remote_get( $url );

  // Handle 404, 503, etc.:

  $status = wp_remote_retrieve_response_code( $response );
  if ( $status !== 200 ) {
	return printapi_button_error_html( 'Error (' . $status . ')' );
  }

  // Parse the upload data:

  $upload = json_decode( wp_remote_retrieve_body( $response ) );
  if ( ! $upload ) {
	return printapi_button_error_html( 'Error' );
  }

  // Check if upload has been deleted:

  if ( $upload->state !== 'available' ) {
	return printapi_button_error_html( 'Niet beschikbaar' );
  }

  // Allow title override:

  if ( isset( $atts['title'] ) ) {
	$upload->title = $atts['title'];
  }

  // Allow extra CSS classes:

  $cssClasses = isset( $atts['class'] )
	  ? $atts['class']
	  : '';

  // Show Print API button:

  return printapi_button_html( $upload, $cssClasses );
}

/**
 * Registers the Print API stylesheet.
 */
function printapi_action_enqueue_scripts() {
  wp_enqueue_style( 'printapi-style', plugins_url( 'style.css', __FILE__ ) );
}

/**
 * Registers the Print API TinyMCE extension if applicable.
 */
function printapi_action_admin_init() {
  if ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) ) {
	add_filter( 'mce_external_plugins', 'printapi_filter_mce_external_plugins' );
	add_filter( 'mce_buttons', 'printapi_filter_mce_buttons' );
  }
}

/**
 * Added as a filter by 'printapi_action_admin_init'.
 */
function printapi_filter_mce_external_plugins( $plugins ) {
  $plugins['printapi'] = plugins_url( 'printapi-mce.js', __FILE__ );

  return $plugins;
}

/**
 * Added as a filter by 'printapi_action_admin_init'.
 */
function printapi_filter_mce_buttons( $buttons ) {
  array_push( $buttons, '|', 'printapi' );

  return $buttons;
}

// ==============
// Welcome screen
// ==============

register_activation_hook( __FILE__, 'printapi_activate_welcome_screen' );

add_action( 'admin_init', 'printapi_welcome_screen_redirect' );
add_action( 'admin_menu', 'printapi_welcome_screen_add' );
add_action( 'admin_head', 'printapi_welcome_screen_remove_menu' );

/**
 * Sets a flag to activate the welcome screen.
 */
function printapi_activate_welcome_screen() {
  set_transient( '_printapi_welcome_screen_redirect', true, DAY_IN_SECONDS );
}

/**
 * Redirects to the welcome screen if the flag is active.
 */
function printapi_welcome_screen_redirect() {

  if ( ! get_transient( '_printapi_welcome_screen_redirect' ) ) {
	return;
  }

  delete_transient( '_printapi_welcome_screen_redirect' );

  // Don't show on network or bulk activations:

  if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
	return;
  }

  // Redirect to welcome screen:

  wp_safe_redirect( add_query_arg( array( 'page' => 'printapi-welcome-screen' ), admin_url( 'index.php' ) ) );

}

/**
 * Adds the welcome screen to the dashboard.
 */
function printapi_welcome_screen_add() {
  add_dashboard_page(
	  'Welkom bij Print API',
	  'Welkom bij Print API',
	  'read',
	  'printapi-welcome-screen',
	  'printapi_welcome_screen_content'
  );
}

/**
 * Outputs the welcome screen.
 */
function printapi_welcome_screen_content() {
  ?>
    <div class="wrap">
        <h2>Print API plugin geactiveerd!</h2>
        <p>
            Hallo! Je hebt de Print API plugin succesvol geactiveerd. Je kunt plugin codes vanuit je account nu in
            een handomdraai omzetten naar een bestelknop.
        </p>
        <ol>
            <li>Open <i>Artikelen</i> in je Print API account</li>
            <li>Klik bij een artikel op <i>Plaats op website</i></li>
            <li>Kopieer de plugin code</li>
            <li>Maak of bewerk een pagina of post in WordPress</li>
            <p style="font-weight: bold;">Als je de classic editor gebruikt:</p>
            <ol>
                <li>Klik op <i>Print API</i> boven het tekstvak</li>
                <li>Plak de plugin code in het venster</li>
                <li>Klik op OK</li>
                <li>De plugin voegt nu een shortcode in. Bekijk je pagina om het resultaat te zien!</li>
            </ol>
            <p style="font-weight: bold;">Als je Gutenberg gebruikt:</p>
            <ol>
                <li>In de gutenberg editor druk je op het plusje om een nieuw blok toe te voegen</li>
                <li>Vervolgens klik op je shortcode</li>
                <li>Daarna wordt je gevraagd de shortcode in te vullen. Vul hier het volgende in en
                    vervang JouwCode met de code die je gekopiëerd hebt in stap 3:
                </li>
                <li>
                    <pre>[printapi code=JouwCode]</pre>
                </li>
            </ol>
            <li>Vergeet niet de pagina op te slaan!</li>
        </ol>
        <div class="">
            <p style="font-weight: bold">De Print API knop in de klassieke editor:</p>
            <img src="<?php echo plugins_url( 'usage.png', __FILE__ ) ?>" alt="Usage">
        </div>
        <div class="">
            <p style="font-weight: bold">De shortcode knop in Gutenberg:</p>
            <img style="max-width: 70%" src="<?php echo plugins_url( 'GutenbergUsage.png', __FILE__ ) ?>" alt="Gutenberg">
        </div>


    </div>
  <?php
}

/**
 * Hides the welcome screen from the menu.
 */
function printapi_welcome_screen_remove_menu() {
  remove_submenu_page( 'index.php', 'printapi-welcome-screen' );
}