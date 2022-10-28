<?php
/*
Plugin Name: s2Member Membership Certificate Maker Plugin
Plugin URI:
Description: Extends S2Member plugin to generate custom membership certificates upon request.
Author: DSI
Author URI: http://www.dragonsociety.com
Version: 1.0.0
Groups: Membership
*/

  // define paths
  define("MEMBERSHIP_CERTIFICATE_FONT_DIR", plugin_dir_path( __FILE__ )."fonts");
  define("MEMBERSHIP_CERTIFICATE_TEMPLATE_DIR", plugin_dir_path( __FILE__ )."images/templates");
  define("CERTIFICATE_SCRIPT_URL", "https://www.dragonsociety.com/wp-content/plugins/s2member-certificates/make-certificate.php");
  define("WP_UPLOAD_DIR", "/opt/bitnami/apache2/htdocs/www.dragonsociety.com/htdocs/wp-content/uploads/");

//---------------------------------------------------------
// Ajax
//---------------------------------------------------------

  add_action('wp_head', 'pluginname_ajaxurl');
  function pluginname_ajaxurl() {
    // define ajaxurl for frontend
    echo "<script type='text/javascript'>\n";
    echo "var ajaxurl='".admin_url("admin-ajax.php")."';\n";
    echo "</script>\n";
  }

  add_action('wp_head', 'my_action_javascript');
  function my_action_javascript() {
    // make ajax call when button is clicked
    echo "<script type='text/javascript'>\n";
    echo "  jQuery(document).ready(function($) {\n";
    echo "    $('.myajax').click(function(){\n";
    echo "      var data = {\n";
    echo "        action: 'my_action',\n";
    echo "        whatever: 1234\n";
    echo "      };\n";
    echo "      $.post(ajaxurl, data, function(response) {\n";
    echo "        $('#certificate').html(decodeURIComponent((response+'').replace(/\+/g, '%20')));\n";
    echo "      });\n";
    echo "    });\n";
    echo "  });\n";
    echo "</script>\n";
  }

  add_action('wp_ajax_my_action', 'my_action_callback');
  function my_action_callback() {
    // make sure they are entitled to a certificate
    if (S2MEMBER_CURRENT_USER_ACCESS_LEVEL > 0) {
      $member_id = sprintf('%06d', S2MEMBER_CURRENT_USER_ID);
      $eot = s2member_eot();
      if ($eot['type'] != "") {
        $expdate = date("m/d/Y", strtotime($eot['type']));
      } else {
        $expdate = "Never";
      }

      //$name = S2MEMBER_CURRENT_USER_DISPLAY_NAME;
      $name = urlencode(S2MEMBER_CURRENT_USER_FIRST_NAME)."+".urlencode(S2MEMBER_CURRENT_USER_LAST_NAME);

      // run the external script to make the certificate
      $URL  = CERTIFICATE_SCRIPT_URL."?name=".$name;
      $URL .= "&membership_level=".S2MEMBER_CURRENT_USER_ACCESS_LEVEL;
      $URL .= "&member_id=".$member_id;
      $URL .= "&expdate=".urlencode($expdate);
      $URL .= "&r=".rand();
      //echo "<script>alert(\"$URL\");</script>";
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $URL);
      curl_setopt($ch, CURLOPT_HEADER, 0);

      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

      //curl_exec($ch);
      if( ! $result = curl_exec($ch)) {
        trigger_error(curl_error($ch));
      } else {
      }
      curl_close($ch);

      // Get the URLs
      $certificate = generate_certificate_urls($member_id);

      // Show the image and the link to its PDF
      $link  = "";
      $link .= "<a href='".$certificate['pdf_url']."?r=".rand()."' target='_new'><img src='".$certificate['web_url']."?lastmod=".rand()."' ";
      $link .= "title='Click to view PDF' border=0 style='border:1px solid black'></a>";
      echo urlencode($link);
    }
    exit(); // this is required to return a proper result & exit is faster than die();
  }


//---------------------------------------------------------
// Hooks & Filters
//---------------------------------------------------------

  function wpse_load_plugin_css() {
    // load plugin stylesheet
    //wp_enqueue_style('membership-certificate', plugin_dir_url( __FILE__ ).'../css/main.css');
    wp_enqueue_style('membership-certificate', plugin_dir_url( __FILE__ ).'css/main.css');
  }
  add_action('wp_enqueue_scripts', 'wpse_load_plugin_css');

  // Add the Certificate to the WooCommerce My Account page
  //add_action( 'woocommerce_after_my_account', 'woocommerce_my_account_membership_certificate', 10, 0 );

  function woocommerce_my_account_membership_certificate() {
    $OUT  = "";
    $OUT .= "<div class='wd_mymembership'>\n";
    $OUT .= "<h2 class='my-account-title'>My Membership</h2>\n";
    $OUT .= "</div>\n";
    $OUT .= "<b>Member Name:</b> ".S2MEMBER_CURRENT_USER_FIRST_NAME." ".S2MEMBER_CURRENT_USER_LAST_NAME."<br>\n";
    $OUT .= "<b>Member ID:</b> ".sprintf('%06d', S2MEMBER_CURRENT_USER_ID)."<br>\n";
    $OUT .= "<b>Membership Type:</b> ".S2MEMBER_CURRENT_USER_ACCESS_LABEL."<br>\n";
    $OUT .= "<b>Expiration:</b> ".do_shortcode("[s2Eot date_format='M jS, Y' empty_format='Never'/]")."<br>\n";
    $OUT .= "<br>\n";
    // make sure they are entitled to a certificate
    if (S2MEMBER_CURRENT_USER_ACCESS_LEVEL > 0) {
      $OUT .= do_shortcode("[membership_certificate]");
    }
    $OUT .= "<br><br>";
    echo $OUT;
  }



//---------------------------------------------------------
// Shortcodes
//---------------------------------------------------------

  //[membership_certificate]
  function show_membership_certificate( $atts ) {
    //if(SwpmMemberUtils::is_member_logged_in()) {
      // Make sure this is a level where we generate a certificate
      if (S2MEMBER_CURRENT_USER_ACCESS_LEVEL > 0) {
        // Get urls for certificate
        $certificate = generate_certificate_urls(S2MEMBER_CURRENT_USER_ID);
        // If membership certificate exists, show it and a link to re-generate it
        if (file_exists($certificate['final'])) {
          $OUT = "<div id='certificate'><a href='".$certificate['pdf_url']."' target='_new'><img src='".$certificate['web_url']."' title='Click to view PDF' border=0 style='border: 1px solid black'></a></div>";
          $OUT .= "<br><a class='myajax'><button>Generate Certificate</button></a>";
          return $OUT;
        // Otherwise, if certificate does not exist, just show a link to generate one
        } else {
          $OUT = "<div id='certificate'></div>\n";
          $OUT .= "<br><a class='myajax'><button>Generate Certificate</button></a>";
          return $OUT;
        }
      } else {
        return "You must upgrade your membership in order to generate a certificate.";
      }
    //}
  }
  add_shortcode('membership_certificate', 'show_membership_certificate');


//---------------------------------------------------------
// Plugin initialization
//---------------------------------------------------------

  add_action('init', 'simple_membership_certificate_maker_init');
  function simple_membership_certificate_maker_init() {
    // Ensure certificates directory exists in /wp-content
    // If not, create it
    $upload_dir = wp_upload_dir();
    $certificates_dir = $upload_dir['basedir']."/certificates";
    $certificates_thumb_dir = $upload_dir['basedir']."/certificates/thumbs";
    if (!file_exists($certificates_dir)) {
      wp_mkdir_p($certificates_dir);
    }
    if (!file_exists($certificates_thumb_dir)) {
      wp_mkdir_p($certificates_thumb_dir);
    }
  }


//---------------------------------------------------------
// Custom Functions
//---------------------------------------------------------

  function generate_certificate_urls($member_id) {
    $member_id = sprintf('%06d', $member_id);

    // Define the certificates directory
    $certificates_dir = WP_UPLOAD_DIR."/certificates";

    $certificate['filename'] = "member-".$member_id.".jpg";
    $certificate['pdf_filename'] = "member-".$member_id.".pdf";
    $certificate['final'] = $certificates_dir."/".$certificate['filename'];
    $certificate['pdf_final'] = $certificates_dir."/".$certificate['pdf_filename'];
    $certificate['thumb_final'] = $certificates_dir."/thumbs/".$certificate['filename'];
    $certificate['web_url'] = "/wp-content/uploads/certificates/".$certificate['filename'];
    $certificate['pdf_url'] = "/wp-content/uploads/certificates/".$certificate['pdf_filename'];
    $certificate['thumb_url'] = "/wp-content/uploads/certificates/thumbs/".$certificate['filename'];
    return $certificate;
  }
?>
