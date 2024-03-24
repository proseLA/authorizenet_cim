<?php
    /**
     *  developed, copyrighted and brought to you by @proseLA (github)
     *  https://mxworks.cc
     *  copyright 2024 proseLA
     *
     *  consider a donation.  payment modules are the core of any shopping cart.
     *  a lot of work went into the development of this module.  consider an annual donation of
     *  5 basis points of your sales if you want to keep this module going.
     *
     *  released under GPU
     *  https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
     *
     *  use of this software constitutes acceptance of license
     *  mxworks will vigilantly pursue any violations of this license.
     *
     *  some portions of code may be copyrighted and licensed by www.zen-cart.com
     *
     *  03/2024  project: authorizenet_cim v3.0.0 file: authorizenet_cim_database_tables.php
     */


    define('TABLE_CUSTOMERS_CC', DB_PREFIX . 'customers_cc');
    define('TABLE_CIM_PAYMENTS', DB_PREFIX . 'authorize_cim_payments');
    define('TABLE_CIM_REFUNDS', DB_PREFIX . 'authorize_cim_refunds');
    define('TABLE_CIM_PAYMENT_TYPES', DB_PREFIX . 'authorize_cim_payment_types');
    define('TABLE_CUSTOMERS_CIM_PROFILE', DB_PREFIX . 'customers_cim_profile');

    define('FILENAME_AUTHNET_PAYMENTS', 'authorizenet_payments');