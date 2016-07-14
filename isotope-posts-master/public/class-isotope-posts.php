<?php
/**
 * Isotope Posts.
 *
 * @package   Isotope_Posts
 * @author    Mandi Wise <hello@mandiwise.com>
 * @license   GPL-2.0+
 * @link      http://mandiwise.com
 * @copyright 2014 Mandi Wise
 */



class Isotope_Posts {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   2.0.0
	 *
	 * @var     string
	 */
	const VERSION = '2.1';

	/**
	 * Unique identifier for the plugin.
	 *
	 * @since    2.0.0
	 *
	 * @var      string
	 */
	protected $plugin_slug = 'isotope-posts';

	/**
	 * Instance of this class.
	 *
	 * @since    2.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 *
	 * @since     2.0.0
	 */
	private function __construct() {

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Load public-facing stylesheet.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

      // Initiate the plugin settings class so we can use what's saved in those options.
      require_once( ISO_DIR . '/admin/views/settings.php' );
      $Isotope_Posts_Settings = new Isotope_Posts_Settings();

		// Register the shortcode
		add_action( 'init', array( $this, 'register_shortcode') );

	}

	/**
	 * Return the plugin slug.
	 *
	 * @since    2.0.0
	 *
	 * @return    Plugin slug variable.
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     2.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    2.0.0
	 *
	 * @param    boolean    $network_wide
	 */
	public static function activate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide  ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {
					switch_to_blog( $blog_id );
					self::single_activate();
				}

				restore_current_blog();

			} else {
				self::single_activate();
			}

		} else {
			self::single_activate();
		}

	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    2.0.0
	 *
	 * @param    boolean    $network_wide
	 */
	public static function deactivate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {
					switch_to_blog( $blog_id );
					self::single_deactivate();
				}

				restore_current_blog();

			} else {
				self::single_deactivate();
			}

		} else {
			self::single_deactivate();
		}

	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @since    2.0.0
	 *
	 * @return   array|false    The blog ids, false if no matches.
	 */
	private static function get_blog_ids() {

		global $wpdb;

		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";

		return $wpdb->get_col( $sql );

	}

	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since    2.0.0
	 */
	private static function single_activate() {
		// Option needs to be initially added here to fix a bug that should be patched in WP 4.0
		add_option( 'isotope_options' );
	}

	/**
	 * Fired for each blog when the plugin is deactivated.
	 *
	 * @since    2.0.0
	 */
	private static function single_deactivate() {
		// @TODO: Define deactivation functionality here
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    2.0.0
	 */
	public function load_plugin_textdomain() {

		$domain = $this->plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, FALSE, basename( plugin_dir_path( dirname( __FILE__ ) ) ) . '/languages/' );

	}

	/**
	 * Register and enqueue public-facing style sheet.
	 *
	 * @since    2.0.0
	 */
	public function enqueue_styles() {

		wp_register_style( $this->plugin_slug . '-plugin-styles', plugins_url( 'assets/css/public.css', __FILE__ ), array(), self::VERSION );

		global $post;

		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'isotope-posts') ) {
			wp_enqueue_style( $this->plugin_slug . '-plugin-styles' );
		}
	}

	/**
	 * Create the Isotope loop of posts and enqueue the scripts.
	 *
	 * @since    2.0.0
	 */
	public function isotope_loop( $atts ) {

      extract( shortcode_atts( array(
         'id' => '',
			'load_css' => 'false',
      ), $atts ) );

      /*
       * Grab the stored options.
       */

      // Get Isotope opptions by loop id.
      $loop_id = isotope_option( $id );
		$shortcode_id = $loop_id['shortcode_id'];

      // Set the post type to display with Isotope.
      $post_type = $loop_id['post_type'];

      // Set the taxonomy and terms to limit what posts are displayed, if desired.
		$limit_posts = $loop_id['limit_posts'];
      $limit_by = !empty( $loop_id['limit_by'] ) ? $loop_id['limit_by'] : null;
      $limit_term = !empty( $loop_id['limit_term'] ) ? $loop_id['limit_term'] : null;

      // Set the filter menu options.
		$filter_menu = $loop_id['filter_menu'];
      $filter_by = !empty( $loop_id['filter_by'] ) ? $loop_id['filter_by'] : null;

      // Set pagination options for the post loop.
      $pagination = $loop_id['pagination'];
      $posts_per_page = ( $pagination == 'yes' && $loop_id['posts_per_page'] != 0 ) ? absint( $loop_id['posts_per_page'] ) : -1;
      $finished_message = !empty( $loop_id['finished_message'] ) ? $loop_id['finished_message'] : '';

      // Set layout options for the posts.
		$layout = $loop_id['layout'];
		$sort_by = $loop_id['sort_by'];
      $order = ( $sort_by == 'date' ) ? 'DESC' : 'ASC';

      // Get the current page url.
      $page_url = get_permalink();

      // Enqueue and localize the Isotope styles and scripts
		if ( $load_css == 'true' ) {
			wp_enqueue_style( $this->plugin_slug . '-plugin-styles' );
		}

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( $this->plugin_slug . '-isotope-script', plugins_url( 'assets/js/isotope.pkgd.min.js', __FILE__ ), array(), '2.0.0' );
		wp_enqueue_script( $this->plugin_slug . '-imagesloaded-script', plugins_url( 'assets/js/imagesloaded.pkgd.min.js', __FILE__ ), array( 'jquery' ), '3.1.8' );
		wp_enqueue_script( $this->plugin_slug . '-infinitescroll-script', plugins_url( 'assets/js/jquery.infinitescroll.min.js', __FILE__ ), array( 'jquery' ), '2.0.2' );
		wp_enqueue_script( $this->plugin_slug . '-plugin-script', plugins_url( 'assets/js/public.js', __FILE__ ), array( 'jquery' ), self::VERSION );
		wp_enqueue_script( $this->plugin_slug . '-packery-script', plugins_url( 'assets/js/packery.pkgd.min.js', __FILE__ ), array( 'jquery' ), '2.1.1');
		wp_enqueue_script( $this->plugin_slug . '-packery-layout-script', plugins_url( 'assets/js/packery-mode.pkgd.js', __FILE__ ), array( 'jquery' ), '2.0');
		// "https://npmcdn.com/packery@2.1/dist/packery.pkgd.min.js"
		// wp_enqueue_script( $this->plugin_slug . '-perfectmasonry-script', plugins_url( 'assets/js/jquery.isotope.perfectmasonry.js', __FILE__ ), array(), '1.0' );

		wp_localize_script( $this->plugin_slug . '-plugin-script', 'iso_vars', array(
            'loader_gif' => plugins_url( 'public/assets/images/ajax-loader.gif' , dirname(__FILE__) ),
            'finished_message' => $finished_message,
            'page_url' => $page_url,
            'iso_paginate' => $pagination,
				'iso_layout' => $layout,
			)
		);

      /*
       * Set the WP query args for the post loop.
       */

      if ( get_query_var( 'paged' ) ) {
         $paged = get_query_var( 'paged' );
      } elseif ( get_query_var( 'page' ) ) {
         $paged = get_query_var( 'page' );
      } else {
         $paged = 1;
      }

      // Set the post type and order args.
		$args = array(
			'post_type' => $post_type,
			'post_status' => 'publish', 
         'paged' => $paged,
         'posts_per_page' => $posts_per_page,
         'orderby' => $sort_by,
         'order' => $order,
		);

      // Set the limiting taxonomy args.
		if ( $limit_posts == 'yes' && taxonomy_exists( $limit_by ) ) {
			$limited_terms = explode( ',', $limit_term );
			$args['tax_query'] = array(
				array (
					'taxonomy' => $limit_by,
					'field' => 'slug',
					'terms' => $limited_terms,
					'operator' => 'NOT IN',
				)
			);
		}

		$isoposts = new WP_Query( $args );

      /*
       * Now, generate the loop output.
       */
      ob_start();

      //add new search field
      	$keyID = $_GET['id'];
      	if($keyID!=='' && $keyID!==null){
      		$keySearch = get_the_title($keyID);
      	}else{
      		$keySearch = '';
      	}

      	echo ('<p><input type="text" class="quicksearch" placeholder="Search"/>');
      	echo ('<input tyep="text" class="word-search" style="display:none;" value="');
      	echo (isset($keySearch))?$keySearch:'';
      	echo ('"/>');
		echo ('</p>');

		// Create the filter menu if option selected.
		if ( $filter_menu == 'yes' && taxonomy_exists( $filter_by ) ) {

			// If the menu taxonomy is the same as the limiting taxonomy, the convert the limited term slugs into IDs.
			if ( $filter_by == $limit_by ) {
				$limited_terms = explode( ',', $limit_term );
				$excluded_ids = array();

            foreach( $limited_terms as $term ) {
					$term_id = get_term_by( 'slug', $term, $limit_by )->term_id;
					$excluded_ids[] = $term_id;
				}
				$id_string = implode( ',', $excluded_ids );

			} else {
				$id_string = '';
			}

			// Display the menu if there are terms to display.
			$terms = get_terms( $filter_by, array( 'exclude' => $id_string ) );
			$count = count( $terms );

         if ( $count > 0 ) {
				echo '<ul id="filters" class="button-group">';
				echo '<li><button class="is-checked" href="#" data-filter="*">' . __('See All', 'isotope-posts-locale') . '</button></li>';

            foreach ( $terms as $term ) {
            		$color = $this->get_color($term);
            		$text = $this->get_text_color($term);
					echo '<li><button class="" href="#'. $term->slug .'" data-filter=".'. $term->slug .'" style="background:'.$color.'; color:'.$text.'">' . $term->name . '</button></li>';
				}
				echo '</ul>';
			}

		} // end if filter_menu


		// Start the post loop if the post type exists.
		if ( post_type_exists( $post_type ) && $isoposts->have_posts() ) : ?>
		
		

         <div class="iso-container">
   			<ul class="grid" id="iso-loop" >
   			<?php while ( $isoposts->have_posts() ) : $isoposts->the_post(get_the_ID(), 'public'); ?>
   				<li class="mix grid-item <?php if ( $filter_menu == 'yes' && taxonomy_exists( $filter_by ) ) {
						$terms = get_the_terms( $isoposts->post->ID, $filter_by );
						if ( ! empty( $terms ) ) {
	                  foreach( $terms as $term ) {
	                  	$color = $this->get_color($term);
	                  	$text = $this->get_text_color($term);
	                    echo $term->slug.' ';
	                  }
						}
               } ?>iso-post hvr-glow" style="background:<?php echo $color ?>">
						<?php
							do_action( "before_isotope_title" );
							do_action( "before_isotope_title_{$shortcode_id}" );
						?>
   					<h6 class="iso-title " style="color:<?php echo $text ?>"><?php the_title(); ?></h6> 
						<?php
							do_action( "before_isotope_content" );
							do_action( "before_isotope_content_{$shortcode_id}" );
						?>
   					<?php
   						if ( '' != get_the_post_thumbnail() ) { ?>
   							<div class="iso-thumb">
   								<a href="<?php the_permalink() ?>"><?php the_post_thumbnail(); ?></a>
   							</div>
   						<?php }
   					?>
   					
   					<!--Answer is added -->
   					<?php
   							echo ('<div class="answer" style="display: none;">');
							$comments = get_comments(array('post_id' => get_the_ID(), 'status' => 'approve'));
							// echo get_the_ID();
					        foreach($comments as $comment) {
						        //format comments
						        echo ('Answer: '. $comment->comment_content.'<br/>');
						        echo ('Author: '. $comment->comment_author. '<br/>');
						        
						    }
						    echo ('</div>');
					

						echo ('<div class="leave_comments" style="display: none;">');
							


    					echo ('<button data-popup-open="popup-1" href="#" style="float:left">Add/Update</button>');
    						echo ('<div class="rating" style="display:none; float:right" >');
							echo do_shortcode('[mr_rating_form]');
							echo ('</div>');

						echo ('<div class="popup" data-popup="popup-1" style="z-index:10">');
						echo ('<div class="popup-inner" style="z-index:10">');
						    	// $post_id = get_the_ID();
						    	// // echo $post_id;
						    	// echo get_permalink($post_id);
						$this->test_comment_form();
						echo ('<p><a data-popup-close="popup-1" href="#">Close</a></p>');
						echo ('<a class="popup-close" data-popup-close="popup-1" href="#">x</a>');
						echo ("</div>");
						echo ("</div>");

						echo ('</div>');

						echo ('<div class="social_share" style="display: none; float:right">');
						
						if ( function_exists( 'ADDTOANY_SHARE_SAVE_KIT' ) ) { 
    						ADDTOANY_SHARE_SAVE_KIT( array( 
        					'buttons' => array( 'facebook', 'twitter', 'linkedin' ),
        					'linkurl'  => 'https://www.togethersa.org.au/travel-guide?id='+get_the_ID(),
    					) );}

						echo ('</div>');
					?>
						<?php
							do_action( "after_isotope_content" );
							do_action( "after_isotope_content_{$shortcode_id}" );
						?>
   				</li>
   			<?php endwhile; ?>
   			</ul>
         </div>

         <div class="iso-posts-loading"></div>
         <nav role="navigation" class="iso-pagination">
            <span class="more-iso-posts"><?php echo get_next_posts_link( 'More Posts', $isoposts->max_num_pages ); ?></span>
         </nav>

		<?php
         // Reset the post loop.
			wp_reset_postdata();

			$content = ob_get_contents();
			ob_end_clean();
			return $content;

		else : ?>
			<p>Nothing found. Please check back soon!</p>

		<?php endif; // end post loop

	}

	
   /**
    * Register the shortcode "[isotope-posts]".
    *
    * @since    2.0.0
    */
	public function register_shortcode() {
		add_shortcode( 'isotope-posts', array( $this, 'isotope_loop' ) );
	}

	/**
	* function to return color of the customise tanoxomy
	*/

	public function get_color($term){
		$theCatId = get_term_by( 'slug', $term->slug, 'question_type' );
		$theCatId = $theCatId->term_id;
		$meta = get_term_meta($theCatId,'color',true);
		return $meta;
	}


	/**
	* function to return color of the customise tanoxomy
	*/

	public function get_text_color($term){
		$theCatId = get_term_by( 'slug', $term->slug, 'question_type' );
		$theCatId = $theCatId->term_id;
		$meta = get_term_meta($theCatId,'text',true);
		return $meta;
	}



	//added new comment form

	function test_comment_form( $args = array(), $post_id = null ) {
	if ( null === $post_id )
		$post_id = get_the_ID();

	$commenter = wp_get_current_commenter();
	$user = wp_get_current_user();
	$user_identity = $user->exists() ? $user->display_name : '';

	$args = wp_parse_args( $args );
	if ( ! isset( $args['format'] ) )
		$args['format'] = current_theme_supports( 'html5', 'comment-form' ) ? 'html5' : 'xhtml';

	$req      = get_option( 'require_name_email' );
	$aria_req = ( $req ? " aria-required='true'" : '' );
	$html_req = ( $req ? " required='required'" : '' );
	$html5    = 'html5' === $args['format'];
	$fields   =  array(
		'author' => '<p class="comment-form-author">' . '<label for="author">' . __( 'Name' ) . ( $req ? ' <span class="required">*</span>' : '' ) . '</label> ' .
		            '<input id="author" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) . '" size="30" maxlength="245"' . $aria_req . $html_req . ' required /></p>',
		'email'  => '<p class="comment-form-email"><label for="email">' . __( 'Email' ) . ( $req ? ' <span class="required">*</span>' : '' ) . '</label> ' .
		            '<input id="email" name="email" ' . ( $html5 ? 'type="email"' : 'type="text"' ) . ' value="' . esc_attr(  $commenter['comment_author_email'] ) . '" size="30" maxlength="100" aria-describedby="email-notes"' . $aria_req . $html_req  . ' required /></p>',
		
	); //'url'    => '<p class="comment-form-url"><label for="url">' . __( 'Website' ) . '</label> ' .
		            //'<input id="url" name="url" ' . ( $html5 ? 'type="url"' : 'type="text"' ) . ' value="' . esc_attr( $commenter['comment_author_url'] ) . '" size="30" maxlength="200" /></p>',

	$required_text = sprintf( ' ' . __('Required fields are marked %s'), '<span class="required">*</span>' );

	/**
	 * Filter the default comment form fields.
	 *
	 * @since 3.0.0
	 *
	 * @param array $fields The default comment fields.
	 */
	$fields = apply_filters( 'comment_form_default_fields', $fields );
	$defaults = array(
		'fields'               => $fields,
		'comment_field'        => '<p class="comment-form-comment"><label for="comment">' . _x( 'Answer/Question', 'noun' ) . '</label> <textarea id="comment" name="comment" cols="45" rows="8" maxlength="65525" aria-required="true" required="required"></textarea></p>',
		/** This filter is documented in wp-includes/link-template.php */
		'must_log_in'          => '<p class="must-log-in">' . sprintf(
		                              /* translators: %s: login URL */
		                              __( 'You must be <a href="%s">logged in</a> to post a comment.' ),
		                              wp_login_url( apply_filters( 'the_permalink', get_permalink( $post_id ) ) )
		                          ) . '</p>',
		/** This filter is documented in wp-includes/link-template.php */
		'logged_in_as'         => '<p class="logged-in-as">' . sprintf(
		                              /* translators: 1: edit user link, 2: accessibility text, 3: user name, 4: logout URL */
		                              __( '<a href="%1$s" aria-label="%2$s">Logged in as %3$s</a>. <a href="%4$s">Log out?</a>' ),
		                              get_edit_user_link(),
		                              /* translators: %s: user name */
		                              esc_attr( sprintf( __( 'Logged in as %s. Edit your profile.' ), $user_identity ) ),
		                              $user_identity,
		                              wp_logout_url( apply_filters( 'the_permalink', get_permalink( $post_id ) ) )
		                          ) . '</p>',
		'comment_notes_before' => '<p class="comment-notes"><span id="email-notes">' . __( 'Your email address will not be published.' ) . '</span>'. ( $req ? $required_text : '' ) . '</p>',
		'comment_notes_after'  => '',
		'id_form'              => 'commentform',
		'id_submit'            => 'comment_submit',
		'class_form'           => 'comment-form',
		'class_submit'         => 'submit',
		'name_submit'          => 'submit',
		'title_reply'          => __( 'Leave an answer/question' ),
		'title_reply_to'       => __( 'Leave a Reply to %s' ),
		'title_reply_before'   => '<h3 id="reply-title" class="comment-reply-title">',
		'title_reply_after'    => '</h3>',
		'cancel_reply_before'  => ' <small>',
		'cancel_reply_after'   => '</small>',
		'cancel_reply_link'    => __( 'Cancel reply' ),
		'label_submit'         => __( 'Post' ),
		'submit_button'        => '<input name="%1$s" type="submit" id="%2$s" class="%3$s" value="%4$s" />',
		'submit_field'         => '<p class="form-submit">%1$s %2$s</p>',
		'format'               => 'xhtml',
	);

	/**
	 * Filter the comment form default arguments.
	 *
	 * Use 'comment_form_default_fields' to filter the comment fields.
	 *
	 * @since 3.0.0
	 *
	 * @param array $defaults The default comment form arguments.
	 */
	$args = wp_parse_args( $args, apply_filters( 'comment_form_defaults', $defaults ) );

	// Ensure that the filtered args contain all required default values.
	$args = array_merge( $defaults, $args );

	if ( comments_open( $post_id ) ) : ?>
		<?php
		/**
		 * Fires before the comment form.
		 *
		 * @since 3.0.0
		 */
		do_action( 'comment_form_before' );
		?>
		<div id="respond" class="comment-respond">
			<?php
			echo $args['title_reply_before'];

			comment_form_title( $args['title_reply'], $args['title_reply_to'] );

			echo $args['cancel_reply_before'];

			cancel_comment_reply_link( $args['cancel_reply_link'] );

			echo $args['cancel_reply_after'];

			echo $args['title_reply_after'];

			if ( get_option( 'comment_registration' ) && !is_user_logged_in() ) :
				echo $args['must_log_in'];
				/**
				 * Fires after the HTML-formatted 'must log in after' message in the comment form.
				 *
				 * @since 3.0.0
				 */
				do_action( 'comment_form_must_log_in_after' );
			else : ?> 
				<form action="" method="post" id="<?php echo esc_attr( $args['id_form'] ); ?>" class="<?php echo esc_attr( $args['class_form'] ); ?>"<?php echo $html5 ? ' validate' : ''; ?>>

					<h3 class="thank_you" style="display:none">Thank you for your comment.</h3>
					<?php

					/**
					 * Fires at the top of the comment form, inside the form tag.
					 *
					 * @since 3.0.0
					 */

					do_action( 'comment_form_top' );

					if ( is_user_logged_in() ) :
						/**
						 * Filter the 'logged in' message for the comment form for display.
						 *
						 * @since 3.0.0
						 *
						 * @param string $args_logged_in The logged-in-as HTML-formatted message.
						 * @param array  $commenter      An array containing the comment author's
						 *                               username, email, and URL.
						 * @param string $user_identity  If the commenter is a registered user,
						 *                               the display name, blank otherwise.
						 */
						echo apply_filters( 'comment_form_logged_in', $args['logged_in_as'], $commenter, $user_identity );

						/**
						 * Fires after the is_user_logged_in() check in the comment form.
						 *
						 * @since 3.0.0
						 *
						 * @param array  $commenter     An array containing the comment author's
						 *                              username, email, and URL.
						 * @param string $user_identity If the commenter is a registered user,
						 *                              the display name, blank otherwise.
						 */
						do_action( 'comment_form_logged_in_after', $commenter, $user_identity );

					else :

						echo $args['comment_notes_before'];

					endif;

					// Prepare an array of all fields, including the textarea
					$comment_fields = array( 'comment' => $args['comment_field'] ) + (array) $args['fields'];

					/**
					 * Filter the comment form fields, including the textarea.
					 *
					 * @since 4.4.0
					 *
					 * @param array $comment_fields The comment fields.
					 */
					$comment_fields = apply_filters( 'comment_form_fields', $comment_fields );

					// Get an array of field names, excluding the textarea
					$comment_field_keys = array_diff( array_keys( $comment_fields ), array( 'comment' ) );

					// Get the first and the last field name, excluding the textarea
					$first_field = reset( $comment_field_keys );
					$last_field  = end( $comment_field_keys );

					foreach ( $comment_fields as $name => $field ) {

						if ( 'comment' === $name ) {

							/**
							 * Filter the content of the comment textarea field for display.
							 *
							 * @since 3.0.0
							 *
							 * @param string $args_comment_field The content of the comment textarea field.
							 */
							echo apply_filters( 'comment_form_field_comment', $field );

							echo $args['comment_notes_after'];

						} elseif ( ! is_user_logged_in() ) {

							if ( $first_field === $name ) {
								/**
								 * Fires before the comment fields in the comment form, excluding the textarea.
								 *
								 * @since 3.0.0
								 */
								do_action( 'comment_form_before_fields' );
							}

							/**
							 * Filter a comment form field for display.
							 *
							 * The dynamic portion of the filter hook, `$name`, refers to the name
							 * of the comment form field. Such as 'author', 'email', or 'url'.
							 *
							 * @since 3.0.0
							 *
							 * @param string $field The HTML-formatted output of the comment form field.
							 */
							echo apply_filters( "comment_form_field_{$name}", $field ) . "\n";

							if ( $last_field === $name ) {
								/**
								 * Fires after the comment fields in the comment form, excluding the textarea.
								 *
								 * @since 3.0.0
								 */
								do_action( 'comment_form_after_fields' );
							}
						}
					}

					$submit_button = sprintf(
						$args['submit_button'],
						esc_attr( $args['name_submit'] ),
						esc_attr( $args['id_submit'] ),
						esc_attr( $args['class_submit'] ),
						esc_attr( $args['label_submit'] )
					);

					/**
					 * Filter the submit button for the comment form to display.
					 *
					 * @since 4.2.0
					 *
					 * @param string $submit_button HTML markup for the submit button.
					 * @param array  $args          Arguments passed to `comment_form()`.
					 */
					$submit_button = apply_filters( 'comment_form_submit_button', $submit_button, $args );

					$submit_field = sprintf(
						$args['submit_field'],
						$submit_button,
						get_comment_id_fields( $post_id )
					);

					/**
					 * Filter the submit field for the comment form to display.
					 *
					 * The submit field includes the submit button, hidden fields for the
					 * comment form, and any wrapper markup.
					 *
					 * @since 4.2.0
					 *
					 * @param string $submit_field HTML markup for the submit field.
					 * @param array  $args         Arguments passed to comment_form().
					 */
					echo apply_filters( 'comment_form_submit_field', $submit_field, $args );

					/**
					 * Fires at the bottom of the comment form, inside the closing </form> tag.
					 *
					 * @since 1.5.0
					 *
					 * @param int $post_id The post ID.
					 */
					do_action( 'comment_form', $post_id );
					?>
				</form>
			<?php endif; ?>
		</div><!-- #respond -->
		<?php
		/**
		 * Fires after the comment form.
		 *
		 * @since 3.0.0
		 */
		do_action( 'comment_form_after' );
	else :
		/**
		 * Fires after the comment form if comments are closed.
		 *
		 * @since 3.0.0
		 */
		do_action( 'comment_form_comments_closed' );
	endif;
}


}
