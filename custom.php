<?php
/*
* Plugin Name: NE Alt Tag Plugin
* Plugin URI: https://github.com/jlopes1283/ne-alt-tags
* Description: This plugin lets you know what images on your site that doesn't have an alt tag assigned. It will also add it to the front end on every page/post it is used on.
* Version: 1.0
* Author: Josh Lopes
* Author URI: http://www.iwebri.com
* License: GPL2
* License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

/*--------------------------------------------------------------------------------------
*
* Gets Attachment ID from URL
*
*--------------------------------------------------------------------------------------*/
function neat_attachment_id( $url ) {
	$home = home_url();
	if(strpos($url, $home) !== false) {
		$attachment_id = 0;
		$dir = wp_upload_dir();
		if ( false !== strpos( $url, $dir['baseurl'] . '/' ) ) {
			$file = basename( $url );
			$query_args = array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'fields'      => 'ids',
				'meta_query'  => array(
					array(
						'value'   => $file,
						'compare' => 'LIKE',
						'key'     => '_wp_attachment_metadata',
					),
				)
			);
			$query = new WP_Query( $query_args );
			if ( $query->have_posts() ) {
				foreach ( $query->posts as $post_id ) {
					$meta = wp_get_attachment_metadata( $post_id );
					$original_file       = basename( $meta['file'] );
					$cropped_image_files = wp_list_pluck( $meta['sizes'], 'file' );
					if ( $original_file === $file || in_array( $file, $cropped_image_files ) ) {
						$attachment_id = $post_id;
						break;
					}
				}
			}
		}
		return $attachment_id;
	}
}

/*--------------------------------------------------------------------------------------
*
* Adds alt tags to images of the post
*
*--------------------------------------------------------------------------------------*/
function neat_alt_tags($content) {
        global $post;
        preg_match_all('/<img (.*?)\/>/', $content, $images);
        if(!is_null($images))
        {	
	        $i = 0;
                foreach($images[0] as $index => $value)
                {
                    preg_match( '@src="([^"]+)"@' , $images[1][$i] , $match );
                    $imid = get_post_meta(neat_attachment_id(str_replace('src=', '', str_replace('"', '', $match[1]))), '_wp_attachment_image_alt', true );
                    
                    if (strpos($images[0][$i], 'alt=""') !== false || strpos($images[0][$i], 'alt=""') !== false || strpos($images[0][$i], "alt=''") !== false) {
                        $pattern ='~(<img.*? alt=")("[^>]*>)~i';
                        $imalt = str_replace('alt=""', 'alt="'.$imid.'"', str_replace('alt=""', 'alt="'.$imid.'"',str_replace("alt=''", "alt='".$imid."'", $images[1][$i])));
                        $content = str_replace($images[0][$index], '<img '.$imalt.' />', $content);
					} elseif (strpos($images[0][$i], 'alt=') == false) {
                        $pattern ='~(<img.*? alt=")("[^>]*>)~i';
                        $imalt = str_replace('src=', 'alt="'.$imid.'" src=', $images[1][$i]);
                        $content = str_replace($images[0][$index], '<img '.$imalt.' />', $content);
					}
					
                    $i++;
                }
        }
        return $content;
}

add_filter('the_content', 'neat_alt_tags', 99999);

/*--------------------------------------------------------------------------------------
*
* Adds alt tags functionality to ACF custom fields
*
*--------------------------------------------------------------------------------------*/
function neat_acf_alt_tags($value, $post_id, $field ) {
    // run the_content filter on all textarea values
    $value = apply_filters('the_content',$value);
    return $value;
}

add_filter('acf/format_value/type=wysiwyg', 'neat_acf_alt_tags', 10, 3);
 
function neat_add_query_vars_filter( $vars ){
  $vars[] = "hide";
  return $vars;
}

add_filter( 'query_vars', 'neat_add_query_vars_filter' );

/*--------------------------------------------------------------------------------------
*
* Distinguishes all images with empty Alt tags
*
*--------------------------------------------------------------------------------------*/
function neat_alt_admin_notice($attachement_id) {
	$aposts = array(  
	    'post_type' => 'attachment',  
	    'post_mime_type' =>array(  
            'jpg|jpeg|jpe' => 'image/jpeg',  
            'gif' => 'image/gif', 
            'svg' => 'image/svg',   
	        'png' => 'image/png',  
	    ),  
	    'post_status' => 'inherit',  
	    'posts_per_page' => -1,  
    );
    $query_img = new WP_Query( $aposts );  
    $post_ids = wp_list_pluck( $query_img->posts, 'ID' );
    $nocount = '';
    $ic = '';
    $style = '';
    $co = array();
    foreach ($post_ids as $pid){
	    $ialt = get_post_meta($pid, '_wp_attachment_image_alt', true );
	    if ( !$ialt ) {
		    $co[] = $pid;
		    $hidden = get_query_var('hide');
		    $hide = '';
			if($hidden == 'all'){
				$hide = '#the-list tr{display:none}';
			}
		    $style .= 'li[data-id="'.$pid.'"]{position:relative;}li[data-id="'.$pid.'"] *{position:relative;z-index:9}li[data-id="'.$pid.'"]:after{background:rgba(220,50,50,.5);content:"";display:block;height:100%;position:absolute;left:0; top:0; width:100%;transition:all .25s ease;z-index:10}li[data-id="'.$pid.'"]:hover:after{background:rgba(220,50,50,.2);z-index:1}#the-list tr#post-'.$pid.'{background: rgba(220,50,50,.25);transition:all .25s ease;display:table-row}#the-list tr#post-'.$pid.':hover{background: rgba(220,50,50,.5)}'.$hide;
	    }
	}
	$ic = count($co);
	if( $ic >= 1 ){
		$full_link = admin_url().'upload.php?mode=list&orderby=alt_text&order=asc&hide=all';
		if ( $ic == 1 ){
			$count = '1 image';
		} else {
			$count = $ic.' images';
		}
		$nocount .= '<div class="notice notice-error">';
		$nocount .= '<p><span style="color:red">You have '.$count.' without an alt tag.</span> <a href="'.$full_link.'">Show</a></p>';
		$nocount .= '</div>';
		$nocount .= '<style>'.$style.'</style>';
	}
	echo $nocount;
	
}
function neat_alt_hook_admin_notice() {
    add_action( 'admin_notices', 'neat_alt_admin_notice' );
}

add_action( 'admin_init', 'neat_alt_hook_admin_notice' );
 
/*--------------------------------------------------------------------------------------
*
* Adds "No Alt Tag" sort function to all attachments
*
*--------------------------------------------------------------------------------------*/
add_filter( 'manage_upload_columns', 'neat_add_column_alt_text' );
add_action( 'manage_media_custom_column', 'neat_column_alt_text', 10, 2 );
add_filter( 'manage_upload_sortable_columns', 'neat_add_column_alt_text_sortable_columns' );
add_filter( 'posts_clauses', 'neat_alt_text_sort_columns_by', 1, 2 );

function neat_add_column_alt_text( $columns ) { 
	$columns['alt_text_column'] = 'Alt Text';
	return $columns;
}

function neat_column_alt_text( $column_name, $media_item ) {
	if ( 'alt_text_column' != $column_name || !wp_attachment_is_image( $media_item ) ) {
		return;
	}
	
	$filesize = get_post_meta( $media_item , '_wp_attachment_image_alt', true );
	echo $filesize;
}

// Make Sortable
function neat_add_column_alt_text_sortable_columns( $columns ) {
	
	$columns[ 'alt_text_column' ] = 'alt_text';
	return $columns;
   
}

// Sort by Alt text
function neat_alt_text_sort_columns_by($pieces, $query ) {  
   
   global $wpdb;
   
   if ( $query->is_main_query() && ( $orderby = $query->get( 'orderby' ) ) ) {
	   
      $order = strtoupper( $query->get( 'order' ) );
      switch( $orderby ) {
	      
         case 'alt_text':
         
            $pieces[ 'join' ] .= " LEFT JOIN $wpdb->postmeta wp_rd ON wp_rd.post_id = {$wpdb->posts}.ID AND wp_rd.meta_key = '_wp_attachment_image_alt'";
            
            $pieces[ 'orderby' ] = "wp_rd.meta_value $order, " . $pieces[ 'orderby' ];
				
         break;
		
      }
	
   }
   return $pieces;
  
}
?>
