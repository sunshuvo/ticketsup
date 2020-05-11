<?php
/**
 * Copyright (C) - 2013-2016    Jean-François FERRY    <hello@librethic.io>
 *                    2016         Christophe Battarel <christophe@altairis.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *     Tickets List
 *
 *    @package ticketsup
 */

$res = 0;
if (file_exists("../main.inc.php")) {
    $res = include "../main.inc.php"; // From htdocs directory
} elseif (!$res && file_exists("../../main.inc.php")) {
    $res = include "../../main.inc.php"; // From "custom" directory
} else {
    die("Include of main fails");
}

require_once 'class/actions_ticketsup.class.php';
require_once 'class/html.formticketsup.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
if (!empty($conf->projet->enabled)) {
    include DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
    include_once DOL_DOCUMENT_ROOT . '/core/class/html.formprojet.class.php';
    include_once DOL_DOCUMENT_ROOT . '/core/lib/project.lib.php';
}

// Load traductions files requiredby by page
$langs->load("companies");
$langs->load("other");
$langs->load("ticketsup@ticketsup");

// Get parameters
$id = GETPOST('id', 'int');
$msg_id = GETPOST('msg_id', 'int');
$socid = GETPOST('socid', 'int');
$projectid = GETPOST('projectid', 'int');

$action = GETPOST('action', 'alpha', 3);
$mode = GETPOST('mode', 'alpha');

// Filters
$search_soc = GETPOST("search_soc");
$search_fk_status = GETPOST("search_fk_status", 'alpha');
$search_subject = GETPOST("search_subject");
$search_type = GETPOST("search_type", 'alpha');
$search_category = GETPOST("search_category", 'alpha');
$search_severity = GETPOST("search_severity", 'alpha');
$search_project = GETPOST("search_project", 'int');
$search_fk_user_create = GETPOST("search_fk_user_create", 'int');
$search_fk_user_assign = GETPOST("search_fk_user_assign", 'int');

// Security check
if (!$user->rights->ticketsup->read) {
    accessforbidden();
}

// Store current page url
$url_page_current = dol_buildpath('/ticketsup/list.php', 1);


// Do we click on purge search criteria ?
if (GETPOST("button_removefilter_x")) {
    $search_soc = '';
    $search_fk_status = '';
    $search_subject = '';
    $search_type = '';
    $search_category = '';
    $search_severity = '';
    $search_project = '';
    $search_fk_user_create = '';
    $search_fk_user_assign = '';
}

// Initialize technical object to manage hooks. Note that conf->hooks_modules contains array
$hookmanager->initHooks(array('ticketsuplist'));
$extrafields = new ExtraFields($db);

// fetch optionals attributes and labels
$extralabels = $extrafields->fetch_name_optionals_label('ticketsup');
$search_array_options = $extrafields->getOptionalsFromPost($extralabels, '', 'search_');

$filter = array();
$param = '';

// Definition of fields for list
$arrayfields = array(
    't.datec' => array('label' => $langs->trans("Date"), 'checked' => 1),
    't.date_read' => array('label' => $langs->trans("TicketReadOn"), 'checked' => 0),
    't.date_close' => array('label' => $langs->trans("TicketCloseOn"), 'checked' => 0),
    't.ref' => array('label' => $langs->trans("Ref"), 'checked' => 1),
    't.fk_statut' => array('label' => $langs->trans("Status"), 'checked' => 1),
    't.subject' => array('label' => $langs->trans("Subject"), 'checked' => 1),
    'type.code' => array('label' => $langs->trans("Type"), 'checked' => 1),
    'category.code' => array('label' => $langs->trans("Category"), 'checked' => 1),
    'severity.code' => array('label' => $langs->trans("Severity"), 'checked' => 1),
    't.progress' => array('label' => $langs->trans("Progression"), 'checked' => 0),
    't.fk_project' => array('label' => $langs->trans("Project"), 'checked' => 0),
    //'t.fk_contract' => array('label' => $langs->trans("Contract"), 'checked' => 0),
    't.fk_user_create' => array('label' => $langs->trans("Author"), 'checked' => 1),
    't.fk_user_assign' => array('label' => $langs->trans("AuthorAssign"), 'checked' => 0),

    //'t.entity'=>array('label'=>$langs->trans("Entity"), 'checked'=>1, 'enabled'=>(! empty($conf->multicompany->enabled) && empty($conf->multicompany->transverse_mode))),
    //'t.datec' => array('label' => $langs->trans("DateCreation"), 'checked' => 0, 'position' => 500),
    //'t.tms' => array('label' => $langs->trans("DateModificationShort"), 'checked' => 0, 'position' => 2)
    //'t.statut'=>array('label'=>$langs->trans("Status"), 'checked'=>1, 'position'=>1000),
);

if ($mode != 'my_assign') {
    $arrayfields['t.fk_user_assign'] = array('label' => $langs->trans("UserAssignedTo"), 'checked' => 1);
}
if (!$socid) {
    $arrayfields['t.fk_soc'] = array('label' => $langs->trans("Company"), 'checked' => 1);
}

// Extra fields
if (is_array($extrafields->attribute_label) && count($extrafields->attribute_label)) {
    foreach ($extrafields->attribute_label as $key => $val) {
    	if ($extrafields->attribute_type[$key] != 'separate') {
        	$arrayfields["ef." . $key] = array('label' => $extrafields->attribute_label[$key], 'checked' => $extrafields->attribute_list[$key], 'position' => $extrafields->attribute_pos[$key], 'enabled' => $extrafields->attribute_perms[$key]);
    	}
    }
}

if (!empty($search_soc)) {
    $filter['s.nom'] = $search_soc;
    $param .= "&search_soc=" . $search_soc;
}
if (!empty($search_subject)) {
    $filter['t.subject'] = $search_subject;
    $param .= '&search_subject=' . $search_subject;
}
if (!empty($search_type)) {
    $filter['t.type_code'] = $search_type;
    $param .= '&search_type=' . $search_type;
}
if (!empty($search_category)) {
    $filter['t.category_code'] = $search_category;
    $param .= '&search_category=' . $search_category;
}
if (!empty($search_severity)) {
    $filter['t.severity_code'] = $search_severity;
    $param .= '&search_severity=' . $search_severity;
}
if (!empty($projectid)) {
    $filter['t.fk_project'] = $projectid;
}
if (!empty($search_project)) {
    $filter['t.fk_project'] = $search_project;
    $param .= '&search_project=' . $search_project;
}
if (!empty($search_fk_user_assign)) {
    // -1 value = all so no filter
    if ($search_fk_user_assign > 0) {
        $filter['t.fk_user_assign'] = $search_fk_user_assign;
        $param .= '&search_fk_user_assign=' . $search_fk_user_assign;
    }
}
if (!empty($search_fk_user_create)) {
    // -1 value = all so no filter
    if ($search_fk_user_create > 0) {
        $filter['t.fk_user_create'] = $search_fk_user_create;
        $param .= '&search_fk_user_create=' . $search_fk_user_create;
    }
}

if ((isset($search_fk_status) && $search_fk_status != '') && $search_fk_status != '-1' && $search_fk_status != 'non_closed') {
    $filter['t.fk_statut'] = $search_fk_status;
    $param .= '&search_fk_status=' . $search_fk_status;
}

if (isset($search_fk_status) && $search_fk_status == 'non_closed') {
    $filter['t.fk_statut'] = array(0, 1, 3, 4, 5, 6);
    $param .= '&search_fk_status=non_closed';
}
$object = new ActionsTicketsup($db);
$object->getInstanceDao();

require DOL_DOCUMENT_ROOT . '/core/actions_changeselectedfields.inc.php';

/***************************************************
 * PAGE
 *
 * Put here all code to build page
 ****************************************************/
$help_url = 'FR:DocumentationModuleTicket';
llxHeader('', $langs->trans('TicketList'), $help_url);

$form = new Form($db);
$user_assign = new User($db);
$user_create = new User($db);
$socstatic = new Societe($db);

$sortfield = GETPOST("sortfield", 'alpha');
$sortorder = GETPOST("sortorder", 'alpha');

if (!$sortfield) {
    $sortfield = 't.datec';
}

if (!$sortorder) {
    $sortorder = 'DESC';
}

$limit = $conf->liste_limit;

$page = GETPOST("page", 'int');
if ($page == -1) {
    $page = 0;
}
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

if ($socid || $user->societe_id > 0) {
    $socid = $user->societe_id ? $user->societe_id : $socid;
    $param .= '&socid=' . $socid;
    $filter['t.fk_soc'] = $socid;
}

if (!$user->societe_id && ($mode == "my_assign" || (!$user->admin && $conf->global->TICKETS_LIMIT_VIEW_ASSIGNED_ONLY))) {
    $filter['t.fk_user_assign'] = $user->id;
    $param .= "&mode=my_assign";
}

$num_total = $object->dao->fetchAll($user, $sortorder, $sortfield, '', $offset, $arch, $filter);
$num = $object->dao->fetchAll($user, $sortorder, $sortfield, $limit, $offset, $arch, $filter);

if ($socid && !$projectid && $user->rights->societe->lire) {
    $socstat = new Societe($db);
    $res = $socstat->fetch($socid);
    if ($res > 0) {
        $head = societe_prepare_head($socstat);
        dol_fiche_head($head, 'ticketsup', $langs->trans("ThirdParty"), 0, 'company');

        dol_banner_tab($socstat, 'socid', '', ($user->societe_id ? 0 : 1), 'rowid', 'nom');

        print '<div class="fichecenter">';

        print '<div class="underbanner clearboth"></div>';
        print '<table class="border centpercent">';

        // Customer code
        if ($socstat->client && !empty($socstat->code_client)) {
            print '<tr><td>';
            print $langs->trans('CustomerCode') . '</td><td colspan="' . (2 + (($showlogo || $showbarcode) ? 0 : 1)) . '">';
            print $socstat->code_client;
            if ($socstat->check_codeclient() != 0) {
                print ' <font class="error">(' . $langs->trans("WrongCustomerCode") . ')</font>';
            }

            print '</td>';
            print $htmllogobar;
            $htmllogobar = '';
            print '</tr>';
        }
        print '</table>';
        print '</div>';
        dol_fiche_end();
    }
}

if ($projectid) {
    $projectstat = new Project($db);
    if ($projectstat->fetch($projectid) > 0) {
        $projectstat->fetch_thirdparty();

        // To verify role of users
        //$userAccess = $object->restrictedProjectArea($user,'read');
        $userWrite = $projectstat->restrictedProjectArea($user, 'write');
        //$userDelete = $object->restrictedProjectArea($user,'delete');
        //print "userAccess=".$userAccess." userWrite=".$userWrite." userDelete=".$userDelete;

        $head = project_prepare_head($projectstat);
        dol_fiche_head($head, 'ticketsup', $langs->trans("Project"), 0, ($projectstat->public ? 'projectpub' : 'project'));

        /*
         *   Projet synthese pour rappel
         */
        print '<table class="border" width="100%">';

        $linkback = '<a href="' . DOL_URL_ROOT . '/projet/list.php">' . $langs->trans("BackToList") . '</a>';

        // Ref
        print '<tr><td width="30%">' . $langs->trans('Ref') . '</td><td colspan="3">';
        // Define a complementary filter for search of next/prev ref.
        if (!$user->rights->projet->all->lire) {
            $objectsListId = $projectstat->getProjectsAuthorizedForUser($user, $mine, 0);
            $projectstat->next_prev_filter = " rowid in (" . (count($objectsListId) ? join(',', array_keys($objectsListId)) : '0') . ")";
        }
        print $form->showrefnav($projectstat, 'ref', $linkback, 1, 'ref', 'ref', '');
        print '</td></tr>';

        // Label
        print '<tr><td>' . $langs->trans("Label") . '</td><td>' . $projectstat->title . '</td></tr>';

        // Customer
        print "<tr><td>" . $langs->trans("ThirdParty") . "</td>";
        print '<td colspan="3">';
        if ($projectstat->thirdparty->id > 0) {
            print $projectstat->thirdparty->getNomUrl(1);
        } else {
            print '&nbsp;';
        }

        print '</td></tr>';

        // Visibility
        print '<tr><td>' . $langs->trans("Visibility") . '</td><td>';
        if ($projectstat->public) {
            print $langs->trans('SharedProject');
        } else {
            print $langs->trans('PrivateProject');
        }

        print '</td></tr>';

        // Statut
        print '<tr><td>' . $langs->trans("Status") . '</td><td>' . $projectstat->getLibStatut(4) . '</td></tr>';

        print "</table>";

        print '</div>';
    } else {
        print "ErrorRecordNotFound";
    }
}

print_barre_liste($langs->trans('TicketList'), $page, 'list.php', $param, $sortfield, $sortorder, '', $num, $num_total, 'img/ticketsup-32.png', 1);

if ($mode == 'my_assign') {
    print '<div class="info">' . $langs->trans('TicketAssignedToMeInfos') . '</div>';
}

if ($search_fk_status == 'non_closed') {
    print '<div><a href="' . $url_page_current . '?search_fk_status=-1' . ($socid ? '&socid=' . $socid : '') . '">' . $langs->trans('TicketViewAllTickets') . '</a></div>';
    $param .= '&search_fk_status=non_closed';
} else {
    print '<div><a href="' . $url_page_current . '?search_fk_status=non_closed' . ($socid ? '&socid=' . $socid : '') . '">' . $langs->trans('TicketViewNonClosedOnly') . '</a></div>';
    $param .= '&search_fk_status=-1';
}

/*
 * Search bar
 */
print '<form method="get" action="' . $url_form . '" id="searchFormList" >' . "\n";
print '<input type="hidden" name="mode" value="' . $mode . '" >';
print '<input type="hidden" class="flat" name="socid" value="' . $socid . '" />';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="sortfield" value="' . $sortfield . '">';
print '<input type="hidden" name="sortorder" value="' . $sortorder . '">';

$varpage = empty($contextpage) ? $url_page_current : $contextpage;
$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage); // This also change content of $arrayfields

print '<table class="liste ' . ($moreforfilter ? "listwithfilterbefore" : "") . '">';

print '<tr class="liste_titre">';
if (!empty($arrayfields['t.datec']['checked'])) {
    print_liste_field_titre($arrayfields['t.datec']['label'], $url_page_current, 't.datec', '', $param, '', $sortfield, $sortorder);
}
if (!empty($arrayfields['t.date_read']['checked'])) {
    print_liste_field_titre($arrayfields['t.date_read']['label'], $url_page_current, 't.date_read', '', $param, '', $sortfield, $sortorder);
}
if (!empty($arrayfields['t.date_close']['checked'])) {
    print_liste_field_titre($arrayfields['t.date_close']['label'], $url_page_current, 't.date_close', '', $param, '', $sortfield, $sortorder);
}
if (!empty($arrayfields['t.ref']['checked'])) {
    print_liste_field_titre($arrayfields['t.ref']['label'], $url_page_current, 't.ref', '', $param, '', $sortfield, $sortorder);
}
if (!empty($arrayfields['t.fk_statut']['checked'])) {
    print_liste_field_titre($arrayfields['t.fk_statut']['label'], $url_page_current, 't.fk_statut', '', $param, '', $sortfield, $sortorder);
}
if (!empty($arrayfields['t.subject']['checked'])) {
    print_liste_field_titre($arrayfields['t.subject']['label']);
}
if (!empty($arrayfields['type.code']['checked'])) {
    print_liste_field_titre($arrayfields['type.code']['label'], $url_page_current, 'type.code', '', $param, '', $sortfield, $sortorder);
}
if (!empty($arrayfields['category.code']['checked'])) {
    print_liste_field_titre($arrayfields['category.code']['label'], $url_page_current, 'category.code', '', $param, '', $sortfield, $sortorder);
}
if (!empty($arrayfields['severity.code']['checked'])) {
    print_liste_field_titre($arrayfields['severity.code']['label'], $url_page_current, 'severity.code', '', $param, '', $sortfield, $sortorder);
}
if (!empty($arrayfields['t.progress']['checked'])) {
    print_liste_field_titre($arrayfields['t.progress']['label'], $url_page_current, 't.progress', '', $param, '', $sortfield, $sortorder);
}
if (!empty($arrayfields['t.fk_project']['checked'])) {
    print_liste_field_titre($arrayfields['t.fk_project']['label'], $url_page_current, 't.fk_project', '', $param, '', $sortfield, $sortorder);
}
if (!empty($arrayfields['t.fk_user_create']['checked'])) {
    print_liste_field_titre($arrayfields['t.fk_user_create']['label'], $url_page_current, 't.fk_user_create', '', $param, '', $sortfield, $sortorder);
}
if ($mode != 'my_assign') {
    if (!empty($arrayfields['t.fk_user_assign']['checked'])) {
        print_liste_field_titre($arrayfields['t.fk_user_assign']['label'], $url_page_current, 't.fk_user_assign', '', $param, '', $sortfield, $sortorder);
    }
}
if (!$socid) {
    if (!empty($arrayfields['t.fk_soc']['checked'])) {
        print_liste_field_titre($langs->trans('Company'), $url_page_current, 't.fk_soc', '', $param, '', $sortfield, $sortorder);
    }
}
if (!empty($arrayfields['t.tms']['checked'])) {
    print_liste_field_titre($arrayfields['t.tms']['label'], $url_page_current, 't.tms', '', $param, '', $sortfield, $sortorder);
}
// Extra fields
if (is_array($extrafields->attribute_label) && count($extrafields->attribute_label)) {
    foreach ($extrafields->attribute_label as $key => $val) {
        if (!empty($arrayfields["ef." . $key]['checked'])) {
            $align = $extrafields->getAlignFlag($key);
            print_liste_field_titre($extralabels[$key], $url_page_current, "ef." . $key, "", $param, ($align ? 'align="' . $align . '"' : ''), $sortfield, $sortorder);
        }
    }
}
print_liste_field_titre($selectedfields, $url_page_current, "", '', '', 'align="right"', $sortfield, $sortorder, 'maxwidthsearch ');
print '</tr>';


/*
 * Filter bar
 */
if (!empty($conf->projet->enabled)) {
    $formProject = new FormProjets($db);
}
$formTicket = new FormTicketsup($db);

print '<tr class="liste_titre">';

if (!empty($arrayfields['t.datec']['checked'])) {
    print '<td class="liste_titre"></td>';
}

if (!empty($arrayfields['t.date_read']['checked'])) {
    print '<td class="liste_titre"></td>';
}
if (!empty($arrayfields['t.date_close']['checked'])) {
    print '<td class="liste_titre"></td>';
}

if (!empty($arrayfields['t.ref']['checked'])) {
    print '<td class="liste_titre"></td>';
}

// Status
if (!empty($arrayfields['t.fk_statut']['checked'])) {
    print '<td>';
    $selected = ($search_fk_status != "non_closed" ? $search_fk_status : '');
    $object->printSelectStatus($selected);
    print '</td>';
}

if (!empty($arrayfields['t.subject']['checked'])) {
    print '<td class="liste_titre">';
    print '<input type="text" class="flat" name="search_subject" value="' . $search_subject . '" size="20">';
    print '</td>';
}

if (!empty($arrayfields['type.code']['checked'])) {
    print '<td class="liste_titre">';
    $formTicket->selectTypesTickets($search_type, 'search_type', '', 2, 1, 1);
    print '</td>';
}

if (!empty($arrayfields['category.code']['checked'])) {
    print '<td class="liste_titre">';
    $formTicket->selectCategoriesTickets($search_category, 'search_category', '', 2, 1, 1);
    print '</td>';
}

if (!empty($arrayfields['severity.code']['checked'])) {
    print '<td class="liste_titre">';
    $formTicket->selectSeveritiesTickets($search_severity, 'search_severity', '', 2, 1, 1);
    print '</td>';
}

if (!empty($arrayfields['t.progress']['checked'])) {
    print '<td class="liste_titre"></td>';
}

if (!empty($arrayfields['t.fk_project']['checked']) && !empty($conf->projet->enabled)) {
    print '<td class="liste_titre">';
    print $formProject->select_projects($socid, $search_subject, 'search_project');
    print '</td>';
}

if (!empty($arrayfields['t.fk_user_create']['checked'])) {
    print '<td class="liste_titre">';
    $usersCreateToExclude = array();
    $usersCreateToInclude = array();
    print $form->select_dolusers($search_fk_user_create, 'search_fk_user_create', 1, $usersCreateToExclude, 0, $usersCreateToInclude);
    print '</td>';
}

if (!empty($arrayfields['t.fk_user_assign']['checked'])) {
    print '<td class="liste_titre">';
    $usersAssignToExclude = array();
    $usersAssignToInclude = array();
    print $form->select_dolusers($search_fk_user_assign, 'search_fk_user_assign', 1, $usersAssignToExclude, 0, $usersAssignToInclude);
    print '</td>';
}

if (!$socid) {
    if (!empty($arrayfields['t.fk_soc']['checked'])) {
        print '<td class="liste_titre">';
        print '<input type="text" class="flat" name="search_soc" value="' . $search_soc . '" size="20">';
        print '</td>';
    }
}

if (!empty($arrayfields['t.tms']['checked'])) {
    print '<td class="liste_titre"></td>';
}

// Extra fields
if (is_array($extrafields->attribute_label) && count($extrafields->attribute_label)) {
    foreach ($extrafields->attribute_label as $key => $val) {
        if (!empty($arrayfields["ef." . $key]['checked'])) {
            print '<td class="liste_titre"></td>';
        }
    }
}

print '<td class="liste_titre" align="right">';
print '<input type="image" class="liste_titre" name="button_search" src="' . img_picto($langs->trans("Search"), 'search.png', '', '', 1) . '" value="' . dol_escape_htmltag($langs->trans("Search")) . '" title="' . dol_escape_htmltag($langs->trans("Search")) . '">';
print '<input type="image" class="liste_titre" name="button_removefilter" src="' . img_picto($langs->trans("Search"), 'searchclear.png', '', '', 1) . '" value="' . dol_escape_htmltag($langs->trans("RemoveFilter")) . '" title="' . dol_escape_htmltag($langs->trans("RemoveFilter")) . '">';
print '</td>';
print '</tr>';

if (is_array($object->dao->lines) && count($object->dao->lines) > 0) {
    $num = count($object->dao->lines);
    $i = 0;
    $total = 0;

    $var = true;
    while ($i < min($num, $conf->liste_limit)) {
        $var = !$var;
        print "<tr " . $bc[$var] . ">";

        // Date ticket
        if (!empty($arrayfields['t.datec']['checked'])) {
            print '<td>';
            print dol_print_date($object->dao->lines[$i]->datec, 'dayhour');
            print '</td>';
        }

        // Date read
        if (!empty($arrayfields['t.date_read']['checked'])) {
            print '<td>';
            print dol_print_date($object->dao->lines[$i]->date_read, 'dayhour');
            print '</td>';
        }

        // Date close
        if (!empty($arrayfields['t.date_close']['checked'])) {
            print '<td>';
            print dol_print_date($object->dao->lines[$i]->date_close, 'dayhour');
            print '</td>';
        }

        // ref
        if (!empty($arrayfields['t.ref']['checked'])) {
            print '<td>';
            print $object->dao->lines[$i]->ref;
            print '</td>';
        }

        // Statut
        if (!empty($arrayfields['t.fk_statut']['checked'])) {
            print '<td>';
            $object->fk_statut = $object->dao->lines[$i]->fk_statut;
            print $object->getLibStatut(2);
            print '</td>';
        }

        // Subject
        if (!empty($arrayfields['t.subject']['checked'])) {
            print '<td>';
            print '<a href="card.php?track_id=' . $object->dao->lines[$i]->track_id . '&projectid=' . $projectid . '">' . $object->dao->lines[$i]->subject . '</a>';
            print '</td>';
        }

        // Type
        if (!empty($arrayfields['type.code']['checked'])) {
            print '<td>';
            print $object->dao->lines[$i]->type_label;
            print '</td>';
        }

        // Category
        if (!empty($arrayfields['category.code']['checked'])) {
            print '<td>';
            print $object->dao->lines[$i]->category_label;
            print '</td>';
        }

        // Severity
        if (!empty($arrayfields['severity.code']['checked'])) {
            print '<td>';
            print $object->dao->lines[$i]->severity_label;
            print '</td>';
        }

        // Progression
        if (!empty($arrayfields['t.progress']['checked'])) {
            print '<td>';
            print $object->dao->lines[$i]->progress;
            print '</td>';
        }

        // Project
        if (!empty($arrayfields['t.fk_project']['checked'])) {
            print '<td>';
            print dolGetElementUrl($object->dao->lines[$i]->fk_project, 'projet_project');
            print '</td>';
        }
        // Message author
        if (!empty($arrayfields['t.fk_user_create']['checked'])) {
            print '<td>';
            if ($object->dao->lines[$i]->fk_user_create) {
                $user_create->firstname = (!empty($object->dao->lines[$i]->user_create_firstname) ? $object->dao->lines[$i]->user_create_firstname : '');
                $user_create->name = (!empty($object->dao->lines[$i]->user_create_lastname) ? $object->dao->lines[$i]->user_create_lastname : '');
                $user_create->id = (!empty($object->dao->lines[$i]->fk_user_create) ? $object->dao->lines[$i]->fk_user_create : '');
                print $user_create->getNomUrl();
            } else {
                print $langs->trans('Email');
            }
            print '</td>';
        }

        // Assigned author
        if ($mode != 'my_assign') {
            if (!empty($arrayfields['t.fk_user_assign']['checked'])) {
                print '<td>';
                if ($object->dao->lines[$i]->fk_user_assign) {
                    $user_assign->firstname = (!empty($object->dao->lines[$i]->user_assign_firstname) ? $object->dao->lines[$i]->user_assign_firstname : '');
                    $user_assign->lastname = (!empty($object->dao->lines[$i]->user_assign_lastname) ? $object->dao->lines[$i]->user_assign_lastname : '');
                    $user_assign->id = (!empty($object->dao->lines[$i]->fk_user_assign) ? $object->dao->lines[$i]->fk_user_assign : '');
                    print $user_assign->getNomUrl();
                } else {
                    print $langs->trans('None');
                }
                print '</td>';
            }
        }

        // Company
        if (!$socid) {
            if (!empty($arrayfields['t.fk_soc']['checked'])) {
                print '<td>';
                if ($object->dao->lines[$i]->fk_soc) {
                    $socstatic->fetch($object->dao->lines[$i]->fk_soc);
                    print $socstatic->getNomUrl();
                } else {
                    print $langs->trans('None');
                }
                print '</td>';
            }
        }

        if (!empty($arrayfields['t.tms']['checked'])) {
            print '<td>' . dol_print_date($object->dao->lines[$i]->tms, 'dayhour') . '</td>';
        }

        // Extra fields
        if (is_array($extrafields->attribute_label) && count($extrafields->attribute_label)) {
            foreach ($extrafields->attribute_label as $key => $val) {
                if (!empty($arrayfields["ef." . $key]['checked'])) {
                    print '<td';
                    $align = $extrafields->getAlignFlag($key);
                    if ($align) {
                        print ' align="' . $align . '"';
                    }
                    print '>';
                    $tmpkey = 'options_' . $key;
                    print $extrafields->showOutputField($key, $object->dao->lines[$i]->$tmpkey, '', 1);
                    print '</td>';
                }
            }
        }
        print '<td></td>';
        $i++;
        print '</tr>';
    }
}

print '</table>';
print '</form>';

if (!is_array($object->dao->lines) || !count($object->dao->lines)) {
    print '<div class="info">' . $langs->trans('NoTicketsFound') . '</div>';
}

if ($socid) {
    print '</div>';
}

print '<div class="tabsAction">';
print '<div class="inline-block divButAction"><a class="butAction" href="new.php?action=create_ticket' . ($socid ? '&socid=' . $socid : '') . ($projectid ? '&origin=projet_project&originid=' . $projectid : '') . '">' . $langs->trans('NewTicket') . '</a></div>';
print '</div>';

// End of page
llxFooter('');
$db->close();
