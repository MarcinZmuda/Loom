# Jak połączyć LOOM z Google Search Console
## Instrukcja krok po kroku (5 minut)

> LOOM  -  wtyczka autorstwa [Marcin Żmuda](https://marcinzmuda.com)

Potrzebujesz: konta Google z dostępem do Google Search Console Twojej strony.

---

## Część 1: Utwórz Service Account w Google Cloud

### Krok 1  -  Otwórz Google Cloud Console

Wejdź na: **https://console.cloud.google.com**

Jeśli nie masz projektu, Google poprosi o utworzenie. Kliknij **Utwórz projekt**, nazwij go np. "LOOM SEO" i kliknij **Utwórz**.

Jeśli masz już projekt  -  upewnij się, że jest wybrany na górze strony (dropdown obok "Google Cloud").

---

### Krok 2  -  Włącz API Search Console

Wejdź na: **https://console.cloud.google.com/apis/library/searchconsole.googleapis.com**

Lub znajdź to ręcznie: lewe menu -> **Interfejsy API i usługi** -> **Biblioteka** -> wyszukaj "Google Search Console API".

Kliknij **Włącz** (niebieski przycisk). Jeśli widzisz "Zarządzaj" zamiast "Włącz"  -  API jest już aktywne, przejdź dalej.

---

### Krok 3  -  Utwórz Service Account

Wejdź na: **https://console.cloud.google.com/iam-admin/serviceaccounts**

Lub: lewe menu -> **IAM i administracja** -> **Konta usługi**.

Kliknij **+ Utwórz konto usługi** (przycisk na górze).

Wypełnij formularz:
- **Nazwa konta usługi**: `loom-gsc` (lub cokolwiek chcesz)
- **Identyfikator konta**: wypełni się automatycznie, np. `loom-gsc@twoj-projekt.iam.gserviceaccount.com`
- **Opis**: opcjonalny, np. "LOOM plugin  -  odczyt danych GSC"

Kliknij **Utwórz i kontynuuj**.

Na ekranie "Przyznaj temu kontu usługi dostęp do projektu"  -  **nic nie wybieraj**. Po prostu kliknij **Kontynuuj**.

Na ekranie "Przyznaj użytkownikom dostęp do tego konta usługi"  -  **nic nie wypełniaj**. Kliknij **Gotowe**.

---

### Krok 4  -  Pobierz klucz JSON

Na liście kont usługi zobaczysz swoje nowo utworzone konto. Kliknij na nie (na email).

Przejdź do zakładki **Klucze** (na górze strony).

Kliknij **Dodaj klucz** -> **Utwórz nowy klucz**.

Wybierz format: **JSON** (powinien być zaznaczony domyślnie).

Kliknij **Utwórz**.

Plik JSON automatycznie się pobierze na Twój komputer. Nazwa będzie wyglądać mniej więcej tak: `twoj-projekt-abc123def456.json`.

**Zapamiętaj gdzie jest ten plik**  -  za chwilę wkleisz jego zawartość do LOOM.

---

## Część 2: Dodaj Service Account jako użytkownika w GSC

### Krok 5  -  Skopiuj email Service Account

Otwórz pobrany plik JSON w dowolnym edytorze tekstu (Notatnik, VS Code, cokolwiek).

Znajdź linię `"client_email"`. Będzie wyglądać tak:

```
"client_email": "loom-gsc@twoj-projekt.iam.gserviceaccount.com"
```

Skopiuj ten email (bez cudzysłowów).

---

### Krok 6  -  Dodaj email do Google Search Console

Wejdź na: **https://search.google.com/search-console**

Wybierz swoją stronę (property).

W lewym menu kliknij **Ustawienia** (na samym dole, ikona zębatki).

Kliknij **Użytkownicy i uprawnienia**.

Kliknij **Dodaj użytkownika** (niebieski przycisk).

W polu email wklej skopiowany adres Service Account, np.:
`loom-gsc@twoj-projekt.iam.gserviceaccount.com`

Uprawnienia: wybierz **Ograniczone** (wystarczy do odczytu danych  -  LOOM nie modyfikuje niczego w GSC).

Kliknij **Dodaj**.

---

## Część 3: Połącz w LOOM

### Krok 7  -  Wklej JSON w LOOM

W WordPressie wejdź do **LOOM -> Ustawienia**.

Znajdź sekcję **📊 Google Search Console**.

Otwórz pobrany plik JSON i skopiuj **całą zawartość** (Ctrl+A, Ctrl+C w edytorze tekstu).

Wklej ją do pola tekstowego w LOOM.

W polu "URL strony w GSC" wpisz dokładnie taki adres, jaki masz w Google Search Console. Ważne: musi pasować dokładnie  -  ze slashem na końcu lub bez, z `https://` lub `http://`, z `www.` lub bez, dokładnie tak jak w GSC.

Kliknij **📊 Połącz GSC**.

Jeśli zobaczysz **✅ GSC połączony!**  -  gotowe. LOOM automatycznie zweryfikował połączenie.

---

## Krok 8  -  Pierwsza synchronizacja

Po połączeniu kliknij **🔄 Synchronizuj** żeby pobrać dane z ostatnich 28 dni.

Synchronizacja potrwa kilka-kilkanaście sekund (zależy od ilości stron).

Po zakończeniu LOOM pokaże ile stron zsynchronizował i ile jest w "striking distance" (pozycja 5-20 w Google).

---

## Rozwiązywanie problemów

**"403 Forbidden" lub "User does not have sufficient permissions"**
-> Email Service Account nie jest dodany jako użytkownik w GSC (krok 6). Sprawdź czy email się zgadza.

**"404 Not Found" lub "Site not found"**
-> URL strony w LOOM nie pasuje do property w GSC. Sprawdź czy jest dokładnie taki sam. Częsty problem: w GSC masz `https://www.example.com/` a w LOOM wpisałeś `https://example.com` (bez www).

**"Nie udało się podpisać JWT"**
-> Plik JSON jest uszkodzony lub niekompletny. Upewnij się, że skopiowałeś CAŁĄ zawartość (od `{` do `}`). Sprawdź czy pole `private_key` zawiera klucz zaczynający się od `-----BEGIN PRIVATE KEY-----`.

**"To nie jest klucz Service Account"**
-> Wybrałeś zły typ klucza. Upewnij się, że w pliku JSON jest `"type": "service_account"`. Jeśli masz `"type": "authorized_user"`  -  to nie ten plik. Wróć do kroku 4.

**"Google Search Console API has not been used in project"**
-> Nie włączyłeś API (krok 2). Wejdź na link z kroku 2 i kliknij "Włącz".

**Dane GSC się nie pojawiają mimo "Połączono"**
-> Kliknij "Synchronizuj" w ustawieniach. Dane nie pobierają się automatycznie przy połączeniu  -  trzeba ręcznie uruchomić pierwszą synchronizację.

---

## Bezpieczeństwo

- Klucz prywatny z pliku JSON jest szyfrowany algorytmem AES-256-CBC i przechowywany w bazie WordPressa. Nikt nie może go odczytać bez dostępu do bazy danych i pliku `wp-config.php`.
- Service Account ma uprawnienia **tylko do odczytu** danych Search Console (scope: `webmasters.readonly`). LOOM nie może modyfikować niczego w Twojej stronie w Google.
- Plik JSON możesz usunąć z komputera po wklejeniu do LOOM  -  nie jest więcej potrzebny.
- W każdej chwili możesz kliknąć "Rozłącz" w LOOM lub usunąć Service Account z Google Cloud Console.
