<?php
/* Osmium
 * Copyright (C) 2012 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Osmium\Page\ExportFit;

require __DIR__.'/../inc/root.php';

if(!isset($_GET['type'])) {
	\Osmium\fatal(400, "No type specified.");
}

$type = $_GET['type'];
$formats = \Osmium\Fit\get_export_formats();

if(!isset($formats[$type])) {
	\Osmium\fatal(400, "Invalid type specified.");
}

if(!isset($_GET['loadoutid'])) {
	\Osmium\fatal(400, "No loadoutid specified.");
}

if(isset($_GET['revision'])) {
	$revision = intval($_GET['revision']);
}
if(!isset($revision) || $revision == 0) {
	$revision = null;
}


$loadoutid = $_GET['loadoutid'];

if(!\Osmium\State\can_view_fit($loadoutid)) {
	\Osmium\fatal(404, "Loadout not found.");
}

$fit = \Osmium\Fit\get_fit($loadoutid, $revision);

if($fit === false) {
	\Osmium\fatal(404, "Loadout not found (get_fit() failure).");
}

if(!\Osmium\State\can_access_fit($fit)) {
	\Osmium\fatal(403, "This fit is password-protected. Please go to ../loadout/".$loadoutid." and input the password, then retry.");
}

if(isset($_GET['pid']) && isset($fit['presets'][$_GET['pid']])) {
	\Osmium\Fit\use_preset($fit, $_GET['pid']);
}
if(isset($_GET['cpid']) && isset($fit['chargepresets'][$_GET['cpid']])) {
	\Osmium\Fit\use_charge_preset($fit, $_GET['cpid']);
}
if(isset($_GET['dpid']) && isset($fit['dronepresets'][$_GET['dpid']])) {
	\Osmium\Fit\use_drone_preset($fit, $_GET['dpid']);
}

header('X-Robots-Tag: noindex');
header('Content-Type: '.$formats[$type][1]);
echo $formats[$type][2]($fit, $_GET);
die();
