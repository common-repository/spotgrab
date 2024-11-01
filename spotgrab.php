<?php

/*
Plugin Name: SpotGrab
Plugin URI: http://wordpress.org/extend/plugins/spotgrab/
Description: SpotGrab features videos of rental properties in your area with great maps to help you grab your new place. Find your spot. Grab it.
Version: 1.0.7
Author: Neil Simon
Author URI: http://solidcode.com/
*/

/*
 Copyright 2009 SpotGrab.

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin St, 5th Floor, Boston, MA 02110 USA
*/

// From wordpress271/js/tinymce/plugins/spellchecker/classes/utils
include_once ('JSON.php');

// Constants
define ('SG_PLUGIN_VERSION', 'spotgrab-v1.0.7');
define ('SG_OPTIONS',        'spotgrab_options');
define ('SG_API_URL',        'http://spotgrab.com/api/');
define ('SG_URL',            'http://spotgrab.com/');

function spotgrab_cURL ()
    {
    // Init curl session
    $hCurl = curl_init ();

    // Return response from curl_exec() into variable
    curl_setopt ($hCurl, CURLOPT_RETURNTRANSFER, 1);

    // Max seconds to allow cURL to execute
    curl_setopt ($hCurl, CURLOPT_TIMEOUT, 5);

    // Set the URL
    curl_setopt ($hCurl, CURLOPT_URL, SG_API_URL);

    // Exec the call
    $response = curl_exec ($hCurl);

    // Close the session
    curl_close ($hCurl);

    // Output the results
    return ($response);
    }

function spotgrab_buildSidebar (&$buf)
    {
    $rc = 1;  // Reset to 0 on success

    // Get the spotgrab properties list -- returned as JSON
    if (($response = spotgrab_cURL ()) == FALSE)
        {
        // This can happen when the site is found, but the API is down
        // -- just exit
        printf ("No properties found -- please try later.<br />");
        }

    elseif (strpos ($response, "Not Found"))
        {
        // This can happen when the site is NOT found
        // -- just exit
        printf ("No properties found - please try later.<br />");
        }

    else
        {
        // Instantiate JSON object
        $json_spotgrab = new spotgrab_Moxiecode_JSON ();

        // Decode the returned JSON
        $consolidated = $json_spotgrab->decode ($response);

        // Parse JSON and load sidebar buf
        spotgrab_parse_json ($consolidated, $buf);

        // NULL the object
        $json_spotgrab = 0;

        $rc = 0;
        }

    return ($rc);
    }

function spotgrab_parse_json ($consolidated, &$buf)
    {
    // Get number of properties
    if (($numItems = sizeof ($consolidated)) == 0)
        {
        $buf .= 'No properties found. Please try later.<br />';
        }
    else
        {
        // Get the max number of items to show -- based on admin user configured choice
        $spotgrab_options = get_option (SG_OPTIONS);
        $numPropsToDisplay = $spotgrab_options ['numPropsToDisplay'];

        // If there are LESS (or same) number returned by the api...
        if ($numItems <= $numPropsToDisplay)
            {
            // Use the number returned by the api
            $itemsToShow = $numItems;
            }
        else
            {
            // Use the number configured by the admin user
            $itemsToShow = $numPropsToDisplay;
            }

        $buf .= '<ul>';

        // Display each property
        for ($i = 0; $i < $itemsToShow; $i++)
            {
            $buf .= '<li>';

            $buf .=                $consolidated [$i] ['propertyName']   . '<br />';
            $buf .= 'Beds: '     . $consolidated [$i] ['bedrooms']       . ', ';
            $buf .= 'Baths: '    . $consolidated [$i] ['bathrooms']      . '<br />';
            $buf .= 'Rent: $'    . $consolidated [$i] ['rent']           . ', ';
            $buf .= 'Deposit: $' . $consolidated [$i] ['deposit']        . '<br />';
            $buf .= '<img src="' . $consolidated [$i] ['img'] . '" height="150" width="150"><br /><br />';
//          $buf .=                $consolidated [$i] ['img']            . '<br /><br />';
//          $buf .=                $consolidated [$i] ['propertyID']     . '<br />';
//          $buf .=                $consolidated [$i] ['userID']         . '<br />';
//          $buf .=                $consolidated [$i] ['cityID']         . '<br />';
//          $buf .=                $consolidated [$i] ['rentalPeriodID'] . '<br />';
//          $buf .=                $consolidated [$i] ['propertyTypeID'] . '<br />';
//          $buf .=                $consolidated [$i] ['dateAvailable']  . '<br />';
//          $buf .=                $consolidated [$i] ['costToOwner']    . '<br />';

            $buf .= '</li>';
            }
        $buf .= '</ul>';

        $buf .= '<a href="http://spotgrab.com" target="_blank">Powered by Spotgrab</a>';
        }
    }

function spotgrab_initWidget ()
    {
    // MUST be able to register the widget... else exit
    if (function_exists ('register_sidebar_widget'))
        {
        // Declare function -- called from Wordpress -- during page-loads
        function spotgrab_widget ($args)
            {
            // Load existing options from wp database
            $spotgrab_options = get_option (SG_OPTIONS);

            // Accept param array passed-in from WP ($before_widget, $before_title, CSS styles, etc.)
            extract ($args);

            // Display sidebar title above the about-to-be-rendered spotgrab events table
            echo $before_widget .
                 $before_title  .
                 '<a href="' . SG_URL . '" title="Visit SpotGrab" target="_blank">SpotGrab</a>' .
                 $after_title;

            // Open a plugin-version-tracking DIV tag
            printf ("<div id=\"%s\">", SG_PLUGIN_VERSION);

            $buf = '';

            // Dynamically build the table and display it
            if (spotgrab_buildSidebar ($buf) == 0)
                {
                printf ("%s", $buf);
                }

            // Close the plugin-version-tracking DIV tag
            printf ("</div>");

            echo $after_widget;
            }

        // Register the widget function to be called from Wordpress on each page-load
        register_sidebar_widget ('SpotGrab', 'spotgrab_widget');
        }
    }

function spotgrab_createOptions ()
    {
    // Create the initial options array of keys/values
    $spotgrab_initialOptions = array ('numPropsToDisplay' => '5');

    // Store the initial options to the wp database
    add_option (SG_OPTIONS, $spotgrab_initialOptions);
    }

function spotgrab_deleteOptions ()
    {
    // Remove the options from the wp database
    delete_option (SG_OPTIONS);
    }

function spotgrab_updateSettingsPage ()
    {
    // Load existing options from wp database
    $spotgrab_options = get_option (SG_OPTIONS);

    // Localize all displayed strings
    $spotgrab_enterOptionsStr      = __('Please enter the SpotGrab options:',         'spotgrab');
    $spotgrab_numPropsToDisplayStr = __('Number of properties to display:',           'spotgrab');
    $spotgrab_saveStr              = __('Save',                                       'spotgrab');
    $spotgrab_optionsSavedStr      = __('Options saved successfully.',                'spotgrab');
    $spotgrab_addWidgetStr         = __('Don\'t forget to add SpotGrab as a Widget.', 'spotgrab');
    $spotgrab_show05Str            = __('Show 5 properties in the sidebar.',          'spotgrab');
    $spotgrab_show10Str            = __('Show 10 properties in the sidebar.',         'spotgrab');

    // If data fields contain values...
    if (isset ($_POST ['numPropsToDisplay']))
        {
        // Copy the fields to the persistent wp options array
        if ($_POST ['numPropsToDisplay'] == "10")
            {
            $spotgrab_options ['numPropsToDisplay'] = 10;
            }

        else   // must be 5
            {
            $spotgrab_options ['numPropsToDisplay'] = 5;
            }

        // Store changed options back to wp database
        update_option (SG_OPTIONS, $spotgrab_options);

        // Display update message to user
        echo '<div id="message" class="updated fade"><p>' . $spotgrab_optionsSavedStr . '</p></div>';
        }

    // Initialize data fields for radio buttons
    $show05 = "";
    $show10 = "";

    // Set variable for form to use to show sticky-value for radio button
    if ($spotgrab_options ['numPropsToDisplay'] == 10)
        {
        $show10 = "checked";
        }

    else // must be 5
        {
        $show05 = "checked";
        }

    // Display the options form to the user

    echo
     '<div class="wrap">

      <h3>&nbsp;' . $spotgrab_enterOptionsStr . '</h3>

      <form action="" method="post">

      <table border="0" cellpadding="10">
          <tr>
          <td width="300"><input type="radio" name="numPropsToDisplay" value="5"  ' . $show05  . ' />
          ' . $spotgrab_show05Str . '<br />
                          <input type="radio" name="numPropsToDisplay" value="10" ' . $show10 . ' />
          ' . $spotgrab_show10Str . '</td>
          </tr>
      </table>

      <p>&nbsp;<input type="submit" value="' . $spotgrab_saveStr . '" /></p>

      <p>&nbsp;' . $spotgrab_addWidgetStr . '</p>

      </form>

      </div>';
    }

function spotgrab_addSubmenu ()
    {
    // Define the options for the submenu page
    add_submenu_page ('options-general.php',           // Parent page
                      'spotgrab page',                 // Page title, shown in titlebar
                      'SpotGrab',                      // Menu title
                      10,                              // Access level all
                      __FILE__,                        // This file displays the options page
                      'spotgrab_updateSettingsPage');  // Function that displays options page
    }

// Initialize for localized strings
load_plugin_textdomain ('spotgrab', 'wp-content/plugins/spotgrab');

// Runs only once at activation time
register_activation_hook (__FILE__, 'spotgrab_createOptions');

// Runs only once at deactivation time
register_deactivation_hook (__FILE__, 'spotgrab_deleteOptions');

// Load the widget, show it in the widget control in the admin section
add_action ('plugins_loaded', 'spotgrab_initWidget');

// Add the spotgrab submenu to the Settings page
add_action ('admin_menu', 'spotgrab_addSubmenu');

?>
