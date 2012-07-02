<?php 
/*
Plugin Name: Carbon Voyage
Plugin URI: http://github.com/cernoch/carbonvoyage
Description: Calculates journey's carbon footprint
Version: 0.1
Author: Radomír Černoch
License: MIT
*/



/**
 * Replace the text in the argument with CarbonVoyage code
 */
function carbonvoyage_hook($content) {
	$content = preg_replace('#\[carbon\](.*?)\[/carbon\]#sie',
		'carbonvoyage_code(\'\\1\', $content);', $content);
	return $content;
}
add_filter('the_content',  'carbonvoyage_hook', +10);
add_filter('the_excerpt',  'carbonvoyage_hook',   1);



/**
 * Gives the HTML code for carbon footprint
 */
function carbonvoyage_code($destination) {
	$hash = dechex(rand(1024,4294967295));
	return <<<CARBONVOYAGE_CODE
<a class='carbonfootprint' data-destination='$destination' href='http://www.transportdirect.info/Web2/JourneyPlanning/JourneyEmissionsCompare.aspx'>Calculate the carbon footprint</a>
<!--
http://www.terrapass.com/carbon-footprint-calculator/methodology-popup.html
http://www.transportdirect.info/web2/journeyplanning/journeyemissionscompare.aspx?CurrentLanguage=English
-->
CARBONVOYAGE_CODE;
}


/*function carbonvoyage_sendheaders() {
	header("Access-Control-Allow-Origin: http://heartofgold.endofinternet.org");
}
add_action('send_headers', 'carbonvoyage_sendheaders');*/



/**
 * Request jQuery library
 */
function carbonvoyage_jquery() {
    wp_enqueue_script('jquery');            
}    
add_action('wp_enqueue_scripts', 'carbonvoyage_jquery');



/**
 * Load header files
 */
function carbonvoyage_head() { ?>
<script src="<?php echo plugins_url('carbonvoyage.js', __FILE__); ?>" type="text/javascript"></script>
<link rel="stylesheet" href="<?php echo plugins_url('carbonvoyage.css', __FILE__); ?>" type="text/css" media="all" />
<script type="text/javascript">
carbonVoyageEndPoint = "<?php echo plugins_url('location.php', __FILE__); ?>";
</script>
<?php }
add_action('wp_head', 'carbonvoyage_head');

