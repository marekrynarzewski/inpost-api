# Inpost API

## Opis

To niewielki projekt integracyjny oparty o InPost ShipX API. Jego rdzeniem jest przeplyw:

1. utworzenie przesylki kurierskiej `inpost_courier_standard`
2. polling statusu przesylki do momentu `confirmed`
3. utworzenie `dispatch_order`, czyli zamowienia odbioru przez kuriera

Projekt dostal tez warstwe prezentacji, zeby mozna bylo pokazac go pracodawcy bez przechodzenia od razu do kodu CLI.

## Wymagania

- PHP 8.1+
- Composer
- token API InPost do trybu live

## Instalacja

```bash
composer install
cp .env.example .env
```

W pliku `.env` uzupelnij:

```dotenv
PACZKOMATY_INPOST_APITOKEN=your_real_api_token_here
PACZKOMATY_INPOST_ORGANIZATIONID=1111
```

## Skad wziac zmienne `.env`

Zgodnie z instrukcja InPost ShipX:

1. Zaloguj sie do [Managera Paczek](https://manager.paczkomaty.pl/).
2. Przejdz do `Moje Konto`.
3. Upewnij sie, ze uzupelnione sa:
   - dane adresowe firmy
   - dane do faktury
4. Otworz panel zarzadzania dostepami API.
5. W sekcji `API ShipX` kliknij `Generuj`.
6. Jesli masz numer klienta z umowy kurierskiej, zaznacz odpowiednia opcje i podaj ten numer.
   Jesli chcesz dostep tylko do uslug paczkomatowych, generuj bez numeru klienta.
7. Po wygenerowaniu dostepu:
   - w `Ustawienia organizacji` znajdziesz `ID organizacji`
   - w sekcji `API ShipX` znajdziesz `Token`

Mapowanie do `.env`:

```dotenv
PACZKOMATY_INPOST_ORGANIZATIONID=<ID organizacji>
PACZKOMATY_INPOST_APITOKEN=<Token API ShipX>
```

Uwagi:

- token `Geowidget` nie jest potrzebny w tym projekcie
- webhook z panelu InPost nie jest wymagany dla obecnej wersji integracji
- dla jednej organizacji mozna wygenerowac do 10 tokenow ShipX

Zrodlo: [Instrukcja konfiguracji API ShipX](https://inpost.pl/sites/default/files/2022-03/instrukcja-konfiguracji-api-shipx.pdf)

## Showcase w przegladarce

Uruchom lokalny serwer PHP:

```bash
php -S localhost:8080 -t public
```

Nastepnie otworz `http://localhost:8080`.

Ekran prezentacyjny wspiera dwa tryby:

- `demo` pokazuje symulowany, ale realistyczny przebieg integracji
- `live` wykonuje realne requesty do sandboxowego ShipX, jesli w `.env` jest poprawny token
- kody pocztowe dla `sender`, `receiver` i `dispatch` sa walidowane oraz normalizowane do formatu `00-000`

## Docker

Projekt ma gotowy `docker-compose.yaml` z `php-fpm` i `nginx`.

Uruchomienie:

```bash
cp .env.example .env
docker compose up --build -d
docker compose exec -T php composer install
```

Showcase bedzie dostepny pod adresem:

```text
http://localhost:8090
```

Mozesz tez uruchomic go przez workspace `Nx`:

```bash
cd /home/marek/Projekty/portfolio/bitbucket
npm run nx -- run focus-garden:build
```

## CLI

Tryb demonstracyjny:

```bash
php create_shipment.php
```

Tryb live:

```bash
php create_shipment.php --live
```
