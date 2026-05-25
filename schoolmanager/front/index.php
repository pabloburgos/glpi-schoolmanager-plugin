<?php
include('../../../inc/includes.php');
Session::checkLoginUser();
Html::redirect(($CFG_GLPI['root_doc'] ?? '') . '/plugins/schoolmanager/front/formularios.php');

