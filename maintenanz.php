<?php
/**
 * Maintenance tool: module for Prestashop
 *
 * @link http://prestashop.modulez.ru/en/ Modules for Prestashop CMS
 * @author zapalm <zapalm@ya.ru>
 * @copyright (c) 2014, zapalm
 * @license http://www.opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_'))
	exit;

class Maintenanz extends Module
{
	public function __construct()
	{
		$this->name = 'maintenanz';
		$this->tab = 'administration';
		$this->version = '0.1';
		$this->author = 'zapalm';
		$this->need_instance = 0;

		parent::__construct();

		$this->displayName = $this->l('Maintenance tool');
		$this->description = $this->l('Allow to add maintenance messages.');
	}

	public function install()
	{
		return parent::install() && $this->registerHook('top') && $this->registerHook('header');
	}

	public function uninstall()
	{
		return parent::uninstall();
	}

	public function hookHeader($params)
	{
		$this->context->controller->addCss($this->_path.$this->name.'.css', 'all');
	}

	public function hookTop($params)
	{
		$contact_url = '<a href="'.$this->context->link->getPageLink('contact').'">'.$this->l('Please, contact us if you have a problem.').'</a>';
		$this->context->smarty->assign(array(
			'msg' => Configuration::get('PS_SHOP_NAME').': '.$this->l('is under alpha-testing.').' '.$contact_url,
		));

		return $this->display(__FILE__, 'maintenanz.tpl');
	}
}