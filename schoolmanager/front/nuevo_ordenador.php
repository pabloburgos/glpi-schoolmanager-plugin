<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
global $CFG_GLPI;
$root = $CFG_GLPI['root_doc'] ?? '';
Html::redirect($root . '/plugins/schoolmanager/front/nuevo_activo.php?itemtype=Computer&v=234');

