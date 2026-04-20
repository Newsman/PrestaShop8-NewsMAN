# Modulul Newsman pentru PrestaShop 8 - Ghid de Configurare

Acest ghid prezinta toate setarile din modulul Newsman pentru PrestaShop 8, pentru a va putea conecta magazinul la contul Newsman si a incepe sa colectati abonati, sa trimiteti newslettere si sa urmariti comportamentul clientilor.

---

## Unde Gasiti Setarile Modulului

Dupa instalarea modulului, accesati **Admin > Modules > Module Manager**, gasiti **Newsman** in lista si faceti click pe **Configure**. Toate setarile se gasesc pe o singura pagina de configurare organizata in sectiuni.

---

## Primii Pasi - Conectarea la Newsman

Inainte de a putea folosi orice functionalitate, trebuie sa conectati modulul la contul dvs. Newsman. Exista doua modalitati:

### Optiunea A: Configurare Rapida cu OAuth (Recomandat)

1. Accesati **Admin > Modules > Newsman > Configure**.
2. Faceti click pe butonul **Connect with Newsman** (sau **Reconfigure** daca ati configurat modulul anterior).
3. Veti fi redirectionat catre site-ul Newsman. Autentificati-va daca este necesar si acordati acces.
4. Veti fi redirectionat inapoi catre o pagina in PrestaShop unde alegeti lista de email dintr-un dropdown. Selectati lista pe care doriti sa o folositi si faceti click pe **Save**.
5. Asta e tot - API Key, User ID, Lista si Remarketing ID sunt toate configurate automat.

### Optiunea B: Configurare Manuala

1. Autentificati-va in contul Newsman pe newsman.app.
2. Accesati setarile contului si copiati **API Key** si **User ID**.
3. In PrestaShop, accesati **Admin > Modules > Newsman > Configure**.
4. Activati modulul setand **Enable Newsman** la **Yes**.
5. Introduceti **User ID** si **API Key** in campurile corespunzatoare.
6. Faceti click pe **Save**. Indicatorul de stare a conexiunii de sub campul API Key va arata daca conexiunea a fost realizata cu succes.
7. Selectati **Email List** din dropdown. Listele sunt preluate din Newsman folosind credentialele introduse.
8. Optional, selectati un **Segment**.
9. Faceti click pe **Save** din nou.

---

## Reconfigurare cu Newsman OAuth

Daca trebuie sa reconectati modulul la un alt cont Newsman, sau daca credentialele s-au schimbat, faceti click pe butonul **Reconfigure** de pe pagina de configurare. Acest lucru va va ghida prin acelasi flux OAuth descris mai sus - veti fi redirectionat catre site-ul Newsman pentru a autoriza accesul, apoi inapoi in PrestaShop pentru a selecta lista de email. API Key, User ID, Lista si Remarketing ID vor fi actualizate cu noile credentiale.

---

## Setari Cont

- **Enable Newsman** - Activeaza sau dezactiveaza modulul Newsman. Cand este dezactivat, toate functiile Newsman sunt inactive.

- **User ID** - User ID-ul dvs. Newsman. Se completeaza automat daca ati folosit OAuth.

- **API Key** - API Key-ul dvs. Newsman. Se completeaza automat daca ati folosit OAuth.

- **Stare Conexiune** - Afisata sub campul API Key. Arata un indicator verde "Connected to Newsman." cand credentialele sunt valide, sau un mesaj de eroare rosu daca conexiunea a esuat.

---

## Setari Generale

- **Email List** - Selectati lista de email Newsman care va primi abonatii dvs. Dropdown-ul afiseaza toate listele de email din contul dvs. Newsman (listele SMS sunt excluse).

- **Segment** - Optional, selectati un segment din lista aleasa. Segmentele va permit sa organizati abonatii in grupuri. Daca nu folositi segmente, lasati acest camp gol.

- **Double Opt-in** - Cand este activat, noii abonati primesc un email de confirmare si trebuie sa faca click pe un link pentru a-si confirma abonarea. Aceasta optiune este recomandata pentru conformitatea GDPR. Cand este dezactivat, abonatii sunt adaugati imediat in lista.

- **Send User IP Address** - Cand este activat, adresa IP a vizitatorului este trimisa catre Newsman cand se aboneaza sau dezaboneaza. Acest lucru poate ajuta la analiza si conformitate. Cand este dezactivat, se trimite in schimb adresa **Server IP**.

- **Server IP** - O adresa IP de rezerva folosita cand "Send User IP Address" este dezactivat. De obicei puteti lasa acest camp gol si adresa IP a serverului va fi detectata automat.

### Notificare Multi-Magazin

Daca rulati o configuratie PrestaShop multi-shop si mai multe magazine din grupuri de magazine diferite sunt legate de aceeasi lista Newsman, va aparea un banner de avertizare in partea de sus a sectiunii Generale. Aceasta configuratie adauga complexitate care poate sa nu fie pe deplin rezolvata in versiunea implicita a modulului. Recomandam sa atribuiti o lista diferita fiecarui magazin.

---

## Setari Remarketing

Remarketing-ul permite Newsman sa urmareasca ce pagini si produse vizualizeaza vizitatorii dvs., astfel incat sa le puteti trimite emailuri personalizate (de ex., reamintiri de cos abandonat, recomandari de produse).

- **Enable Remarketing** - Activeaza sau dezactiveaza pixelul de remarketing pe magazinul dvs.

- **Remarketing ID** - Acesta identifica magazinul dvs. in sistemul de urmarire Newsman. Se completeaza automat daca ati folosit OAuth. Il puteti gasi si in contul Newsman la setarile de remarketing.

- **Remarketing ID Status** - Afisat sub campul Remarketing ID. Arata daca Remarketing ID-ul este valid (verde) sau invalid (rosu). Daca este invalid, verificati valoarea Remarketing ID in contul dvs. Newsman.

- **Anonymize IP Address** - Cand este activat, adresele IP ale vizitatorilor sunt anonimizate inainte de a fi trimise catre Newsman. Recomandat pentru conformitatea GDPR.

- **Send Telephone** - Include numerele de telefon ale clientilor in datele de remarketing. Se aplica doar clientilor autentificati care au furnizat un numar de telefon.

- **Theme Cart Compatibility** - Controleaza modul in care pixelul de remarketing detecteaza modificarile din cos. Activat implicit.
  - **Activat** - Foloseste polling in fundal si intercepteaza cererile AJAX/fetch catre endpoint-ul cart al Newsman pentru a detecta schimbarile din cos. Este modul cel mai fiabil si functioneaza pe orice tema, dar genereaza cereri suplimentare in fundal pe fiecare pagina.
  - **Dezactivat** - Mod mai usor care asculta raspunsurile JSON AJAX ale PrestaShop pentru `/cart` si citeste direct `cart.products`. Fara polling in fundal si fara cereri catre endpoint-ul cart al Newsman, dar functioneaza doar daca tema ta foloseste fluxurile standard PrestaShop pentru cos (Classic si majoritatea temelor personalizate se incadreaza).
  - **Nota:** Daca dezactivezi aceasta optiune, goleste cache-ul PrestaShop si apoi foloseste instrumentul **Check installation** din newsman.app Remarketing pentru a verifica daca evenimentele de adaugare/stergere/golire a cosului sunt detectate corect pe tema ta.

---

## Setari pentru Dezvoltatori

Aceste setari sunt destinate utilizatorilor avansati si dezvoltatorilor. In cele mai multe cazuri, ar trebui sa le lasati la valorile implicite.

- **Log Severity** - Controleaza cat de mult detaliu scrie modulul in fisierul de log. Optiunile variaza de la **None** (fara logare) prin **Error**, **Warning**, **Notice**, **Info**, pana la **Debug** (detaliu maxim). Valoarea implicita este **Error**, care inregistreaza doar problemele. Setati la **Debug** daca investigati o problema (dar nu uitati sa il setati inapoi dupa aceea, deoarece modul Debug creeaza fisiere de log mari).

- **Log Clean Days** - Sterge automat fisierele de log mai vechi decat acest numar de zile. Minim 1 zi.

- **API Timeout** - Cate secunde asteapta modulul un raspuns de la Newsman inainte de a renunta. Minim 5 secunde. Valoarea implicita functioneaza bine pentru majoritatea configuratiilor.

- **Enable IP Restriction** - Doar pentru dezvoltare si testare. Cand este activat, functionalitatea modulului este restrictionata la adresa IP de dezvoltator specificata. Aceasta optiune nu ar trebui activata intr-un mediu de productie.

- **Developer IP** - Adresa IP permisa cand restrictia IP este activata. Vizibila doar cand Enable IP Restriction este setat la Yes.

---

## Setari Autorizare Export

- **Authenticate Token** - Afisat ca valoare mascata doar pentru citire. Folosit pentru autentificarea API Newsman. Este schimbat automat cand se modifica API key, User ID, flag-ul de activare sau lista, si cand se face reconfigurare sau autentificare cu Newsman.

- **Header Name** - Nume personalizat de header HTTP pentru autorizarea exportului. Format: caractere alfanumerice separate de cratime. Setati aceasta valoare in campurile corespunzatoare din Newsman App > E-Commerce > Coupons > Authorisation Header name, Newsman App > E-Commerce > Feed > un feed > Header Authorization, etc.

- **Header Key** - Valoare personalizata de header HTTP pentru autorizarea exportului. Format: caractere alfanumerice separate de cratime. Setati aceasta valoare in campurile corespunzatoare din Newsman App asa cum este descris mai sus.

Daca v-ati conectat prin OAuth, Authenticate Token-ul este schimbat automat si in general nu trebuie sa configurati manual Header Name si Header Key. Aceste campuri sunt furnizate pentru configurari avansate unde doriti sa adaugati un nivel suplimentar de securitate la exporturile de date.

---

## Intrebari Frecvente

### Cum stiu daca conexiunea functioneaza?

Dupa introducerea credentialelor si salvare, verificati indicatorul de stare a conexiunii de sub campul API Key. Ar trebui sa arate un mesaj verde "Connected to Newsman." De asemenea, verificati ca dropdown-ul **Email List** afiseaza listele dvs. Newsman. Fiecare cont Newsman are cel putin o lista implicit, deci daca credentialele sunt corecte, listele vor aparea.

### Ce este Double Opt-in?

Cand Double Opt-in este activat, noii abonati primesc un email de confirmare cu un link pe care trebuie sa faca click pentru a-si confirma abonarea. Aceasta asigura ca adresa de email este valida si ca persoana chiar doreste sa se aboneze. Double Opt-in este recomandat pentru conformitatea GDPR.

### Scripturile de remarketing nu apar pe storefront. Ce ar trebui sa fac?

Verificati ca **Enable Remarketing** este setat la Yes si ca **Remarketing ID** este valid (verificati indicatorul de stare). Apoi vizualizati sursa paginii storefront-ului dvs. si cautati scriptul de remarketing Newsman. Daca scriptul inca nu apare, verificati logurile PrestaShop pentru erori.

### Unde sunt logurile modulului?

Modulul scrie loguri care pot fi vizualizate in **Admin > Modules > Newsman > Logs** (vizualizator de loguri incorporat in modul). Fisierele de log sunt de asemenea stocate pe disc. Nivelul de logare este controlat din Setarile pentru Dezvoltatori. Fisierele de log mai vechi decat numarul de zile configurat sunt curatate automat.

### Pot configura liste diferite pentru magazine diferite?

Da. Intr-o configuratie PrestaShop multi-shop, puteti configura liste, segmente, ID-uri de remarketing si alte setari diferite pentru fiecare magazin sau grup de magazine. Recomandam sa atribuiti o lista diferita fiecarui magazin.

### Ce se intampla cand un client se aboneaza la newsletter?

Cand un client se aboneaza prin formularul de newsletter, inregistrarea contului sau pagina de setari a contului, modulul trimite automat abonarea catre Newsman folosind lista si segmentul configurate. Daca Double Opt-in este activat, Newsman va trimite mai intai un email de confirmare.
