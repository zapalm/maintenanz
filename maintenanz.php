<?php
/**
 * Maintenance tool: module for PrestaShop 1.5-1.6
 *
 * @author zapalm <zapalm@ya.ru>
 * @copyright (c) 2014, zapalm
 * @link http://prestashop.modulez.ru/en/home/24-maintenance-tool.html The module's homepage
 * @license http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_'))
	exit;

class Maintenanz extends Module
{
 	private $conf_default = array(
		'MAINTENANZ_MSG' => array('value' => '', 'lang' => true),
		'MAINTENANZ_CONT' => array('value' => '', 'lang' => true),
		'MAINTENANZ_SHOP' => array('value' => 1, 'lang' => false),
 	);

	private static $vars_assigned = false;	// to assign smarty vars only ones

	public function __construct()
	{
		$this->name = 'maintenanz';
		$this->tab = 'administration';
		$this->version = '0.2';
		$this->author = 'zapalm';
		$this->need_instance = 0;
		$this->ps_versions_compliancy = array('min' => '1.5.0.0', 'max' => '1.6.1.0');

		parent::__construct();

		$this->displayName = $this->l('Maintenance tool');
		$this->description = $this->l('Allow to add maintenance messages.');
	}

	public function install()
	{
		return parent::install() && $this->registerHook('top') && $this->registerHook('header') && $this->registerHook('displayMaintenance');
	}

	public function uninstall()
	{
		foreach ($this->conf_default as $setting => $value)
			Configuration::deleteByName($setting);

		return parent::uninstall();
	}

	private function _hookCommon()
	{
		if(self::$vars_assigned)
			return;

		$id_lang = $this->context->cookie->id_lang;

		$settings = Configuration::getMultiple(array('MAINTENANZ_MSG', 'MAINTENANZ_CONT'), $id_lang);
		$settings['MAINTENANZ_SHOP'] = Configuration::get('MAINTENANZ_SHOP') ? Configuration::get('PS_SHOP_NAME') : '';

		$this->context->smarty->assign($settings);
	}

	public function hookHeader($params)
	{
		$this->context->controller->addCSS($this->_path.$this->name.'.css');
	}

	public function hookTop($params)
	{
		$this->_hookCommon();
		return $this->display(__FILE__, 'top.tpl');
	}

	public function hookDisplayMaintenance($params)
	{
		$this->_hookCommon();
		return $this->display(__FILE__, 'maintenance.tpl');
	}

	public function getContent()
	{
		$this->context->controller->getLanguages();
		$output = '';
		$submit = !empty($_POST['submit'.$this->name]);	// Tools::isSubmit() method is unusable here
		$res = true;

		foreach($this->conf_default as $name => $definition)
		{
			if($definition['lang'])
			{
				foreach ($this->context->controller->_languages as $lang)
				{
					$name_lang = $name.'_'.$lang['id_lang'];
					if ($submit && !Tools::isEmpty($value = Tools::getValue($name_lang)))
						$res &= Configuration::updateValue($name, array($lang['id_lang'] => $value));

					$this->fields_value[$name][$lang['id_lang']] = Configuration::get($name, $lang['id_lang']);
				}
			}
			else
			{
				if ($submit && !Tools::isEmpty($value = Tools::getValue($name)))
					$res &= Configuration::updateValue($name, $value);

				$this->fields_value[$name] = Configuration::get($name);
			}
		}

		if($submit)
			$output .= $res ? $this->displayConfirmation($this->l('Successfull update')) : $this->displayError($this->l('Unsuccessfull update'));

		return $output.$this->displayForm();
	}

	protected function displayForm()
	{
		$token = Tools::getAdminTokenLite('AdminModules');
		$currentIndex = AdminController::$currentIndex;
		$url = $currentIndex.'&configure='.$this->name;

		$form = new HelperForm();
		$form->module = $this;
		$form->name_controller = $this->name;
		$form->token = $token;
		$form->currentIndex = $url;
		$form->title = $this->displayName;
		$form->show_toolbar = true;
		$form->toolbar_scroll = true;
		$form->submit_action = 'submit'.$this->name;
		$form->languages = $this->context->controller->_languages;
		$form->default_form_language = $this->context->controller->default_form_language;
		$form->allow_employee_form_lang = $this->context->controller->allow_employee_form_lang;
		$form->toolbar_btn = array(
			'save' => array(
				'href' => $url.'&save'.$this->name.'&token='.$token,
				'desc' => $this->l('Save')
			),
			'back' => array(
				'href' => $currentIndex.'&token='.$token,
				'desc' => $this->l('Back')
			)
		);

		$this->fields_form[0]['form'] = array(
			'legend' => array('title' => $this->l('Settings')),
			'input' => array(
				array(
					'type' => 'text',
					'label' => $this->l('Displaying message'),
					'name' => 'MAINTENANZ_MSG',
					'size' => 100,
					'lang' => true,
					'required' => true
				),
				array(
					'type' => 'text',
					'label' => $this->l('Contact message'),
					'name' => 'MAINTENANZ_CONT',
					'size' => 100,
					'lang' => true,
					'required' => true
				),
				array(
					'type' => 'radio',
					'label' => $this->l('Display shop name'),
					'name' => 'MAINTENANZ_SHOP',
					'class' => 't',
					'is_bool' => true,
					'values' => array(
						array(
							'id' => '',
							'value' => 1,
							'label' => $this->l('Yes')
						),
						array(
							'id' => '',
							'value' => 0,
							'label' => $this->l('No')
						),
					),
				),
			),
			'submit' => array(
				'title' => $this->l('Save'),
				'class' => 'button'
			)
		);

		$form->fields_value = $this->fields_value;

		return $form->generateForm($this->fields_form);
	}
}