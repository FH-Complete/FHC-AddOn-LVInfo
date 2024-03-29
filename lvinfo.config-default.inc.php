<?php
/**
 * Array mit Sprachen die bei den LV-Infos zur Verfügung stehen
 * DEFAULT: array('German','English')
 */
$config_lvinfo_sprachen = array('German','English');

/**
 * Gibt an ob beim Abschicken der LVInfo ein Freigabemail verschickt wird
 * DEFAULT: true
 */
define('ADDON_LVINFO_SEND_FREIGABEMAIL', true);

/**
 * Gibt an ob beim Vorrücken die neuen LV-Infos bereits freigegeben sind wenn diese bereits freigegeben waren
 * oder ob diese auf "in Bearbeitung" gesetzt werden
 * DEFAULT: false
 */
define('ADDON_LVINFO_VORRUECKUNG_FREIGABE_UEBERNEHMEN', false);

/**
 * Gibt an ob die Drop Down Menüs ausgeblendet werden sollen oder nicht
 * DEFAULT: false
 */
define('ADDON_LVINFO_HIDE_MENU', false);

/**
 * Array mit optionalen Zeichenbeschränkungen für Eingabefelder
 * SYNTAX: array(Kurzbz des Feldes => Anzahl erlaubter Zeichen)
 * DEFAULT: array()
 */
$config_lvinfo_chars = array();
?>
