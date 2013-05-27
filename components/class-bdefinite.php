<?php
class bDefinite
{
	public $admin = FALSE; // the admin object
	public $tools = FALSE; // the tools object
	public $version = 1;
	public $id_base = 'bdefinite';
	public $post_type_name = 'bdefinite';
	public $post_meta_key = 'bdefinite';

	// from http://en.wikipedia.org/wiki/Parts_of_speech
	public $default_parts_of_speech = array(
		'noun' => array(
			'name' => 'Noun',
			'description' => 'Any abstract or concrete entity; a person (police officer, Michael), place (coastline, London), thing (necktie, television), idea (happiness), or quality (bravery)',
		),
		'pronoun' => array(
			'name' => 'Pronoun',
			'description' => 'Any substitute for a noun or noun phrase',
		),
		'adjective' => array(
			'name' => 'Adjective',
			'description' => 'Any qualifier of a noun',
		),
		'verb' => array( 
			'name' => 'Verb',
			'description' => 'Any action (walk), occurrence (happen), or state of being (be)',
		),
		'adverb' => array(
			'name' => 'Adverb',
			'description' => 'Any qualifier of an adjective, verb, clause, sentence, or other adverb',
		),
		'preposition' => array(
			'name' => 'Preposition',
			'description' => 'Any establisher of relation and syntactic context',
		),
		'conjunction' => array(
			'name' => 'Conjunction',
			'description' => 'Any syntactic connector',
		),
		'interjection' => array(
			'name' => 'Interjection',
			'description' => 'Any emotional greeting (or "exclamation")',
		),
		'article' => array(
			'name' => 'Article',
			'description' => 'Indicates the type of reference being made by the noun',
		),
	);

	public $meta_defaults = array(
		'word' => '',
		'pronunciation' => '',
		'partofspeech' => 'noun',
	);

	public function __construct()
	{
		$this->plugin_url = untrailingslashit( plugin_dir_url( __FILE__ ) );

		add_action( 'init' , array( $this, 'register_post_type' ), 12 );

		// intercept attempts to save the post so we can update our meta
		add_action( 'save_post', array( $this, 'save_post' ) );

		if ( is_admin() )
		{
			$this->admin();
		}

	} // END __construct

	// a singleton for the admin object
	public function admin()
	{
		if ( ! $this->admin )
		{
			require_once dirname( __FILE__ ) . '/class-bdefinite-admin.php';
			$this->admin = new bDefinite_Admin();
			$this->admin->plugin_url = $this->plugin_url;
		}

		return $this->admin;
	} // END admin

	// a singleton for the tools object
	public function tools()
	{
		if ( ! $this->tools )
		{
			require_once dirname( __FILE__ ) . '/class-bdefinite-tools.php';
			$this->tools = new bDefinite_Tools();
		}

		return $this->tools;
	} // END tools

	// make sure the post ID is valid for a post of this type
	public function sanitize_post_id( $post_id )
	{
		$post = $this->get_post( $post_id );

		if ( ! isset( $post->ID ) )
		{
			return FALSE;
		}

		return $post->ID;
	} // END sanitize_post_id

	// get the post, don't get a revision, make sure it's of our type, return it
	public function get_post( $post_id )
	{
		// revisions are not supported for this post type, but still check for future proofing
		$post_id = ( wp_is_post_revision( $post_id ) ) ? wp_is_post_revision( $post_id ) : $post_id;

		// attempt to get the post
		$post = get_post( $post_id );

		// confirm that the post exists and is usable
		if ( ! isset( $post->post_type ) || $post->post_type != $this->post_type_name )
		{
			return FALSE;
		}

		// return the post if found
		return $post;
	} // END get_post

	// wrapper method for save_post in the admin object, allows lazy loading
	public function save_post( $post_id )
	{
		$this->admin()->save_post( $post_id );
	} // END save_post

	// get the meta, optionally return just one field of the meta
	public function get_meta( $post_id, $field = FALSE )
	{
		$meta = get_post_meta( $post_id, $this->post_meta_key, TRUE );

		// if a field was specified and exists in the meta, return that
		if ( ! empty( $field ) )
		{
			if ( isset( $meta[ $field ] ) )
			{
				return $meta[ $field ];
			}
			else
			{
				return FALSE;
			}
		} // END if

		// default to returning whatever came back from get_post_meta();
		return $meta;
	} // END get_meta

	/**
	 * update post meta from $meta. as part of the update we also
	 * update some taxonomy terms of this post: we set or unset the
	 * 'feature' term in the post's category taxonomy depending on
	 * its checkbox setting in the admin dashboard, and we also
	 * update the post's exertise terms (terms in $expertise_taxonomies)
	 * based on what scriblio's facets class collects.
	 */
	public function update_meta( $post_id , $meta )
	{
		// $meta is typically $_POST[ $this->id_base ], but
		// the following silly scenario must be valid through this method:
		// $this->update_meta( $post_id, $this->get_meta( $post_id ) );

		// $meta needs to be fully sanitized and validated in this method
		// assume people -- even employees -- will try to hack this
		// review WP's methods and best practices for this thoroughly
		// http://codex.wordpress.org/Data_Validation
		// http://codex.wordpress.org/Function_Reference/wp_filter_nohtml_kses

		// make sure we have a valid post_id
		if ( ! $post_id = $this->sanitize_post_id( $post_id ) )
		{
			return;
		}

		// what word are we talking about
		$sanitized_meta['word'] = wp_kses( $meta['word'], array() );
		wp_set_object_terms( $post_id, $sanitized_meta['word'], $this->post_type_name . '_words', FALSE );

		// the pronunciation
		$sanitized_meta['pronunciation'] = wp_kses( $meta['pronunciation'], array() );

		// the part of speech
		$sanitized_meta['partofspeech'] = isset( $this->default_parts_of_speech[ $meta['partofspeech'] ] ) ? $meta['partofspeech'] : $meta_defaults['partofspeech'];
		wp_set_object_terms( $post_id, $sanitized_meta['partofspeech'], $this->post_type_name . '_partsofspeech', FALSE );

		// save the meta
		update_post_meta( $post_id , $this->post_meta_key , $sanitized_meta );

		// updating the post title is a pain in the ass, just look at what happens when we try to save it
		$post = (object) array();
		$post->ID = $post_id;

		// update the title
		$post->post_title = $sanitized_meta['word'];
		$post->post_name = sanitize_title_with_dashes( $sanitized_meta['word'] . '-' . $sanitized_meta['partofspeech'] );

		// remove the save post action and revision support
		remove_post_type_support(  $this->post_type_name , 'revisions' );
		remove_action( 'save_post', array( bdefinite(), 'save_post' ) ); // not using '$this' because it's ambiguous when this is called within a child class

		// update the post
		wp_update_post( $post );

		// add back the save post action and revision support
		add_action( 'save_post', array( bdefinite(), 'save_post' ) );
		add_post_type_support( $this->post_type_name , 'revisions' );

	} // END update_meta

	// register this post type, as well as any taxonomies that go with it
	public function register_post_type()
	{

		register_taxonomy( $this->post_type_name . '_words', $this->post_type_name, array(
			'label' => 'Words',
			'labels' => array(
				'singular_name' => 'Word',
				'menu_name' => 'Words',
				'all_items' => 'All words',
				'edit_item' => 'Edit word',
				'view_item' => 'View word',
				'update_item' => 'View word',
				'add_new_item' => 'Add word',
				'new_item_name' => 'New word',
				'search_items' => 'Search words',
				'popular_items' => 'Popular words',
				'separate_items_with_commas' => 'Separate words with commas',
				'add_or_remove_items' => 'Add or remove words',
				'choose_from_most_used' => 'Choose from most used words',
				'not_found' => 'No words found',
			),
			'show_ui' => FALSE,
			'show_admin_column' => TRUE,
			'query_var' => TRUE,
			'rewrite' => array(
				'slug' => 'word',
				'with_front' => FALSE,
			),
		));

		register_taxonomy( $this->post_type_name . '_partsofspeech', $this->post_type_name, array(
			'label' => 'Parts of Speech',
			'labels' => array(
				'singular_name' => 'Part of Speech',
				'menu_name' => 'Parts of Speech',
				'all_items' => 'All parts of speech',
				'edit_item' => 'Edit part of speech',
				'view_item' => 'View part of speech',
				'update_item' => 'View part of speech',
				'add_new_item' => 'Add part of speech',
				'new_item_name' => 'New part of speech',
				'search_items' => 'Search parts of speech',
				'popular_items' => 'Popular parts of speech',
				'separate_items_with_commas' => 'Separate parts of speech with commas',
				'add_or_remove_items' => 'Add or remove parts of speech',
				'choose_from_most_used' => 'Choose from most used parts of speech',
				'not_found' => 'No parts of speech found',
			),
			'show_ui' => FALSE,
			'show_admin_column' => TRUE,
			'query_var' => TRUE,
			'rewrite' => array(
				'slug' => 'partofspeech',
				'with_front' => FALSE,
			),
		));

		// register the damn post type already
		register_post_type( 
			$this->post_type_name,
			array(
				'labels' => array(
					'name' => 'Definitions',
					'singular_name' => 'Definition',
					'add_new' => 'Add New',
					'add_new_item' => 'Add New Definition',
					'edit_item' => 'Edit Definition',
					'new_item' => 'New Definition',
					'all_items' => 'All Definitions',
					'view_item' => 'View Definitions',
					'search_items' => 'Search Definitions',
					'not_found' =>  'No definitions found',
					'not_found_in_trash' => 'No definitions found in Trash',
					'parent_item_colon' => '',
					'menu_name' => 'Definitions',
				),
				'supports' => array(
					'title',
					'editor',
					'revisions',
				),
				'public' => TRUE,
				'has_archive' => 'definitions',
				'rewrite' => array(
					'slug' => 'define',
					'with_front' => FALSE,
				),
				'register_meta_box_cb' => array( $this, 'metaboxes' ),
				'public' => TRUE,
				'taxonomies' => array(
					$this->post_type_name . '_partsofspeech',
					$this->post_type_name . '_words',
				),
			)
		);
	} // END register_post_type

	// wrapper method for metaboxes in the admin object, allows lazy loading
	public function metaboxes()
	{
		$this->admin()->metaboxes();
	} // END metaboxes

} // END bDefinite class

// Singleton
function bdefinite()
{
	global $bdefinite;

	if ( ! $bdefinite )
	{
		$bdefinite = new bDefinite();
	}

	return $bdefinite;
} // END bdefinite