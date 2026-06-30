<?php
/**
 * Plugin Name:       WP Query Block Extension - Frontend Taxonomy Filters
 * Description:       Add taxonomy filter in the frontend page that filters the posts returned from the query block.
 * Version:           0.1.1
 * Requires at least: 6.5
 * Requires PHP:      7.0
 * Author:            CTLT WordPress
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-query-loop-extension-frontend-taxonomy-filter
 *
 * @package           query-taxonomy-filters
 */

namespace UBC\CTLT\BLOCKS\QUERY_BLOCK\FILTERS\TAXONOMY;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'init', __NAMESPACE__ . '\\init' );
add_filter( 'pre_render_block', __NAMESPACE__ . '\\pre_render_block', 10, 2 );

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function init() {
	register_block_type_from_metadata( __DIR__ . '/build' );
}

/**
 * Recursive function to retrieve all inner blocks of a given block with a specific inner block name.
 *
 * @param array  $block The block to search for inner blocks.
 * @param string $inner_block_name The name of the inner block to search for.
 * @return array An array of inner blocks with the specified name.
 */
function get_inner_blocks( $block, $inner_block_name ) {
	if ( ! array_key_exists( 'innerBlocks', $block ) || ! is_array( $block['innerBlocks'] ) ) {
		return array();
	}

	$inner_blocks = array();
	foreach ( $block['innerBlocks'] as $key => $inner_block ) {
		if ( $inner_block_name === $inner_block['blockName'] ) {
			array_push( $inner_blocks, $inner_block );
		} else {
			$inner_blocks = array_merge( $inner_blocks, get_inner_blocks( $inner_block, $inner_block_name ) );
		}
	}

	return $inner_blocks;
}

/**
 * Recursively walks the block tree and collects taxonomy filter blocks that sit
 * outside of any core/query block (global filters).
 *
 * @param array $blocks        List of parsed blocks to inspect.
 * @param array $map           Reference to the map being built: instanceId => taxonomyType.
 * @param bool  $inside_query  Whether the current recursion is inside a core/query block.
 */
function collect_global_filter_blocks( $blocks, &$map, $inside_query = false ) {
	foreach ( $blocks as $block ) {
		if ( 'core/query' === $block['blockName'] ) {
			// Recurse into the query loop but mark it as inside-query so any filter
			// blocks found there are not added to the global map.
			collect_global_filter_blocks( $block['innerBlocks'] ?? array(), $map, true );
		} elseif ( 'ctlt/query-taxonomy-filter' === $block['blockName'] ) {
			if ( ! $inside_query
				&& isset( $block['attrs']['instanceId'] )
				&& ! empty( $block['attrs']['selectedTaxonomyType'] )
			) {
				$map[ absint( $block['attrs']['instanceId'] ) ] = sanitize_title( $block['attrs']['selectedTaxonomyType'] );
			}
		} else {
			// Recurse into any other container block (groups, columns, etc.).
			collect_global_filter_blocks( $block['innerBlocks'] ?? array(), $map, $inside_query );
		}
	}
}

/**
 * Returns an instanceId => taxonomyType map for all taxonomy filter blocks that
 * are placed outside any core/query block on the current post.
 *
 * Result is computed once per request and cached via a static variable.
 *
 * @return array
 */
function get_global_taxonomy_filter_map() {
	static $map = null;

	if ( null !== $map ) {
		return $map;
	}

	$map  = array();
	$post = get_post();

	if ( ! $post || ! has_blocks( $post->post_content ) ) {
		return $map;
	}

	$blocks = parse_blocks( $post->post_content );
	collect_global_filter_blocks( $blocks, $map );

	return $map;
}

/**
 * Inject new tax query from the filter based on query ID.
 *
 * @param string|null $pre_render   The pre-rendered content. Default null.
 * @param array       $parsed_block The block being rendered.
 *
 * @return string|null The modified pre-rendered block content or the original pre-rendered content if the block name is not 'core/query'.
 */
function pre_render_block( $pre_render, $parsed_block ) {
	if ( 'core/query' !== $parsed_block['blockName'] ) {
		return $pre_render;
	}

	$is_interactive = isset( $parsed_block['attrs']['enhancedPagination'] )
		&& true === $parsed_block['attrs']['enhancedPagination']
		&& isset( $parsed_block['attrs']['queryId'] );

	if ( ! $is_interactive ) {
		return $pre_render;
	}

	// Build hash map from filter blocks nested inside this query loop.
	$inner_tax_blocks = get_inner_blocks( $parsed_block, 'ctlt/query-taxonomy-filter' );

	$hash_map = array();
	foreach ( $inner_tax_blocks as $inner_block ) {
		if ( array_key_exists( 'attrs', $inner_block ) &&
			array_key_exists( 'instanceId', $inner_block['attrs'] ) &&
			array_key_exists( 'selectedTaxonomyType', $inner_block['attrs'] )
		) {
			$hash_map[ $inner_block['attrs']['instanceId'] ] = $inner_block['attrs']['selectedTaxonomyType'];
		}
	}

	// Merge in any global filter blocks (placed outside all query loops).
	// Global filters affect every query loop on the page simultaneously.
	// Use + instead of array_merge() to preserve numeric instanceId keys — array_merge()
	// reindexes numeric keys starting from 0, which breaks the instanceId → taxonomy lookup.
	$hash_map = $hash_map + get_global_taxonomy_filter_map();

	if ( empty( $hash_map ) ) {
		return $pre_render;
	}

	add_filter(
		'query_loop_block_query_vars',
		function ( $query, $block ) use ( $hash_map ) {
			$query_id        = $block->context['queryId'];
			$term_identifier = 'query-' . $query_id . '-term-';

			if ( ! array_key_exists( 'tax_query', $query ) ) {
				$query['tax_query'] = array();
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			foreach ( $_GET as $key => $value ) {
				if ( preg_match( '/^' . $term_identifier . '(?<instance_id>\d+)$/', $key, $matches ) && ! empty( $value ) && array_key_exists( $matches['instance_id'], $hash_map ) ) {
					$terms = explode( ',', $value );

					$new_sub_tax_query = array(
						'taxonomy'         => $hash_map[ absint( $matches['instance_id'] ) ],
						'terms'            => $terms,
						'include_children' => false,
					);

					array_push( $query['tax_query'], $new_sub_tax_query );
				}
			}

			return $query;
		},
		10,
		2
	);

	return $pre_render;
}
