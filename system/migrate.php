<?php
/* Copyright (C) 2015 fhcomplete.org
 *
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as
* published by the Free Software Foundation; either version 2 of the
* License, or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307, USA.
*
* Authors: Andreas Oesterreicher <andreas.oesterreicher@technikum-wien.at> and
*/
/**
 * Migriert die Lehrveranstaltungsinformationen vom FH-Complete Core ins Addon
 */
require_once('../../../config/cis.config.inc.php');
require_once('../../../include/functions.inc.php');
require_once('../../../include/benutzerberechtigung.class.php');

$uid = get_uid();
$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen($uid);

if(!$rechte->isBerechtigt('admin'))
	die('Sie haben keine Berechtigung für diese Seite');

$db = new basis_db();
/*
 - Alle LV Infos aus Core Holen
 - Basis Set erstellen
 - LVInfos für das aktuelle Jahr setzen
	- Lernergebnisse aufsplitten
	- HTML Tags herausfiltern
	- Word Aufzählungszeichen
	- Lange Bindestriche
	- ...
 - eventuelle LVInfo Archiv aus alten Versionen übernehmen falls möglich

aktuelle Felder -> Feldbezeichnung in DB:

Kurzbeschreibung -> kurzbeschreibung
Methodik -> methodik
Lernergenisse -> lehrziele
Lehrinhalte -> lehrinhalte
Vorkenntnisse -> voraussetzungen
Literatur -> unterlagen
Leistungsbeurteilung -> pruefungsordnung
Anwesenheit -> anwesenheit
Anmerkungen -> anmerkung
*/

if(!isset($_GET['start']))
{
	echo 'Dieses Script startet die Migration der LV-Infos vom FHC-Core in das Addon. Wollen Sie die Daten wirklich migrieren?
	<a href="migrate.php?start=true">Ja, Migration starten</a>';
	exit;
}
$qry = 'SELECT * FROM addon.tbl_lvinfo_set';

if($result = $db->db_query($qry))
{
	if($db->db_num_rows($result)>0)
	{
		die('addon.tbl_lvinfo_set ist nicht leer -> Abbruch');
	}
}

$qry = "
INSERT INTO addon.tbl_lvinfo_set (lvinfo_set_kurzbz, lvinfo_set_bezeichnung, sort, lvinfo_set_typ, gueltigab_studiensemester_kurzbz, oe_kurzbz, insertamum, insertvon, updateamum, updatevon) VALUES ('kurzbesch', '{Kurzbeschreibung,\"Course Description\"}', 1, 'text', 'WS2015', 'gst', now(), 'migrate', now(), 'migrate');
INSERT INTO addon.tbl_lvinfo_set (lvinfo_set_kurzbz, lvinfo_set_bezeichnung, sort, lvinfo_set_typ, gueltigab_studiensemester_kurzbz, oe_kurzbz, insertamum, insertvon, updateamum, updatevon) VALUES ('methodik', '{Methodik,\"Teaching Methods\"}', 2, 'text', 'WS2015', 'gst', now(), 'migrate', now(), 'migrate');
INSERT INTO addon.tbl_lvinfo_set (lvinfo_set_kurzbz, lvinfo_set_bezeichnung, sort, lvinfo_set_typ, gueltigab_studiensemester_kurzbz, oe_kurzbz, insertamum, insertvon, updateamum, updatevon) VALUES ('lernerg', '{Lernergebnisse,\"Learning outcomes\"}', 3, 'array', 'WS2015', 'gst', now(), 'migrate', now(), 'migrate');
INSERT INTO addon.tbl_lvinfo_set (lvinfo_set_kurzbz, lvinfo_set_bezeichnung, sort, lvinfo_set_typ, gueltigab_studiensemester_kurzbz, oe_kurzbz, insertamum, insertvon, updateamum, updatevon) VALUES ('lehrinhalte', '{Lehrinhalte,\"Course Contents\"}', 4, 'array', 'WS2015', 'gst', now(), 'migrate', now(), 'migrate');
INSERT INTO addon.tbl_lvinfo_set (lvinfo_set_kurzbz, lvinfo_set_bezeichnung, sort, lvinfo_set_typ, gueltigab_studiensemester_kurzbz, oe_kurzbz, insertamum, insertvon, updateamum, updatevon) VALUES ('vorkenntnisse', '{Vorkenntnisse,\"Prerequisites\"}', 5, 'text', 'WS2015', 'gst', now(), 'migrate', now(), 'migrate');
INSERT INTO addon.tbl_lvinfo_set (lvinfo_set_kurzbz, lvinfo_set_bezeichnung, sort, lvinfo_set_typ, gueltigab_studiensemester_kurzbz, oe_kurzbz, insertamum, insertvon, updateamum, updatevon) VALUES ('literatur', '{Literatur,\"Recommended Reading and Material\"}', 6, 'array', 'WS2015', 'gst', now(), 'migrate', now(), 'migrate');
INSERT INTO addon.tbl_lvinfo_set (lvinfo_set_kurzbz, lvinfo_set_bezeichnung, sort, lvinfo_set_typ, gueltigab_studiensemester_kurzbz, oe_kurzbz, insertamum, insertvon, updateamum, updatevon) VALUES ('leistungsb', '{Leistungsbeurteilung,\"Assessment Methods\"}', 7, 'array', 'WS2015', 'gst', now(), 'migrate', now(), 'migrate');
INSERT INTO addon.tbl_lvinfo_set (lvinfo_set_kurzbz, lvinfo_set_bezeichnung, sort, lvinfo_set_typ, gueltigab_studiensemester_kurzbz, oe_kurzbz, insertamum, insertvon, updateamum, updatevon) VALUES ('anwesenheit', '{Anwesenheit,\"Attendance\"}', 8, 'text', 'WS2015', 'gst', now(), 'migrate', now(), 'migrate');
INSERT INTO addon.tbl_lvinfo_set (lvinfo_set_kurzbz, lvinfo_set_bezeichnung, sort, lvinfo_set_typ, gueltigab_studiensemester_kurzbz, oe_kurzbz, insertamum, insertvon, updateamum, updatevon) VALUES ('anmerkungen', '{Anmerkungen,\"Comments\"}', 9, 'text', 'WS2015', 'gst', now(), 'migrate', now(), 'migrate');
";

if(!$db->db_query($qry))
	die('Fehler beim Anlegen des SETS');

$qry = "SELECT *, (SELECT semester FROM lehre.tbl_lehrveranstaltung WHERE lehrveranstaltung_id=tbl_lvinfo.lehrveranstaltung_id) as ausbildungssemester FROM campus.tbl_lvinfo";

$i=0;
if($result = $db->db_query($qry))
{
	while($row = $db->db_fetch_object($result))
	{
		if($row->ausbildungssemester%2==1)
			$studiensemester_kurzbz = 'WS2015';
		else
			$studiensemester_kurzbz = 'SS2015';

		$data = array();
		$data['kurzbesch']=clearStuff($row->kurzbeschreibung);
		$data['methodik']=clearStuff($row->methodik);

		$row->lehrziele=mb_str_replace('Nach erfolgreichem Abschluss sind die Studierenden in der Lage, ...<br>','', $row->lehrziele);
		$row->lehrziele=mb_str_replace('Nach erfolgreichem Abschluss sind die Studierenden in der Lage,... <br>','', $row->lehrziele);
		$row->lehrziele=mb_str_replace('After passing this course successfully students are able to ...<br>','',$row->lehrziele);
		$row->lehrziele=mb_str_replace('After passing this course successfully students are able to...<br>','',$row->lehrziele);

		$data['lernerg']=getArray($row->lehrziele);
		$data['lehrinhalte']=getArray($row->lehrinhalte);
		$data['vorkenntnisse']=clearStuff($row->voraussetzungen);

		$row->unterlagen=mb_str_replace('Empfehlungen / Recommendations:<br>','',$row->unterlagen);
		$data['literatur']=getArray($row->unterlagen);
		$data['leistungsb']=getArray($row->pruefungsordnung);
		$data['anwesenheit']=clearStuff($row->anwesenheit);
		$data['anmerkungen']=clearStuff($row->anmerkung);

		$qry_ins = "BEGIN;INSERT INTO addon.tbl_lvinfo(sprache,lehrveranstaltung_id,studiensemester_kurzbz,data,insertamum, insertvon,updateamum,updatevon) VALUES(".
			$db->db_add_param($row->sprache).",".
			$db->db_add_param($row->lehrveranstaltung_id).",".
			$db->db_add_param($studiensemester_kurzbz).",".
			$db->db_add_param(json_encode($data)).",".
			$db->db_add_param($row->insertamum).",".
			$db->db_add_param($row->insertvon).",".
			$db->db_add_param($row->updateamum).",".
			$db->db_add_param($row->updatevon).");";

		if($result_ins = $db->db_query($qry_ins))
		{
			$qry_seq = "SELECT currval('addon.tbl_lvinfo_lvinfo_id_seq') as id";
			if($result_seq = $db->db_query($qry_seq))
			{
				if($row_seq = $db->db_fetch_object($result_seq))
				{
					$lvinfo_id = $row_seq->id;

					if($db->db_parse_bool($row->genehmigt))
					{
						$qry_ins="INSERT INTO addon.tbl_lvinfostatus_zuordnung(lvinfo_id, lvinfostatus_kurzbz, gesetztamum, uid,
							insertamum, insertvon, updateamum, updatevon) VALUES(".$db->db_add_param($lvinfo_id).",'freigegeben',".
							$db->db_add_param($row->updateamum).",".$db->db_add_param($row->updatevon).", 'migrate',now(),null, null);";
					}
					$db->db_query('Commit;');
					echo '.';
					$i++;
					if($i%200==0)
						echo '<br>';
					flush();
					ob_flush();
				}
				else
				{
					$db->db_query('ROLLBACK;');
					die('Sequence konnte nicht ausgelesen werden');
				}
			}
			else
			{
				$db->db_query('ROLLBACK;');
				die('Sequence konnte nicht ausgelesen werden');
			}
		}
		else
		{
			die('Query Failed');
		}
	}
}

echo $i.' Einträge angelegt';
/**
 * Felder mit Aufzaehlungszeichen werden aufgebrochen in einzelne Elemente und als Array zurueckgegeben
 */
function getArray($data)
{
	$arr = array();
	$arr=explode("<br>- ",$data);
	foreach($arr as $row)
		$arr2=explode("•	",$row);
	$arr = array_merge($arr, $arr2);
	$arr = array_unique($arr);

	// Beim ersten Eintrag das fuehrende '- ' wegschneiden
	if(isset($arr[0]) && mb_substr($arr[0],0,2)=='- ')
		$arr[0]=mb_substr($arr[0],2);

	foreach($arr as $key=>$val)
		$arr[$key]=clearStuff($val);

	return $arr;
}

function clearStuff($str)
{
	$str = strip_tags($str);
	return $str;
}
?>
