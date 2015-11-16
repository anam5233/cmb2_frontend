<?php 


// bringing CMB2 form to frontend 
############################################

function love_porinite(){
	$args = array(
		'public'		=> true,
		'label'			=> 'Parinite',
		'taxonomies'	=> array('category', 'post_tag')
	);
	register_post_type('parinite', $args);
}


add_action('cmb2_init', 'front_end_metabox');
function front_end_metabox(){
	$prefix = '_frontend_';
	$cmb = new_cmb2_box(array(
		'id'		=> $prefix.'form',
		'title'		=> __('Post Info', 'sexy'),
		'object_types'	=> array('parinite'),
		'save_fields'  => false,
		
	));

	$cmb->add_field(array(
		'name'			=> __('Name', 'sexy'),
		'id'			=> $prefix.'name',
		'type'			=> 'text',
		// 'repeatable'	=> true
	));

	$cmb->add_field(array(
		'name'			=> __('Email', 'sexy'),
		'id'			=> $prefix.'email',
		'type'			=> 'text_email',
		// 'repeatable'	=> true
	));
	
	$cmb->add_field(array(
		'name'			=> __('Phone Number', 'sexy'),
		'id'			=> $prefix.'phone',
		'type'			=> 'text',
		// 'repeatable'	=> true
	));
	
}
// end of the function front_end_metabox
add_action('cmb2_init', 'love_porinite');


function get_parinite(){
	$metabox_id = '_frontend_form';
	$object_id = 'fake-object-id';
	return cmb2_get_metabox($metabox_id, $object_id);
}

function parinite_shortcode($atts = array()){
	$cmb = get_parinite();
	$post_types = $cmb->prop('object_types');

	$user_id = get_current_user_id();
	$atts = shortcode_atts(array(
		'post_author'	=> $user_id ? $user_id : 1,
		'post_status'	=> 'pending',
		'post_type'		=> reset( $post_types ),
	), $atts, 'cmb-frontend-form');

	foreach ($atts as $key => $value) {
		$cmb->add_hidden_field(array(
			'id'		=> "atts[$key]",
			'type'		=> 'hidden',
			'default'	=> $value,
		));
	}
	$output = '';
	// if error
	if (($error = $cmb->prop('submission_error')) && is_wp_error($error)) {
		$output .='<h3>'. sprintf(__('There was an error in the submission: %s', 'sexy'),'<strong>'.$error-> get_error_message().'</strong>').'</h3>';
	}
	// if succes 
	if(isset($_GET['post_submitted']) && ($post = get_post(absint($_GET['post_submitted'])))){
		$name = get_post_meta($post->ID, '_frontend_name', 1);
		$name = $name ? ' '.$name : '';

		$output .='<h3>'. sprintf(__('Thank you%s, your new post has been submitted and is pending review by a site administrator .', 'sexy'), esc_html( $name )).'</h3>';
	}

	$output .= cmb2_get_metabox_form( $cmb, 'fake-object-id', array(
			'save_button' => __('Submit Post', 'sexy')
		));

	return $output;

}
add_shortcode('cmb-frontend-form', 'parinite_shortcode');

function parinite_handle(){
	if(empty($_POST) || ! isset($_POST['submit-cmb'], $_POST['object_id'])
	){
		return false;
	}
// above line is important speciall [submit-cmb] don't mix it with 
// [submit_cmb] and also [object_id]

	$cmb = get_parinite();
	$post_data = array();


	// get our shortcode
	if(isset($_POST['atts'])){
		foreach ( (array) $_POST['atts'] as $key => $value) {
			$post_data[$key] = sanitize_text_field($value);
		}
		unset( $_POST['atts'] );
	}


	// security nonce
	if(
		! isset($_POST[$cmb->nonce()])
		|| ! wp_verify_nonce($_POST[$cmb->nonce()], $cmb->nonce())
	){
		return $cmb->prop('submission_error', new WP_Error('security_fail', __('security check failed')));
	}


	// check phone number submitted
	if(
		empty($_POST['_frontend_phone'])
	){	
		return $cmb->prop('submission_error', 
			new WP_Error(
				'post_data_missing', __('New post requires a title')
			)
		);
	}

	// phone number is not default title
	if(
		$cmb->get_field('_frontend_phone')->default() == $_POST['_frontend_phone']
	){
		return $cmb->prop(
			'submission_error', new WP_Error(
				'post_data_missing', __('Please enter a post title.')
			)
		);
	}

	// fetch sanitize value
	$sanitize_values = $cmb->get_sanitized_values($_POST);

	$post_data['post_title'] = $sanitize_values['_frontend_phone'];
	unset($sanitize_values['_frontend_phone']);

	$post_data['post_email'] = $sanitize_values['_frontend_email'];
	unset($sanitize_values['_frontend_email']);

	$new_submission_id = wp_insert_post($post_data, true);
	if ( is_wp_error( $new_submission_id ) ) {
		return $cmb->prop( 'submission_error', $new_submission_id );
	}

	unset( $post_data['post_type'] );
	unset( $post_data['post_status'] );

	foreach ($sanitize_values as $key => $value) {
		if(is_array($value)){
			$value = array_filter($value);
			if(! empty($value)){
				update_post_meta($new_submission_id, $key, $value);
			}
			else{
				update_post_meta($new_submission_id, $key, $value);
			}
		}
	}


	wp_redirect(esc_url_raw( add_query_arg('post_submitted', $new_submission_id)));
	exit;
}
add_action('cmb2_after_init', 'parinite_handle');