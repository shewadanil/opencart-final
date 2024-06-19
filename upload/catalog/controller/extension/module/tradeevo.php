<?php
class ControllerExtensionModuleTradeevo extends Controller {
	public function __construct($registry) {
    		parent::__construct($registry);
	}

	public function getCategories() {
		$this->checkAuth();

		require_once(DIR_SYSTEM . 'library/tradeevo.php');
		$this->tradeevo = new TradeEvo($this->registry);
		$categories = $this->tradeevo->getCategories();

		$output = array();

		foreach($categories as $category){
			$output[] = array(
				'Id' => $category['category_id'],
				'ParentId' => $category['parent_id'],
				'Name' => $category['name'],
			);
		}

		$this->output($output);
	}

	public function getProducts() {
		$this->checkAuth();

		require_once(DIR_SYSTEM . 'library/tradeevo.php');
		$this->tradeevo = new TradeEvo($this->registry);

		if ($this->request->server['HTTPS']) {
			$server = $this->config->get('config_ssl');
		} else {
			$server = $this->config->get('config_url');
		}

		$output = array(
			'Total' => 0,
			'Products' => array(),
		);

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		if (isset($this->request->get['limit'])) {
			$limit = (int)$this->request->get['limit'];
		} else {
			$limit = (int)$this->config->get($this->config->get('config_theme') . '_product_limit');
		}


		$output['Total'] = $this->tradeevo->getProductsTotal();

		$filter_data = array(
			'start' => ($page - 1) * $limit,
			'limit' => $limit,
		);

		$products = $this->tradeevo->getProducts($filter_data);

		foreach($products as $product){

			if($product['special']){
				$price = $product['special'];
				$old_price = $product['price'];
			} else {
				$price = $product['price'];
				$old_price = 0;
			}

			$images = array();

			if($product['image']){
				$images[] = $server . 'image/' . $product['image'];
			}

			$images_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_image WHERE product_id = " . $product['product_id'] . " ORDER BY sort_order ASC");
			foreach($images_query->rows as $image){
				$images[] = $server . 'image/' . $image['image'];
			}

			$attributes = array();

			foreach($this->tradeevo->getProductAttributes($product['product_id']) as $attribute){
				$attributes[] = array(
					'Name' => $attribute['name'],
					'Value' => $attribute['text'],
					'DisplayOrder' => $attribute['sort_order'],
				);
			}

			$options = array();

			foreach($this->tradeevo->getProductOptions($product['product_id']) as $option){

				if($option['price_prefix'] == '-'){
					$option['price'] = -$option['price'];
				}

				$options[] = array(
					'Id' => $option['product_option_value_id'],
					'Sku' => '',
					'Price' => $option['price'],
					'Quantity' => $option['quantity'],
					'Mapping' => array(
						array(
							'Name' => $option['option_name'],
							'Value' => $option['value_name'],
						),
					),
				);
			}

			$category_id = $this->tradeevo->getProductMainCategory($product['product_id']);

			$output['Products'][] = array(
				'Id' => $product['product_id'],
				'Sku' => $product['model'],
				'Name' => $product['name'],
				'Description' => $product['description'],
				'Tags' => $product['tag'] ? explode(',', $product['tag']) : array(),
				'Brand' => $product['manufacturer'],
				'Price' => $price,
				'CategoryId' => $category_id,
				'OldPrice' => $old_price,
				'Quantity' => $product['quantity'],
				'MinQty' => $product['minimum'],
				'Weight' => $product['weight'],
				'Length' => $product['length'],
				'Height' => $product['height'],
				'Available' => (bool)$product['status'],
				'Images' => $images,
				'Variants' => $options,
				'Characteristics' => $attributes,
			);
		}


		$this->output($output);
	}


	public function getOrders() {
		$this->checkAuth();

		require_once(DIR_SYSTEM . 'library/tradeevo.php');
		$this->tradeevo = new TradeEvo($this->registry);

		if ($this->request->server['HTTPS']) {
			$server = $this->config->get('config_ssl');
		} else {
			$server = $this->config->get('config_url');
		}

		$output = array(
			'Orders' => array(),
		);

		if ($this->request->server['HTTPS']) {
			$server = $this->config->get('config_ssl');
		} else {
			$server = $this->config->get('config_url');
		}


		if(isset($this->request->get['date_from'])){
			$date_from = $this->request->get['date_from'];
		} else {
			$date_from = date('Y-m-d', strtotime('-1 week'));
		}
		
		
		$ids = array();
		if(isset($this->request->get['ids'])){
			foreach(explode(',', $this->request->get['ids']) as $order_id){
				$ids[] = (int)$order_id;
			}
		} 

		$orders = $this->tradeevo->getOrders($date_from, $ids);

		// echo "<pre>";
		// var_dump($orders);
		// die;

		$cancel_order_status_id = 7;
		$complete_order_status_id = 5;

		foreach($orders as $order){
			// if($order['order_status_id'] == $complete_order_status_id){
				// $status = 'Complete';
			// } else {
				// $status = 'Cancel';
			// }


      		$products = array();
	  		foreach($order['products'] as $product){

				$varian_id = 0;
				foreach($product['options'] as $option){
					$varian_id = $option['product_option_value_id'];
				}

	  			$products[] = array(
					'Id' => $product['order_product_id'],
					'ProductId' => $product['product_id'],
					'Sku' => $product['sku'],
					'VariantId' => $varian_id,
					'Name' => $product['name'],
					'Image' => $product['image'] ? $server . 'image/'. $product['image'] : '',
					'Price' => $product['price'],
					'Quantity' => $product['quantity'],
				);
	  		}


			$output['Orders'][] = array(
				'Id' => $order['order_id'],
				'Status' => $order['order_status'],
				'StatusId' =>$order['order_status_id'],
				'CreatedOnUtc' =>gmdate("Y-m-d H:i:s", strtotime($order['date_added'])),
				'Address' => array(
					'Company' => $order['shipping_company'],
					'FirstName' => $order['firstname'],
					//'MiddleName' => $order['MiddleName'],
					'MiddleName' => '',
					'LastName' => $order['lastname'],
					'Email' => $order['email'],
					'Phone' =>$order['telephone'],
					'Country' =>$order['country_code'],
					'StateProvince' => $order['payment_zone'],
					'City' => $order['shipping_city'],
					'Address1' => $order['shipping_address_1'],
					'Address2' => $order['shipping_address_2'],
					'PostCode' => $order['shipping_postcode'],
					// 'Appartment' => $order['appartment'],
					// 'Building' => $order['building'],
					// 'Street' => $order['street'],
					// 'Warehouse' => $order['warehouse'],
				),
				'PaymentMethod' => array(
					'Name' => $order['payment_code'],
					'Value' => $order['payment_method'],
				),
				'ShippingService' => array(
					'Name' => $order['shipping_code'],
					'Value' => $order['shipping_method'],
				),
				'OrderItems' => $products,
				'Data' => isset($order['data']) ? $order['data'] : false,
			);
		}


		$this->output($output);
	}

	public function setProducts(){
		$this->checkAuth();

		$output = array();

		$log_name = 'tradeevo.log';
		$this->registry->set('log', new Log($log_name));
		$this->log->write('START');

		require_once(DIR_SYSTEM . 'library/tradeevo.php');
		$this->tradeevo = new TradeEvo($this->registry);

		if(file_get_contents("php://input")){
			$input_data = file_get_contents("php://input");

			$this->log->write('PRODUCTS JSON ' . $input_data);

			$data = json_decode(file_get_contents("php://input"), true);

			if(!empty($data['Categories'])){
				foreach($data['Categories'] as $category){
					$query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'category WHERE category_id = ' . (int)$category['Id']);
					if(!$query->num_rows){
						$this->tradeevo->addCategory($category);
					}
				}
				$this->cache->delete('category');
			}

			if(!empty($data['Products'])){

				$this->tradeevo->setDefaults();

				foreach($data['Products'] as $product){
					$output['Products'][] = $this->tradeevo->setProduct($product);
				}

				$this->cache->delete('product');
			}
		} else {
			$output['Errors'][] = 'ERROR 1';
		}

		if($this->request->post){
			$this->log->write('PRODUCTS POST ' . json_encode($this->request->post));
		}


		$this->output($output);
	}



	public function setOrders(){
		$this->checkAuth();

		$output = array();

		$log_name = 'tradeevo.log';
		$this->registry->set('log', new Log($log_name));
		$this->log->write('START');

		require_once(DIR_SYSTEM . 'library/tradeevo.php');
		$this->tradeevo = new TradeEvo($this->registry);

		if(file_get_contents("php://input")){
			$input_data = file_get_contents("php://input");

			$this->log->write('ORDERS JSON ' . $input_data);

			$data = json_decode(file_get_contents("php://input"), true);

			if(!empty($data['Orders'])){
				$output['Orders'] = $this->tradeevo->setOrders($data['Orders']);
			}
		} else {
			$output['Errors'][] = 'ERROR 1';
		}

		if($this->request->post){
			$this->log->write('ORDERS POST ' . json_encode($this->request->post));
		}

		$this->output($output);
	}



	private function output($data){
		header('Content-Type: application/json');
		echo json_encode($data);
		exit();
	}

	public function testGetOrders(){
		$url = $this->url->link('extension/module/tradeevo/getOrders');
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL, $url);

		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		    'Signature: ' . base64_encode(sha1($this->config->get('module_tradeevo_private_key') . '' . $this->config->get('module_tradeevo_private_key'), 1)),
		));

		$result=curl_exec($ch);
		curl_close($ch);
		
		//echo $result;
		
		print_r(json_decode($result, true));
	}

	public function testGetProducts(){
		$url = $this->url->link('extension/module/tradeevo/getProducts');
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL, $url);

		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		    'Signature: ' . base64_encode(sha1($this->config->get('module_tradeevo_private_key') . '' . $this->config->get('module_tradeevo_private_key'), 1)),
		));

		$result=curl_exec($ch);
		curl_close($ch);

		echo $result;

	//	print_r(json_decode($result, true));
	}

	public function testGetCategories(){
		$url = $this->url->link('extension/module/tradeevo/getCategories');
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL, $url);
		$result=curl_exec($ch);
		curl_close($ch);
		print_r(json_decode($result, true));
	}

	public function testSetProducts(){
		$url = $this->url->link('extension/module/tradeevo/setProducts');

		$data_string = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/test_products.json');

		$ch=curl_init($url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER,
		    array(
		        'Content-Type:application/json',
		        'Content-Length: ' . strlen($data_string),
		        'Signature: ' . base64_encode(sha1($this->config->get('module_tradeevo_private_key') . $data_string . $this->config->get('module_tradeevo_private_key'), 1)),
		    )
		);

		$result = curl_exec($ch);

		echo $result	;
		print_r(json_decode($result, 1));
		curl_close($ch);
	}

	public function testSetOrders(){
		$url = $this->url->link('extension/module/tradeevo/setOrders');

		$data_string = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/test_orders.json');


		//$data_string = json_encode($data);

		$ch=curl_init($url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER,
			    array(
			        'Content-Type:application/json',
			        'Content-Length: ' . strlen($data_string)
			    )
			);

		$result = curl_exec($ch);

		echo $result	;
		print_r(json_decode($result, 1));
		curl_close($ch);
	}

	public function checkAuth(){
		if(!$this->config->get('module_tradeevo_status')){
			exit('Module TradeEVO disabled');
		}


		if($this->config->get('module_tradeevo_public_key')){
			$public_key = $this->config->get('tradeevo_public_key');
		} else {
			exit('Public key not set');
		}

		if($this->config->get('module_tradeevo_private_key')){
			$private_key = $this->config->get('module_tradeevo_private_key');
		} else {
			exit('Public key not set');
		}

		if($this->config->get('module_tradeevo_public_key')){
			$public_key = $this->config->get('module_tradeevo_public_key');
		}

		$body = file_get_contents('php://input');
		$signature = base64_encode(sha1($private_key . $body . $private_key, 1));

		$request_signature = isset($_SERVER['HTTP_SIGNATURE']) ? $_SERVER['HTTP_SIGNATURE'] : '';


		if($signature != $request_signature){
			exit('Auth failed');
		}
	}
}
