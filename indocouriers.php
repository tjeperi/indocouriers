<?php
/**
* 2007-2021 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2021 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Indocouriers extends CarrierModule
{
    const PREFIX = 'indocouriers_';

    public $id_carrier;

    protected $config_form = false;

    protected $the_carriers = array(
        'BEST (Besok Sampai Tujuan)' => 'sicepatbest',
        'REGULER (Layanan Reguler)' => 'sicepatreg',
        'SIUNT (SiUntung)' => 'sicepatunt',
        'GOKIL (Cargo (Minimal 10kg))' => 'sicepatgokil',
        'OKE (Ongkos Kirim Ekonomis)' => 'jneoke',
        'REG (Layanan Reguler)' => 'jnereg',
        'YES (Yakin Esok Sampai)' => 'jneyes',
        'EZ (Regular Service)' => 'jntez',
        'JND (Next Day Service)' => 'jntjnd',
        'ND (Next Day)' => 'aand',
        'REG (Regular)' => 'aareg',
        'ECO (Economy Service)' => 'tikieco',
        'REG (Regular Service)' => 'tikireg',
        'ONS (Over Night Service)' => 'tikions',
        'Service Normal (Ekonomi)' => 'wahanaeko',
        'Paket Kilat Khusus' => 'poskilat',
        'Express Next Day Barang' => 'posnext',
    );

    public function __construct()
    {
        $this->name = 'indocouriers';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.0';
        $this->author = 'tjeperi';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Indonesia Couriers');
        $this->description = $this->l('Indonesian courier shipping module.');

        $this->confirmUninstall = $this->l('Are you sure to uninstall this module?');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->addCarrier() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('actionCarrierUpdate') &&
            $this->registerHook('actionValidateOrder') &&
            $this->registerHook('displayAdminOrderTabLink') &&
            $this->registerHook('displayAdminOrderTabContent') &&
            $this->registerHook('additionalCustomerAddressFields') &&
            $this->registerHook('actionValidateCustomerAddressForm') &&
            $this->registerHook('actionSubmitCustomerAddressForm');
    }

    public function uninstall()
    {
        $this->deleteCarriers();
        Configuration::deleteByName('INDOCOURIERS_CITY_FROM');
        Configuration::deleteByName('INDOCOURIERS_ACCOUNT_APIKEY');
        foreach ($this->the_carriers as $value) {
            Configuration::deleteByName(self::PREFIX . $value);
            Configuration::deleteByName(self::PREFIX . $value . '_reference');
        }

        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall() &&
            $this->unregisterHook('header') &&
            $this->unregisterHook('backOfficeHeader') &&
            $this->unregisterHook('actionCarrierUpdate') &&
            $this->unregisterHook('actionValidateOrder') &&
            $this->unregisterHook('displayAdminOrderTabLink') &&
            $this->unregisterHook('displayAdminOrderTabContent') &&
            $this->unregisterHook('additionalCustomerAddressFields') &&
            $this->unregisterHook('actionValidateCustomerAddressForm') &&
            $this->unregisterHook('actionSubmitCustomerAddressForm');
    }

    protected function deleteCarriers()
    {
        foreach ($this->the_carriers as $value) {
            $tmp_carrier_id = Configuration::get(self::PREFIX . $value);
            $carrier = new Carrier($tmp_carrier_id);
            //$carrier->delete();
            $carrier->deleted = true;
            $carrier->update();
            @unlink(_PS_SHIP_IMG_DIR_ . '/' . (int) $tmp_carrier_id . '.jpg');
        }

        return true;
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submit' . $this->name)) == true) {
            $this->postProcess();
            $this->context->smarty->assign('success', $this->l('Information successfully updated.'));
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit' . $this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        $opt = array();
        $cityData = Tools::file_get_contents(_PS_MODULE_DIR_ . "indocouriers/controllers/admin/city.json", true);
        $cityData = json_decode($cityData);
          
        foreach ($cityData->rajaongkir->results as $key) {
            $opt[] = array(
                "id" => $key->city_id,
                "name" => $key->city_name . " (" .$key->type. ")"
            );
        }
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'select',
                        'desc' => $this->l('Select origin city'),
                        'name' => 'INDOCOURIERS_CITY_FROM',
                        'label' => $this->l('Origin City'),
                        'required' => true,
                        'options' => array(
                            'query' => $opt,
                            'id' => 'id',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-key"></i>',
                        'desc' => $this->l('Enter your rajaongkir API PRO Key'),
                        'name' => 'INDOCOURIERS_ACCOUNT_APIKEY',
                        'label' => $this->l('API Key'),
                        'required' => true
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            //'INDOCOURIERS_LIVE_MODE' => Configuration::get('INDOCOURIERS_LIVE_MODE', true),
            'INDOCOURIERS_CITY_FROM' => Configuration::get('INDOCOURIERS_CITY_FROM', '23'),
            'INDOCOURIERS_ACCOUNT_APIKEY' => Configuration::get('INDOCOURIERS_ACCOUNT_APIKEY', null),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    public function getOrderShippingCost($params, $shipping_cost)
    {
        if (!isset(Context::getContext()->customer)) {
            return false;
        }

        $ongkir_sicepatbest = false;
        $ongkir_sicepatreg = false;
        $ongkir_sicepatunt = false;
        $ongkir_sicepatgokil = false;
        $ongkir_jneoke = false;
        $ongkir_jnereg = false;
        $ongkir_jneyes = false;
        $ongkir_jntez = false;
        $ongkir_jntjnd = false;
        $ongkir_aand = false;
        $ongkir_aareg = false;
        $ongkir_tikieco = false;
        $ongkir_tikireg = false;
        $ongkir_tikions = false;
        $ongkir_wahanaeko = false;
        $ongkir_poskilat = false;
        $ongkir_posnext = false;
        $apiKey = Configuration::get('INDOCOURIERS_ACCOUNT_APIKEY');
        $origin = Configuration::get('INDOCOURIERS_CITY_FROM');
        $toCity = "";
        $order_id = Context::getContext()->cart->id;

        $id_address_delivery = Context::getContext()->cart->id_address_delivery;
        $address = new Address($id_address_delivery);
        $to = $address->city;

        $cityData = Tools::file_get_contents(_PS_MODULE_DIR_ . "indocouriers/controllers/admin/city.json", true);
        $cityData = json_decode($cityData);

        foreach ($cityData->rajaongkir->results as $key) {
            if (preg_match_all("/\(([^\]]*)\)/", $to, $matches)) {
                if (strpos($matches[0][0], "(Kota)") !== false) {
                    $temp = str_replace(" (Kota)", "", $to);
                    if ($temp == $key->city_name && $key->type == "Kota") {
                        $toCity = $key->city_id;
                    }
                }
                if (strpos($matches[0][0], "(Kabupaten)") !== false) {
                    $temp = str_replace(" (Kabupaten)", "", $to);
                    if ($temp == $key->city_name && $key->type == "Kabupaten") {
                        $toCity = $key->city_id;
                    }
                }
            } elseif ($key->city_name == $to) {
                $toCity = $key->city_id;
            } else {
                continue;
            }

            if (!empty($origin) && !empty($toCity)) {
                break;
            }
        }

        $weight = (float) Context::getContext()->cart->getTotalWeight(
            Context::getContext()->cart->getProducts()
        )*1000.0;
        $weight = max($weight, 1000.00);

        if (!empty($origin) && !empty($toCity) && !empty($order_id) && !empty($apiKey)) {
            $cacheCostSiCepat = $order_id.'_COST_SICEPAT_'.$origin.'_'.$toCity.'_'.$weight;
            $cacheCostJNE = $order_id.'_COST_JNE_'.$origin.'_'.$toCity.'_'.$weight;
            $cacheCostJNT = $order_id.'_COST_JNT_'.$origin.'_'.$toCity.'_'.$weight;
            $cacheCostAA = $order_id.'_COST_AA_'.$origin.'_'.$toCity.'_'.$weight;
            $cacheCostTIKI = $order_id.'_COST_TIKI_'.$origin.'_'.$toCity.'_'.$weight;
            $cacheCostWAHANA = $order_id.'_COST_WAHANA_'.$origin.'_'.$toCity.'_'.$weight;
            $cacheCostPOS = $order_id.'_COST_POS_'.$origin.'_'.$toCity.'_'.$weight;
            
            $cache = $order_id.'_'.$origin.'_'.$toCity.'_'.$weight;

            if (Cache::isStored($cacheCostSiCepat) &&
                Cache::isStored($cacheCostJNE) &&
                Cache::isStored($cacheCostJNT) &&
                Cache::isStored($cacheCostAA) &&
                Cache::isStored($cacheCostTIKI) &&
                Cache::isStored($cacheCostWAHANA) &&
                Cache::isStored($cacheCostPOS)) {
                $costData = Cache::retrieve($cacheCostSiCepat);
                $ongkir_sicepatbest = isset($costData['SICEPATBEST']) ? $costData['SICEPATBEST'] : false;
                $ongkir_sicepatreg = isset($costData['SICEPATREG']) ? $costData['SICEPATREG'] : false;
                $ongkir_sicepatunt = isset($costData['SICEPATUNT']) ? $costData['SICEPATUNT'] : false;
                $ongkir_sicepatgokil = isset($costData['SICEPATGOKIL']) ? $costData['SICEPATGOKIL'] : false;
                
                $costData = Cache::retrieve($cacheCostJNE);
                $ongkir_jneoke = isset($costData['JNEOKE']) ? $costData['JNEOKE'] : false;
                $ongkir_jnereg = isset($costData['JNEREG']) ? $costData['JNEREG'] : false;
                $ongkir_jneyes = isset($costData['JNEYES']) ? $costData['JNEYES'] : false;

                $costData = Cache::retrieve($cacheCostJNT);
                $ongkir_jntez = isset($costData['JNTEZ']) ? $costData['JNTEZ'] : false;
                $ongkir_jntjnd = isset($costData['JNTJND']) ? $costData['JNTJND'] : false;

                $costData = Cache::retrieve($cacheCostAA);
                $ongkir_aand = isset($costData['AAND']) ? $costData['AAND'] : false;
                $ongkir_aareg = isset($costData['AAREG']) ? $costData['AAREG'] : false;

                $costData = Cache::retrieve($cacheCostTIKI);
                $ongkir_tikieco = isset($costData['TIKIECO']) ? $costData['TIKIECO'] : false;
                $ongkir_tikireg = isset($costData['TIKIREG']) ? $costData['TIKIREG'] : false;
                $ongkir_tikions = isset($costData['TIKIONS']) ? $costData['TIKIONS'] : false;

                $costData = Cache::retrieve($cacheCostWAHANA);
                $ongkir_wahanaeko = isset($costData['WAHANAEKO']) ? $costData['WAHANAEKO'] : false;

                $costData = Cache::retrieve($cacheCostPOS);
                $ongkir_poskilat = isset($costData['POSKILAT']) ? $costData['POSKILAT'] : false;
                $ongkir_posnext = isset($costData['POSNEXT']) ? $costData['POSNEXT'] : false;
            } else {
                $getCacheQuery = "SELECT * FROM "._DB_PREFIX_."indocouriers WHERE cache_name = '$cache'
                ORDER BY id_indocouriers DESC";
                $getCache = Db::getInstance()->getRow($getCacheQuery);
                $isCache = isset($getCache['id_indocouriers']) ? $getCache['id_indocouriers'] : "";
                if (empty($isCache)) {
                    $kurir = 'sicepat:jne:jnt:anteraja:tiki:wahana:pos';
                    $post = $this->getShippingCost($apiKey, $origin, $toCity, $weight, $kurir);
                    if (isset($post['rajaongkir']['results'])) {
                        Db::getInstance()->execute("INSERT INTO "._DB_PREFIX_."indocouriers
                            (order_id, cache_name, `value`) VALUES
                            ('".$order_id."', '".$cache."', '".json_encode($post)."')");
                    } else {
                        return false;
                    }
                } else {
                    $post = json_decode($getCache['value'], true);
                }

                if (isset($post['rajaongkir']['results'])) {
                    foreach ($post['rajaongkir']['results'] as $list) {
                        $cacheResultCost = array();
                        $ongkirList = array();

                        // SICEPAT
                        if ($list['code'] == "sicepat") {
                            $ongkirList = $list['costs'];
                            if ($ongkirList) {
                                foreach ($ongkirList as $value) {
                                    if ($value['service'] == 'BEST') {
                                        $ongkir_sicepatbest = $value['cost'][0]['value'];
                                        $cacheResultCost['SICEPATBEST'] = $ongkir_sicepatbest;
                                    }
                                    if ($value['service'] == 'REG') {
                                        $ongkir_sicepatreg = $value['cost'][0]['value'];
                                        $cacheResultCost['SICEPATREG'] = $ongkir_sicepatreg;
                                    }
                                    if ($value['service'] == 'SIUNT') {
                                        $ongkir_sicepatunt = $value['cost'][0]['value'];
                                        $cacheResultCost['SICEPATUNT'] = $ongkir_sicepatunt;
                                    }
                                    if ($value['service'] == 'GOKIL') {
                                        $ongkir_sicepatgokil = $value['cost'][0]['value'];
                                        $cacheResultCost['SICEPATGOKIL'] = $ongkir_sicepatgokil;
                                    }
                                }
                                Cache::store($cacheCostSiCepat, $cacheResultCost);
                            }
                        }

                        // JNE
                        if ($list['code'] == "jne") {
                            $ongkirList = $list['costs'];
                            if ($ongkirList) {
                                foreach ($ongkirList as $value) {
                                    if ($value['service'] == 'OKE') {
                                        $ongkir_jneoke = $value['cost'][0]['value'];
                                        $cacheResultCost['JNEOKE'] = $ongkir_jneoke;
                                    }
                                    if ($value['service'] == 'REG') {
                                        $ongkir_jnereg = $value['cost'][0]['value'];
                                        $cacheResultCost['JNEREG'] = $ongkir_jnereg;
                                    }
                                    if ($value['service'] == 'YES') {
                                        $ongkir_jneyes = $value['cost'][0]['value'];
                                        $cacheResultCost['JNEYES'] = $ongkir_jneyes;
                                    }
                                }
                                Cache::store($cacheCostJNE, $cacheResultCost);
                            }
                        }

                        // JNT
                        if ($list['code'] == "J&T") {
                            $ongkirList = $list['costs'];
                            if ($ongkirList) {
                                foreach ($ongkirList as $value) {
                                    if ($value['service'] == 'EZ') {
                                        $ongkir_jntez = $value['cost'][0]['value'];
                                        $cacheResultCost['JNTEZ'] = $ongkir_jntez;
                                    }
                                    if ($value['service'] == 'JND') {
                                        $ongkir_jntjnd = $value['cost'][0]['value'];
                                        $cacheResultCost['JNTJND'] = $ongkir_jntjnd;
                                    }
                                }
                                Cache::store($cacheCostJNT, $cacheResultCost);
                            }
                        }

                        // AterAja
                        if ($list['code'] == "anteraja") {
                            $ongkirList = $list['costs'];
                            if ($ongkirList) {
                                foreach ($ongkirList as $value) {
                                    if ($value['service'] == 'ND') {
                                        $ongkir_aand = $value['cost'][0]['value'];
                                        $cacheResultCost['AAND'] = $ongkir_aand;
                                    }
                                    if ($value['service'] == 'REG') {
                                        $ongkir_aareg = $value['cost'][0]['value'];
                                        $cacheResultCost['AAREG'] = $ongkir_aareg;
                                    }
                                }
                                Cache::store($cacheCostAA, $cacheResultCost);
                            }
                        }

                        // TIKI
                        if ($list['code'] == "tiki") {
                            $ongkirList = $list['costs'];
                            if ($ongkirList) {
                                foreach ($ongkirList as $value) {
                                    if ($value['service'] == 'ECO') {
                                        $ongkir_tikieco = $value['cost'][0]['value'];
                                        $cacheResultCost['TIKIECO'] = $ongkir_tikieco;
                                    }
                                    if ($value['service'] == 'REG') {
                                        $ongkir_tikireg = $value['cost'][0]['value'];
                                        $cacheResultCost['TIKIREG'] = $ongkir_tikireg;
                                    }
                                    if ($value['service'] == 'ONS') {
                                        $ongkir_tikions = $value['cost'][0]['value'];
                                        $cacheResultCost['TIKIONS'] = $ongkir_tikions;
                                    }
                                }
                                Cache::store($cacheCostTIKI, $cacheResultCost);
                            }
                        }

                        // WAHANA
                        if ($list['code'] == "wahana") {
                            $ongkirList = $list['costs'];
                            if ($ongkirList) {
                                foreach ($ongkirList as $value) {
                                    if ($value['service'] == 'Normal') {
                                        $ongkir_wahanaeko = $value['cost'][0]['value'];
                                        $cacheResultCost['WAHANAEKO'] = $ongkir_wahanaeko;
                                    }
                                }
                                Cache::store($cacheCostPOS, $cacheResultCost);
                            }
                        }

                        // POS
                        if ($list['code'] == "pos") {
                            $ongkirList = $list['costs'];
                            if ($ongkirList) {
                                foreach ($ongkirList as $value) {
                                    if ($value['service'] == 'Paket Kilat Khusus') {
                                        $ongkir_poskilat = $value['cost'][0]['value'];
                                        $cacheResultCost['POSKILAT'] = $ongkir_poskilat;
                                    }
                                    if ($value['service'] == 'Express Next Day Barang') {
                                        $ongkir_posnext = $value['cost'][0]['value'];
                                        $cacheResultCost['POSNEXT'] = $ongkir_posnext;
                                    }
                                }
                                Cache::store($cacheCostPOS, $cacheResultCost);
                            }
                        }
                    }
                }
            }
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'sicepatbest_reference'))) {
                return $ongkir_sicepatbest;
            }
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'sicepatreg_reference'))) {
                return $ongkir_sicepatreg;
            }
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'sicepatunt_reference'))) {
                return $ongkir_sicepatunt;
            }
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'sicepatgokil_reference'))) {
                return $ongkir_sicepatgokil;
            }
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'jneoke_reference'))) {
                return $ongkir_jneoke;
            }
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'jnereg_reference'))) {
                return $ongkir_jnereg;
            }
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'jneyes_reference'))) {
                return $ongkir_jneyes;
            }
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'jntez_reference'))) {
                return $ongkir_jntez;
            }
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'jntjnd_reference'))) {
                return $ongkir_jntjnd;
            }
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'aand_reference'))) {
                return $ongkir_aand;
            }
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'aareg_reference'))) {
                return $ongkir_aareg;
            }
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'tikieco_reference'))) {
                return $ongkir_tikieco;
            }
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'tikireg_reference'))) {
                return $ongkir_tikireg;
            }
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'tikions_reference'))) {
                return $ongkir_tikions;
            }
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'wahanaeko_reference'))) {
                return $ongkir_wahanaeko;
            }
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'poskilat_reference'))) {
                return $ongkir_poskilat;
            }
            if ($this->id_carrier == (int)(Configuration::get(self::PREFIX.'posnext_reference'))) {
                return $ongkir_posnext;
            }

            return false;
        }

        return false;
    }

    public function getOrderShippingCostExternal($params)
    {
        return $this->getOrderShippingCost($params, 0);
    }

    protected function addCarrier()
    {
        foreach ($this->the_carriers as $key => $value) {
            $carrier = new Carrier();

            switch ($value) {
                case 'sicepatbest':
                case 'sicepatreg':
                case 'sicepatunt':
                case 'sicepatgokil':
                    $carrier->name = 'SiCepat';
                    $carrier->url = 'https://www.sicepat.com/checkAwb';
                    break;

                case 'jneoke':
                case 'jnereg':
                case 'jneyes':
                    $carrier->name = 'JNE';
                    $carrier->url = 'https://www.jne.co.id/en/tracking/trace';
                    break;

                case 'jntez':
                case 'jntjnd':
                    $carrier->name = 'J&T';
                    $carrier->url = 'https://jet.co.id/track';
                    break;

                case 'aand':
                case 'aareg':
                    $carrier->name = 'AnterAja';
                    $carrier->url = 'https://anteraja.id/tracking';
                    break;

                case 'tikieco':
                case 'tikireg':
                case 'tikions':
                    $carrier->name = 'TIKI';
                    $carrier->url = 'https://www.tiki.id/id/tracking';
                    break;

                case 'wahanaeko':
                    $carrier->name = 'Wahana';
                    $carrier->url = 'https://www.wahana.com/';
                    break;

                case 'poskilat':
                case 'posnext':
                    $carrier->name = 'POS Indonesia (POS)';
                    $carrier->url = 'https://www.posindonesia.co.id/id/tracking';
                    break;
                
                default:
                    $carrier->name = $key;
                    break;
            }

            $carrier->is_module = true;
            $carrier->active = 1;
            $carrier->range_behavior = 0;
            $carrier->need_range = 1;
            $carrier->shipping_external = true;
            $carrier->shipping_handling = 0;
            $carrier->external_module_name = $this->name;

            foreach (Language::getLanguages() as $lang) {
                $carrier->delay[$lang['id_lang']] = $key;
            }

            if ($carrier->add() == true) {
                $zones = Zone::getZones();
                foreach ($zones as $zone) {
                    $carrier->addZone($zone['id_zone']);
                }

                $groups_ids = array();
                $groups = Group::getGroups(Context::getContext()->language->id);
                foreach ($groups as $group) {
                    $groups_ids[] = $group['id_group'];
                }

                $carrier->setGroups($groups_ids);

                $range_price = new RangePrice();
                $range_price->id_carrier = $carrier->id;
                $range_price->delimiter1 = '0';
                $range_price->delimiter2 = '1000000';
                $range_price->add();

                $range_weight = new RangeWeight();
                $range_weight->id_carrier = $carrier->id;
                $range_weight->delimiter1 = '0';
                $range_weight->delimiter2 = '1000000';
                $range_weight->add();

                @copy(dirname(__FILE__) . '/views/img/' . $carrier->name . '.jpg', _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg');
                Configuration::updateValue(self::PREFIX . $value, (int)$carrier->id);
                Configuration::updateValue(self::PREFIX . $value . '_reference', (int)$carrier->id);
            }
        }

        return true;
    }

    protected function addGroups($carrier)
    {
        $groups_ids = array();
        $groups = Group::getGroups(Context::getContext()->language->id);
        foreach ($groups as $group) {
            $groups_ids[] = $group['id_group'];
        }

        $carrier->setGroups($groups_ids);
    }

    protected function addRanges($carrier)
    {
        $range_price = new RangePrice();
        $range_price->id_carrier = $carrier->id;
        $range_price->delimiter1 = '0';
        $range_price->delimiter2 = '10000';
        $range_price->add();

        $range_weight = new RangeWeight();
        $range_weight->id_carrier = $carrier->id;
        $range_weight->delimiter1 = '0';
        $range_weight->delimiter2 = '10000';
        $range_weight->add();
    }

    protected function addZones($carrier)
    {
        $zones = Zone::getZones();

        foreach ($zones as $zone) {
            $carrier->addZone($zone['id_zone']);
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    public function hookActionCarrierUpdate($params)
    {
        foreach ($this->the_carriers as $carrier_name => $carrier_properties) {
            if ($params['carrier']->id_reference == Configuration::get(self::PREFIX . "{$carrier_properties}_reference")) {
                Configuration::updateValue(self::PREFIX . "{$carrier_properties}", $params['carrier']->id);
            }
        }
    }

    public function hookActionValidateOrder($params)
    {
        $order_id = $params['cart']->id;
        Db::getInstance()->execute("DELETE FROM "._DB_PREFIX_."indocouriers WHERE order_id=" . $order_id);
    }

    public function hookDisplayAdminOrderTabLink($params)
    {
        return $this->context->smarty->fetch($this->local_path.'views/templates/admin/tracking_link.tpl');
    }

    public function hookDisplayAdminOrderTabContent($params)
    {
        $order_id = $params['id_order'];
        $manifest = "";
        if (((bool)Tools::isSubmit('submitupdateresi')) == true) {
            $new_tracking_number = Tools::getValue('no_resi');
            $query = "UPDATE "._DB_PREFIX_."order_carrier SET tracking_number='$new_tracking_number'
            WHERE id_order = '$order_id'";
            Db::getInstance()->execute($query);

            $this->get('session')->getFlashBag()->add('success', 'Successful update.');
            $router = $this->get('router');
            $urlResend = $router->generate('admin_orders_view', ['orderId'=> (int)$order_id]);
            Tools::redirectAdmin($urlResend);
        }

        if (((bool)Tools::isSubmit('submitlacak')) == true) {
            $lacak_no_resi = Tools::getValue('lacak_no_resi');
            if (!empty($lacak_no_resi)) {
                $lacak_carrier_name = Tools::getValue('lacak_carrier_name');
                if (!empty($lacak_carrier_name) && !empty($lacak_no_resi)) {
                    switch ($lacak_carrier_name) {
                        case 'SiCepat':
                            $carrier = 'sicepat';
                            break;

                        case 'J&T':
                            $carrier = 'jnt';
                            break;

                        case 'AnterAja':
                            $carrier = 'anteraja';
                            break;

                        case 'Wahana':
                            $carrier = 'wahana';
                            break;

                        case 'POS Indonesia (POS)':
                            $carrier = 'pos';
                            break;
                        
                        default:
                            $carrier = '';
                            break;
                    }
                    if (!empty($carrier)) {
                        $apiKey = Configuration::get('INDOCOURIERS_ACCOUNT_APIKEY');
                        $getPosition = $this->getShippingPosition($apiKey, $lacak_no_resi, $carrier);
                        if (isset($getPosition['rajaongkir']['result']['manifest'])) {
                            $manifest = json_encode($getPosition['rajaongkir']['result']['manifest']);
                            $status = isset($getPosition['rajaongkir']['result']['summary']['status']) ? $getPosition['rajaongkir']['result']['summary']['status'] : "";
                            $queryU = "INSERT INTO "._DB_PREFIX_."indocouriers_tracking
                            (order_id, tracking_number, `value`, `status`)
                            VALUES($order_id, '$lacak_no_resi', '$manifest', '$status')
                            ON DUPLICATE KEY UPDATE `value`='$manifest',`status`='$status'";
                            Db::getInstance()->execute($queryU);
                            $this->get('session')->getFlashBag()->add('success', $this->l('Shipping tracking successful update.'));
                        } else {
                            $this->get('session')->getFlashBag()->add('error', $this->l('Failed get tracking data.'));
                        }
                    } else {
                        $this->get('session')->getFlashBag()->add('error', $this->l('Lacak pengiriman untuk kurir ini tidak tersedia.'));
                    }
                } else {
                    $this->get('session')->getFlashBag()->add('error', $this->l('Nomor Resi atau Kurir tidak diketahui/kosong.'));
                }
            } else {
                $this->get('session')->getFlashBag()->add('error', $this->l('Kurir tidak diketahui/kosong.'));
            }

            $router = $this->get('router');
            $urlResend = $router->generate('admin_orders_view', ['orderId'=> (int)$order_id]);
            Tools::redirectAdmin($urlResend);
        }
        
        $query = "SELECT id_carrier, tracking_number FROM "._DB_PREFIX_."order_carrier WHERE id_order = '$order_id'";
        $gettracking_number = Db::getInstance()->getRow($query);
        $tracking_number = isset($gettracking_number['tracking_number']) ? $gettracking_number['tracking_number'] : "";
        $carrier_name = "";

        if (isset($gettracking_number['id_carrier'])) {
            $carrier_id = $gettracking_number['id_carrier'];
            $query2 = "SELECT a.name,b.delay FROM ps_carrier a LEFT JOIN ps_carrier_lang b
            ON(a.id_carrier=b.id_carrier) WHERE a.id_carrier = '$carrier_id'";
            $get_carrier = Db::getInstance()->getRow($query2);
            $carrier_name = isset($get_carrier['name']) ? $get_carrier['name'] : "";
            $delay = isset($get_carrier['delay']) ? $get_carrier['delay'] : "";
            //$image = _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier_id . '.jpg';
            $image = file_exists(_PS_SHIP_IMG_DIR_ . (int) $carrier_id . '.jpg') ? _THEME_SHIP_DIR_ . (int) $carrier_id . '.jpg' : '';
        }

        $queryT = "SELECT `value`, `status` FROM "._DB_PREFIX_."indocouriers_tracking WHERE order_id = '$order_id'";
        $getManifest = Db::getInstance()->getRow($queryT);
        $manifest = isset($getManifest['value']) ? json_decode($getManifest['value'], true) : "";
        $shipping_status = isset($getManifest['status']) ? $getManifest['status'] : "";
        if (!empty($manifest)) {
            krsort($manifest);
        }

        $this->context->smarty->assign(
            array(
                'manifest' => $manifest,
                'shipping_status' => $shipping_status,
                'resi_number' => $tracking_number,
                'carrier_name' => $carrier_name,
                'delay' => $delay,
                'image_url' => $image,
            )
        );
        return $this->context->smarty->fetch($this->local_path.'views/templates/admin/tracking.tpl');
    }

    public function hookAdditionalCustomerAddressFields($params)
    {
        ($params['fields']['city'])->setType('hidden');
        ($params['fields']['city'])->setName('city_text');

        // New field
        $formField = $params['fields'];
        $formField = (new FormField())
        ->setName('city')
        ->setLabel($this->l('City'))
        ->setRequired(true)
        ->setType('select')
        ;

        //$cities = City::getCitiesByIdState((int) $address->id_state);
        $cities = Tools::file_get_contents(_PS_MODULE_DIR_ . "indocouriers/controllers/admin/city.json", true);
        $cities = json_decode($cities);

        if (!empty($cities)) {
            foreach ($cities->rajaongkir->results as $city) {
                $formField->addAvailableValue(
                    $city->city_name,
                    $city->city_name . ' (' . $city->type . ')'
                );
            }
        }

        // If an address already exits, select the default city
        if (Tools::getIsset('id_address')) {
            $address = new Address(Tools::getValue('id_address'));

            if (!empty($address->city)) {
                //$id_city = CityAddress::getIdCityByIdAddress((int) $address->id);
                $formField->setValue($address->city);
            }
        }

        // Add the id_city field in the position of the city field
        $keys = array_keys($params['fields']);

        $search = 'city_text';
        foreach ($keys as $key => $value) {
            if ($value == $search) {
                break;
            }
        }

        $part1 = array_slice($params['fields'], 0, $key + 1);
        $part2 = array_slice($params['fields'], $key + 1);

        $part1['city'] = $formField;

        $params['fields'] = array_merge($part1, $part2);
    }

    public function hookActionValidateCustomerAddressForm($params)
    {
        if (empty(Tools::getValue('city'))
        || empty(Tools::getValue('city_text'))) {
            return false;
        }

        $form = $params['form'];
        $idCityField = $form->getField('city');
        $cityObj = pSQL(Tools::getValue('city'));
        $city = pSQL(Tools::getValue('city_text'));
        
        if ($cityObj !== $city) {
            $idCityField->addError(sprintf(
                $this->l('Invalid name in field city %s and city_text %s'),
                $cityObj,
                $city
            ));

            return false;
        }

        return true;
    }

    public function hookActionSubmitCustomerAddressForm($params)
    {
        /** @var Address */
        $address = $params['address'];
        $address->save();

        if (!Validate::isLoadedObject($address)) {
            throw new PrestaShopException($this->l('Address object error while trying to save city'));
        }
    }

    protected function getShippingCost($key, $origin, $destination, $weight, $carrier)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://pro.rajaongkir.com/api/cost',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => "origin={$origin}&originType=city&destination={$destination}&destinationType=city&weight={$weight}&courier={$carrier}",
            CURLOPT_HTTPHEADER => array(
                "key: {$key}",
                "Content-Type: application/x-www-form-urlencoded"
            ),
        ));

        $response = json_decode(curl_exec($curl), true);

        curl_close($curl);
        return $response;
    }

    protected function getShippingPosition($key, $waybill, $carrier)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://pro.rajaongkir.com/api/waybill',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => "waybill={$waybill}&courier={$carrier}",
            CURLOPT_HTTPHEADER => array(
                "key: {$key}",
                "Content-Type: application/x-www-form-urlencoded"
            ),
        ));

        $response = json_decode(curl_exec($curl), true);

        curl_close($curl);
        return $response;
    }
}
