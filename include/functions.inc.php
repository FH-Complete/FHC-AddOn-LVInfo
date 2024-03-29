<?php
/**
 * Liefert die Unterschiede zwischen der uebergebenen LVInfo und der zuvor freigegebenen
 * @param $lvinfo_id
 * @return string mit den Diff
 */
function getDiffPrevious($lvinfo_id)
{
	$ret='';

	$data = getDiffPreviousData($lvinfo_id);

	$ret.='<div class="lvinfo">'."\n";
	foreach($data['diff'] as $key=>$row_data)
	{
		$ret.= '<h2>'.$row_data['bezeichnung'].'</h2>'."\n";

		$ret.='<div class="lvinfo_data">'."\n";

		if(isset($row_data['diff']))
		{
			if(is_array($row_data['diff']))
			{
				$ret.='<ul>'."\n";
				foreach($row_data['diff'] as $item)
					$ret.='<li>'.$item.'</li>'."\n";
				$ret.='</ul>'."\n";
			}
			else
			{
				$ret.=$row_data['diff'];
			}
		}
		$ret.='</div>'."\n";
	}
	$ret.='</div>'."\n";
	return $ret;
}

function getDiffPreviousData($lvinfo_id)
{
	$ret=array();

	$lvinfo = new lvinfo();
	$lvinfo->load($lvinfo_id);

	$ret['new']=$lvinfo;

	$lvinfo_prev = new lvinfo();
	$lvinfo_prev->loadPreviousLvinfo($lvinfo_id);

	$ret['prev'] = $lvinfo_prev;

	if(!is_array($lvinfo->data))
		$keys1 = array();
	else
		$keys1 = array_keys($lvinfo->data);

	if(!is_array($lvinfo_prev->data))
		$keys2 = array();
	else
		$keys2 = array_keys($lvinfo_prev->data);

	$keys = array_unique(array_merge($keys1, $keys2));

	foreach($keys as $row)
	{
		$set = new lvinfo();
		$set->load_lvinfo_set_kurzbz_nearest($row, $lvinfo->studiensemester_kurzbz);

		$ret['diff'][$row]['bezeichnung']=$set->lvinfo_set_bezeichnung[DEFAULT_LANGUAGE];

		switch($set->lvinfo_set_typ)
		{
			case 'array':
				if(isset($lvinfo->data[$row]))
				{
					foreach($lvinfo->data[$row] as $item_key=>$item)
					{
						if(isset($lvinfo->data[$row][$item_key]))
							$a = $lvinfo->data[$row][$item_key];
						else
							$a='';

						if(isset($lvinfo_prev->data[$row][$item_key]))
							$b = $lvinfo_prev->data[$row][$item_key];
						else
							$b='';

						if(!is_array($a) && !is_array($b))
						{
							// Feinere Version pro Buchstabe
							//$diff = new cogpowered\FineDiff\Diff;

							$granularity = new cogpowered\FineDiff\Granularity\Word;
							$diff = new cogpowered\FineDiff\Diff($granularity);
							if(!isset($ret['diff'][$row]['diff']))
								$ret['diff'][$row]['diff']=array();
							$ret['diff'][$row]['diff'][]=$diff->render($b, $a);
						}
					}
				}
				break;

			case 'boolean':
				if(isset($lvinfo->data[$row]))
					$a = ($lvinfo->data[$row]?'Ja':'Nein');
				else
					$a='';

				if(isset($lvinfo_prev->data[$row]))
					$b = ($lvinfo_prev->data[$row]?'Ja':'Nein');
				else
					$b='';

				// Feinere Version pro Buchstabe
				//$diff = new cogpowered\FineDiff\Diff;

				$granularity = new cogpowered\FineDiff\Granularity\Word;
				$diff = new cogpowered\FineDiff\Diff($granularity);
				$ret['diff'][$row]['diff']=$diff->render($b, $a);

				break;

			case 'text':
			default:
				if(isset($lvinfo->data[$row]))
					$a = $lvinfo->data[$row];
				else
					$a='';

				if(isset($lvinfo_prev->data[$row]))
					$b = $lvinfo_prev->data[$row];
				else
					$b='';

				// Feinere Version pro Buchstabe
				//$diff = new cogpowered\FineDiff\Diff;

				$granularity = new cogpowered\FineDiff\Granularity\Word;
				$diff = new cogpowered\FineDiff\Diff($granularity);
				$ret['diff'][$row]['diff']=$diff->render($b, $a);
				break;
		}
	}
	return $ret;
}

function printInfoTable($lehrveranstaltung_id, $studiensemester_kurzbz, $sprache)
{
	$p = new phrasen($sprache);
	$db = new basis_db();
	$sprache_obj = new sprache();

	$lv = new lehrveranstaltung();
	$lv->load($lehrveranstaltung_id);

	$studiengang = new studiengang();
	$studiengang->load($lv->studiengang_kz);

	$leitung = array();

	$oetyp = new organisationseinheit();
	$oetyp->getTypen();
	foreach($oetyp->result as $row)
		$oetyp_arr[$row->organisationseinheittyp_kurzbz] = $row->bezeichnung;

	$oe = new organisationseinheit();

	if($lv->oe_kurzbz!='')
	{
		$oe->load($lv->oe_kurzbz);

		$benutzerfunktion = new benutzerfunktion();
		$benutzerfunktion->getBenutzerFunktionen('Leitung',$lv->oe_kurzbz);

		foreach($benutzerfunktion->result as $row)
		{
			$benutzer = new benutzer();
			$benutzer->load($row->uid);

			$leitung[] = $db->convert_html_chars(trim($benutzer->titelpre.' '.$benutzer->vorname.' '.$benutzer->nachname.' '.$benutzer->titelpost));
		}
		if (count($leitung) == 0)
			$leitung[] = '-';
	}
	$koordinator = array();

	$koord = new lehrveranstaltung();
	$koord->getKoordinator($lehrveranstaltung_id, $studiensemester_kurzbz);

	if(isset($koord->result) && is_array($koord->result))
	{
		foreach($koord->result as $row)
		{
			$koordinator[] = $db->convert_html_chars(trim($row->titelpre.' '.$row->vorname.' '.$row->nachname.' '.$row->titelpost));
		}
	}

	$lem = new lehreinheitmitarbeiter();
	$lem->getMitarbeiterLV($lehrveranstaltung_id, $studiensemester_kurzbz);

	$lektoren='';
	//if lektors exist
	if(isset($lem->result) && is_array($lem->result))
	{
		$lkt_arr=array();
		foreach($lem->result as $row)
		{
			if(!in_array($row->uid,$lkt_arr))
			{
				// Lektor wird erst angezeigt wenn der Auftrag erteilt wurde
				if (defined('CIS_LV_LEKTORINNENZUTEILUNG_VERTRAGSPRUEFUNG_VON')
				 && CIS_LV_LEKTORINNENZUTEILUNG_VERTRAGSPRUEFUNG_VON != '')
				{
					$vertrag = new vertrag();
					if (!$vertrag->isVertragErteiltLV($lehrveranstaltung_id, $studiensemester_kurzbz, $row->uid))
					{
						continue;
					}
				}

				//if lektor is not KOLLISIONSFREIE_USER (e.g. dummy)
				if (!in_array($row->uid, unserialize(KOLLISIONSFREIE_USER)))
				{
					$lektoren.=trim($row->titelpre.' '.$row->vorname.' '.$row->nachname.' '.$row->titelpost).', ';
					$lkt_arr[]=$row->uid;
				}
				//if lektor is KOLLISIONSFREIE_USER (e.g. dummy) but other lektors exist, do not pass to $lektoren
				elseif (in_array($row->uid, unserialize(KOLLISIONSFREIE_USER)) && count($lem->result) > 1)
				{
					$lkt_arr[]=$row->uid;
				}
				//if lektor is KOLLISIONSFREIE_USER (e.g. dummy) and NO other lektors exist
				elseif (in_array($row->uid, unserialize(KOLLISIONSFREIE_USER)) && count ($lem->result) == 1)
				{
					$lektoren = $p->t('lvinfo/keinLektorZugeordnet');
				}
			}
		}
	}
	else
		$lektoren = $p->t('lvinfo/keinLektorZugeordnet');

	$lektoren = chop($lektoren, ", ");

	//LV-Leitung
	$leiter_uid = $lv->getEingetrageneLVLeitung($lehrveranstaltung_id, $studiensemester_kurzbz);
	if($leiter_uid!==false)
	{
		$benutzer = new benutzer();
		$benutzer->load($leiter_uid);

		$lvleitung = $benutzer->titelpre.' '.$benutzer->vorname.' '.$benutzer->nachname.' '.$benutzer->titelpost;
	}
	else
	{
		$lvleitung = false;
	}

	echo '
	<table class="tablesorter">
		<tr>
			<td>'.$p->t('global/lehrveranstaltung').':</td>
			<td>'.$db->convert_html_chars($lv->bezeichnung).'</td>
		</tr>
		<tr>
			<td>'.$p->t('global/studiengang').':</td>
			<td>'.$db->convert_html_chars($studiengang->kurzbzlang).'</td>
		</tr>
		<tr>
			<td>'.$p->t('global/semester').':</td>
			<td>'.$db->convert_html_chars($lv->semester).'</td>
		</tr>
		<tr>
			<td>'.$p->t('global/studiensemester').':</td>
			<td>'.$db->convert_html_chars($studiensemester_kurzbz).'</td>
		</tr>
		<tr>
			<td>'.$p->t('global/organisationsform').':</td>
			<td>'.$db->convert_html_chars($lv->orgform_kurzbz).'</td>
		</tr>
		<tr>
			<td>'.$p->t('lehre/lehrbeauftragter').':</td>
			<td>'.$db->convert_html_chars($lektoren).'</td>
		</tr>';

	if ($lvleitung !== false)
	{
		echo '
		<tr>
			<td>'.$p->t('lehre/lvleitung').':</td>
			<td>'.$db->convert_html_chars($lvleitung).'</td>
		</tr>';
	}

	echo '
		<tr>
			<td>'.$p->t('global/sprache').':</td>
			<td>'.$db->convert_html_chars($sprache_obj->getBezeichnung($lv->sprache,$sprache)).'</td>
		</tr>
		<tr>
			<td>'.$p->t('global/ects').':</td>
			<td>'.$db->convert_html_chars($lv->ects).'</td>
		</tr>
		<tr>
			<td>'.$p->t('lvinfo/incomingplaetze').':</td>
			<td>'.$db->convert_html_chars($lv->incoming).'</td>
		</tr>
		<tr>
			<td>'.$p->t('global/organisationseinheit').':</td>
			<td>'.(isset($oetyp_arr[$oe->organisationseinheittyp_kurzbz])?$db->convert_html_chars($oetyp_arr[$oe->organisationseinheittyp_kurzbz].' '.$oe->bezeichnung):'').'
			 	<br>
				(
					<i>'.$p->t('global/leitung').'</i>: '.implode(', ', $leitung).' ';

	if(count($koordinator)>0)
		echo '<i>'.$p->t('global/koordination').'</i>: '.implode(', ', $koordinator);

	echo '			)
			</td>
		</tr>
	</table>';

}
?>
