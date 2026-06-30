=== WP Query Block Extension - Frontend Taxonomy Filters ===
Requires at least: 6.5
Tested up to: 7.0
Stable tag: 0.1.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add taxonomy filter in the frontend page that filters posts returned from the query block.

== Description ==

Add taxonomy filter functionality to the WP Query block. Allows frontend filtering of posts by taxonomy terms using dropdowns or checkboxes.

Documentation available at https://cms.ubc.ca/support/cms-manual/list-of-blocks/wp-query-block-extension-taxonomy-filters/

== Changelog ==

= 0.1.1 =

**Modo global: filtro fuera del loop afecta todos los query loops de la página**

* Se agregó detección automática en `render.php` — si el bloque de filtro se coloca fuera de cualquier `core/query`, entra en modo global (`isGlobal: true` en el contexto).
* En modo global, el JS actualiza los parámetros de URL de todos los `.wp-block-query[data-wp-router-region]` presentes en la página simultáneamente.
* En PHP, `get_global_taxonomy_filter_map()` escanea el contenido de la página con `parse_blocks()` y recopila los filtros globales; cada `core/query` los incorpora al construir su `tax_query`.

**Soporte para múltiples loops independientes en la misma página**

* Cada loop con su propio filtro anidado filtra de forma independiente.
* Corrección de bug crítico: `array_merge()` reindexaba las claves numéricas del `$hash_map` (instanceId), rompiendo la búsqueda de taxonomía para cualquier filtro con `instanceId > 0`. Se reemplazó por el operador `+` que preserva las claves.

**Corrección de bug de reactividad en el sistema de Interactivity API**

* `getContext()` debe llamarse antes de cualquier `return` anticipado en el callback de `data-wp-watch`. Llamarlo después del early return impedía que Preact Signals registrara `selectedTerm` como dependencia reactiva, por lo que el callback nunca se volvía a ejecutar al cambiar el filtro.
* Se reemplazó el booleano de módulo `didRunInitially` por un `WeakSet` por elemento (`initializedElements`), evitando que el segundo filtro en la página omitiera su inicialización incorrectamente.

**Corrección de advertencias de auditoría del plugin**

* Textdomain corregido: `ctlt-gf-saved-forms` → `wp-query-loop-extension-frontend-taxonomy-filter`.
* Agregado `if ( ! defined( 'ABSPATH' ) ) { exit; }` en `render.php`.
* Comentarios `phpcs:ignore` para verificación de nonce en parámetros GET de solo lectura.
* Prefijo de namespace aplicado a todas las funciones globales.
* Descripción corta del plugin ajustada a más de 10 palabras.
* Encabezados del README reestructurados al formato estándar de WordPress.org.
* Versión mínima de WordPress actualizada a 6.5.
