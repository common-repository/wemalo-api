<?php

require_once __DIR__.'/WemaloPluginBase.php';
require_once __DIR__.'/../WemaloAPIConnect.php';

class WemaloPluginProducts extends WemaloPluginBase
{
	private ?WemaloAPIConnect $connect = null;
	
	/**
	 * loads product data by post data
	 * @param WP_POST $post
	 * @param boolean $displayMeta
	 * @return array
	 */
	public function getProductData(WP_POST $post, bool $displayMeta=false): array
    {
		//if variation, get parent post
		$title = "";
        $tmp = [];
        $variations = [];
		$addAttributeInformation = false;
        $content = $post->post_content;

		if ($post->post_type == 'product_variation') {
			$parentProducts = array();
			//load parent product
			$title = $this->loadParentPost($post->ID, $parentProducts);
			//add attribute information
			$addAttributeInformation = true;
		} else {
            $handle = new WC_Product_Variable($post->ID);
            $variations = $handle->get_children();
			$title = $post->post_title;
		}
        $tmp1 = $this->createProductArray($post->ID, $title, $content, $addAttributeInformation, get_post_meta($post->ID), $displayMeta);
        if ($tmp1) {
            $tmp1['post_type'] = $post->post_type;
            $tmp1['post_status'] = $post->post_status;
        }

        $tmp[] = $tmp1;

        foreach ($variations as $value) {
            $tmp2 = null;
            $single_variation = new WC_Product_Variation($value);
            $tmp2 = $this->createProductArray($single_variation->get_id(), $title, $content, true, get_post_meta($single_variation->get_id()), $displayMeta);
            if ($tmp2) {
                $tmp2['post_type'] = $post->post_type;
                $tmp2['post_status'] = $post->post_status;
            }
            $tmp[] = $tmp2;
        }

        return $tmp;
	}

    /**
     * returns the connect client
     * @return WemaloAPIConnect|null
     */
	public function getConnect(): ?WemaloAPIConnect
    {
		if ($this->connect == null) {
			$this->connect = new WemaloAPIConnect();
		}
		return $this->connect;
	}

    /**
     * serves product information for a single product
     * @param $postId
     * @param $title
     * @param $description
     * @param $addAttributeInformation
     * @param $postMeta
     * @param bool $addMetaInformation
     * @return array|null
     */
    private function createProductArray($postId, $title, $description, $addAttributeInformation, $postMeta, bool $addMetaInformation=false): ?array
    {
        $product_sku = $this->getValue("_sku", $postMeta);
        if ($product_sku != "") {
        	//load by wemalo ean first
        	$ean = $this->getValue("_wemalo_ean", $postMeta, "");
        	$ean2 = $this->getValue("_gtin", $postMeta, "");
        	$ean3 = $this->getValue("_ean", $postMeta, "");
        	if ($ean == "") {
        		if ($ean2 != "") {
        			$ean = $ean2;
        		}
        		else if ($ean3 != "") {
        			$ean = $ean3;
        		}
        	}
	        $product_weight = $this->getValue("_weight", $postMeta, 0);
	        if (is_numeric($product_weight)) {
		        $product_weight = wc_get_weight($product_weight,'g');//convert measurement unit according woocommerce function and option
	        }
	        else {
	        	$product_weight = 0;
	        }
	        $product_price = $this->getValue("_price", $postMeta);
	        $myProduct = array();
	        $myProduct['shop'] = $this->getConnect()->getShopName();
	        $myProduct['externalId'] = $postId;
	        $myProduct['stock'] = $this->getValue("_stock", $postMeta);
	        $myProduct['b-stock'] = $this->getValue("_wemalo_bstock", $postMeta);
	        $myProduct['hasSerialNumber'] = $this->getValue("_wemalo_sn", $postMeta, "") === "a";
	        if ($addAttributeInformation) {
	        	//search for attributes in post meta and add those to the product title
	        	$tmp = '';
	        	foreach ($postMeta as $k=>$meta) {
	        		if ($this->startsWith($k, "attribute_")) {
	        			$tmp .= ($tmp != '' ? ', ' : '').$meta[0];
	        		}
	        	}
	        	if ($tmp != '') {
	        		$title .= ' ('.$tmp.')';
	        	}
	        }
	        if ($addMetaInformation) {
	        	$myProduct['meta'] = $postMeta;
	        }
	        $myProduct['name'] = $title;
	        $myProduct['sku'] = sanitize_text_field($product_sku);
	        if ($ean) {
	        	$myProduct['ean'] = sanitize_text_field($ean);
	        }
	        if ($ean2) {
	        	$myProduct['ean2'] = sanitize_text_field($ean2);
	        }
	        if ($ean3) {
	        	$myProduct['ean3'] = sanitize_text_field($ean3);
	        }
	        $myProduct['netWeight'] = $product_weight;
	        $myProduct['salesPrice'] = $product_price;

	        //convert measurement unit with woocommerce function and option
	        $myProduct['depth'] = wc_get_dimension($this->getValue("_length", $postMeta, 0),'cm');
			$myProduct['width'] = wc_get_dimension($this->getValue("_width", $postMeta, 0),'cm');
	        $myProduct['height'] = wc_get_dimension($this->getValue("_height", $postMeta, 0),'cm');

	        $myProduct['thumbnail'] = "";
	        $post_thumbnail_id = $this->getValue("_thumbnail_id", $postMeta, false);
	    	if ($post_thumbnail_id) {
	    		//currently, this function is not used (and supported since WP 4.4 only)
	    		if (function_exists("wp_get_attachment_image_url")) {
	    			$myProduct['thumbnail'] = wp_get_attachment_image_url($post_thumbnail_id, 'thumbnail');
	    		}
			}
			$myProduct['productGroup'] = "default";
	        return $myProduct;
        }
        return null;
    }
    
    /**
     * determines whether the product should be handled by post type
     * @param string $postType
     * @return boolean
     */
    public function handlePostType(string $postType): bool
    {
    	return ($postType == 'product_variation' || $postType == 'product');
    }

    /**
     * loads parents information
     * @param int $postId
     * @param array $parentProducts
     * @return string
     */
    private function loadParentPost(int $postId, array $parentProducts): string
    {
    	//load parent product
    	$parentId = wp_get_post_parent_id($postId);
    	$title = "";
    	if (!$parentId) {
    		$parentId = $postId;
    	}
    	if (!array_key_exists($parentId, $parentProducts)) {
    		$title = get_the_title($parentId);
    		$parentProducts[$parentId] = $title;
    	}
    	else {
    		$title = $parentProducts[$parentId];
    	}
    	return $title;
    }

    /**
     * serves all products and variant products
     * @param string $time_stamp
     * @return array :NULL
     */
    public function getAllProducts(string $time_stamp): array
    {
    	try {
	        $date_args = $this->createDateQuery($time_stamp);
	        $loop = new WP_Query(array('post_type' => array('product', 'product_variation'), 
	        	'date_query' => array($date_args), 
	        	'posts_per_page' => -1,
	        	'post_status' => array('publish', 'private')));
	        $my_product_list = array();
	        $parentProducts = array();
	        $posts = $loop->get_posts();
	        $counter = 0;
	        foreach ($posts as $p) {
	        	$addAttributeInformation = false;
	        	$postId = $p->ID;
	        	$title = "";
	        	if ($p->post_type == 'product_variation') {
	        		//load parent product
	                $title = $this->loadParentPost($postId, $parentProducts);
	                //add attribute information
	                $addAttributeInformation = true;
	        	}
	        	else {
	        		$title = $p->post_title;
	        		$parentProducts[$postId] = $title;
	        	}
	        	$p = $this->createProductArray($postId, $title, get_post_field('post_content', $postId),
	        		$addAttributeInformation, get_post_meta($postId), false);
	        	if ($p != null) {
	        		$my_product_list[] = $p;
	        	}
	        	$counter++;
	        }
	        wp_reset_query();
	        return $my_product_list;
    	}
    	catch (Exception $e) {
    		return $this->returnError(51, $e->getMessage());
    	}
    }
}