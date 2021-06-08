<?php

use PHPUnit\Framework\TestCase;

require_once( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/libs/site-search/jetpack-search.php' );

class TestJetpackSearchClass extends WP_UnitTestCase {

	protected $jetpack_search;

	function setUp(): void {
		if ( is_null( $this->jetpack_search ) ) {
			require_once( ABSPATH . 'wp-content/plugins/jetpack/jetpack.php' );
			$this->jetpack_search = Jetpack_Search::instance();
		}

		if ( !defined( 'WP_CORE_STABLE_BRANCH' ) ) {
			define( 'WP_CORE_STABLE_BRANCH', '5.0' );
		}
	}

	function var_export($expression, $return=FALSE) {
		$export = var_export($expression, TRUE);
		$patterns = [
			"/array \(/" => '[',
			"/^([ ]*)\)(,?)$/m" => '$1]$2',
			"/=>[ ]?\n[ ]+\[/" => '=> [',
			"/([ ]*)(\'[^\']+\') => ([\[\'])/" => '$1$2 => $3',
		];
		$export = preg_replace(array_keys($patterns), array_values($patterns), $export);
		if ((bool)$return) return $export; else echo $export;
	}

	function test_convert_wp_es_to_es_args_empty() {
		$expected = [
			'blog_id' => 1,
			'size' => 10,
			'query' => [
			  'filtered' => [
				'query' => [
				  'function_score' => [
					'query' => [
					  'match_all' => [
					  ],
					],
					'functions' => [
					  0 => [
						'exp' => [
						  'plugin_modified' => [
							'origin' => date( 'Y-m-d' ),
							'offset' => '180d',
							'scale' => '360d',
							'decay' => 0.5,
						  ],
						],
					  ],
					  1 => [
						'exp' => [
						  'tested' => [
							'origin' => '5.0',
							'offset' => 0.1,
							'scale' => 0.4,
							'decay' => 0.6,
						  ],
						],
					  ],
					  2 => [
						'field_value_factor' => [
						  'field' => 'active_installs',
						  'factor' => 0.375,
						  'modifier' => 'log2p',
						  'missing' => 1,
						],
					  ],
					  3 => [
						'filter' => [
						  'range' => [
							'active_installs' => [
							  'lte' => 1000000,
							],
						  ],
						],
						'exp' => [
						  'active_installs' => [
							'origin' => 1000000,
							'offset' => 0,
							'scale' => 900000,
							'decay' => 0.75,
						  ],
						],
					  ],
					  4 => [
						'field_value_factor' => [
						  'field' => 'support_threads_resolved',
						  'factor' => 0.25,
						  'modifier' => 'log2p',
						  'missing' => 0.5,
						],
					  ],
					  5 => [
						'field_value_factor' => [
						  'field' => 'rating',
						  'factor' => 0.25,
						  'modifier' => 'sqrt',
						  'missing' => 2.5,
						],
					  ],
					],
					'boost_mode' => 'multiply',
				  ],
				],
			  ],
			],
			'sort' => [
			  0 => [
				'date' => [
				  'order' => 'desc',
				],
			  ],
			],
			'filter' => [
			  'match_all' =>
			   (object) [
			  ],
			],
		];

		$actual = $this->jetpack_search->convert_wp_es_to_es_args( [] );

		$this->assertEquals( $expected, $actual );

	}

	function test_convert_wp_es_to_es_args_typical() {
		// A typical search
		$es_wp_query_args = array(
			'query'          => 'an example search',
			'posts_per_page' => 27,
			'paged'          => 2,
			'orderby'        => '',
			'order'          => '',
			'filters'        => array(
				array( 'term' => array( 'disabled' => array( 'value' => false ) ) ),
			),
		);
		$actual = $this->jetpack_search->convert_wp_es_to_es_args( $es_wp_query_args );
		#$this->var_export( $actual );

		$expected = [
			'blog_id' => 1,
			'size' => 27,
			'from' => 27,
			'query' => [
			  'filtered' => [
				'query' => [
				  'function_score' => [
					'query' => [
					  'bool' => [
						'must' => [
						  'multi_match' => [
							'query' => 'an example search',
							'fields' => [
							  0 => 'all_content_en',
							],
							'boost' => 0.1,
							'operator' => 'and',
						  ],
						],
						'should' => [
						  0 => [
							'multi_match' => [
							  'query' => 'an example search',
							  'fields' => [
								0 => 'title_en',
								1 => 'excerpt_en',
								2 => 'description_en^1',
								3 => 'taxonomy.plugin_tags.name',
							  ],
							  'type' => 'phrase',
							  'boost' => 2,
							],
						  ],
						  1 => [
							'multi_match' => [
							  'query' => 'an example search',
							  'fields' => [
								0 => 'title_en.ngram',
							  ],
							  'type' => 'phrase',
							  'boost' => 0.2,
							],
						  ],
						  2 => [
							'multi_match' => [
							  'query' => 'an example search',
							  'fields' => [
								0 => 'title_en',
								1 => 'slug_text',
							  ],
							  'type' => 'best_fields',
							  'boost' => 2,
							],
						  ],
						  3 => [
							'multi_match' => [
							  'query' => 'an example search',
							  'fields' => [
								0 => 'excerpt_en',
								1 => 'description_en^1',
								2 => 'taxonomy.plugin_tags.name',
							  ],
							  'type' => 'best_fields',
							  'boost' => 2,
							],
						  ],
						  4 => [
							'multi_match' => [
							  'query' => 'an example search',
							  'fields' => [
								0 => 'author',
								1 => 'contributors',
							  ],
							  'type' => 'best_fields',
							  'boost' => 2,
							],
						  ],
						],
					  ],
					],
					'functions' => [
					  0 => [
						'exp' => [
						  'plugin_modified' => [
							'origin' => date('Y-m-d'),
							'offset' => '180d',
							'scale' => '360d',
							'decay' => 0.5,
						  ],
						],
					  ],
					  1 => [
						'exp' => [
						  'tested' => [
							'origin' => '5.0',
							'offset' => 0.1,
							'scale' => 0.4,
							'decay' => 0.6,
						  ],
						],
					  ],
					  2 => [
						'field_value_factor' => [
						  'field' => 'active_installs',
						  'factor' => 0.375,
						  'modifier' => 'log2p',
						  'missing' => 1,
						],
					  ],
					  3 => [
						'filter' => [
						  'range' => [
							'active_installs' => [
							  'lte' => 1000000,
							],
						  ],
						],
						'exp' => [
						  'active_installs' => [
							'origin' => 1000000,
							'offset' => 0,
							'scale' => 900000,
							'decay' => 0.75,
						  ],
						],
					  ],
					  4 => [
						'field_value_factor' => [
						  'field' => 'support_threads_resolved',
						  'factor' => 0.25,
						  'modifier' => 'log2p',
						  'missing' => 0.5,
						],
					  ],
					  5 => [
						'field_value_factor' => [
						  'field' => 'rating',
						  'factor' => 0.25,
						  'modifier' => 'sqrt',
						  'missing' => 2.5,
						],
					  ],
					],
					'boost_mode' => 'multiply',
				  ],
				],
			  ],
			],
			'sort' => [
			  0 => [
				'_score' => [
				  'order' => 'desc',
				],
			  ],
			],
			'filter' => [
			  'and' => [
				0 => [
				  'term' => [
					'disabled' => [
					  'value' => false,
					],
				  ],
				],
			  ],
			],
		];

		$this->assertEquals( $expected, $actual );

	}
}