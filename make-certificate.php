<?php
// #!/opt/bitnami/php/bin/php -q

  // define paths
  define("BASEDIR", "/opt/bitnami/apache2/htdocs/www.dragonsociety.com/htdocs/wp-content/plugins/s2member-certificates");
  define("CERTIFICATE_DIR", "/opt/bitnami/apache2/htdocs/www.dragonsociety.com/htdocs/wp-content/uploads/certificates");
  define("CERTIFICATE_BASE_URL", "/wp-content/uploads/certificates");
  define("MEMBERSHIP_CERTIFICATE_FONT_DIR", BASEDIR."/fonts");
  define("MEMBERSHIP_CERTIFICATE_TEMPLATE_DIR", BASEDIR."/images/templates");

  require(BASEDIR."/fpdf.php");

  // membership levels
  // 0: Free Subscriber
  // 1: Standard Member
  // 2: Premier Member
  // 3: Certified Instructor
  // 4: Platinum Member

  // ./make-certificate.php "Jan Brown" 0 11041 "Never" 

  // Read the command line arguments (if commandline)
  if ($argc > 1) {
    // .e.g. ./make-certificate.php "Grumpy Patrick" 3 1 "2016-03-03" active
    $name = $argv[1];
    $membership_level = $argv[2];
    $member_id = $argv[3];
    $expdate = $argv[4];
  // Read the post vars if not 
  } else {
    // .e.g. http://dev.dragonsociety.com/wp-content/plugins/s2member-certificates/make-certificate.php?
    // name=Snoopy+Patrick&membership_level=4&member_id=1&expdate=2016-01-01
    $name = $_REQUEST['name'];
    $membership_level = $_REQUEST['membership_level'];
    $member_id = $_REQUEST['member_id'];
    $expdate = $_REQUEST['expdate'];
  }

  // Determine template name from membership level
  switch($membership_level) {
    case 1: $certificate['template'] = "DSI-Membership-Certificate-Template.jpg"; break;
    case 2: $certificate['template'] = "DSI-Membership-Premier-Certificate.jpg"; break;
    case 3: $certificate['template'] = "DSI-Membership-Instructor-Certificate.jpg"; break;
    case 4: $certificate['template'] = "DSI-Membership-Instructor-Certificate.jpg"; break;
  }

  // Build associative array with fields for certificate
  $certificate_fields[0]['value'] = $name;
  $certificate_fields[0]['font'] = "papyrus.ttf";
  $certificate_fields[0]['font_size'] = 100;
  $certificate_fields[0]['font_color'] = "#ffffff";
  $certificate_fields[0]['x_offset'] = NULL;
  $certificate_fields[0]['y_offset'] = 730;
  $certificate_fields[0]['horizontal_alignment'] = "center";
  $certificate_fields[0]['vertical_alignment'] = "none";

  $certificate_fields[1]['value'] = $member_id;
  $certificate_fields[1]['font'] = "papyrus.ttf";
  $certificate_fields[1]['font_size'] = 70;
  $certificate_fields[1]['font_color'] = "#ffffff";
  $certificate_fields[1]['x_offset'] = 990;
  $certificate_fields[1]['y_offset'] = 1610;
  $certificate_fields[1]['horizontal_alignment'] = "none";
  $certificate_fields[1]['vertical_alignment'] = "none";

  $certificate_fields[2]['value'] = $expdate;
  $certificate_fields[2]['font'] = "papyrus.ttf";
  $certificate_fields[2]['font_size'] = 70;
  $certificate_fields[2]['font_color'] = "#ffffff";
  $certificate_fields[2]['x_offset'] = 1720;
  $certificate_fields[2]['y_offset'] = 1610;
  $certificate_fields[2]['horizontal_alignment'] = "none";
  $certificate_fields[2]['vertical_alignment'] = "none";

  // Define some variables
  $certificate['filename'] = "member-".$member_id.".jpg";
  $certificate['pdf_filename'] = "member-".$member_id.".pdf";
  $certificate['final'] = CERTIFICATE_DIR."/".$certificate['filename'];
  $certificate['pdf_final'] = CERTIFICATE_DIR."/".$certificate['pdf_filename'];
  $certificate['thumb_final'] = CERTIFICATE_DIR."/thumbs/".$certificate['filename'];
  $certificate['web_url'] = CERTIFICATE_BASE_URL."/".$certificate['filename'];
  $certificate['pdf_url'] = CERTIFICATE_BASE_URL."/".$certificate['pdf_filename'];
  $certificate['thumb_url'] = CERTIFICATE_BASE_URL."/thumbs/".$certificate['filename'];

  // Read in the image to a resource
  $image['filename'] = MEMBERSHIP_CERTIFICATE_TEMPLATE_DIR."/".$certificate['template'];
  $size = getimagesize($image['filename']);
  $image['width'] = $size[0];
  $image['height'] = $size[1];
  $image['im'] = imagecreatefromjpeg($image['filename']);

  function resize_image($file, $w, $h, $crop=FALSE) {
    list($width, $height) = getimagesize($file);
    $r = $width / $height;
    if ($crop) {
        if ($width > $height) {
            $width = ceil($width-($width*abs($r-$w/$h)));
        } else {
            $height = ceil($height-($height*abs($r-$w/$h)));
        }
        $newwidth = $w;
        $newheight = $h;
    } else {
        if ($w/$h > $r) {
            $newwidth = $h*$r;
            $newheight = $h;
        } else {
            $newheight = $w/$r;
            $newwidth = $w;
        }
    }
    $src = imagecreatefromjpeg($file);
    $dst = imagecreatetruecolor($newwidth, $newheight);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

    imagejpeg ($dst, $file, 100);
  }

  function get_image_center_with_text($image, $text, $ttf, $fontsize, $angle=0) {
    // Calculate the center of the image horizontally
    $tb = imagettfbbox($fontsize, $angle, $ttf, $text);
    $dimensions['x'] = ceil(($image['width'] - $tb[2]) / 2); // lower left X coordinate for text

    // Calculate the center of the image vertically
    $dimensions['y'] = ceil(($image['height'] - $tb[3]) / 2); // lower left X coordinate for text

    return $dimensions;
  }

  // Loop through all fields added to the certificate
  for ($i=0; $i < count($certificate_fields); $i++) {
    // Define some local variables to make lines shorter
    $value = $certificate_fields[$i]['value'];
    $font = MEMBERSHIP_CERTIFICATE_FONT_DIR."/".$certificate_fields[$i]['font'];
    $font_size = $certificate_fields[$i]['font_size'];
    $font_color = $certificate_fields[$i]['font_color'];

    // Get the correct font color values for RGB
    $red = hexdec(substr($font_color, 1, 2));
    $green = hexdec(substr($font_color, 3, 2));
    $blue = hexdec(substr($font_color, 5, 2));

    // Allocate color to the certificate image
    $color = imagecolorallocate($image['im'], $red, $green, $blue);

    // Check horizontal alignment of text
    switch ($certificate_fields[$i]['horizontal_alignment']) {
      case "center":
        $dimensions = get_image_center_with_text($image, $value, $font, $font_size, 0);
        $x_offset = $dimensions['x'];
        break; 

      default:
        $x_offset = $certificate_fields[$i]['x_offset'];
        break;
    }

    // Check vertical alignment of text
    switch ($certificate_fields[$i]['vertical_alignment']) {
      case "center":
        if (!is_set($dimensions)) $dimensions = get_image_center_with_text($image, $value, $font, $font_size, 0);
        $y_offset = $dimensions['y'];
        break;

      default:
        $y_offset = $certificate_fields[$i]['y_offset'];
        break;
    }

    // Write field to the certificate image
    imagettftext($image['im'], $font_size, 0, $x_offset, $y_offset, $color, $font, $value);
  }

  // Output image
  imagejpeg ($image['im'], $certificate['final'], 100);  // 100 quality

  // make a pdf
  $pdf = new FPDF('L','in','Letter');
  $pdf->AddPage();
  $pdf->Image($certificate['final'],0,0,11,8.5);
  $pdf->Output($certificate['pdf_final'],'F');

  // Make a thumbnail image
  copy($certificate['final'], $certificate['thumb_final']);
  resize_image($certificate['thumb_final'], 250, 250);
?>
