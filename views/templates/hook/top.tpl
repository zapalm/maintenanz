{**
 * Tool for maintenance & debug: module for PrestaShop.
 *
 * @author    Maksim T. <zapalm@yandex.com>
 * @copyright 2014 Maksim T.
 * @link      https://prestashop.modulez.ru/en/administrative-features/24-tool-for-maintenance-debug.html
 * @license   https://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 *}

<!-- MODULE: maintenanz  -->
{if ($MAINTENANZ_MODE && false === $IS_SHOP_ENABLED) || false === $MAINTENANZ_MODE}
    <div class="maintenanz-top-block">
        <img src="{$img_uri|escape:'html'}maintenance-icon.png" alt=""/>
        <span class="maintenanz-shop-name">{$MAINTENANZ_SHOP|escape:'html'}</span>
        <span class="maintenanz-message">{$MAINTENANZ_MSG|escape:'html'}</span>
        <a class="maintenanz-contact" href="{$link->getPageLink('contact')|escape:'html'}">{$MAINTENANZ_CONT|escape:'html'}</a>
    </div>
{/if}
<!-- /MODULE: maintenanz  -->