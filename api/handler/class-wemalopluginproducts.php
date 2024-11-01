<?php

require_once __DIR__ . '/class-wemalopluginbase.php';
require_once __DIR__ . '/../class-wemaloapiconnect.php';

class WemaloPluginProducts extends WemaloPluginBase {

	private ?WemaloAPIConnect $connect = null;

	/**
	 * loads product data by post data
	 *
	 * @param WP_POST $post
	 * @param boolean $display_meta
	 * @return array
	 */
	public function get_product_data( WP_POST $post, bool $display_meta = false ): array {
		// if variation, get parent post
		$title                     = '';
		$tmp                       = array();
		$variations                = array();
		$add_attribute_information = false;
		$content                   = $post->post_content;

		if ( $post->post_type === 'product_variation' ) {
			$parent_products = array();
			// load parent product
			$title = $this->load_parent_post( $post->ID, $parent_products );
			// add attribute information
			$add_attribute_information = true;
		} else {
			$handle     = new WC_Product_Variable( $post->ID );
			$variations = $handle->get_children();
			$title      = $post->post_title;
		}
		$tmp1 = $this->create_product_array( $post->ID, $title, $content, $add_attribute_information, get_post_meta( $post->ID ), $display_meta );
		if ( $tmp1 ) {
			$tmp1['post_type']   = $post->post_type;
			$tmp1['post_status'] = $post->post_status;
		}

		$tmp[] = $tmp1;

		foreach ( $variations as $value ) {
			$tmp2             = null;
			$single_variation = new WC_Product_Variation( $value );
			$tmp2             = $this->create_product_array( $single_variation->get_id(), $title, $content, true, get_post_meta( $single_variation->get_id() ), $display_meta );
			if ( $tmp2 ) {
				$tmp2['post_type']   = $post->post_type;
				$tmp2['post_status'] = $post->post_status;
			}
			$tmp[] = $tmp2;
		}

		return $tmp;
	}

	/**
	 * returns the connect client
	 *
	 * @return WemaloAPIConnect|null
	 */
	public function get_wemalo_api_connect(): ?WemaloAPIConnect {
		$this->connect = $this->connect ?? new WemaloAPIConnect();

		return $this->connect;
	}

	/**
	 * serves product information for a single product
	 *
	 * @param $post_id
	 * @param $title
	 * @param $description
	 * @param $add_attribute_information
	 * @param $post_meta
	 * @param bool                      $addMetaInformation
	 * @return array|null
	 */
	private function create_product_array( $post_id, $title, $description, $add_attribute_information, $post_meta, bool $addMetaInformation = false ): ?array {
		$product_sku = $this->get_value( '_sku', $post_meta );
		if ( $product_sku !== '' ) {
			// load by wemalo ean first
			$ean  = $this->get_value( '_wemalo_ean', $post_meta, '' );
			$ean2 = $this->get_value( '_gtin', $post_meta, '' );
			$ean3 = $this->get_value( '_ean', $post_meta, '' );
			if ( $ean === '' ) {
				if ( $ean2 !== '' ) {
					$ean = $ean2;
				} elseif ( $ean3 !== '' ) {
					$ean = $ean3;
				}
			}
			$product_weight = $this->get_value( '_weight', $post_meta, 0 );
			if ( is_numeric( $product_weight ) ) {
				$product_weight = wc_get_weight( $product_weight, 'g' );// convert measurement unit according woocommerce function and option
			} else {
				$product_weight = 0;
			}
			$product_price                 = $this->get_value( '_price', $post_meta );
			$my_product                    = array();
			$my_product['shop']            = $this->get_wemalo_api_connect()->get_shop_name();
			$my_product['externalId']      = $post_id;
			$my_product['stock']           = $this->get_value( '_stock', $post_meta );
			$my_product['b-stock']         = $this->get_value( '_wemalo_bstock', $post_meta );
			$my_product['hasSerialNumber'] = $this->get_value( '_wemalo_sn', $post_meta, '' ) === 'a';
			if ( $add_attribute_information ) {
				// search for attributes in post meta and add those to the product title
				$tmp = '';
				foreach ( $post_meta as $k => $meta ) {
					if ( $this->starts_with( $k, 'attribute_' ) ) {
						$tmp .= ( $tmp !== '' ? ', ' : '' ) . $meta[0];
					}
				}
				if ( $tmp !== '' ) {
					$title .= ' (' . $tmp . ')';
				}
			}
			if ( $addMetaInformation ) {
				$my_product['meta'] = $post_meta;
			}
			$my_product['name'] = $title;
			$my_product['sku']  = sanitize_text_field( $product_sku );
			if ( $ean ) {
				$my_product['ean'] = sanitize_text_field( $ean );
			}
			if ( $ean2 ) {
				$my_product['ean2'] = sanitize_text_field( $ean2 );
			}
			if ( $ean3 ) {
				$my_product['ean3'] = sanitize_text_field( $ean3 );
			}
			$my_product['netWeight']  = $product_weight;
			$my_product['salesPrice'] = $product_price;

			// convert measurement unit with woocommerce function and option
			$my_product['depth']  = wc_get_dimension( $this->get_value( '_length', $post_meta, 0 ), 'cm' );
			$my_product['width']  = wc_get_dimension( $this->get_value( '_width', $post_meta, 0 ), 'cm' );
			$my_product['height'] = wc_get_dimension( $this->get_value( '_height', $post_meta, 0 ), 'cm' );

			$my_product['thumbnail'] = '';
			$post_thumbnail_id       = $this->get_value( '_thumbnail_id', $post_meta, false );
			if ( $post_thumbnail_id ) {
				// currently, this function is not used (and supported since WP 4.4 only)
				if ( function_exists( 'wp_get_attachment_image_url' ) ) {
					$my_product['thumbnail'] = wp_get_attachment_image_url( $post_thumbnail_id, 'thumbnail' );
				}
			}
			$my_product['productGroup'] = 'default';
			return $my_product;
		}
		return null;
	}

	/**
	 * determines whether the product should be handled by post type
	 *
	 * @param string $post_type
	 * @return boolean
	 */
	public function handle_post_type( string $post_type ): bool {
		return ( $post_type === 'product_variation' || $post_type === 'product' );
	}

	/**
	 * loads parents information
	 *
	 * @param int   $post_id
	 * @param array $parent_products
	 * @return string
	 */
	private function load_parent_post( int $post_id, array $parent_products ): string {
		// load parent product
		$parent_id = wp_get_post_parent_id( $post_id );
		$title     = '';
		if ( ! $parent_id ) {
			$parent_id = $post_id;
		}
		if ( ! array_key_exists( $parent_id, $parent_products ) ) {
			$title                         = get_the_title( $parent_id );
			$parent_products[ $parent_id ] = $title;
		} else {
			$title = $parent_products[ $parent_id ];
		}
		return $title;
	}

	/**
	 * serves all products and variant products
	 *
	 * @param string $time_stamp
	 * @return array
	 */
	public function get_all_products( string $time_stamp ): array {
		try {
			$date_args       = $this->create_date_query( $time_stamp );
			$loop            = new WP_Query(
				array(
					'post_type'      => array( 'product', 'product_variation' ),
					'date_query'     => array( $date_args ),
					'posts_per_page' => -1,
					'post_status'    => array(
						'publish',
						'private',
					),
				)
			);
			$my_product_list = array();
			$parent_products = array();
			$posts           = $loop->get_posts();
			$counter         = 0;
			foreach ( $posts as $p ) {
				$add_attribute_information = false;
				$post_id                   = $p->ID;
				$title                     = '';
				if ( $p->post_type === 'product_variation' ) {
					// load parent product
					$title = $this->load_parent_post( $post_id, $parent_products );
					// add attribute information
					$add_attribute_information = true;
				} else {
					$title                       = $p->post_title;
					$parent_products[ $post_id ] = $title;
				}
				$p = $this->create_product_array(
					$post_id,
					$title,
					get_post_field( 'post_content', $post_id ),
					$add_attribute_information,
					get_post_meta( $post_id ),
					false
				);
				if ( ! empty( $p ) ) {
					$my_product_list[] = $p;
				}
				++$counter;
			}
			wp_reset_query();
			return $my_product_list;
		} catch ( Exception $e ) {
			return $this->return_error( 51, $e->getMessage() );
		}
	}
}
