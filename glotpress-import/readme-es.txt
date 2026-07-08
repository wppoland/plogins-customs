=== Plogins Customs - EU Import Duty for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, import duty, customs, eu, checkout
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Calcule y añade el derecho fijo de importación de la UE como una línea de pago clara para los paquetes enviados a la UE desde fuera de ella. Listo para WooCommerce.

== Description ==

A partir del 1 de julio de 2026, la UE elimina el umbral libre de derechos de 150 EUR para las importaciones de bajo valor y aplica un derecho de aduana fijo por línea arancelaria a envíos de hasta 150 EUR enviados a la UE desde fuera de ella. La Aduana calcula ese impuesto y se lo muestra al comprador como su propia línea en el carrito y en la caja, por lo que no hay cargos sorpresa en la entrega.

Solo añade el deber cuando todo esto es cierto: la función está habilitada, la tienda realiza envíos desde fuera de la UE, el destino es un país de la UE y el valor de los productos del carrito está en o por debajo de su umbral. Los pedidos dentro de la UE y los pedidos que superan el umbral no se modifican.

Qué hace:

* Añade una tarifa de "derechos de importación de la UE (estimación)" en el carrito y en el momento del pago utilizando la API de tarifas nativa de WooCommerce
* Calcula el arancel como el número de líneas arancelarias distintas en el carrito multiplicado por el monto por línea
* Cuenta las líneas arancelarias de un código arancelario por producto, volviendo a la categoría de producto y luego al producto.
* Funciona en la caja clásica y en los bloques Carrito y Caja, y es compatible con HPOS
* El monto por línea, el umbral, el país de origen de la tienda, la tasa de conversión de EUR, la base de la línea arancelaria, la etiqueta de tarifa y la bandera imponible son todos configurables.
* Añade impuestos encima: el impuesto se muestra como una línea propia además del IVA

Este es el equivalente en WooCommerce del manejo de derechos de importación que las plataformas alojadas agregan al finalizar la compra, sin una suscripción mensual.

== Translations ==

Customs incluye traducciones al polaco, alemán y español para la interfaz del complemento. El dominio de texto es `plogins-customs`, por lo que los paquetes de idioma de WordPress.org también pueden anular o ampliar estas traducciones empaquetadas.

== Installation ==

1. Instale y active WooCommerce.
2. Instale Aduana y actívelo.
3. Abra WooCommerce y luego los derechos de importación de la UE, establezca el monto y el umbral por línea y confirme el país de origen de su tienda.
4. Asigne líneas arancelarias a los productos si desea un control más preciso; de lo contrario, cada categoría de producto distinta cuenta como una línea.

== Frequently Asked Questions ==

= When does the duty apply? =
Solo para pedidos enviados a un país de la UE desde una tienda ubicada fuera de la UE, con un valor de mercancía igual o inferior a su umbral (150 EUR por defecto). Se excluyen los pedidos dentro de la UE.

= How is the duty calculated? =
El número de líneas arancelarias distintas en el carrito multiplicado por el importe por línea (3 EUR de forma predeterminada). Un paquete de un tipo de producto es una línea; un paquete que abarca varias categorías distintas cuenta como varias líneas.

= Does it work with the Cart and Checkout Blocks? =
Sí. El impuesto se añade a través de la API de tarifas nativa de WooCommerce, por lo que aparece tanto en el pago clásico como en el pago de Bloques, y es compatible con HPOS.

= Is the amount exact? =
Es una estimación basada en tu configuración. Los derechos finales y cualquier tarifa de manipulación nacional los determina la aduana en el momento de la importación. Mantenga su monto por línea y su umbral actualizados con las reglas actuales.

= Can I sell in a currency other than EUR? =
Sí. Establezca el tipo de cambio de EUR a la moneda de la tienda en la configuración y el impuesto se convertirá antes de agregarlo.


= Does this plugin work on WordPress Multisite? =

Sí. Este complemento es compatible con WordPress Multisite. Activarlo en red o activarlo en sitios individuales; Cada sitio mantiene su propia configuración y datos.

== Screenshots ==

1. El derecho de importación estimado de la UE se muestra como su propia línea en los totales del carrito.
2. La configuración de los derechos de importación de la UE en WooCommerce: monto por línea, umbral, país de origen y cómo se cuentan las líneas arancelarias.
3. La misma línea de trabajo en el carrito del móvil.

== Changelog ==

= 1.0.2 =
* Corregidas las traducciones al polaco, alemán y español (terminología aduanera: Einfuhrzoll, Zolltarifnummer, cło importowe, arancel).

= 1.0.1 =
* Primera versión estable.

= 0.1.4 =
* Se agregaron traducciones integradas en polaco, alemán y español para la interfaz del complemento.
* Se actualizó la plantilla de traducción para el dominio de texto actual y las cadenas de configuración.

= 0.1.3 =
* Se agregó una descripción general de la pantalla de configuración de las próximas funciones PRO (tipos de cambio en vivo, clasificación de códigos HS). No hay cambios en el cálculo del derecho libre.

= 0.1.2 =
* El dominio de texto ahora coincide con el complemento (plogins-customs) en todas las cadenas y la plantilla de traducción, por lo que los paquetes de idioma de wp.org funcionan.

= 0.1.1 =
* Se restauró el nombre del archivo de arranque, el dominio de texto y la plantilla de traducción alineados con wp.org para el slug `customs`.

= 0.1.0 =
* Lanzamiento inicial: impuesto de importación fijo de la UE estimado y agregado como carrito y tarifa de pago, con monto por línea configurable, umbral, origen de la tienda, tipo de cambio, base de línea arancelaria, etiqueta y bandera imponible.
