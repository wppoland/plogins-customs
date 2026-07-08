=== Plogins Customs - EU Import Duty for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, import duty, customs, eu, checkout
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Oszacuj i dodaj zryczałtowane cło importowe UE jako wyraźną linię kasową dla paczek wysyłanych do UE spoza UE. Gotowy na WooCommerce.

== Description ==

Od 1 lipca 2026 r. UE zniesie próg bezcłowy w wysokości 150 EUR w przypadku importu o niskiej wartości i zastosuje zryczałtowane cło w każdej pozycji taryfowej w przypadku przesyłek o wartości do 150 EUR wysyłanych do UE spoza UE. Urząd celny szacuje tę kwotę i pokazuje ją kupującemu jako własną linię przy koszyku i kasie, dzięki czemu nie występują żadne niespodziewane opłaty za dostawę.

Dodaje cło tylko wtedy, gdy wszystkie poniższe warunki są spełnione: funkcja jest włączona, sklep wysyła towary spoza UE, miejscem docelowym jest kraj UE, a wartość towarów w koszyku jest równa lub niższa od Twojego progu. Zamówienia wewnątrzunijne i zamówienia przekraczające próg pozostają niezmienione.

Co robi:

* Dodaje opłatę „cło importowe UE (szacunkowe)” przy koszyku i kasie przy użyciu natywnego interfejsu API opłat WooCommerce
* Oblicza cło jako liczbę odrębnych linii taryfowych w koszyku pomnożoną przez kwotę przypadającą na linię
* Zlicza linie taryfowe z kodu taryfowego dla poszczególnych produktów, cofając się do kategorii produktu, a następnie do produktu
* Działa w klasycznej kasie oraz w koszyku i blokach kasowych i jest kompatybilny z HPOS
* Kwota w wierszu, próg, kraj pochodzenia sklepu, kurs wymiany EUR, podstawa linii taryfowej, etykieta opłaty i flaga podatku są konfigurowalne
* Dodaje podatki na górze: cło jest wyświetlane jako osobna linia oprócz podatku VAT

Jest to odpowiednik WooCommerce opłaty celnej importowej, którą platformy hostowane dodają przy kasie, bez miesięcznej subskrypcji.

== Translations ==

Customs zawiera tłumaczenia interfejsu wtyczki na język polski, niemiecki i hiszpański. Domena tekstowa to `plogins-customs`, więc pakiety językowe WordPress.org mogą również zastąpić lub rozszerzyć te dołączone tłumaczenia.

== Installation ==

1. Zainstaluj i aktywuj WooCommerce.
2. Zainstaluj Customs i aktywuj go.
3. Otwórz WooCommerce, a następnie EU Import Duty, ustaw kwotę i próg w wierszu oraz potwierdź kraj pochodzenia sklepu.
4. Przypisz linie taryfowe do produktów, jeśli chcesz mieć lepszą kontrolę, w przeciwnym razie każda odrębna kategoria produktów liczy się jako jedna linia.

== Frequently Asked Questions ==

= When does the duty apply? =
Tylko w przypadku zamówień wysyłanych do kraju UE ze sklepu znajdującego się poza UE, o wartości towaru równej lub niższej od Twojego progu (domyślnie 150 EUR). Zamówienia wewnątrzunijne są wyłączone.

= How is the duty calculated? =
Liczba odrębnych linii taryfowych w koszyku pomnożona przez kwotę na linię (domyślnie 3 EUR). Przesyłka jednego rodzaju produktu to jedna linia; paczka obejmująca kilka odrębnych kategorii liczy się jako kilka linii.

= Does it work with the Cart and Checkout Blocks? =
Tak. Cło jest dodawane poprzez natywny interfejs API opłat WooCommerce, więc pojawia się zarówno w kasie klasycznej, jak i kasie blokowej i jest kompatybilne z HPOS.

= Is the amount exact? =
Jest to wartość szacunkowa oparta na Twoich ustawieniach. Ostateczne cła i wszelkie krajowe opłaty manipulacyjne ustalane są przez organy celne przy imporcie. Aktualizuj kwotę i próg w wierszu zgodnie z obowiązującymi zasadami.

= Can I sell in a currency other than EUR? =
Tak. Ustaw w ustawieniach kurs waluty EUR do przechowywania, a cło zostanie przeliczone przed dodaniem.


= Does this plugin work on WordPress Multisite? =

Tak. Ta wtyczka jest kompatybilna z WordPress Multisite. Aktywuj go w sieci lub aktywuj na poszczególnych stronach; każda witryna przechowuje własne ustawienia i dane.

== Screenshots ==

1. Szacunkowe cło importowe UE pokazane jako osobna pozycja w sumie koszyka.
2. Ustawienia ceł importowych UE w WooCommerce: kwota w wierszu, próg, kraj pochodzenia i sposób liczenia linii taryfowych.
3. Ten sam zakres obowiązków w wózku na telefonie komórkowym.

== Changelog ==

= 1.0.2 =
* Poprawiono tłumaczenia na język polski, niemiecki i hiszpański (terminologia celna: Einfuhrzoll, Zolltarifnummer, cło importowe, arancel).

= 1.0.1 =
* Pierwsza stabilna wersja.

= 0.1.4 =
* Dodano dołączone tłumaczenia na język polski, niemiecki i hiszpański dla interfejsu wtyczki.
* Odświeżono szablon tłumaczenia dla bieżącej domeny tekstowej i ciągów ustawień.

= 0.1.3 =
* Dodano przegląd ustawień na ekranie nadchodzących funkcji PRO (kursy wymiany na żywo, klasyfikacja kodów HS). Brak zmian w kalkulacji bezpłatnego cła.

= 0.1.2 =
* Domena tekstowa pasuje teraz do wtyczki (plogins-customs) we wszystkich ciągach znaków i szablonie tłumaczenia, więc działają pakiety językowe wp.org.

= 0.1.1 =
* Przywrócono dostosowaną do wp.org nazwę pliku startowego, domenę tekstową i szablon tłumaczenia dla ślimaka „customs”.

= 0.1.0 =
* Wersja pierwsza: zryczałtowana stawka celna importowa UE oszacowana i dodana jako opłata za koszyk i kasę, z konfigurowalną kwotą w wierszu, progiem, pochodzeniem sklepu, kursem waluty, podstawą linii taryfowej, etykietą i flagą podlegającą opodatkowaniu.
