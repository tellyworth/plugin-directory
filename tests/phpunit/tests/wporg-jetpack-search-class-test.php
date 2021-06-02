<?php

use PHPUnit\Framework\TestCase;

require_once( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/libs/site-search/jetpack-search.php' );

class TestJetpackSearchClass extends WP_UnitTestCase {

	protected $jetpack_class;

	function setUp(): void {
		if ( is_null( $this->jetpack_class ) ) {
			$this->jetpack_class = Jetpack_Search::instance();
		}
	}

	function test_convert_wp_es_to_es_args() {
		var_dump( $this->jetpack_class->convert_wp_es_to_es_args( [] ) );
	}
}