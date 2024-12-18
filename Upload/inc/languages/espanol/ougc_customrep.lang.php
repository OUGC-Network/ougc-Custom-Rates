<?php

/***************************************************************************
 *
 *    ougc Custom Rates plugin (/inc/languages/espanol/ougc_customrep.lang.php)
 *    Author: Omar Gonzalez
 *    Copyright: © 2012 - 2020 Omar Gonzalez
 *
 *    Website: https://ougc.network
 *
 *    Create custom rates for users to use in posts.
 *
 ***************************************************************************
 ****************************************************************************
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 ****************************************************************************/

$l['ougc_customrep'] = 'ougc Custom Rates';

// Error messages
$l['ougc_customrep_error'] = 'Error';
$l['ougc_customrep_error_nopermission'] = 'No tienes autorizado reaccionar a este mensaje.';
$l['ougc_customrep_error_nopermission_guests'] = 'Por favor, entra como usuario para ver las reacciones a este mensaje.';
$l['ougc_customrep_error_nopermission_rate'] = 'Tú no tienes permitido eliminar reacciones a este mensaje.';
$l['ougc_customrep_error_nopermission_attachment'] = 'No tienes permitido descargar este archivo. Por favor, reacciona a este mensaje para poder tener acceso al archivo.';
$l['ougc_customrep_error_invlidadpost'] = 'Mensaje no válido';
$l['ougc_customrep_error_invalidpostcode'] = 'Código de mensaje inválido';
$l['ougc_customrep_error_invalidthread'] = 'Tema inválido';
$l['ougc_customrep_error_invalidforum'] = 'Foro inválido';
$l['ougc_customrep_error_selftrating'] = 'No puedes reaccionar a tus propios mensajes.';
$l['ougc_customrep_error_closedthread'] = 'No se puede reaccionar a un tema cerrado.';
$l['ougc_customrep_error_invalidrep'] = 'Tipo de reacción inválida.';
$l['ougc_customrep_error_invalidrating'] = 'Estás tratando de eliminar una reacción que no existe.';
$l['ougc_customrep_error_multiple'] = 'No se permite reaccionar dos veces al mismo mensaje.';
$l['ougc_customrep_error_points'] = 'No tienes puntos suficientes para utilizar la reacción seleccionada. Necesitas {1} puntos para poder utilizarla.';

// Misc page
$l['ougc_customrep_viewall'] = 'Ver quien reaccionó a esto.';
$l['ougc_customrep_popuptitle'] = '{1}: {2}';
$l['ougc_customrep_popup_empty'] = 'Actualmente nadie a reaccionado a este mensaje.';
$l['ougc_customrep_popup_date'] = '{1} a las {2}';
$l['ougc_customrep_popup_more'] = 'Clic para ver más.';
$l['ougc_customrep_popup_latest'] = 'Actualmente han reaccionado {1} usuarios.';
$l['ougc_customrep_popup_fullview'] = 'Ver lista completa.';
$l['ougc_customrep_close'] = 'Cerrar';

// UI variables
$l['ougc_customrep_viewlatest'] = 'Ver reacciones recientes';
$l['ougc_customrep_viewlatest_noajax'] = 'Ver lista completa';
$l['ougc_customrep_vote'] = 'Dar {1} a este mensaje.';
$l['ougc_customrep_voted'] = 'Ya reaccionaste a este mensaje.';
$l['ougc_customrep_voted_undo'] = 'Elimina tu reacción para poder reaccionar de nuevo a este mensaje.';
$l['ougc_customrep_delete'] = 'Eliminar {1} a este mensaje.';

// Profile
$l['ougc_customrep_profile_stats'] = 'Reacciones de {1}';
$l['ougc_customrep_profile_stats_empty'] = 'Actualmente no hay reacciones para mostrar.';
$l['ougc_customrep_profile_stats_received'] = 'Reacciones recibidas.';
$l['ougc_customrep_profile_stats_given'] = 'Reacciones dadas.';

$l['ougc_customrep_postbit_ignoredbit'] = 'El contenido de este mensaje ha sido ocultado debido a sus reacciones negativas.';

$l['ougc_customrep_xthreads_error'] = 'Configura correctamente xThreads para que esto funcione.';
$l['ougc_customrep_xthreads_error_user'] = 'Necesitas reaccionar con un "{1}" a este mensaje para poder ver el contenido oculto.';
$l['ougc_customrep_xthreads_error_user_any'] = 'Necesitas reaccionar a este mensaje para poder ver el contenido oculto.';
$l['ougc_customrep_xthreads_error_author'] = 'Estas configurando una reacción para mostrar contenido oculto. Agrega el contenido a ocultar o desactiva esta opción para que esto no sea necesario.';

$l['myalerts_setting_ougc_customrep'] = 'Deseas recibir una alerta cuando alguien reaccione a tus mensajes?';
$l['ougc_customrep_myalerts_alert'] = '{1} ha reaccionado a tu mensaje con un "{2}".';
$l['ougc_customrep_myalerts_alert_simple'] = '{1} ha reaccionado a tu mensaje.';

// Multi-lang support for individual rate types
#$l['ougc_customrep_name_RID'] = 'Name';