<?php
namespace WordPressdotorg\Plugin_Directory;

/**
 ** Override Jetpack Search class with special features for the Plugin Directory
 **
 ** @package WordPressdotorg\Plugin_Directory
 **/
class Plugin_Search {

	const USE_OLD_SEARCH = false;

	/**
	 * Fetch the instance of the Plugin_Search class.
	 *
	 * @static
	 */
	public static function instance() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Plugin_Search constructor.
	 *
	 * @access private
	 */
	private function __construct() {
		if ( isset( $_GET['s'] ) )
			return false;

		error_log( __CLASS__ . ' __construct()' );
		error_log( '$this? ' . is_object( $this ) );
		add_action( 'init', array( $this, 'init' ) );

		// Filters needed regardless of whether we're using old or new search
		add_filter( 'option_jetpack_active_modules', array( $this, 'option_jetpack_active_modules' ) );

		// $es_wp_query_args = apply_filters( 'jetpack_search_es_wp_query_args', $es_wp_query_args, $query );
		add_filter( 'jetpack_search_es_wp_query_args', array( $this, 'log_search_es_wp_query_args' ), 99999, 2 );

		add_filter( 'jetpack_search_abort', array( $this, 'log_jetpack_search_abort' ) );

		#add_filter( 'jetpack_get_module', function($mod, $slug) { if ( 'search' == $slug ) { error_log( 'jetpack_get_module'); error_log( var_export( func_get_args(), true ) ); } }, 10, 2 );
		add_filter( 'jetpack_get_module', array( $this, 'jetpack_get_module' ), 10, 2 );

		add_filter( 'did_jetpack_search_query', array( $this, 'log_did_jetpack_search_query' ) );
	}

	public function init() {
		if ( self::USE_OLD_SEARCH ) {
			// Instantiate our copy of the Jetpack_Search class.
			if ( class_exists( 'Jetpack' ) && \Jetpack::get_option( 'id' ) && ! class_exists( 'Jetpack_Search' )
				&& ! isset( $_GET['s'] ) ) { // Don't run the ES query if we're going to redirect to the pretty search URL
					require_once __DIR__ . '/libs/site-search/jetpack-search.php';
					\Jetpack_Search::instance();
			}
		} else {
			// $es_query_args = apply_filters( 'jetpack_search_es_query_args', $es_query_args, $query );
			//
			add_filter( 'jetpack_search_es_wp_query_args', array( $this, 'jetpack_search_es_wp_query_args' ), 10, 2 );
			add_filter( 'jetpack_search_es_query_args', array( $this, 'jetpack_search_es_query_args' ), 10, 2 );
		}

	}
/*
       $decay_params = apply_filters(
            'jetpack_search_recency_score_decay',
            array(
                'origin' => date( 'Y-m-d' ),
                'scale'  => '360d',
                'decay'  => 0.9,
            ),
            $args
        );

	public function jetpack_search_recency_score_decay( $decay, $query ) {

		$decay['decay'] = 0.5;

		return $decay;
	}
 */


	public function option_jetpack_active_modules( $modules ) {
		if ( self::USE_OLD_SEARCH ) {
			if ( $i = array_search( 'search', $modules ) )
				unset( $modules[$i] );
		} else {
			$modules[] = 'search';
		}

		return array_unique( $modules );
	}

	/* Make sure the search module is available regardless of Jetpack plan.
	 * This works because search indexes were manually created for w.org.
	 */
	public function jetpack_get_module( $module, $slug ) {
		if ( !self::USE_OLD_SEARCH ) {
			if ( 'search' === $slug && isset( $module[ 'plan_classes' ] ) && !in_array( 'free', $module[ 'plan_classes' ] ) ) {
				$module[ 'plan_classes' ][] = 'free';
			}
		}

		return $module;
	}

	public function jetpack_search_es_wp_query_args( $es_wp_query_args, $query ) {
error_log( __FUNCTION__ );
		$es_wp_query_args[ 'filters' ][] =
			array (
			  'term' =>
			  array (
				'disabled' =>
				array (
				  'value' => false,
				),
			  ),
			);

		#if ( isset( $es_wp_query_args['post_type'] ) && in_array( 'plugin', $es_wp_query_args['post_type'] ) ) {
		#	unset( $es_wp_query_args['post_type'][ array_search( 'plugin', $es_wp_query_args['post_type'] ) ] );
#}
#
#unset( $es_wp_query_args['post_type'] );
#$es_wp_query_args['post_type'] = array( 'any' );

		$es_wp_query_args['locale'] = get_locale();
		#$es_wp_query_args['blog_id'] = \Jetpack::get_option( 'id' );
		$es_wp_query_args['query_fields'] = array(
			'all_content_en^0.1',
			#'title_en^2',
			#'excerpt_en^2',
			#'description_en^2',
			#'taxonomy.plugin_tags.name^2',
			#'slug_text^2',
			#'author^2',
			#'contributors^2',
			#'title_en.ngram^0.2',
		);
		/*$es_wp_query_args['query_fields'] = array(
			'all_content_en' => 0.1,
			'title_en' => 2,
			'excerpt_en' => 2,
			'description_en' => 2,
			'taxonomy.plugin_tags.name' => 2,
			'slug_text' => 2,
			'author' => 2,
			'contributors' => 2,
			'title_en.ngram' => 0.2,
		);*/

error_log( var_export( $es_wp_query_args, true ) );

		return $es_wp_query_args;
	}

	public function jetpack_search_es_query_args( $es_query_args, $query ) {
		/* if ( isset( $es_query_args[ 'blog_id' ] ) ) {
			// Strange that convert_wp_es_to_es_args() uses get_current_blog_id() and not the jetpack blog ID
			$es_query_args[ 'blog_id' ] = \Jetpack::get_option( 'id' );
		} */

		// Weirdly, filtering on post_type = plugin causes the query to find zero results
		if ( isset( $es_query_args[ 'filter' ][ 'terms' ][ 'post_type' ] ) ) {
			#unset( $es_query_args[ 'filter' ][ 'terms' ][ 'post_type' ] );
		}

error_log( '--- should --- ' );
error_log( var_export( $es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'should' ], true ) );
if ( isset( $es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'should' ] ) ) {
			#$query = $es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'should' ][ 0 ]
			#$es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'should' ] =
			#	[
			#		[ 'multi_match' =>
			#			[ 'query' =>
			#	];
		}

		/*
		$es_query_args[ 'filter' ][ 'and' ][] = array(
			'term' => array(
				'disabled' => array( 'value' => false )
			)
		); */

		// TODO: make sure filter includes term => disabled => false

error_log( __CLASS__ . ':'  . var_export( $es_query_args, true ) );

		return $es_query_args;
	}

	public function log_search_es_wp_query_args( $es_wp_query_args, $query ) {
		error_log( '--- ' . __FUNCTION__ . ' ---' );
		error_log( var_export( $es_wp_query_args, true ) );

		return $es_wp_query_args;
	}
// jetpack_search_abort

	public function log_jetpack_search_abort( $reason ) {
		error_log( "--- jetpack_search_abort $reason ---" );
	}

	public function log_did_jetpack_search_query( $query ) {
		error_log( '--- did_jetpack_search_query ---' );
		error_log( var_export( $query, true ) );
	}
}
