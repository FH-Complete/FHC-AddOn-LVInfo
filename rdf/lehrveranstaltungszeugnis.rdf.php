<?php
/* Copyright (C) 2006 fhcomplete.org
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
 * Authors: Christian Paminger <christian.paminger@technikum-wien.at>,
 *          Andreas Oesterreicher <andreas.oesterreicher@technikum-wien.at> and
 *          Rudolf Hangl <rudolf.hangl@technikum-wien.at> and
 *			Gerald Raab <gerald.raab@technikum-wien.at>.
 */
// content type setzen
header("Content-type: application/xhtml+xml");
require_once(dirname(__FILE__).'/../../../config/vilesci.config.inc.php');
require_once(dirname(__FILE__).'/../../../include/functions.inc.php');
require_once(dirname(__FILE__).'/../../../include/zeugnisnote.class.php');
require_once(dirname(__FILE__).'/../../../include/datum.class.php');
require_once(dirname(__FILE__).'/../../../include/note.class.php');
require_once(dirname(__FILE__).'/../../../include/studiensemester.class.php');
require_once(dirname(__FILE__).'/../../../include/studiengang.class.php');
require_once(dirname(__FILE__).'/../../../include/mitarbeiter.class.php');
require_once(dirname(__FILE__).'/../../../include/mitarbeiter.class.php');
require_once(dirname(__FILE__).'/../include/lvinfo.class.php');

$datum = new datum();
$db = new basis_db();
$projektarbeit=array();
$fussnotenzeichen=array('¹)','²)','³)');
$anzahl_fussnoten=0;
$studiengang_typ='';
$xml_fussnote='';

function breaktext($text, $zeichen)
{
	$arr = explode(' ',$text);
	$ret = '';
	$teilstring='';

	foreach($arr as $elem)
	{
		if(strlen($teilstring.$elem)>$zeichen)
		{
			$ret.=' '.$teilstring.'\n';
			$teilstring=$elem;
		}
		else
			$teilstring .=' '.$elem;
	}
	$ret.=$teilstring;
	return $ret;
}

if (isset($_REQUEST["xmlformat"]) && $_REQUEST["xmlformat"] == "xml")
{

	if(isset($_GET['uid']))
		$uid = $_GET['uid'];
	else
		$uid = null;

	$uid_arr = explode(";",$uid);

	if ($uid_arr[0] == "")
	{
		unset($uid_arr[0]);
		$uid_arr = array_values($uid_arr);
	}

	$note_arr = array();
	$note = new note();
	$note->getAll($offiziell = true);
	foreach ($note->result as $n){
		$note_arr[$n->note] = $n->anmerkung;
		$note_bezeichnung_arr[$n->note] = $n->bezeichnung;

	}
	if(isset($_GET['ss']))
		$studiensemester_kurzbz = $_GET['ss'];
	else
		die('Studiensemester muss uebergeben werden');

	if(isset($_GET['lvid']))
		$lehrveranstaltung_id = $_GET['lvid'];
	else
		$lehrveranstaltung_id = 0;

	//Daten holen

	$lqry = "SELECT
				tbl_person.titelpre, tbl_person.vorname, tbl_person.nachname, tbl_person.titelpost
			FROM
				public.tbl_benutzer JOIN public.tbl_person using (person_id)
			WHERE
				tbl_benutzer.uid = (SELECT
										tbl_lehreinheitmitarbeiter.mitarbeiter_uid
									FROM
										lehre.tbl_lehreinheitmitarbeiter JOIN lehre.tbl_lehrfunktion USING(lehrfunktion_kurzbz),
										lehre.tbl_lehreinheit JOIN lehre.tbl_lehrveranstaltung USING(lehrveranstaltung_id)
									WHERE
										tbl_lehreinheitmitarbeiter.lehreinheit_id = tbl_lehreinheit.lehreinheit_id AND
										tbl_lehrveranstaltung.lehrveranstaltung_id = ".$db->db_add_param($lehrveranstaltung_id)." AND
										tbl_lehreinheit.studiensemester_kurzbz=".$db->db_add_param($studiensemester_kurzbz)."
									ORDER BY tbl_lehrfunktion.standardfaktor desc limit 1)";

	$leiter_titel = '';
	$leiter_vorname = '';
	$leiter_nachname = '';
	$leiter_titelpost = '';

	if($db->db_query($lqry))
	{
		if ($lrow = $db->db_fetch_object())
		{
			$leiter_titel = $lrow->titelpre;
			$leiter_vorname = $lrow->vorname;
			$leiter_nachname = $lrow->nachname;
			$leiter_titelpost = $lrow->titelpost;
		}
	}

	$qry = "SELECT wochen FROM public.tbl_semesterwochen
						WHERE (studiengang_kz, semester) in (SELECT studiengang_kz, semester
						FROM lehre.tbl_lehrveranstaltung WHERE lehrveranstaltung_id=".$db->db_add_param($lehrveranstaltung_id, FHC_INTEGER).")";
	$wochen = 15;
	if($result_wochen = $db->db_query($qry))
	{
		if($row_wochen = $db->db_fetch_object($result_wochen))
		{
			$wochen = $row_wochen->wochen;
		}
	}
	$lvqry = "SELECT * from lehre.tbl_lehrveranstaltung where lehrveranstaltung_id = ".$db->db_add_param($lehrveranstaltung_id, FHC_INTEGER);

	if($db->db_query($lvqry))
	{
		if ($lvrow = $db->db_fetch_object())
		{
			$sws = $lvrow->semesterstunden/$wochen;
			$ects = $lvrow->ects;
			$lvbezeichnung = $lvrow->bezeichnung;
			$lvbezeichnung_eng = $lvrow->bezeichnung_english;
			$lvstg = $lvrow->studiengang_kz;
			$sws_lv = $lvrow->sws;
		}
	}

	$lehrinhalte = '';
	$lehrziele = '';
	$lvinfo = new lvinfo();
	if($lvinfo->loadLvinfo($lehrveranstaltung_id, $studiensemester_kurzbz, 'German', true))
	{
		if(isset($lvinfo->result[0]) && isset($lvinfo->result[0]->data['lehrinhalte']))
		{
			$lehrinhalte=implode('\n',$lvinfo->result[0]->data['lehrinhalte']);
		}
		if(isset($lvinfo->result[0]) && isset($lvinfo->result[0]->data['lernerg']))
		{
			$lehrziele=implode('\n- ',$lvinfo->result[0]->data['lernerg']);
			$lehrziele='\n- '.$lehrziele;
		}
	}

	$lehrinhalte_eng = '';
	$lehrziele_eng = '';
	$lvinfo_eng = new lvinfo();
	if($lvinfo_eng->loadLvinfo($lehrveranstaltung_id, $studiensemester_kurzbz, 'English', true))
	{
		if(isset($lvinfo_eng->result[0]) && isset($lvinfo_eng->result[0]->data['lehrinhalte']))
		{
			$lehrinhalte_eng=implode('\n',$lvinfo_eng->result[0]->data['lehrinhalte']);
		}
		if(isset($lvinfo_eng->result[0]) && isset($lvinfo_eng->result[0]->data['lernerg']))
		{
			$lehrziele_eng=implode('\n- ',$lvinfo_eng->result[0]->data['lernerg']);
			$lehrziele_eng='\n- '.$lehrziele_eng;
		}
	}

	$xml = "<?xml version='1.0' encoding='UTF-8' standalone='yes'?>";
	$xml .= "<zertifikate>";

	$studiensemester = new studiensemester();
	$studiensemester->load($studiensemester_kurzbz);

	for ($i = 0; $i < sizeof($uid_arr); $i++)
	{
		$anzahl_fussnoten=0;
		$studiengang_typ='';
		$xml_fussnote='';

		$query = "	SELECT 	tbl_student.matrikelnr,
							tbl_student.studiengang_kz,
							tbl_studiengang.typ,
							tbl_studiengang.bezeichnung,
							tbl_person.vorname,
							tbl_person.nachname,
							tbl_person.gebdatum,
							tbl_person.titelpre,
							tbl_person.titelpost,
							tbl_person.geschlecht
					FROM 	tbl_person,
							tbl_student,
							tbl_studiengang,
							tbl_benutzer
					WHERE 	tbl_student.studiengang_kz = tbl_studiengang.studiengang_kz
					AND 	tbl_student.student_uid = tbl_benutzer.uid
					AND 	tbl_benutzer.person_id = tbl_person.person_id
					AND 	tbl_student.student_uid = '".$uid_arr[$i]."'";

		if($db->db_query($query))
		{
				if(!$row = $db->db_fetch_object())
					die('Student not found');
		}
		else
			die('Student not found');
		$stg_oe_obj = new studiengang($row->studiengang_kz);
		$stgleiter = $stg_oe_obj->getLeitung($lvstg);
		$stgl='';
		foreach ($stgleiter as $stgleiter_uid)
		{
			$stgl_ma = new mitarbeiter($stgleiter_uid);
			$stgl .= trim($stgl_ma->titelpre.' '.$stgl_ma->vorname.' '.$stgl_ma->nachname.' '.$stgl_ma->titelpost);
		}

		$xml .= "\n	<zertifikat>";
		$xml .= "\n		<studiensemester>".$studiensemester->bezeichnung."</studiensemester>";
		$xml .= "\n		<vorname>".$row->vorname."</vorname>";
		$xml .= "\n		<nachname>".$row->nachname."</nachname>";
		$xml .= "\n		<name>".trim($row->titelpre.' '.$row->vorname.' '.mb_strtoupper($row->nachname).($row->titelpost!=''?', '.$row->titelpost:''))."</name>";
		$gebdatum = date('d.m.Y',strtotime($row->gebdatum));
		$xml .= "\n		<gebdatum>".$gebdatum."</gebdatum>";
		$xml .= "\n		<geschlecht>".$row->geschlecht."</geschlecht>";
		$xml .= "\n		<matrikelnr>".trim($row->matrikelnr)."</matrikelnr>";
		$xml .= "\n		<studiengangsleiter>".$stgl."</studiengangsleiter>";
		$datum_aktuell = date('d.m.Y');
		$xml .= "\n		<ort_datum>Wien, am ".$datum_aktuell."</ort_datum>";
		$xml .= "\n		<datum_aktuell>".$datum_aktuell."</datum_aktuell>";


		$obj = new zeugnisnote();
		$obj->load($lehrveranstaltung_id, $uid_arr[$i], $studiensemester_kurzbz);

		if ($obj->note && isset($note_arr[$obj->note]))
		{
			$note = $note_arr[$obj->note];
			$note_bezeichnung = $note_bezeichnung_arr[$obj->note];
		}
		else
		{
			$note = "";
			$note_bezeichnung = "";
		}
		$note2=$note;

		$stg = new studiengang();
		$stg->load($lvstg);
		if($stg->typ=='b')
			$stg_art='Bachelor';
		elseif($stg->typ=='m')
			$stg_art='Master';
		elseif($stg->typ=='d')
			$stg_art='Diplom';
		else
			$stg_art='';
		$xml .= "				<lv_studiengang_bezeichnung>".$stg->bezeichnung."</lv_studiengang_bezeichnung>";
		$xml .= "				<lv_studiengang_bezeichnung_eng>".$stg->english."</lv_studiengang_bezeichnung_eng>";
		$xml .= "				<lv_studiengang_art>".$stg_art."</lv_studiengang_art>";
		$xml .= "				<lv_studiengang_typ>".$stg->typ."</lv_studiengang_typ>";
		$xml .= "				<lv_studiengang_kennzahl>".sprintf('%04s',$lvstg)."</lv_studiengang_kennzahl>";

		$xml .= "				<bezeichnung><![CDATA[".$lvbezeichnung."]]></bezeichnung>";
		$xml .= "				<bezeichnung_eng><![CDATA[".$lvbezeichnung_eng."]]></bezeichnung_eng>";
		$xml .= "				<note>".$note."</note>";
		$xml .= "				<note_bezeichnung>".$note_bezeichnung."</note_bezeichnung>";
		$xml .= "				<sws>".($sws==0?'':number_format(sprintf('%.1F',$sws),1))."</sws>";
		$xml .= "				<sws_lv>".($sws_lv==0?'':number_format(sprintf('%.1F',$sws_lv),1))."</sws_lv>";
		$xml .= "				<ects>".number_format($ects,1)."</ects>";
		$xml .= "				<lvleiter>".$leiter_titel." ".$leiter_vorname." ".$leiter_nachname.($leiter_titelpost!=''?', '.$leiter_titelpost:'')."</lvleiter>";
		$xml .= "				<lehrinhalte><![CDATA[".clearHtmlTags($lehrinhalte)."]]></lehrinhalte>";
		$xml .= "				<lehrziele><![CDATA[".clearHtmlTags($lehrziele)."]]></lehrziele>";
		$xml .= "				<lehrziele_arr>";
								if (isset($lvinfo->result[0]->data['lernerg']))
								{
									foreach ($lvinfo->result[0]->data['lernerg'] AS $lehrziel)
		$xml .= "					<lehrziel><![CDATA[".clearHtmlTags($lehrziel)."]]></lehrziel>";
								}
								else
		$xml .= "					<lehrziel></lehrziel>";
		$xml .= "				</lehrziele_arr>";
		$xml .= "				<lehrinhalte_eng><![CDATA[".clearHtmlTags($lehrinhalte_eng)."]]></lehrinhalte_eng>";
		$xml .= "				<lehrziele_eng><![CDATA[".clearHtmlTags($lehrziele_eng)."]]></lehrziele_eng>";
		$xml .= "				<lehrziele_eng_arr>";
								if (isset($lvinfo_eng->result[0]->data['lernerg']))
								{
									foreach ($lvinfo_eng->result[0]->data['lernerg'] AS $lehrziel_eng)
		$xml .= "					<lehrziel_eng><![CDATA[".clearHtmlTags($lehrziel_eng)."]]></lehrziel_eng>";
								}
								else
		$xml .= "					<lehrziel_eng></lehrziel_eng>";
		$xml .= "				</lehrziele_eng_arr>";
		$xml .= "	</zertifikat>";
	}
	$xml .= "</zertifikate>";
	echo $xml;
}
?>
