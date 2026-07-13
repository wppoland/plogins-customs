=== Plogins Customs - EU Import Duty for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, import duty, customs, eu, checkout
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Schätze den pauschalierten EU-Einfuhrzoll und füge ihn als klare Checkout-Linie für Pakete hinzu, die von außerhalb der EU in die EU versendet werden. WooCommerce bereit.

== Description ==

Ab dem 1. Juli 2026 hebt die EU die Zollfreischwelle von 150 EUR für Einfuhren von geringem Wert auf und erhebt einen Pauschalzoll pro Tariflinie auf Sendungen bis zu 150 EUR, die von außerhalb der EU in die EU versandt werden. Der Zoll schätzt diesen Zoll und zeigt ihn dem Käufer als eigene Zeile im Warenkorb und an der Kasse an, sodass bei der Lieferung keine überraschenden Kosten anfallen.

Der Zoll wird nur dann erhoben, wenn all diese Punkte zutreffen: Die Funktion ist aktiviert, der Shop versendet von außerhalb der EU, der Bestimmungsort ist ein EU-Land und der Warenwert im Warenkorb liegt bei oder unter deinem Schwellenwert. EU-interne Bestellungen und Bestellungen über dem Schwellenwert bleiben unberührt.

Was es macht:

* Fügt eine „EU-Einfuhrzoll (Schätzung)“-Gebühr im Warenkorb und an der Kasse über die native WooCommerce-Gebühren-API hinzu
* Berechnet den Zoll als Anzahl der verschiedenen Tarifpositionen im Warenkorb multipliziert mit deinem Betrag pro Zeile
* Zählt Tarifpositionen ausgehend von einem Tarifcode pro Produkt, wobei auf die Produktkategorie und dann auf das Produkt zurückgegriffen wird
* Funktioniert im klassischen Checkout sowie in den Warenkorb- und Checkout-Blöcken und ist HPOS-kompatibel
* Zeilenbetrag, Schwellenwert, Ursprungsland des Shops, EUR-Umrechnungskurs, Tarifzeilenbasis, Gebührenetikett und Steuerkennzeichen sind alle konfigurierbar
* Hinzu kommen Steuern: Der Zoll wird zusätzlich zur Mehrwertsteuer als eigene Zeile ausgewiesen

Dies ist das WooCommerce-Äquivalent zur Einfuhrzollabwicklung, die gehostete Plattformen an der Kasse hinzufügen, ohne ein monatliches Abonnement.

== Translations ==

Customs umfasst polnische, deutsche und spanische Übersetzungen für die Plugin-Schnittstelle. Die Textdomäne ist „plogins-customs“, sodass WordPress.org-Sprachpakete diese gebündelten Übersetzungen auch überschreiben oder erweitern können.

== Installation ==

1. Installiere und aktiviere WooCommerce.
2. Installiere Customs und aktiviere es.
3. Öffne WooCommerce und dann EU Import Duty, lege den Betrag pro Zeile und den Schwellenwert fest und bestätige das Herkunftsland deines Shops.
4. Ordne den Produkten Tarifpositionen zu, wenn du eine genauere Kontrolle möchtest. Andernfalls zählt jede einzelne Produktkategorie als eine Zeile.

== Frequently Asked Questions ==

= When does the duty apply? =
Nur für Bestellungen, die von einem Geschäft außerhalb der EU in ein EU-Land versandt werden und deren Warenwert deinem Schwellenwert oder darunter entspricht (standardmäßig 150 EUR). Bestellungen innerhalb der EU sind ausgeschlossen.

= How is the duty calculated? =
Die Anzahl der einzelnen Tarifzeilen im Warenkorb multipliziert mit deinem Betrag pro Zeile (standardmäßig 3 EUR). Ein Paket eines Produkttyps ist eine Zeile; ein Paket, das mehrere unterschiedliche Kategorien umfasst, zählt als mehrere Zeilen.

= Does it work with the Cart and Checkout Blocks? =
Ja. Die Gebühr wird über die native WooCommerce-Gebühren-API hinzugefügt, sodass sie sowohl im klassischen Checkout als auch im Blocks-Checkout angezeigt wird und HPOS-kompatibel ist.

= Is the amount exact? =
Es handelt sich um eine Schätzung, die auf deinen Einstellungen basiert. Die endgültigen Zölle und etwaige nationale Bearbeitungsgebühren werden vom Zoll beim Import festgelegt. Halte deinen Betrag pro Zeile und deinen Schwellenwert gemäß den aktuellen Regeln auf dem neuesten Stand.

= Can I sell in a currency other than EUR? =
Ja. Lege in den Einstellungen den EUR-zu-Shop-Währungskurs fest und der Zoll wird umgerechnet, bevor er hinzugefügt wird.


= Does this plugin work on WordPress Multisite? =

Ja. Dieses Plugin ist mit WordPress Multisite kompatibel. Aktiviere es im Netzwerk oder auf einzelnen Websites. Jede Site behält ihre eigenen Einstellungen und Daten.

== Screenshots ==

1. Der geschätzte EU-Einfuhrzoll wird als eigene Zeile in den Warenkorbsummen angezeigt.
2. Die EU-Einfuhrzolleinstellungen unter WooCommerce: Betrag pro Zeile, Schwellenwert, Ursprungsland und Art und Weise, wie Zollpositionen gezählt werden.
3. Dieselbe Zollzeile im Warenkorb auf dem Handy.

== Changelog ==

= 1.0.3 =
* Gebündelte polnische, deutsche und spanische Übersetzungen für die Plugin-Schnittstelle hinzugefügt.

= 1.0.2 =
* Die polnischen, deutschen und spanischen Übersetzungen wurden korrigiert (Zollterminologie: Einfuhrzoll, Zolltarifnummer, cło importowe, arancel).

= 1.0.1 =
* Erste stabile Version.

= 0.1.4 =
* Gebündelte polnische, deutsche und spanische Übersetzungen für die Plugin-Schnittstelle hinzugefügt.
* Die Übersetzungsvorlage für die aktuelle Textdomäne und die Einstellungszeichenfolgen wurde aktualisiert.

= 0.1.3 =
* Auf dem Einstellungsbildschirm wurde eine Übersicht über kommende PRO-Funktionen hinzugefügt (Live-Wechselkurse, HS-Code-Klassifizierung). Keine Änderung an der Berechnung des freien Zolls.

= 0.1.2 =
* Die Textdomäne stimmt jetzt über alle Zeichenfolgen und die Übersetzungsvorlage mit dem Plugin-Slug (plogins-customs) überein, sodass wp.org-Sprachpakete funktionieren.

= 0.1.1 =
* Der an wp.org angepasste Bootstrap-Dateiname, die Textdomäne und die Übersetzungsvorlage für den „Customs“-Slug wurden wiederhergestellt.

= 0.1.0 =
* Erstveröffentlichung: EU-Pauschal-Einfuhrzoll geschätzt und als Warenkorb- und Checkout-Gebühr hinzugefügt, mit konfigurierbarem Betrag pro Zeile, Schwellenwert, Ursprungsort im Geschäft, Währungskurs, Tariflinienbasis, Etikett und Steuerkennzeichen.
