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
     *  03/2024  project: authorizenet_cim v3.0.0 file: config.cim_auth.php
     */

    if (!defined('IS_ADMIN_FLAG')) {
        die('Illegal Access');
    }

    $autoLoadConfig[202][] = [
        'autoType' => 'class',
        'loadFile' => 'observers/class.cim_admin_observer.php',
        'classPath' => DIR_WS_CLASSES,
    ];
    $autoLoadConfig[202][] = [
        'autoType' => 'classInstantiate',
        'className' => 'cim_admin_observer',
        'objectName' => 'cimAdminObserver',
    ];
	