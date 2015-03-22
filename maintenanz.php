<?php
/**
 * Maintenance tool: module for PrestaShop
 *
 * @link      http://prestashop.modulez.ru/en/ Modules for PrestaShop CMS
 * @author    zapalm <zapalm@ya.ru>
 * @copyright 2014-2015 zapalm
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
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
		$this->version = '0.3.0';
		$this->author = 'zapalm';
		$this->need_instance = 0;
		$this->bootstrap = true;	// true, but styles still not compatible with PS.16 bootstrup
		$this->ps_versions_compliancy = array('min' => '1.5', 'max' => '1.6');

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
		$settings['ps_img_uri'] = _PS_IMG_;

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
		$errors = array();
		$submit_settings = (bool)Tools::getValue('submit_settings');
		$submit_debug = (bool)Tools::getValue('submit_debug');
		$res = true;

		// change mode (debug or normal)
		$this->fields_value['MAINTENANZ_DEBUG'] = (bool)_PS_MODE_DEV_;
		if ($submit_debug && Tools::getValue('MAINTENANZ_DEBUG') != _PS_MODE_DEV_)
		{
			$prev_mode = var_export($this->fields_value['MAINTENANZ_DEBUG'], true);
			$new_mode = var_export(!$this->fields_value['MAINTENANZ_DEBUG'], true);

			$prev_settings = file_get_contents(dirname(__FILE__).'/../../config/defines.inc.php');
			$new_settings = preg_replace('/define\(\'_PS_MODE_DEV_\', '.$prev_mode.'\);/Ui', 'define(\'_PS_MODE_DEV_\', '.$new_mode.');', $prev_settings);

			// todo: need to backup
			if (file_put_contents(dirname(__FILE__).'/../../config/defines.inc.php', $new_settings))
				$this->fields_value['MAINTENANZ_DEBUG'] = !$this->fields_value['MAINTENANZ_DEBUG'];
			else
				$errors[] = $this->l('The "defines.inc.php" file cannot be overwritten.');
		}

		// change other settings
		foreach($this->conf_default as $name => $definition)
		{
			if($definition['lang'])
			{
				foreach ($this->context->controller->_languages as $lang)
				{
					$name_lang = $name.'_'.$lang['id_lang'];
					if ($submit_settings && !Tools::isEmpty($value = Tools::getValue($name_lang)))
						$res &= Configuration::updateValue($name, array($lang['id_lang'] => $value));

					$this->fields_value[$name][$lang['id_lang']] = Configuration::get($name, $lang['id_lang']);
				}
			}
			else
			{
				if ($submit_settings && !Tools::isEmpty($value = Tools::getValue($name)))
					$res &= Configuration::updateValue($name, $value);

				$this->fields_value[$name] = Configuration::get($name);
			}
		}

		if($submit_settings || $submit_debug)
		{
			if (!$res)
				$errors[] = $this->l('Unsuccessfull settings update.');

			$output .= $errors ? $this->displayError(implode('<br/>', $errors)) : $this->displayConfirmation($this->l('Successfull update.'));
		}

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
		$form->show_toolbar = false;
		$form->submit_action = 'submit_settings';
		$form->languages = $this->context->controller->_languages;
		$form->default_form_language = $this->context->controller->default_form_language;
		$form->allow_employee_form_lang = $this->context->controller->allow_employee_form_lang;

		$this->fields_form[0]['form'] = array(
			'legend' => array('title' => $this->l('Switch-off settings')),
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

		$this->fields_form[1]['form'] = array(
			'legend' => array('title' => $this->l('Debug settings')),
			'input' => array(
				array(
					'type' => 'radio',
					'label' => $this->l('Enable debug mode'),
					'name' => 'MAINTENANZ_DEBUG',
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
				'class' => 'button',
				'name' => 'submit_debug'
			)
		);

		$form->fields_value = $this->fields_value;

		return $form->generateForm($this->fields_form);
	}
}