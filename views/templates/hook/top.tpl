{**
* Maintenance tool: module for PrestaShop
*
* @link      http://prestashop.modulez.ru/en/administrative-tools/24-tool-for-maintenance-debug.html The module homepage
* @author    zapalm <zapalm@ya.ru>
* @copyright 2014-2016 zapalm
* @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
*}

<!-- MODULE: maintenanz  -->
<div id="maintenance_notice" class="clearfix col-lg-12">
    <img src="{$ps_img_uri}admin/prefs.gif"/>
    {$MAINTENANZ_SHOP}
    {$MAINTENANZ_MSG}
    <a href="{$link->getPageLink('contact')}">{$MAINTENANZ_CONT}</a>
</div>
<!-- /MODULE: maintenanz  -->