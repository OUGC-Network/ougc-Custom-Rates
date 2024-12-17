<?php

/***************************************************************************
 *
 *    ougc Custom Reputation plugin (/inc/languages/espanol/admin/config_ougc_customrep.lang.php)
 *    Author: Omar Gonzalez
 *    Copyright: © 2012 - 2020 Omar Gonzalez
 *
 *    Website: https://ougc.network
 *
 *    Allow users rate posts with custom post reputations with rich features.
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

$l['ougc_customrep'] = 'ougc Custom Reputation';
$l['ougc_customrep_d'] = 'Permite dar reputación personalizada en las publicaciones.';

// PluginLibrary
$l['ougc_customrep_plreq'] = 'Este plugin necesita <a href="{1}">PluginLibrary</a> versión {2} o posterior para funcionar.';
$l['ougc_customrep_plold'] = 'Este plugin necesita PluginLibrary versión {1} o posterior, mientras que tu versión actual es {2}. Favor de actualizar <a href="{3}">PluginLibrary</a>.';

// Messages
$l['ougc_customrep_message_invalidrep'] = 'La reacción seleccionada es inválida.';
$l['ougc_customrep_message_deleterep'] = 'La reacción seleccionada fue eliminada correctamente.';
$l['ougc_customrep_message_empty'] = 'Actualmente no existen reacciones.';
$l['ougc_customrep_message_addrep'] = 'La reacción ha sido añadida correctamente.';
$l['ougc_customrep_message_editrep'] = 'La reacción ha sido editada correctamente.';

// Header titles
$l['ougc_customrep_h_image'] = 'Imagen';
$l['ougc_customrep_h_image_d'] = 'Elige una imagen para identificar esta reacción.<br/><span class="smalltext">&nbsp;&nbsp;{bburl} -> URL del foro<br />
&nbsp;&nbsp;{homeurl} -> URL Principal del sitio<br />
&nbsp;&nbsp;{imgdir} -> URL del directorio del tema
</span>';
$l['ougc_customrep_h_name'] = 'Nombre';
$l['ougc_customrep_h_name_d'] = 'Nombre con el que se identificara a esta reacción (por ejemplo: Gracias).';
$l['ougc_customrep_h_order'] = 'Orden';
$l['ougc_customrep_h_visible'] = 'Visible';

// Permissions page
$l['ougc_customrep_perm'] = 'Puede modificar las reacciones?';

// Tabs
$l['ougc_customrep_tab_view'] = 'Ver';
$l['ougc_customrep_tab_view_d'] = 'Lista de las reacciones existentes.';
$l['ougc_customrep_tab_add'] = 'Agregar';
$l['ougc_customrep_tab_add_d'] = 'Agrega una nueva reacción.';
$l['ougc_customrep_tab_edit'] = 'Editar';
$l['ougc_customrep_tab_edit_d'] = 'Edita una reacción existente.';

// Buttons
$l['ougc_customrep_button_disponder'] = 'Actualizar Orden';
$l['ougc_customrep_button_submit'] = 'Aceptar';

// Form
$l['ougc_customrep_f_groups'] = 'Grupos';
$l['ougc_customrep_f_groups_d'] = 'Selecciona los grupos que pueden utilizar esta reacción.';
$l['ougc_customrep_f_forums'] = 'Foros';
$l['ougc_customrep_f_forums_d'] = 'Selecciona los foros en los que se puede utilizar esta reacción.';
$l['ougc_customrep_f_disporder_d'] = 'Orden en el que aparecerá esta reacción.';
$l['ougc_customrep_f_visible_d'] = 'Te permite habilitar o desactivar temporalmente esta reacción.';
$l['ougc_customrep_h_firstpost'] = 'Primer mensaje solamente';
$l['ougc_customrep_h_firstpost_d'] = 'Elige sí esta reacción solo se puede utilizar en el primer mensaje del tema.';
$l['ougc_customrep_h_allowdeletion'] = 'Permitir eliminar';
$l['ougc_customrep_h_allowdeletion_d'] = 'Permite a los usuarios eliminar sus propias reacciones.';
$l['ougc_customrep_h_customvariable'] = 'Salida en variable personalizada';
$l['ougc_customrep_h_customvariable_d'] = 'Desactiva la salida global de las reacciones. Necesitarás añadir {$post[\'customrep_{1}\']} en tu plantilla "postbit" o "postbit_classic". <strong style="color: red">Esta característica es planeada a futuro, actualmente no hace nada.</strong>';
$l['ougc_customrep_h_requireattach'] = 'Se necesita para descargar archivos adjuntos';
$l['ougc_customrep_h_requireattach_d'] = 'Habilita esta característica si los usuarios tendrán que dar esta reacción a un mensaje para poder descargar sus archivos adjuntos.';
$l['ougc_customrep_h_reptype'] = 'Nivel de reputación';
$l['ougc_customrep_h_reptype_d'] = 'Selecciona cuántos puntos de reputación añadirá a un usuario. Deja vacío para desactivar.';
$l['ougc_customrep_h_points'] = 'Costo en puntos de Newpoints';
$l['ougc_customrep_h_points_d'] = 'Tenga en cuenta que el autor del mensaje recibe los puntos y dichos puntos serán revertidos si se elimina la reacción.';
$l['ougc_customrep_h_ignorepoints'] = 'Ocultar mensaje por conteo';
$l['ougc_customrep_h_ignorepoints_d'] = 'Los mensajes pueden ser ocultados por defecto si alcanzan cierta cantidad de reacciones. Elige la cantidad de reacciones necesarias para ocultar los mensajes.';

// Validation
$l['ougc_customrep_error_invalidname'] = 'Nombre inválido.';
$l['ougc_customrep_error_invalidimage'] = 'Imagen inválida.';
$l['ougc_customrep_error_invaliddisporder'] = 'Orden inválido.';
$l['ougc_customrep_error_invalidreptype'] = 'Nivel de reputación inválido.';

// Settings
$l['setting_group_ougcCustomReputation'] = 'Custom Reputation';
$l['setting_group_ougcCustomReputation_desc'] = 'Permite dar reputación personalizada en las publicaciones.';
$l['setting_ougc_customrep_firstpost'] = 'Primer mensaje solamente (global)';
$l['setting_ougc_customrep_firstpost_desc'] = 'Habilita esta opción si deseas que solo se permitan reacciones en el primer mensaje de un tema. Desactiva si deseas configurar esta opción para cada reacción.';
$l['setting_ougc_customrep_delete'] = 'Permitir borrar (global)';
$l['setting_ougc_customrep_delete_desc'] = 'Habilita esta opción si deseas que se puedan borrar las reacciones. Desactiva si deseas configurar esta opción para cada reacción.';
$l['setting_ougc_customrep_perpage'] = 'Elementos por página';
$l['setting_ougc_customrep_perpage_desc'] = 'Número máximo de elementos a mostrar por página.';
$l['setting_ougc_customrep_fontawesome'] = 'Usar Font Awesome Icons';
$l['setting_ougc_customrep_fontawesome_desc'] = 'Activa esta opción si deseas utilizar font awesome icons en lugar de imágenes.';
$l['setting_ougc_customrep_fontawesome_acp'] = 'Codigo Font Awesome para el ACP';
$l['setting_ougc_customrep_fontawesome_acp_desc'] = 'Agrega el código para cargar Font Awesome icons dentro del panel de administración.';
$l['setting_ougc_customrep_threadlist'] = 'Mostrar en lista de temas';
$l['setting_ougc_customrep_threadlist_desc'] = 'Selecciona los foros donde se mostrarán las reacciones en la lista de temas.';
$l['setting_ougc_customrep_portal'] = 'Mostrar en anuncios del portal';
$l['setting_ougc_customrep_portal_desc'] = 'Selecciona los foros cuyos temas en el portal podrán mostrar la caja de reacciones.';
$l['setting_ougc_customrep_xthreads_hide'] = 'Activar Característica xThreads';
$l['setting_ougc_customrep_xthreads_hide_desc'] = 'Selecciona que campos de xThreads se deben de considerar para controlar el contenido a mostrar. <a href="https://ougc.network/module?faqs&filtertf_plugins_code=ougc_customrep">Favor de leer la documentación para más información.</a> <a href="https://mybb-es.com/hilo-1-8-ougc-custom-reputation-xthreads-pide-a-usuarios-reaccionar-para-mostrar-contenido">También puedes leer la siguiente guía.</a>';
$l['setting_ougc_customrep_stats_profile'] = 'Mostrar estadísticas de los usuarios en sus perfiles';
$l['setting_ougc_customrep_stats_profile_desc'] = 'Habilita esta característica para mostrar las estadísticas de reacciones dadas y recibidas en los perfiles de los usuarios.';
$l['setting_ougc_customrep_enableajax'] = 'Habilitar Características Ajax';
$l['setting_ougc_customrep_enableajax_desc'] = 'Habilitar las características que requieren Ajax. Tenga en cuenta que debe permitirlo en la opción "¿Activar funciones de XMLHttp request?", la cual se encuentra en los ajustes de "Servidor y opciones optimización". De lo contrario, esta función no servirá de nada';
$l['setting_ougc_customrep_guests_popup'] = 'Permitir a los visitantes ver los Popup';
$l['setting_ougc_customrep_guests_popup_desc'] = 'Habilita esta opción si deseas que los visitantes puedan ver las reacciones recibidas a un mensaje.';

$l['setting_ougc_xthreads_information'] = '<span style="color: gray;">Para habilitar esta característica es necesario que tengas instalado <a href="http://mybbhacks.zingaburga.com/showthread.php?tid=288">xThreads</a> y crear algunos campos tal y como se muestra en la documentación.</span> <a href="https://mybb-es.com/hilo-1-8-ougc-custom-reputation-xthreads-pide-a-usuarios-reaccionar-para-mostrar-contenido">Ver guía.</a>';