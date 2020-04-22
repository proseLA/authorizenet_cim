<?php
/*  portions copyright by... zen-cart.com

    developed and brought to you by proseLA
    https://rossroberts.com

    released under GPU
    https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0

   04/2020  project: authorizenet_cim; file: config.cim_auth.php; version 2.0
*/
    if (!defined('IS_ADMIN_FLAG')) {
        die('Illegal Access');
    }
    
    $autoLoadConfig[202][] = array(
      'autoType' => 'class',
      'loadFile' => 'observers/class.cim_admin_observer.php',
      'classPath' => DIR_WS_CLASSES
    );
    $autoLoadConfig[202][] = array(
      'autoType' => 'classInstantiate',
      'className' => 'cim_admin_observer',
      'objectName' => 'cimAdminObserver'
    );
	