<?php
class ControllerExtensionModuleTradeevo extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/tradeevo');

		$this->document->setTitle($this->language->get('heading_title_unstiled'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_tradeevo', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/tradeevo', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/tradeevo', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		if (isset($this->request->post['module_tradeevo_status'])) {
			$data['module_tradeevo_status'] = $this->request->post['module_tradeevo_status'];
		} else {
			$data['module_tradeevo_status'] = $this->config->get('module_tradeevo_status');
		}


		if (isset($this->request->post['module_tradeevo_public_key'])) {
			$data['module_tradeevo_public_key'] = $this->request->post['module_tradeevo_public_key'];
		} else {
			$data['module_tradeevo_public_key'] = $this->config->get('module_tradeevo_public_key');
		}
		
		if (isset($this->request->post['module_tradeevo_private_key'])) {
			$data['module_tradeevo_private_key'] = $this->request->post['module_tradeevo_private_key'];
		} else {
			$data['module_tradeevo_private_key'] = $this->config->get('module_tradeevo_private_key');
		}

		if (isset($this->request->post['module_tradeevo_success_order_status_id'])) {
			$data['module_tradeevo_success_order_status_id'] = $this->request->post['module_tradeevo_success_order_status_id'];
		} else {
			$data['module_tradeevo_success_order_status_id'] = $this->config->get('module_tradeevo_success_order_status_id');
		}

		if (isset($this->request->post['module_tradeevo_fail_order_status_id'])) {
			$data['module_tradeevo_fail_order_status_id'] = $this->request->post['module_tradeevo_fail_order_status_id'];
		} else {
			$data['module_tradeevo_fail_order_status_id'] = $this->config->get('module_tradeevo_fail_order_status_id');
		}

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$data['categories_feed'] = HTTP_CATALOG . 'index.php?route=extension/module/tradeevo/getCategories';
		$data['products_feed'] = HTTP_CATALOG . 'index.php?route=extension/module/tradeevo/getProducts';
		$data['orders_feed'] = HTTP_CATALOG . 'index.php?route=extension/module/tradeevo/getOrders';
		$data['set_products_feed'] = HTTP_CATALOG . 'index.php?route=extension/module/tradeevo/setProducts';
		$data['set_orders_feed'] = HTTP_CATALOG . 'index.php?route=extension/module/tradeevo/setOrders';
		
		
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_public_key'] = $this->language->get('text_public_key');
		$data['text_private_key'] = $this->language->get('text_private_key');
		$data['text_categories_feed'] = $this->language->get('text_categories_feed');
		$data['text_products_feed'] = $this->language->get('text_products_feed');
		$data['text_products_feed_help'] = sprintf($this->language->get('text_products_feed_help'), $data['products_feed']);
		$data['text_orders_feed'] = $this->language->get('text_orders_feed');
		$data['text_orders_feed_help'] = sprintf($this->language->get('text_orders_feed_help'), $data['orders_feed'], date('Y-m-d', strtotime('-1 week')), $data['orders_feed']);
		$data['text_set_products_feed'] = $this->language->get('text_set_products_feed');
		$data['text_set_orders_feed'] = $this->language->get('text_set_orders_feed');
		$data['text_success_order_status_id'] = $this->language->get('text_success_order_status_id');
		$data['text_fail_order_status_id'] = $this->language->get('text_fail_order_status_id');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/tradeevo', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/tradeevo')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}