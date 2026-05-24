# Meta AI DM Integracija - Admin Setup Uputstvo

Ovaj dokument je operativno uputstvo za vlasnika/admina Frizerino platforme. Cilj je da Frizerino ima jedan centralni Meta Business App, a da pojedinacni saloni mogu povezati svoj Facebook Page i/ili Instagram Business nalog samo kada im admin ukljuci premium opciju.

> Vazno: Meta pravila, permissioni i App Review zahtjevi se mijenjaju. Prije produkcije uvijek provjeri aktuelne zahtjeve u Meta for Developers dokumentaciji:
>
> - https://developers.facebook.com/docs/facebook-login
> - https://developers.facebook.com/docs/messenger-platform/webhooks
> - https://developers.facebook.com/docs/messenger-platform/send-messages
> - https://developers.facebook.com/docs/instagram-platform

## 1. Sta ova implementacija trenutno podrzava

Backend podrzava:

- premium entitlement po salonu: `social_integrations_enabled`
- operativni AI prekidac po salonu: `chatbot_enabled`
- Meta OAuth za Facebook Page + povezani Instagram Business account
- izbor Facebook Page-a ako owner ima vise stranica
- cuvanje encrypted Page access tokena
- Meta webhook endpoint: `POST /api/v1/chatbot/webhook`
- webhook signature provjeru preko `X-Hub-Signature-256`
- AI odgovor i zakazivanje termina iz DM poruka
- health/status podatke za integracije
- admin force disconnect endpoint
- Instagram-only scaffold kroz `connection_mode=instagram_only`

Instagram-only je namjerno disabled dok Meta App ne dobije odgovarajuci review i dok se ne unesu posebni env URL-ovi za taj flow.

## 2. Osnovni produkcijski tok

Preporuceni produkcijski tok je:

1. Admin Frizerino platforme napravi Meta Business App.
2. Admin podesi OAuth, webhook i App Review permissione.
3. Admin deploya `.env` konfiguraciju.
4. Admin u Frizerino admin panelu ukljuci `Premium AI DM integracija` za salon.
5. Vlasnik salona otvori dashboard i klikne `Povezi Facebook Page + Instagram`.
6. Vlasnik se loguje na svoj Meta/Facebook nalog i da dozvole.
7. Backend pronalazi Facebook Page i povezani Instagram Business account.
8. Backend aktivira integraciju i AI moze odgovarati na poruke.

## 3. Meta Business i Developer App setup

### 3.1. Business portfolio

U Meta Business Suite / Business Manager:

1. Napravi ili koristi postojeci Business portfolio za Frizerino.
2. Verifikuj business ako Meta to trazi za App Review.
3. Dodaj admin korisnike koji ce upravljati aplikacijom.
4. Pripremi test Facebook Page i test Instagram Business account.
5. Povezi test Instagram Business account sa test Facebook Page-om.

### 3.2. Kreiranje Meta app-a

U Meta for Developers:

1. Idi na **My Apps**.
2. Klikni **Create App**.
3. Izaberi app tip/use-case koji podrzava business integracije i messaging.
4. Naziv aplikacije: npr. `Frizerino AI Booking`.
5. Business portfolio: izaberi Frizerino business.
6. U App Dashboard dodaj proizvode/funkcionalnosti:
   - Facebook Login for Business ili Facebook Login
   - Messenger / Messenger API
   - Instagram / Instagram Messaging API
   - Webhooks

Nazivi proizvoda u Meta dashboardu mogu varirati po verziji dashboarda. Bitno je da aplikacija moze traziti Page permissione i messaging permissione.

## 4. App settings

U **App settings > Basic** podesi:

- App name: `Frizerino AI Booking`
- App domains:
  - `frizerino.com`
  - `api.frizerino.com`
  - test domena ako se koristi, npr. `wizionar.space`
  - test API domena ako se koristi, npr. `api.wizionar.space`
- Privacy Policy URL: javna URL stranica politike privatnosti
- Terms of Service URL: javna URL stranica uslova koristenja
- User Data Deletion URL ili Data Deletion Instructions URL
- Contact email: admin/support email
- Category: odgovarajuca business/service kategorija

Ako Frizerino jos nema Data Deletion URL, pripremi javnu stranicu koja objasnjava kako korisnik moze traziti brisanje podataka. Meta App Review obicno trazi da je ovo javno dostupno.

## 5. OAuth konfiguracija

### 5.1. Produkcija

OAuth redirect URI:

```text
https://api.frizerino.com/api/v1/admin/social-integrations/callback
```

Frontend owner stranica poziva:

```text
https://api.frizerino.com/api/v1/admin/social-integrations/connect?mode=facebook_page
```

Instagram-only, kada bude odobren i konfigurisan:

```text
https://api.frizerino.com/api/v1/admin/social-integrations/connect?mode=instagram_only
```

### 5.2. Test/staging

Ako koristis test okruzenje:

```text
https://api.wizionar.space/api/v1/admin/social-integrations/callback
```

U Meta app dashboardu dodaj i test i produkcijski redirect URI ako testiras oba okruzenja.

## 6. Webhook konfiguracija

U Meta App Dashboard > Webhooks:

Callback URL:

```text
https://api.frizerino.com/api/v1/chatbot/webhook
```

Verify token:

```text
isti string koji je unesen u META_WEBHOOK_VERIFY_TOKEN
```

Webhook verify endpoint u backendu je:

```text
GET /api/v1/chatbot/webhook
```

Inbound poruke stizu na:

```text
POST /api/v1/chatbot/webhook
```

Backend ocekuje Meta potpis:

```text
X-Hub-Signature-256
```

U produkciji ostavi:

```env
META_VERIFY_WEBHOOK_SIGNATURE=true
```

### Webhook fields

Za Facebook Page + Instagram flow app treba biti subscribed na polja koja koristi kod:

```text
messages
messaging_postbacks
message_reads
```

Backend nakon povezivanja poziva Page subscribed apps endpoint i pokusava automatski subscribe-ati app na ta polja.

## 7. Permissioni koje trazimo

Za stabilni `facebook_page` flow backend koristi:

```text
pages_show_list
pages_messaging
pages_manage_metadata
instagram_basic
instagram_manage_messages
```

Zasto:

- `pages_show_list`: da vlasnik salona moze vidjeti svoje Facebook Page-ove.
- `pages_messaging`: da aplikacija moze slati/primati Page poruke.
- `pages_manage_metadata`: da backend moze subscribe-ati app na Page webhook.
- `instagram_basic`: da backend moze pronaci povezani Instagram Business account.
- `instagram_manage_messages`: da aplikacija moze raditi sa Instagram DM porukama.

U App Review objasnjenje treba biti vrlo konkretno:

> Frizerino salon owner connects their own Facebook Page and Instagram Business account. Frizerino AI responds only to appointment booking messages, lists services/prices/staff availability, collects client name/phone, and creates appointments in the salon calendar.

## 8. App Review priprema

Meta reviewer mora moci ponoviti flow. Pripremi:

1. Test Frizerino admin nalog.
2. Test salon owner nalog.
3. Test salon sa:
   - uslugama
   - cijenama
   - trajanjem usluga
   - frizerima
   - radnim vremenom
   - slobodnim terminima
4. Test Facebook Page.
5. Test Instagram Business account povezan sa test Page-om.
6. Kratak demo video.

### Demo video mora pokazati

1. Login u Frizerino kao admin.
2. Ukljucivanje `Premium AI DM integracija` za salon.
3. Login kao vlasnik salona.
4. Otvaranje `Instagram/Facebook Integracija`.
5. Klik na `Povezi Facebook Page + Instagram`.
6. Meta OAuth ekran i odobravanje permissiona.
7. Izbor Page-a ako ih ima vise.
8. Uspjesno povezivanje.
9. Slanje DM poruke test Instagram accountu ili Page-u.
10. AI odgovor koji pita za uslugu/datum/vrijeme.
11. Kreiranje termina nakon potvrde.

### App Review tekst

Primjer teksta za permission review:

```text
Frizerino is a salon booking platform. Salon owners connect their own Facebook Page and Instagram Business account so the platform can automatically answer appointment booking messages. The AI assistant only handles booking-related conversations: services, prices, staff, working hours, available slots, customer name/phone, and appointment creation. If the customer asks for a human or asks outside the booking scope, the conversation is marked for human follow-up.
```

## 9. Backend `.env` konfiguracija

### 9.1. Obavezno za produkciju

U `backend/.env`:

```env
CHATBOT_ENABLED=true

OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
OPENAI_MAX_TOKENS=500
OPENAI_TEMPERATURE=0.7
OPENAI_TIMEOUT=30

META_APP_ID=1234567890
META_APP_SECRET=your-meta-app-secret
META_WEBHOOK_VERIFY_TOKEN=use-a-long-random-secret
META_GRAPH_VERSION=v20.0
META_GRAPH_URL=https://graph.facebook.com
META_OAUTH_REDIRECT_URI=https://api.frizerino.com/api/v1/admin/social-integrations/callback
META_VERIFY_WEBHOOK_SIGNATURE=true
```

`META_GRAPH_VERSION` treba postaviti na aktuelnu stabilnu verziju koju koristis u Meta App dashboardu. Kod ima default `v18.0`, ali za produkciju je bolje eksplicitno postaviti verziju.

### 9.2. Test/staging primjer

```env
CHATBOT_ENABLED=true

OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini

META_APP_ID=staging-or-same-app-id
META_APP_SECRET=staging-or-same-app-secret
META_WEBHOOK_VERIFY_TOKEN=staging-long-random-secret
META_GRAPH_VERSION=v20.0
META_GRAPH_URL=https://graph.facebook.com
META_OAUTH_REDIRECT_URI=https://api.wizionar.space/api/v1/admin/social-integrations/callback
META_VERIFY_WEBHOOK_SIGNATURE=true
```

### 9.3. Instagram-only env

Instagram-only je disabled dok Meta app nije odobren i dok ne znamo tacne endpoint URL-ove za odobreni Instagram flow.

Default:

```env
META_INSTAGRAM_ONLY_ENABLED=false
```

Kada Meta odobri Instagram-only messaging flow, popuniti:

```env
META_INSTAGRAM_ONLY_ENABLED=true
META_INSTAGRAM_ONLY_APP_ID=${META_APP_ID}
META_INSTAGRAM_ONLY_APP_SECRET=${META_APP_SECRET}
META_INSTAGRAM_ONLY_AUTH_URL=https://...
META_INSTAGRAM_ONLY_TOKEN_URL=https://...
META_INSTAGRAM_ONLY_PROFILE_URL=https://...
META_INSTAGRAM_ONLY_SEND_URL=https://...
META_INSTAGRAM_ONLY_REDIRECT_URI=https://api.frizerino.com/api/v1/admin/social-integrations/callback
META_INSTAGRAM_ONLY_SCOPES=instagram_business_basic,instagram_business_manage_messages
```

`META_INSTAGRAM_ONLY_SEND_URL` moze sadrzati placeholder:

```text
{ig_account_id}
```

Backend ce placeholder zamijeniti Instagram account ID-em iz integracije.

## 10. Laravel komande nakon deploya

Nakon sto env bude postavljen:

```bash
php artisan migrate
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan config:cache
```

Provjera ruta:

```bash
php artisan route:list --path=social-integrations
php artisan route:list --path=chatbot
```

Ocekivane rute:

```text
GET  /api/v1/admin/social-integrations
GET  /api/v1/admin/social-integrations/connect
GET  /api/v1/admin/social-integrations/callback
GET  /api/v1/admin/social-integrations/pending-pages
POST /api/v1/admin/social-integrations/select-page
PATCH /api/v1/admin/social-integrations/{id}
POST /api/v1/admin/social-integrations/disconnect
POST /api/v1/admin/social-integrations/{salon}/force-disconnect
GET  /api/v1/admin/social-integrations/health
GET  /api/v1/chatbot/webhook
POST /api/v1/chatbot/webhook
```

## 11. Kako admin ukljucuje premium opciju salonu

U Frizerino admin panelu:

1. Otvori `Upravljanje salonima`.
2. Pronadji salon.
3. Klikni `Ukljuci Premium DM`.
4. Salon dobija `AI DM Premium` badge.
5. Vlasnik salona sada vidi dugmad za povezivanje.

Backend polje:

```text
salons.social_integrations_enabled = true
```

Ako admin iskljuci premium:

- `social_integrations_enabled` postaje `false`
- `chatbot_enabled` se automatski gasi
- owner vise ne moze pokrenuti connect/update/disconnect flow
- admin i dalje moze koristiti force disconnect

## 12. Kako vlasnik salona povezuje nalog

Vlasnik salona:

1. Otvori dashboard.
2. Ide na `Instagram/Facebook`.
3. Ako premium nije ukljucen, vidi locked screen.
4. Ako je premium ukljucen, vidi:
   - `Povezi Facebook Page + Instagram`
   - `Povezi samo Instagram`
5. Preporuceni tok je `Povezi Facebook Page + Instagram`.
6. Nakon OAuth-a, ako ima vise Page-ova, bira Page za salon.
7. Integracija postaje aktivna.

Ako izabrana Facebook Page nema povezan Instagram Business account, vlasnik dobija gresku:

```text
Izabrana Facebook stranica nema povezan Instagram Business account.
```

Tada treba u Meta Business Suite povezati Instagram account sa Facebook Page-om i pokusati ponovo.

## 13. AI pravila nakon povezivanja

AI smije odgovarati samo na booking teme:

- usluge
- cijene
- trajanje
- frizeri/osoblje
- slobodni termini
- zakazivanje
- lokacija
- radno vrijeme

AI treba prikupiti:

- uslugu
- datum
- vrijeme
- ime i prezime
- telefon
- opcionalno email
- opcionalno zeljenog frizera

Ako korisnik pita nesto van opsega, AI ga treba ljubazno vratiti na zakazivanje termina.

Ako korisnik trazi osobu ili AI nije siguran, razgovor se oznacava za human follow-up.

## 14. Test checklist

### 14.1. Premium gate

- Salon bez premiuma ne vidi connect dugmad.
- Direktan poziv na `/connect` bez premiuma vraca `premium_required`.
- Admin ukljuci premium i owner vidi connect dugmad.
- Admin iskljuci premium i `chatbot_enabled` se gasi.

### 14.2. OAuth

- Owner sa jednom Page stranicom se povezuje bez izbora Page-a.
- Owner sa vise Page-ova dobija izbor.
- Owner bez Page-a dobija jasnu gresku.
- Page bez Instagram Business accounta dobija jasnu gresku.
- Odbijeni permission vraca OAuth error status.

### 14.3. Webhook

- Meta verify challenge prolazi sa tacnim tokenom.
- Webhook bez `X-Hub-Signature-256` se odbija.
- Webhook sa pogresnim potpisom se odbija.
- Echo poruke se ignorisu.
- Duplikat `message_id` se ne obradjuje dva puta.

### 14.4. AI booking

- Korisnik pita za usluge i dobija listu usluga.
- Korisnik pita za cijene i dobija cijene iz baze.
- Korisnik trazi termin, AI pita za nedostajuce podatke.
- AI provjerava slobodne termine prije potvrde.
- Nakon potvrde kreira appointment sa `booking_source=chatbot`.
- Ako termin nije vise slobodan, appointment se ne kreira.
- Van-topic pitanje dobija ogranicen odgovor.

## 15. Troubleshooting

### `premium_required`

Salon nema ukljucen premium entitlement.

Rjesenje:

- Admin panel > Upravljanje salonima > `Ukljuci Premium DM`

### `error_config`

Nedostaju osnovni Meta env podaci.

Provjeri:

```env
META_APP_ID
META_APP_SECRET
META_OAUTH_REDIRECT_URI
META_WEBHOOK_VERIFY_TOKEN
```

### `error_no_instagram_business`

Facebook Page nema povezan Instagram Business account.

Rjesenje:

- U Meta Business Suite povezati Instagram account sa Facebook Page-om.
- Instagram mora biti Business ili Creator account.

### Webhook nije verified

Integracija se oznacava kao `webhook_verified=false` dok ne stigne prva validno potpisana inbound poruka.

Rjesenje:

- Poslati test DM poruku na povezani Instagram/Facebook.
- Provjeriti da Meta salje `X-Hub-Signature-256`.
- Provjeriti `META_APP_SECRET`.

### Missing scopes

Health-check prikazuje dozvole koje nisu granted.

Rjesenje:

- Provjeriti App Review status.
- Ponovo povezati salon nakon sto su permissioni odobreni.

### Token expired/revoked

Rjesenje:

- Owner treba pokrenuti povezivanje ponovo.
- Admin moze force-disconnect ako premium vise nije aktivan.

## 16. Sigurnosne napomene

- Nikad ne commitati `.env`.
- `META_APP_SECRET`, `OPENAI_API_KEY` i tokeni moraju ostati tajni.
- U produkciji `META_VERIFY_WEBHOOK_SIGNATURE=true`.
- Koristiti HTTPS za sve OAuth i webhook URL-ove.
- Tokeni se cuvaju encrypted kroz Laravel model accessor/mutator.
- Ako se premium iskljuci salonu, AI se automatski gasi.

## 17. Minimalni production go-live checklist

- [ ] Meta Business App kreiran.
- [ ] Business/app verifikacija zavrsena ako Meta trazi.
- [ ] App domains podeseni.
- [ ] Privacy Policy URL javno dostupan.
- [ ] Terms URL javno dostupan.
- [ ] Data Deletion URL/instructions javno dostupni.
- [ ] OAuth redirect URI dodat.
- [ ] Webhook callback URL dodat.
- [ ] Verify token isti kao u `.env`.
- [ ] Permissioni poslani na App Review.
- [ ] App Review odobren.
- [ ] `.env` podesen na produkciji.
- [ ] `php artisan migrate` izvrsen.
- [ ] `php artisan config:cache` izvrsen.
- [ ] Admin ukljucio premium salonu.
- [ ] Owner uspjesno povezao Page + Instagram.
- [ ] Test DM poruka obradjena.
- [ ] Test appointment kreiran preko AI-a.
