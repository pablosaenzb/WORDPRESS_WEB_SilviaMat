<?php

namespace Kubio\Core\Blocks\Query;

use Kubio\Core\Blocks\BlockContainerBase;
use Kubio\Core\Layout\LayoutHelper;
use Kubio\Core\LodashBasic;
use IlluminateAgnostic\Arr\Support\Arr;

class QueryLoopBase extends BlockContainerBase {
	const CONTAINER  = 'container';
	const INNER      = 'inner';
	const CENTER     = 'center';
	const INNER_GAPS = 'innerGaps';

	private $query_context = null;



	public function mapPropsToElements() {
		$layout_media    = $this->getPropByMedia( 'layout' );
		$layout_helper   = new LayoutHelper( $layout_media );
		$masonry         = $this->getAttribute( 'masonry', false );
		$masonry_classes = array();
		if ( $masonry ) {
			$masonry_classes[] = 'kubio-query-loop--use-masonry';
		}
		$map                    = array();
		$map[ self::CONTAINER ] = array( 'className' => $layout_helper->getRowGapClasses() );
		$map[ self::INNER ]     = array(
			'className' => LodashBasic::concat( $layout_helper->getRowGapInnerClasses(), $layout_helper->getRowAlignClasses(), $masonry_classes ),
			'innerHTML' => $this->renderList(),
		);

		return $map;
	}

	public function renderList() {

		$posts   = $this->getPostList();
		$content = "<!--kubio post list : start-->\n";

		if ( empty( $posts ) && $this->isUsingMainQuery() ) {
			$post_type = get_post_type_object( $this->getPostType() );
			$label     = $post_type->labels->name;

			$not_found_text = $this->getAttribute( 'notFound' );

			if ( $not_found_text === null ) {
				$not_found_text = __( 'No {post_title} found!', 'kubio' );
			}

			$content .= '<h2 class="kubio-empty-query-result">' . str_replace( '{post_title}', $label, kubio_wpml_get_translated_string( $not_found_text ) ) . '</h2>';
		} else {
			foreach ( (array) $posts as $post ) {
				foreach ( $this->block_data['innerBlocks'] as $inner_block ) {
					$content .= (
					new \WP_Block(
						$inner_block,
						array(
							'postType' => $post->post_type,
							'postId'   => $post->ID,
						)
					)
					)->render();
				}
			}
		}

		$content .= "\n<!--kubio post list : end-->";

		return $content;
	}

	public function getPostList() {
		if ( $this->isUsingMainQuery() ) {
			/**
			 * @var \WP_Query $wp_query ;
			 */
			global $wp_query;

			return $wp_query->posts;
		}

		return $this->getPostsQueryList();
	}

	public function getQueryContext() {
		if ( ! $this->query_context ) {
			$this->query_context = array_merge(
				$this->queryDefault(),
				$this->block_context
			);
		}

		return $this->query_context;
	}

	public function queryDefault() {
		return array(
			'queryId'      => 1,
			'useMainQuery' => false,
			'query'        => array(
				'postType'     => 'post',
				'sticky'       => false,
				'post__in'     => array(),
				// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
				'post__not_in' => array(),
				// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
				'exclude'      => array(),
				'categoryIds'  => array(),
				'perPage'      => get_option( 'posts_per_page' ),
				'offset'       => 0,
				'tagIds'       => array(),
				'author'       => array(),
				'search'       => array(),
				'orderBy'      => 'date',
				'order'        => 'DESC',
			),
		);
	}

	public function getPostsQueryList() {
		$context  = $this->getQueryContext();
		$page_key = isset( $context['queryId'] ) ? 'query-' . $context['queryId'] . '-page' : 'query-page';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$page = empty( $_GET[ $page_key ] ) ? 1 : filter_var( $_GET[ $page_key ], FILTER_VALIDATE_INT );

		/** @noinspection PhpParamsInspection */
		$query_args                        = build_query_vars_from_query_block( (object) array( 'context' => $context ), $page );
		$query_args['ignore_sticky_posts'] = Arr::get( $context, 'query.sticky' ) ? false : true;
		$query                             = new \WP_Query( $query_args );

		return $query->posts;
	}

	public function getPostType() {

		if ( $this->isUsingMainQuery() ) {
			$post_type = get_query_var( 'post_type', '' );
			if ( ! $post_type ) {
				$post_type = 'post';
			}
		}

		$context = $this->getQueryContext();

		return $context['query']['postType'];
	}

	public function isUsingMainQuery() {
		$context = $this->getQueryContext();
		return isset( $context['useMainQuery'] ) && $context['useMainQuery'];
	}
}
