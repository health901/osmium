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

namespace Osmium\Page\Moderation\ViewFlags;

require __DIR__.'/../../inc/root.php';

if(!\Osmium\State\is_logged_in()) {
	\Osmium\fatal(403, "Please login first.");
}

$a = \Osmium\State\get_state('a');
if($a['ismoderator'] !== 't') {
	\Osmium\fatal(403, "This page is only for moderators.");
}

if(isset($_POST['status'])) {
	foreach($_POST['status'] as $flagid => $a) {
		foreach($a as $status => $b) {
			break 2;
		}
	}

	$flag = \Osmium\Db\fetch_assoc(\Osmium\Db\query_params('SELECT status, flaggedbyaccountid FROM osmium.flags WHERE flagid = $1', array($flagid)));
	if($flag === false) {
		\Osmium\fatal(404, "Invalid flagid.");
	}

	if($flag['flaggedbyaccountid'] > 0) {
		$deltas = \Osmium\Flag\get_flag_weight_deltas();
		if(!isset($deltas[$status]) || !isset($deltas[$flag['status']])) {
			\Osmium\fatal(500, "Status not in get_flag_weight_deltas().");
		}
		$delta = $deltas[$status] - $deltas[$flag['status']];
	}

	\Osmium\Db\query('BEGIN;');
	\Osmium\Db\query_params('UPDATE osmium.flags SET status = $1 WHERE flagid = $2', array($status, $flagid));
	if($flag['flaggedbyaccountid'] > 0) {
		\Osmium\Db\query_params(
			'UPDATE osmium.accounts SET flagweight = GREATEST($1::integer, LEAST($2::integer, flagweight + $3::integer)) WHERE accountid = $4',
			array(
				\Osmium\Flag\MIN_FLAG_WEIGHT,
				\Osmium\Flag\MAX_FLAG_WEIGHT,
				$delta,
				$flag['flaggedbyaccountid']));
	}
	\Osmium\Log\add_log_entry(\Osmium\Log\LOG_TYPE_CHANGED_FLAG_STATUS, $status, $flagid);
	\Osmium\Db\query('COMMIT;');
}

\Osmium\Chrome\print_header('Cast flags in reverse chronological order', '..');
echo "<div id='modflags'>\n";
echo "<h2>Cast flags in reverse chronological order</h2>\n";

list($total) = \Osmium\Db\fetch_row(\Osmium\Db\query('SELECT COUNT(flagid) FROM osmium.flags'));
$offset = \Osmium\Chrome\paginate('p', 50, $total, $result, $meta);
$rows = "<tr>\n<th>Flag ID</th>\n<th>Creation date</th>\n<th>Reported by</th>\n<th>Type</th>\n<th>Subtype</th>\n<th>Status</th>\n<th>Target</th>\n<th>Action</th>\n</tr>\n";

echo $meta;
echo $result;

$action = htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES);
echo "<form method='POST' action='$action'>\n<table class='d'>\n";
echo "<thead>\n$rows</thead>\n<tfoot>\n$rows</tfoot>\n<tbody>\n";

$types = \Osmium\Flag\get_flag_types();
$subtypes = \Osmium\Flag\get_flag_subtypes();
$statuses = \Osmium\Flag\get_flag_statuses();
$flagsq = \Osmium\Db\query_params('SELECT flagid, createdat, type, subtype, status, other, target1, target2, target3, accountid, nickname, apiverified, charactername, characterid, ismoderator, flagweight FROM osmium.flags LEFT JOIN osmium.accounts ON flaggedbyaccountid = accountid ORDER BY createdat DESC LIMIT 50 OFFSET $1', array($offset));
while($flag = \Osmium\Db\fetch_assoc($flagsq)) {
	echo "<tr class='status".$flag['status']."'>\n";
	echo "<td>".$flag['flagid']."</td>\n";
	echo "<td>".\Osmium\Chrome\format_relative_date($flag['createdat'])."</td>\n";
	echo "<td>".($flag['accountid'] !== null ? \Osmium\Chrome\format_character_name($flag, '..').' ('.$flag['flagweight'].')' : 'N/A')."</td>\n";
	echo "<td title='".htmlspecialchars($flag['other'], ENT_QUOTES)."'>".$types[$flag['type']]."</td>\n<td>".$subtypes[$flag['subtype']]."</td>\n";
	echo "<td>".$statuses[$flag['status']]."</td>\n";
	echo "<td>";
	if($flag['type'] == \Osmium\Flag\FLAG_TYPE_LOADOUT) {
		echo "<a href='../loadout/".$flag['target1']."'>#".$flag['target1']."</a>";
	} else if($flag['type'] == \Osmium\Flag\FLAG_TYPE_COMMENT) {
		echo "<a href='../loadout/".$flag['target2']."?jtc=".$flag['target1']."#c".$flag['target1']."'>#".$flag['target1']."</a>";
	} else if($flag['type'] == \Osmium\Flag\FLAG_TYPE_COMMENTREPLY) {
		echo "<a href='../loadout/".$flag['target3']."?jtc=".$flag['target2']."#r".$flag['target1']."'>#".$flag['target1']."</a>";
	} else {
		echo "<small>N/A</small>";
	}
	echo "</td>\n<td>\n";
	foreach($statuses as $status => $statusname) {
		if($status == $flag['status']) continue;

		echo "<input type='submit' name='status[".$flag['flagid']."][".$status."]' value='mark as ".$statusname."' />\n";
	}
	echo "</td>\n</tr>\n";
}

if($total == 0) {
	echo "<tr>\n<td colspan='6'>No flags to show.</td>\n</tr>\n";
}

echo "</tbody>\n</table>\n</form>\n";
echo $result;

echo "</div>\n";
\Osmium\Chrome\print_footer();