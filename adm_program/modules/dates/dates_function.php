<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Termine
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * dat_id: ID des Termins, der angezeigt werden soll
 * mode:   1 - Neuen Termin anlegen
 *         2 - Termin löschen
 *         3 - Termin ändern
 *         4 - Termin im iCal-Format exportieren
 * url:    kann beim Loeschen mit uebergeben werden
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

require("../../system/common.php");
require_once("../../system/date_class.php");

if($_GET["mode"] != 4)
{
    // Alle Funktionen, ausser Exportieren, duerfen nur eingeloggte User
    require("../../system/login_valid.php");
}

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if(!editDate() && $_GET["mode"] != 4)
{
    $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
    header($location);
    exit();
}

// Uebergabevariablen pruefen

if(isset($_GET["dat_id"]) && is_numeric($_GET["dat_id"]) == false && $_GET["dat_id"]!=NULL)
{
    $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=invalid";
    header($location);
    exit();
}

if(is_numeric($_GET["mode"]) == false
|| $_GET["mode"] < 1 || $_GET["mode"] > 4)
{
    $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=invalid";
    header($location);
    exit();
}

if($_GET["mode"] == 2 || $_GET["mode"] == 3)
{
    // pruefen, ob man den Termin bearbeiten darf
    $sql = "SELECT * FROM ". TBL_DATES. "
             WHERE dat_id = {0}
               AND (  dat_org_shortname = '$g_organization'
                   OR dat_global = 1 ) ";
    $sql = prepareSQL($sql, array($_GET['dat_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    if(mysql_num_rows($result) == 0)
    {
        $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
        header($location);
        exit();
    }
}

$_SESSION['dates_request'] = $_REQUEST;
$err_code = "";
$err_text = "";

if($_GET["mode"] == 1 || $_GET["mode"] == 3)
{
    $headline = strStripTags($_POST['headline']);
    $content  = strStripTags($_POST['description']);
    $place    = strStripTags($_POST['meeting_point']);
    $_POST['date_from']    = trim($_POST['date_from']);

    if(strlen($headline) > 0
    && strlen($content)  > 0
    && strlen($_POST['date_from'])    > 0 )
    {
        // wenn Datum gueltig, dann speichern
        if(dtCheckDate($_POST['date_from']))
        {
            if(strlen($_POST['time_from']) == 0
            || dtCheckTime($_POST['time_from']) == true)
            {
                $dt_datum_von = dtFormatDate($_POST['date_from'], "Y-m-d"). " ". dtFormatTime($_POST['time_from']);

                // wenn Datum-bis nicht gefüllt ist, dann mit Datum-von nehmen
                if(strlen($_POST['date_to'])   == 0)
                {
                	$_POST['date_to']   = $_POST['date_from'];
                }
                if(strlen($_POST['time_to']) == 0)
                {
                	$_POST['time_to'] = $_POST['time_from'];
                }

                if(dtCheckDate($_POST['date_to']))
                {
                    if(dtCheckTime($_POST['time_to'])
                    || $_POST['time_to'] == "")
                    {
                        $dt_datum_bis = dtFormatDate($_POST['date_to'], "Y-m-d"). " ". dtFormatTime($_POST['time_to']);
                    }
                    else
                    {
                        $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=uhrzeit";
                        header($location);
                        exit();
                    }
                }
                else
                {
                    $err_text = "Datum Ende";
                    $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=datum&err_text=$err_text";
                    header($location);
                    exit();
                }

                // Enddatum muss groesser oder gleich dem Startdatum sein
                if(strcmp($dt_datum_von,$dt_datum_bis) > 0)
                {
                    $err_text = "Datum Ende oder Uhrzeit Ende";
                    $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=datum&err_text=$err_text";
                    header($location);
                    exit();
                }

                $act_date = date("Y.m.d G:i:s", time());

                if(array_key_exists("global", $_POST))
                {
                    $global = 1;
                }
                else
                {
                    $global = 0;
                }

                // Termin speichern

                if ($_GET["dat_id"] == 0)
                {
                    $sql = "INSERT INTO ". TBL_DATES. " (dat_global, dat_org_shortname, dat_usr_id, dat_timestamp, dat_headline,
                                                         dat_begin, dat_end, dat_location, dat_description)
                                                 VALUES ($global, '$g_organization', '$g_current_user->id', '$act_date', {0},
                                                         '$dt_datum_von', '$dt_datum_bis', {1}, {2})";
                }
                else
                {
                    $sql = "UPDATE ". TBL_DATES. " SET dat_global         = $global
                                                     , dat_headline   = {0}
                                                     , dat_begin            = '$dt_datum_von'
                                                     , dat_end            = '$dt_datum_bis'
                                                     , dat_location            = {1}
                                                     , dat_description   = {2}
                                                     , dat_last_change    = '$act_date'
                                                     , dat_usr_id_change = $g_current_user->id
                             WHERE dat_id = {3}";
                }
                $sql    = prepareSQL($sql, array($headline, $place, $content, $_GET['dat_id']));
                $result = mysql_query($sql, $g_adm_con);
                db_error($result);
                
                unset($_SESSION['dates_request']);

                $location = "Location: $g_root_path/adm_program/modules/dates/dates.php";
                header($location);
                exit();
            }
            else
            {
                $err_code = "uhrzeit";
            }
        }
        else
        {
        $err_code = "datum";
        $err_text = "Datum Beginn";
        }
    }
    else
    {
        if(strlen($headline) == 0)
        {
            $err_text = "&Uuml;berschrift";
        }
        elseif(strlen($content) == 0)
        {
            $err_text = "Beschreibung";
        }
        else
        {
            $err_text = "Datum von";
        }
        $err_code = "feld";
    }
}
elseif($_GET["mode"] == 2)
{
    $sql = "DELETE FROM ". TBL_DATES. " WHERE dat_id = {0}";
    $sql = prepareSQL($sql, array($_GET['dat_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    if(!isset($_GET["url"]))
    {
        $_GET["url"] = "$g_root_path/$g_main_page";
    }

    $location = "Location: $g_root_path/adm_program/system/err_msg.php?id=$id&err_code=delete&url=". urlencode($_GET["url"]);
    header($location);
    exit();
}
elseif($_GET["mode"] == 4)
{
    // Termindaten aus Datenbank holen
    $date = new Date($g_adm_con);
    $date->getDate($_GET["dat_id"]);

    header('Content-Type: text/calendar');
    header('Content-Disposition: attachment; filename="'. $date->begin. '.ics"');

    echo $date->getIcal($g_domain);
    exit();
}

$location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=$err_code&err_text=$err_text";
header($location);
exit();

?>