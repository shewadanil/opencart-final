<?php
class TradeEvo  {

	private $languages = array();
	private $manufacturers = array();
	private $options = array();
	private $attributes = array();

	public function __construct($registry) {
		$this->config = $registry->get('config');
		$this->session = $registry->get('session');
		$this->db = $registry->get('db');
		$this->shops = $registry->get('shops');
		$this->cache = $registry->get('cache');
	}


	public function getCategories(){
		$category_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category c
										LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id)
										LEFT JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id)
										WHERE
											cd.language_id = '" . (int)$this->config->get('config_language_id') . "'
											AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "'");

		return $category_query->rows;
	}

	public function getProducts($data){
		$sql = "SELECT *,
						p.image,
						m.name as manufacturer,
						(SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special
						FROM " . DB_PREFIX . "product p
										LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
										LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
										LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id)
										WHERE
											pd.language_id = '" . (int)$this->config->get('config_language_id') . "'
											AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";


		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$products_query = $this->db->query($sql);

		return $products_query->rows;
	}

	public function getProductsTotal(){
		$sql = "SELECT COUNT(*) as total FROM " . DB_PREFIX . "product p
										LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
										LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
										WHERE
											pd.language_id = '" . (int)$this->config->get('config_language_id') . "'
											AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";

		$total_query = $this->db->query($sql);

		return $total_query->row['total'];
	}

	public function getProductAttributes($product_id){
		$attributes_query = $this->db->query("SELECT ad.name, pa.text, a.sort_order FROM " . DB_PREFIX . "product_attribute pa
															LEFT JOIN " . DB_PREFIX . "attribute a ON (pa.attribute_id = a.attribute_id)
															LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (pa.attribute_id = ad.attribute_id)
															WHERE
																pa.product_id = " . $product_id . "
																AND ad.language_id = '" . (int)$this->config->get('config_language_id') . "'
																ORDER BY a.sort_order ASC");

		return $attributes_query->rows;
	}

	public function getProductOptions($product_id){
		$options_query = $this->db->query("SELECT *, od.name as option_name, ovd.name as value_name FROM " . DB_PREFIX . "product_option_value pov
															LEFT JOIN " . DB_PREFIX . "option o ON (pov.option_id = o.option_id)
															LEFT JOIN " . DB_PREFIX . "option_description od ON (pov.option_id = od.option_id)
															LEFT JOIN " . DB_PREFIX . "option_value ov ON (pov.option_value_id = ov.option_value_id)
															LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (pov.option_value_id = ovd.option_value_id)
															WHERE
																pov.product_id = " . $product_id . "
																AND od.language_id = '" . (int)$this->config->get('config_language_id') . "'
																AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "'
																ORDER BY o.sort_order ASC, ov.sort_order ASC");

		return $options_query->rows;
	}

	public function getProductMainCategory($product_id){
		$category_query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'product_to_category p2c WHERE product_id = ' . $product_id . ' LIMIT 1');

		if($category_query->num_rows){
			return $category_query->row['category_id'];
		} else {
			return 0;
		}
	}

	public function addCategory($data) {

		$category_id = (int)$data['Id'];
		$parent_id = (int)$data['ParentId'];
		$this->db->query("INSERT INTO " . DB_PREFIX . "category
										SET
											category_id = '" . (int)$category_id . "',
											parent_id = '" . (int)$parent_id . "',
											`top` = '0',
											`column` = '0',
											image = '',
											sort_order = '0',
											status = '1',
											date_modified = NOW(),
											date_added = NOW()");


		$this->db->query("DELETE FROM " . DB_PREFIX . "category_description WHERE category_id = '" . (int)$category_id . "'");

		foreach ($this->getLanguages() as $language_id) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "category_description
										SET
											category_id = '" . (int)$category_id . "',
											language_id = '" . (int)$language_id . "',
											name = '" . $this->db->escape($data['Name']) . "',
											description = '',
											meta_title = '" . $this->db->escape($data['Name']) . "',
											meta_description = '',
											meta_keyword = ''");
		}


		$this->db->query("DELETE FROM " . DB_PREFIX . "category_path WHERE category_id = '" . (int)$category_id . "'");
		// MySQL Hierarchical Data Closure Table Pattern
		$level = 0;

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$parent_id . "' ORDER BY `level` ASC");

		foreach ($query->rows as $result) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET `category_id` = '" . (int)$category_id . "', `path_id` = '" . (int)$result['path_id'] . "', `level` = '" . (int)$level . "'");

			$level++;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET `category_id` = '" . (int)$category_id . "', `path_id` = '" . (int)$category_id . "', `level` = '" . (int)$level . "'");

		$this->db->query("DELETE FROM " . DB_PREFIX . "category_to_store WHERE category_id = '" . (int)$category_id . "'");
		$this->db->query("INSERT INTO " . DB_PREFIX . "category_to_store SET category_id = '" . (int)$category_id . "', store_id = '" . (int)$this->config->get('config_store_id') . "'");

		$this->db->query("DELETE FROM " . DB_PREFIX . "seo_url WHERE query = 'category_id=" . (int)$category_id . "'");
		$keyword = $this->seo($data['Name']);

		$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url
												SET
													store_id = '" . (int)$this->config->get('config_store_id') . "',
													language_id = '" . (int)$this->config->get('config_language_id') . "',
													query = 'category_id=" . (int)$category_id . "',
													keyword = '" . $this->db->escape($keyword) . "'");

		return $category_id;
	}

	public function setProduct($data){

		$manufacturer_id = 0;

        if(!is_null($data['Brand'])){
            $manufacturer_id = $this->getManufacturer($data['Brand']);
        }

		$image_url = null;
		$image_urls = null;

		if(!is_null($data['Images'])){
			$image_url = '';
			$image_urls = array();;

			if(is_array($data['Images'])){
				foreach($data['Images'] as $image){
					if(!$image_url){
						$image_url = $image;
					} else {
						$image_urls[] = $image;
					}
				}
			}
		}

		$price = null;
		$special = null;

		if(!is_null($data['Price'])){
			$price = $data['Price'];
		}

		if(!is_null($data['OldPrice'])){
			if((int)$data['OldPrice'] == 0){
				$special = 0;
			} else {
				$price = $data['OldPrice'];
				if(!is_null($data['Price'])){
					$special = $data['Price'];
				}
			}

		}



		$product_attributes = null;

		if(!is_null($data['Characteristics'])){
			$product_attributes = array();

			if(is_array($data['Characteristics'])){
				foreach($data['Characteristics'] as $attribute){
					$attribute_id = $this->getAttribute($attribute['Name']);
					$product_attributes[] = array(
						'attribute_id' => $attribute_id,
						'text' => $attribute['Value'],
					);
				}
			}
		}


		$product_options = null;

		if(!is_null($data['Variants'])){
			$product_options = array();

			if(is_array($data['Variants'])){
				$product_options_t = array();
				foreach($data['Variants'] as $option){
					$option_name = '';
					$value_name = '';
					$mappings_name = array();
					$mappings_value = array();
					foreach($option['Mapping'] as $map){
						$mappings_name[] = $map['Name'];
						$mappings_value[] = $map['Value'];
					}

					if($mappings_value){
						$option_name = implode('/', $mappings_name);
					}
					if($mappings_name){
						$value_name = implode('/', $mappings_value);
					}
					if($option_name && $value_name){

						$product_options_t[md5($option_name)]['name'] = $option_name;

						$product_options_t[md5($option_name)]['values'][] = array(
							'product_option_value_id' => $option['Id'],
							'sku' => $option['Sku'],
							'quantity' => $option['Quantity'],
							'price' => $option['Price'],
							'name' => $value_name,

						);
					}
				}

				foreach($product_options_t as $option){

					$option_id = $this->getOptionId($option['name']);
					$values_id = array();

					foreach($option['values'] as $value){
						$value['option_value_id'] = $this->getOptionValueId($option['name'], $value['name']);
						$values[] = $value;
					}

					$product_options[] = array(
						'option_id' => $option_id,
						'option_values' => $values,
					);

				}
			}
		}

		$tags = null;
		if(!is_null($data['Tags'])){
			$tags = implode(',', $data['Tags']);
		}

	 	$product_data = array(
            'product_id' => $data['Id'],
            'model' => $data['Sku'],
            'sku' => $data['Sku'],
            'upc' => $data['UPC'],
            'mpn' => $data['MPN'],
            'manufacturer_id' => $manufacturer_id,

            'name' => $data['Name'],
            'description' => $data['Description'],
            'tag' => $tags,

            'image_url' => $image_url,
            'image_urls' => $image_urls,
            'product_category_id' => $data['CategoryId'],

            'price' => $price,
            'special' => $special,
            'quantity' => (int)$data['Quantity'],
            'minimum' => (int)$data['MinQty'],
            'status' => (int)$data['Available'],

		    'weight' => $data['Weight'],
		    'length' => $data['Length'],
		    'height' => $data['Height'],

            'product_options' => $product_options,
            'product_attributes' => $product_attributes,
            'keyword' => $this->seo($data['Name']),
        );


        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'product WHERE product_id = ' . $product_data['product_id']);

        if($query->num_rows){
            return $this->editProduct($query->row['product_id'], $product_data);
        } else {
        		$query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'product WHERE sku = "' . $product_data['sku'] . '"');

			if($query->num_rows){
            		return $this->editProduct($query->row['product_id'], $product_data);
			} else {
			 	return $this->addProduct($product_data);
			}
        }
	}


	public function addProduct($data){
		$this->db->query("INSERT INTO " . DB_PREFIX . "product
														SET
															product_id = '" . $this->db->escape($data['product_id']) . "',
															sku = '" . $this->db->escape($data['sku']) . "',
															model = '" . $this->db->escape($data['model']) . "',
															upc = '" . $this->db->escape($data['upc']) . "',
															mpn = '" . $this->db->escape($data['mpn']) . "',
															ean = '',
															jan = '',
															isbn = '',
															location = '',
															quantity = '" . (int)$data['quantity'] . "',
															minimum = '" . (int)$data['minimum'] . "',
															subtract = '',
															stock_status_id = '0',
															date_available = '',
															manufacturer_id = '" . (int)$data['manufacturer_id'] . "',
															shipping = '1',
															price = '" . (float)$data['price'] . "',
															points = '',
															weight = '" . (float)$data['weight'] . "',
															weight_class_id = '" . (int)$this->config->get('config_weight_class_id') . "',
															length = '" . (float)$data['length'] . "',
															width = '',
															height = '" . (float)$data['height'] . "',
															length_class_id = '" . (int)$this->config->get('config_length_class_id') . "',
															status = '" . (int)$data['status'] . "',
															tax_class_id = '',
															sort_order = '',
															date_added = NOW()");

		$product_id = $this->db->getLastId();

		if (!is_null($data['image_url'])) {
            $image = $this->loadImage($product_id, $data['image_url']);
            $this->db->query("UPDATE " . DB_PREFIX . "product
                                                        SET
                                                            image = '" . $this->db->escape(html_entity_decode($image, ENT_QUOTES, 'UTF-8')) . "'
                                                        WHERE
                                                            product_id = '" . (int)$product_id . "'");
        }



		foreach ($this->getLanguages() as $language_id) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "product_description
														SET
															product_id = '" . (int)$product_id . "',
															language_id = '" . (int)$language_id . "',
															name = '" . $this->db->escape($data['name']) . "',
															description = '" . $this->db->escape($data['description']) . "',
															tag = '" . $this->db->escape($data['tag']) . "',
															meta_title = '" . $this->db->escape($data['name']) . "',
															meta_description = '',
															meta_keyword = ''");
		}

		$this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$this->config->get('config_store_id') . "'");


		if (!is_null($data['product_attributes'])) {
			foreach ($data['product_attributes'] as $product_attribute) {
				$this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$product_attribute['attribute_id'] . "'");

				foreach ($this->getLanguages() as $language_id) {
					$this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$product_attribute['attribute_id'] . "' AND language_id = '" . (int)$language_id . "'");
					$this->db->query("INSERT INTO " . DB_PREFIX . "product_attribute SET product_id = '" . (int)$product_id . "', attribute_id = '" . (int)$product_attribute['attribute_id'] . "', language_id = '" . (int)$language_id . "', text = '" .  $this->db->escape($product_attribute['text']) . "'");
				}
			}
		}

		$result_options = array();

		if (!is_null($data['product_options'])) {
			foreach ($data['product_options'] as $product_option) {

				$this->db->query("INSERT INTO " . DB_PREFIX . "product_option
															SET
																product_id = '" . (int)$product_id . "',
																option_id = '" . (int)$product_option['option_id'] . "',
																required = '1'");

				$product_option_id = $this->db->getLastId();

				foreach ($product_option['option_values'] as $option_value) {


					$this->db->query("INSERT INTO " . DB_PREFIX . "product_option_value
															SET
																product_option_id = '" . (int)$product_option_id . "',
																product_id = '" . (int)$product_id . "',
																option_id = '" . (int)$product_option['option_id'] . "',
																option_value_id = '" . (int)$option_value['option_value_id'] . "',
																quantity = '" . (int)$option_value['quantity'] . "',
																sku = '" . $this->db->escape($option_value['sku']) . "',
																subtract = '1',
																price = '" . (float)$option_value['price'] . "',
																price_prefix = '+',
																points = '',
																points_prefix = '',
																weight = '',
																weight_prefix = ''");

					$product_option_value_id = $this->db->getLastId();

					$result_options[] = array(
						'Id' => $product_option_value_id,
						'Sku' => $option_value['sku'],
					);

				}
			}
		}


		if (!is_null($data['special'])) {
			if ($data['special']) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_special
												SET
													product_id = '" . (int)$product_id . "',
													customer_group_id = '1',
													priority = '1',
													price = '" . (float)$data['special'] . "',
													date_start = '',
													date_end = ''");

			}
		}

		if (!is_null($data['image_urls'])) {
            $i = 0;
            foreach ($data['image_urls'] as $image_url) {
                $i++;
                $image = $this->loadImage($product_id, $image_url);

                $this->db->query("INSERT INTO " . DB_PREFIX . "product_image
                                                        SET
                                                            product_id = '" . (int)$product_id . "',
                                                            image = '" . $this->db->escape(html_entity_decode($image, ENT_QUOTES, 'UTF-8')) . "',
                                                            sort_order = '" . $i . "'
                                                        ");
            }
        }






		if (!is_null($data['product_category_id'])) {
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "' AND category_id = '" . (int)$data['product_category_id'] . "'");
			$this->db->query("INSERT INTO " . DB_PREFIX . "product_to_category SET product_id = '" . (int)$product_id . "', category_id = '" . (int)$data['product_category_id'] . "'");
		}


		if ($data['keyword']) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url
												SET
													store_id = '" . (int)$this->config->get('config_store_id') . "',
													language_id = '" . (int)$this->config->get('config_language_id') . "',
													query = 'product_id=" . (int)$product_id . "',
													keyword = '" . $this->db->escape($data['keyword']) . "'");
		}


		$return_data = array(
			'Id' => $product_id,
			'sku' => $data['sku'],
			'Variants' => $result_options
		);

		return $return_data;
	}

	public function editProduct($product_id, $data){
		$implode = array();

		// sku = '" . $this->db->escape($data['sku']) . "',
		// model = '" . $this->db->escape($data['model']) . "',
		// upc = '" . $this->db->escape($data['upc']) . "',
		// mpn = '" . $this->db->escape($data['mpn']) . "',
		// quantity = '" . (int)$data['quantity'] . "',
		// minimum = '" . (int)$data['minimum'] . "',
		// manufacturer_id = '" . (int)$data['manufacturer_id'] . "',
		// price = '" . (float)$data['price'] . "',
		// weight = '" . (float)$data['weight'] . "',
		// length = '" . (float)$data['length'] . "',
		// height = '" . (float)$data['height'] . "',
		// status = '" . (int)$data['status'] . "',

		if(!is_null($data['sku'])){
			$implode[] = "sku = '" . $this->db->escape($data['sku']) . "'";
		}
		if(!is_null($data['model'])){
			$implode[] = "model = '" . $this->db->escape($data['model']) . "'";
		}
		if(!is_null($data['upc'])){
			$implode[] = "upc = '" . $this->db->escape($data['upc']) . "'";
		}
		if(!is_null($data['mpn'])){
			$implode[] = "mpn = '" . $this->db->escape($data['mpn']) . "'";
		}
		if(!is_null($data['quantity'])){
			$implode[] = "quantity = '" . (int)$data['quantity'] . "'";
		}
		if(!is_null($data['minimum'])){
			$implode[] = "minimum = '" . (int)$data['minimum'] . "'";
		}
		if(!is_null($data['manufacturer_id'])){
			$implode[] = "manufacturer_id = '" . (int)$data['manufacturer_id'] . "'";
		}
		if(!is_null($data['price'])){
			$implode[] = "price = '" . (float)$data['price'] . "'";
		}
		if(!is_null($data['weight'])){
			$implode[] = "weight = '" . (float)$data['weight'] . "'";
		}
		if(!is_null($data['length'])){
			$implode[] = "length = '" . (float)$data['length'] . "'";
		}
		if(!is_null($data['height'])){
			$implode[] = "height = '" . (float)$data['height'] . "'";
		}
		if(!is_null($data['status'])){
			$implode[] = "status = '" . (int)$data['status'] . "'";
		}

		$implode_sql = implode(', ', $implode);

		$this->db->query("UPDATE " . DB_PREFIX . "product
														SET
															" . $implode_sql . ",
															weight_class_id = '" . (int)$this->config->get('config_weight_class_id') . "',
															length_class_id = '" . (int)$this->config->get('config_length_class_id') . "',
															date_modified = NOW()
														WHERE
															product_id = " . $product_id);


		if (!is_null($data['image_url'])) {
            $image = $this->loadImage($product_id, $data['image_url']);
            $this->db->query("UPDATE " . DB_PREFIX . "product
                                                        SET
                                                            image = '" . $this->db->escape(html_entity_decode($image, ENT_QUOTES, 'UTF-8')) . "'
                                                        WHERE
                                                            product_id = '" . (int)$product_id . "'");
        }

		$implode = array();

		// name = '" . $this->db->escape($data['name']) . "',
		// description = '" . $this->db->escape($data['description']) . "',
		// tag = '" . $this->db->escape($data['tag']) . "',
		// meta_title = '" . $this->db->escape($data['name']) . "',

		if(!is_null($data['name'])){
			$implode[] = "name = '" . $this->db->escape($data['name']) . "'";
			$implode[] = "meta_title = '" . $this->db->escape($data['name']) . "'";
		}

		if(!is_null($data['description'])){
			$implode[] = "description = '" . $this->db->escape($data['description']) . "'";
		}

		if(!is_null($data['tag'])){
			$implode[] = "tag = '" . $this->db->escape($data['tag']) . "'";
		}


		$implode_sql = implode(', ', $implode);

		if($implode_sql){
			//foreach ($this->getLanguages() as $language_id) {

				$this->db->query("UPDATE " . DB_PREFIX . "product_description
															SET
																" . $implode_sql . " WHERE product_id = " . $product_id . " AND language_id = " . (int)$this->config->get('config_language_id'));
			//}
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "product_to_store WHERE product_id = '" . (int)$product_id . "'");
		$this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$this->config->get('config_store_id') . "'");


		if(!is_null($data['product_attributes'])){
			//$this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "'");

			foreach ($data['product_attributes'] as $product_attribute) {
				//$this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$product_attribute['attribute_id'] . "'");

				//foreach ($this->getLanguages() as $language_id) {
					//$this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$product_attribute['attribute_id'] . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");
					$this->db->query("REPLACE INTO " . DB_PREFIX . "product_attribute SET product_id = '" . (int)$product_id . "', attribute_id = '" . (int)$product_attribute['attribute_id'] . "', language_id = '" . (int)$this->config->get('config_language_id') . "', text = '" .  $this->db->escape($product_attribute['text']) . "'");
				//}
			}
		}

		if(!is_null($data['product_options'])){
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_option WHERE product_id = '" . (int)$product_id . "'");
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . (int)$product_id . "'");

			$result_options = array();
			foreach ($data['product_options'] as $product_option) {

				$this->db->query("INSERT INTO " . DB_PREFIX . "product_option
															SET
																product_id = '" . (int)$product_id . "',
																option_id = '" . (int)$product_option['option_id'] . "',
																required = '1'");

				$product_option_id = $this->db->getLastId();

				foreach ($product_option['option_values'] as $option_value) {

					$this->db->query("INSERT INTO " . DB_PREFIX . "product_option_value
															SET
																product_option_id = '" . (int)$product_option_id . "',
																product_id = '" . (int)$product_id . "',
																product_option_value_id = '" . (int)$option_value['product_option_value_id'] . "',
																option_id = '" . (int)$product_option['option_id'] . "',
																option_value_id = '" . (int)$option_value['option_value_id'] . "',
																quantity = '" . (int)$option_value['quantity'] . "',
																sku = '" . $this->db->escape($option_value['sku']) . "',
																subtract = '1',
																price = '" . (float)$option_value['price'] . "',
																price_prefix = '+',
																points = '',
																points_prefix = '',
																weight = '',
																weight_prefix = ''");

					$product_option_value_id = $this->db->getLastId();

					$result_options[] = array(
						'Id' => $product_option_value_id,
						'Sku' => $option_value['sku'],
					);
				}
			}
		}




		if(!is_null($data['special'])){
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id = '" . (int)$product_id . "'");
			if($data['special']){
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_special
											SET
												product_id = '" . (int)$product_id . "',
												customer_group_id = '1',
												priority = '1',
												price = '" . (float)$data['special'] . "',
												date_start = '',
												date_end = ''");
			}



		}

		if(!is_null($data['image_urls'])){
			$this->db->query("DELETE FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int)$product_id . "'");

			if (isset($data['image_urls'])) {
	            $i = 0;
	            foreach ($data['image_urls'] as $image_url) {
	                $i++;
	                $image = $this->loadImage($product_id, $image_url);

	                $this->db->query("INSERT INTO " . DB_PREFIX . "product_image
	                                                        SET
	                                                            product_id = '" . (int)$product_id . "',
	                                                            image = '" . $this->db->escape(html_entity_decode($image, ENT_QUOTES, 'UTF-8')) . "',
	                                                            sort_order = '" . $i . "'
	                                                        ");
	            }
	        }
		}

		if(!is_null($data['product_category_id'])){
			//$this->db->query("DELETE FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");

			if(isset($data['product_category_id']) && $data['product_category_id'] > 0) {
				$this->db->query("DELETE FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "' AND category_id = '" . (int)$data['product_category_id'] . "'");
				$this->db->query("INSERT INTO " . DB_PREFIX . "product_to_category SET product_id = '" . (int)$product_id . "', category_id = '" . (int)$data['product_category_id'] . "'");
			}
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "seo_url WHERE query = 'product_id=" . (int)$product_id . "' AND language_id = "  .  (int)$this->config->get('config_language_id'));

		if ($data['keyword']) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url
												SET
													store_id = '" . (int)$this->config->get('config_store_id') . "',
													language_id = '" . (int)$this->config->get('config_language_id') . "',
													query = 'product_id=" . (int)$product_id . "',
													keyword = '" . $this->db->escape($data['keyword']) . "'");

		}


		$return_data = array(
			'Id' => $product_id,
			'sku' => $data['sku'],
			'Variants' => $result_options
		);

		return $return_data;
	}


	public function setDefaults(){
        $manufacturers_query = $this->db->query('SELECT manufacturer_id, name FROM ' . DB_PREFIX . 'manufacturer');
        foreach($manufacturers_query->rows as $row){
            $this->manufacturers[md5(mb_strtolower($row['name']))] = $row['manufacturer_id'];
        }

        $attributes_query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'attribute_description WHERE language_id = ' . (int)$this->config->get('config_language_id'));
        foreach($attributes_query->rows as $row){
            $this->attributes[md5(mb_strtolower($row['name']))] = $row['attribute_id'];
        }


        $options_query = $this->db->query('SELECT * FROM `' . DB_PREFIX . 'option` o LEFT JOIN ' . DB_PREFIX . 'option_description od ON (o.option_id = od.option_id) AND language_id = ' . (int)$this->config->get('config_language_id'));
        foreach($options_query->rows as $row){
              $this->options[$row['option_id']] = array(
                'name' => $row['name'],
                'option_id' => $row['option_id'],
                'values' => array(),
              );
        }

        $options_values_query = $this->db->query('SELECT * FROM `' . DB_PREFIX . 'option_value` ov LEFT JOIN ' . DB_PREFIX . 'option_value_description ovd ON (ov.option_value_id = ovd.option_value_id)');

        foreach($options_values_query->rows as $row){
            $this->options[$row['option_id']]['values'][md5(mb_strtolower($row['name']))] = array(
                'name' => $row['name'],
                'option_value_id' => $row['option_value_id'],
            );
        }
        $temp_options = $this->options;
        $this->options = array();
        foreach($temp_options as $option){
            $this->options[md5(mb_strtolower($option['name']))] = $option;
        }
    }

	private function getAttribute($name){
          $attribute_id = 0;
          if($name){
                $attribute_hash = md5(mb_strtolower($name));

                if(isset($this->attributes[$attribute_hash])){
                    $attribute_id = $this->attributes[$attribute_hash];
                } else {


					$group_query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'attribute_group');
					if($group_query->num_rows){
						$attribute_group_id = $group_query->row['attribute_group_id'];
					} else {
						$this->db->query("INSERT INTO " . DB_PREFIX . "attribute_group SET sort_order = '0'");

						$attribute_group_id = $this->db->getLastId();

						foreach ($this->getLanguages() as $language_id) {
							$this->db->query("INSERT INTO " . DB_PREFIX . "attribute_group_description
															SET
																attribute_group_id = '" . (int)$attribute_group_id . "',
																language_id = '" . (int)$language_id . "',
																name = 'Характеристики'");
						}
					}



                    $this->db->query("INSERT INTO " . DB_PREFIX . "attribute
                                                SET
                                                		attribute_group_id = '" . $attribute_group_id . "'
                                                ");

                    $attribute_id = $this->db->getLastId();

                    foreach($this->getLanguages() as $language_id){
                        $this->db->query("INSERT INTO " . DB_PREFIX . "attribute_description
                                                            SET
                                                                attribute_id = '" . (int)$attribute_id . "',
                                                                language_id = '" . (int)$language_id . "',
                                                                name = '" . $this->db->escape($name) . "'");

                    }

                    $this->attributes[md5(mb_strtolower($name))] = $attribute_id;
              }
          }

          return $attribute_id;
     }
	private function getManufacturer($name){
          $manufacturer_id = 0;
          if($name){
                $manufacturer_hash = md5(mb_strtolower($name));

                if(isset($this->manufacturers[$manufacturer_hash])){
                    $manufacturer_id = $this->manufacturers[$manufacturer_hash];
                } else {

                    $this->db->query("INSERT INTO " . DB_PREFIX . "manufacturer
                                                SET name = '" . $this->db->escape($name) . "'
                                                ");

                    $manufacturer_id = $this->db->getLastId();

                    // foreach($this->getLanguages() as $language_id){
                        // $this->db->query("INSERT INTO " . DB_PREFIX . "manufacturer_description
                                                            // SET
                                                                // manufacturer_id = '" . (int)$manufacturer_id . "',
                                                                // language_id = '" . (int)$language_id . "',
                                                                // name = '" . $this->db->escape($name) . "',
                                                                // description = '',
                                                                // meta_title = '" . $this->db->escape($name) . "',
                                                                // meta_h1 = '',
                                                                // meta_description = '',
                                                                // meta_keyword = ''");
//
                    // }

                    $this->db->query("INSERT INTO " . DB_PREFIX . "manufacturer_to_store
                                                SET
                                                    manufacturer_id = '" . (int)$manufacturer_id . "',
                                                    store_id = '" . (int)$this->config->get('config_store_id') . "'
                                                    ");




					$keyword = $this->seo($name);

					$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url
												SET
													store_id = '" . (int)$this->config->get('config_store_id') . "',
													language_id = '" . (int)$this->config->get('config_language_id') . "',
													query = 'manufacturer_id=" . (int)$manufacturer_id . "',
													keyword = '" . $this->db->escape($keyword) . "'");



                    $this->manufacturers[md5(mb_strtolower($name))] = $manufacturer_id;
              }
          }

          return $manufacturer_id;
     }


	private function getOptionId($option_name){

		$option_hash = md5(mb_strtolower($option_name));
        if(isset($this->options[$option_hash])){
            $option_id = $this->options[$option_hash]['option_id'];
        } else {
            $option_id = $this->addOption($option_name);
        }

		return $option_id;
	}

	private function getOptionValueId($option_name, $value_name){
		$option_hash = md5(mb_strtolower($option_name));
		$value_hash = md5(mb_strtolower($value_name));

        if(isset($this->options[$option_hash]['values'][$value_hash])){
            $option_value_id = $this->options[$option_hash]['values'][$value_hash]['option_value_id'];
        } else {
            $option_value_id = $this->addOptionValue($option_name, (string)$value_name);
        }

		return $option_value_id;
	}

    private function addOption($name){
        $this->db->query("INSERT INTO `" . DB_PREFIX . "option` SET type = 'select', sort_order = '0'");

        $option_id = $this->db->getLastId();


		foreach($this->getLanguages() as $language_id){
	        $this->db->query("INSERT INTO " . DB_PREFIX . "option_description
	                            SET
	                                option_id = '" . (int)$option_id . "',
	                                language_id = '" . (int)$language_id  . "',
	                                name = '" . $this->db->escape($name) . "'");
		}
        $this->options[md5(mb_strtolower($name))] = array(
                'name' => $name,
                'option_id' => $option_id,
                'values' => array(),
              );

        return $option_id;
    }

    private function addOptionValue($option_name, $value_name){
    		$option_hash = md5(mb_strtolower($option_name));
		$option_id = $this->getOptionId($option_name);

        $this->db->query("INSERT INTO " . DB_PREFIX . "option_value
                            SET
                                option_id = '" . (int)$option_id . "',
                                image = '',
                                sort_order = '0'
                          ");

        $option_value_id = $this->db->getLastId();
        foreach($this->getLanguages() as $language_id){
	        $this->db->query("INSERT INTO " . DB_PREFIX . "option_value_description
	                            SET
	                                option_value_id = '" . (int)$option_value_id . "',
	                                language_id = '" . (int)$language_id  . "',
	                                option_id = '" . (int)$option_id . "',
	                                name = '" . $this->db->escape($value_name) . "'");
		}

        $this->options[$option_hash]['values'][md5(mb_strtolower($value_name))] = array(
                'name' => $value_name,
                'option_value_id' => $option_value_id
              );

        return $option_value_id;

    }


	private function getLanguages(){
	     if(!$this->languages){

	     	$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "language ORDER BY sort_order, name");

			foreach ($query->rows as $result) {
				$this->languages[] = $result['language_id'];
			}
	     }

	     return $this->languages;
    }

	public function seo($name){
          return $this->toAscii(trim(html_entity_decode($name)));
    }

    public function toAscii($string){
          // cz
          $source[] = '/а/'; $replace[] = 'a';
          $source[] = '/б/'; $replace[] = 'b';
          $source[] = '/в/'; $replace[] = 'v';
          $source[] = '/г/'; $replace[] = 'g';
          $source[] = '/д/'; $replace[] = 'd';
          $source[] = '/е/'; $replace[] = 'e';
          $source[] = '/ё/'; $replace[] = 'e';
          $source[] = '/ж/'; $replace[] = 'zh';
          $source[] = '/з/'; $replace[] = 'z';
          $source[] = '/и/'; $replace[] = 'i';
          $source[] = '/і/'; $replace[] = 'i';
          $source[] = '/й/'; $replace[] = 'y';
          $source[] = '/к/'; $replace[] = 'k';
          $source[] = '/л/'; $replace[] = 'l';
          $source[] = '/м/'; $replace[] = 'm';
          $source[] = '/н/'; $replace[] = 'n';
          $source[] = '/о/'; $replace[] = 'o';
          $source[] = '/п/'; $replace[] = 'p';
          $source[] = '/р/'; $replace[] = 'r';
          $source[] = '/с/'; $replace[] = 's';
          $source[] = '/т/'; $replace[] = 't';
          $source[] = '/у/'; $replace[] = 'u';
          $source[] = '/ф/'; $replace[] = 'f';
          $source[] = '/х/'; $replace[] = 'h';
          $source[] = '/ц/'; $replace[] = 'ts';
          $source[] = '/ч/'; $replace[] = 'ch';
          $source[] = '/ш/'; $replace[] = 'sh';
          $source[] = '/щ/'; $replace[] = 'shch';
          $source[] = '/ъ/'; $replace[] = '';
          $source[] = '/ы/'; $replace[] = 'y';
          $source[] = '/ь/'; $replace[] = '';
          $source[] = '/э/'; $replace[] = 'e';
          $source[] = '/ю/'; $replace[] = 'yu';
          $source[] = '/я/'; $replace[] = 'ya';



          // CZ
          $source[] = '/А/'; $replace[] = 'A';
          $source[] = '/Б/'; $replace[] = 'B';
          $source[] = '/В/'; $replace[] = 'V';
          $source[] = '/Г/'; $replace[] = 'G';
          $source[] = '/Д/'; $replace[] = 'D';
          $source[] = '/Е/'; $replace[] = 'E';
          $source[] = '/Ё/'; $replace[] = 'E';
          $source[] = '/Ж/'; $replace[] = 'ZH';
          $source[] = '/З/'; $replace[] = 'Z';
          $source[] = '/И/'; $replace[] = 'I';
          $source[] = '/І/'; $replace[] = 'I';
          $source[] = '/Й/'; $replace[] = 'J';
          $source[] = '/К/'; $replace[] = 'K';
          $source[] = '/Л/'; $replace[] = 'L';
          $source[] = '/М/'; $replace[] = 'M';
          $source[] = '/Н/'; $replace[] = 'N';
          $source[] = '/О/'; $replace[] = 'O';
          $source[] = '/П/'; $replace[] = 'P';
          $source[] = '/Р/'; $replace[] = 'R';
          $source[] = '/С/'; $replace[] = 'S';
          $source[] = '/Т/'; $replace[] = 'T';
          $source[] = '/У/'; $replace[] = 'U';
          $source[] = '/Ф/'; $replace[] = 'F';
          $source[] = '/Х/'; $replace[] = 'H';
          $source[] = '/Ц/'; $replace[] = 'TS';
          $source[] = '/Ч/'; $replace[] = 'CH';
          $source[] = '/Ш/'; $replace[] = 'SH';
          $source[] = '/Щ/'; $replace[] = 'SHCH';
          $source[] = '/Ъ/'; $replace[] = '';
          $source[] = '/Ы/'; $replace[] = 'Y';
          $source[] = '/Ь/'; $replace[] = '';
          $source[] = '/Э/'; $replace[] = 'E';
          $source[] = '/Ю/'; $replace[] = 'YU';
          $source[] = '/Я/'; $replace[] = 'YA';


          $string = preg_replace($source, $replace, $string);

          for ($i=0; $i<strlen($string); $i++)
          {
          if ($string[$i] >= 'a' && $string[$i] <= 'z') continue;
          if ($string[$i] >= 'A' && $string[$i] <= 'Z') continue;
          if ($string[$i] >= '0' && $string[$i] <= '9') continue;
          $string[$i] = '-';
          }
          $string = str_replace("--","-",$string);
          return $string;
     }

	private function loadImage($product_id, $image){

          $folder = 'catalog/import_products/';

          if(!file_exists(DIR_IMAGE . $folder)){
               mkdir(DIR_IMAGE . $folder, 0777);
          }

          $image_folder = $folder . $product_id . '/';

          $image_path = pathinfo($image);
          $image_name = $image_folder . $image_path['basename'];

		  if(empty($image_path['extension'])){
		  	$image_name .= '.jpg';
		  }



          if(!file_exists(DIR_IMAGE . $image_folder)){
               mkdir(DIR_IMAGE . $image_folder, 0777);
          }
          if(!file_exists(DIR_IMAGE . $image_name)){
               copy($image, DIR_IMAGE . $image_name);
          }
          return $image_name;
     }

	public function getOrders($date_from = false, $ids = array()){
		$return_data = array();


		$sql = "SELECT *, os.name as order_status FROM `" . DB_PREFIX . "order` o
													LEFT JOIN `" . DB_PREFIX . "order_status` os ON (os.order_status_id = o.order_status_id)
													WHERE
														o.order_status_id > 0
														AND os.language_id = '" . (int)$this->config->get('config_language_id') . "'";
		
		if($date_from){
			$sql .= " AND DATE(date_modified) >= DATE('" . date('Y-m-d', strtotime($data_from)). "')";
		}
		if($ids){
			$sql .= " AND o.order_id IN (" . implode(', ', $ids) . ")";
		}
		
		$orders_query = $this->db->query($sql);
		
		if($orders_query->num_rows){
			foreach($orders_query->rows as $order_row){

				$order_products = array();
				$order_products_query = $this->db->query('SELECT op.*, p.image, p.sku FROM ' . DB_PREFIX . 'order_product op
																	LEFT JOIN ' . DB_PREFIX . 'product p ON (p.product_id = op.product_id)
																	WHERE
																		op.order_id = ' . $order_row['order_id']);

				foreach($order_products_query->rows as $product_row){

					$order_product_options = array();
					$order_product_options_query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'order_option WHERE order_product_id = ' . $product_row['order_product_id']);

					foreach($order_product_options_query->rows as $option_row){
						$order_product_options[] = array(
							'product_option_id' => $option_row['product_option_id'],
							'product_option_value_id' => $option_row['product_option_value_id'],
							'name' => $option_row['name'],
							'value' => $option_row['value'],
						);
					}

					$order_products[] = array(
						'order_product_id' => $product_row['order_product_id'],
						'product_id' => $product_row['product_id'],
						'image' => $product_row['image'],
						'sku' => $product_row['sku'],
						'name' => $product_row['name'],
						'model' => $product_row['model'],
						'quantity' => $product_row['quantity'],
						'price' => $product_row['price'],
						'total' => $product_row['total'],
						'options' => $order_product_options,
					);
				}

				$order_totals = array();
				$order_totals_query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'order_total WHERE order_id = ' . $order_row['order_id'] . ' ORDER BY sort_order');

				foreach($order_totals_query->rows as $total_row){
					$order_totals[] = array(
						'code' => $total_row['code'],
						'title' => $total_row['title'],
						'value' => $total_row['value'],
					);
				}

				$country_code = '';
				$country_query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'country WHERE country_id = ' . $order_row['shipping_country_id']);
				if($country_query->num_rows){
					$country_code = $country_query->row['iso_code_2'];
				}

				$order_data = $order_row;	
				
				//Simple
				$simple_fields = array();
				$simple_check_query = $this->db->query('SELECT count(*) as total FROM information_schema.TABLES WHERE (TABLE_SCHEMA = "' . DB_DATABASE . '") AND (TABLE_NAME = "' . DB_PREFIX . 'order_simple_fields")');
				
				if($simple_check_query->row['total']){
					$simple_fields_query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'order_simple_fields WHERE order_id = ' . $order_row['order_id']);
					
					if($simple_fields_query->num_rows){
						$simple_fields = $simple_fields_query->row;
					}
				}
				
				$order_data['products'] = $order_products;
				$order_data['totals'] = $order_totals;
				$order_data['country_code'] = $country_code;
				$order_data['data'] = $simple_fields;
				
				$return_data[] = $order_data;
			}
		}
		
		return $return_data;
	}

	public function setOrders($orders){
		$return_data = array();

		$cancel_order_status_id = $this->config->get('module_tradeevo_fail_order_status_id');
		$complete_order_status_id = $this->config->get('module_tradeevo_success_order_status_id');


		foreach($orders as $order){
			$order_id = (int)$order['Id'];
			if($order['Status'] == 'Complete'){
				$order_status_id = $complete_order_status_id;
			} else {
				$order_status_id = $cancel_order_status_id;
			}

			$order_query = $this->db->query('SELECT * FROM `' . DB_PREFIX . 'order` WHERE order_id = ' . $order_id);
			if($order_query->num_rows){
				$this->db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id = '" . (int)$order_status_id . "', date_modified = NOW() WHERE order_id = '" . $order_id . "'");

				$this->db->query("INSERT INTO " . DB_PREFIX . "order_history
												SET
													order_id = '" . $order_id . "',
													order_status_id = '" . (int)$order_status_id . "',
													notify = '',
													comment = '',
													date_added = NOW()");
				$return_data = array(
					'Id' => $order_id
				);
			}

		}

		return $return_data;
	}
}
