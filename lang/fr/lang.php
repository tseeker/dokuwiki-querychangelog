<?php
/**
 * french language file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Vincent de Lagabbe <vincent@delagabbe.com>
 */

$lang['encoding']   = 'utf-8';
$lang['direction']  = 'ltr';

// for admin plugins, the menu prompt to be displayed in the admin menu
// if set here, the plugin doesn't need to override the getMenuText() method
$lang['menu'] = 'Query Changelog';
$lang['desc'] = 'Accès paramétrable à tout l\'historique des modifications';

$lang['qc_from']        = 'Depuis';
$lang['qc_to']          = 'Jusqu\'à';
$lang['qc_date']        = 'Date';
$lang['qc_begining']    = 'Démarrage du Wiki';
$lang['qc_now']         = 'Maintenant';

$lang['qc_base_ns']     = 'Restreindre au namespace';
$lang['qc_root']        = '[namespace racine]';
$lang['qc_current']     = '(Courant)';

$lang['qc_users']       = 'Dûs à(aux) utilisateur(s)';
$lang['qc_all_users']   = '[Tous]';

$lang['qc_major_only']  = 'Ne pas prendre en compte les changements mineurs';
$lang['qc_as_csv']      = 'Télécharger sous forme de CSV';

$lang['qc_submit']      = 'Soumettre';

$lang['qc_err_date']    = 'Date invalide';
$lang['qc_err_period']  = 'La date de début doit être avant la date de fin';

$lang['qc_back']        = 'Retour';

$lang['qc_res_nc']      = 'Aucun changement trouvé';
$lang['qc_res_title']   = 'Journal des modifications';
$lang['qc_res_ns']      = 'Dans le namespace';
$lang['qc_res_from']    = 'Date de début du journal';
$lang['qc_res_to']      = 'Date de fin du journal';
$lang['qc_res_users']   = 'Par le(s) utiliateurs';
$lang['qc_res_all']     = 'Tous';

?>
