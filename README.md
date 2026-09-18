# IM Business Solutions — Site vitrine + application Symfony

Dépôt **fusionné** : le site vitrine historique en **HTML / CSS / JS +
PHP/MySQL** (formulaires, back-office `/admin`) cohabite avec une
**application Symfony** (page `/realisations` publique, back-office
Symfony `/admin` en cours de migration — voir plus bas) sur le même
hébergement mutualisé OVH. Les deux parties partagent le même document
racine (`public/`) sur le serveur, d'où la fusion en un seul dépôt.

## Structure

```
composer.json, symfony.lock, importmap.php   Application Symfony (racine du dépôt)
bin/, config/, migrations/, src/, templates/, tests/, translations/
assets/                       Sources Symfony (AssetMapper) — app.js, controllers/, styles/app.css
vendor/, node_modules/          Non commités (.gitignore) — vendor/ régénéré par `composer install`
                                 en CI ; node_modules/ n'est utile qu'en local (aucun build JS en CI,
                                 les assets compilés sont commités dans public/assets/ et public/build/)

sql/schema.sql                Structure de la base du site PHP (à importer une fois)

public/                       Document racine servi par Apache (im-business-solutions.fr)
  .htaccess                     Cohabitation Symfony / site statique (voir commentaires dedans)
  index.php                     Front controller Symfony
  build/, assets/                Assets Symfony compilés (commités, pas de build en CI)
  uploads/                       Images des réalisations Symfony (généré en prod)

  index.html, a-propos.html, services.html, methode.html, contact.html   Pages statiques
  realisations.php                                                       Legacy PHP (redirigé vers
                                                                           /realisations par .htaccess)

  includes/                    Partagé public + admin PHP — voir .htaccess (accès direct bloqué)
    config.sample.php            Modèle de configuration → à copier en config.php
    config.php                   Identifiants (À CRÉER sur le serveur, jamais commité/partagé)
    db.php                       Connexion PDO + app_config()
    helpers.php                  h(), post(), redirect(), flash_*(), query_url()...
    mailer.php                   send_notification_email() (via mail())

  api/                          Endpoints publics anonymes (JSON), sans session
    contact.php                   Traite le formulaire de contact
    devis.php                     Traite la modale « Demander un devis »

  admin/                        Back-office PHP historique (authentification requise)
    login.php, logout.php, setup.php   Connexion / création du 1er compte
    index.php                          Tableau de bord (vrais chiffres)
    demandes.php, demande-voir.php     Demandes de devis (filtres, statut, export CSV)
    messages.php, message-voir.php     Messages de contact (lu/non lu, export CSV)
    realisations.php                   Réalisations (CRUD + upload d'image)
    export-devis.php, export-messages.php
    actions/                           Scripts POST (CSRF requis) : statut, suppression,
                                        sauvegarde réalisation, profil, mot de passe
    includes/                          auth.php, csrf.php, ui.php, layout_top/bottom.php
    css/admin.css, js/admin.js

  css/style.css, js/main.js     Site public (styles + interactions + envoi des formulaires),
                                 réutilisé tel quel par les pages Symfony (même document racine)
  assets/                       Logos, photos de fond, images des réalisations PHP
    images/projets/               Photos des réalisations PHP (créé automatiquement à l'upload)
```

## Application Symfony

Récupérée depuis la production OVH (elle n'était versionnée nulle part
avant) : back-office en cours de migration (`src/Controller/RealisationController.php`,
`UserController.php`, `SecurityController.php`) et page publique
**`/realisations`** (`src/Controller/PublicRealisationController.php` +
`templates/realisation/public.html.twig`), qui réutilise directement
`public/css/style.css` et `public/js/main.js` du site PHP pour rester
visuellement cohérente.

- Base de données MySQL **partagée** avec le site PHP (table `realisation`
  au singulier pour Symfony via Doctrine, `realisations` au pluriel pour le
  site PHP — ce sont deux tables distinctes, pas une migration terminée).
- Cache Symfony (Twig, container) compilé en prod : après un déploiement
  qui touche `templates/` ou `src/`, vider `var/cache/prod/` sur le serveur
  (pas d'accès shell sur cet hébergement → suppression via SFTP) pour que
  les changements soient pris en compte.
- `.env.local.php` (généré via `composer dump-env prod`) contient les
  secrets de prod (`DATABASE_URL`, `APP_SECRET`) — jamais commité, à
  recréer sur le serveur si besoin.

## Mise en ligne (hébergement mutualisé)

1. **Créer une base MySQL** dans l'espace client de l'hébergeur (nom, utilisateur,
   mot de passe — notez-les).
2. **Importer `sql/schema.sql`** dans cette base (phpMyAdmin → Importer, ou
   `mysql -u ... -p nom_base < sql/schema.sql`).
3. **Copier `public/includes/config.sample.php` en `public/includes/config.php`**
   (directement sur le serveur, jamais commité) et renseigner :
   - vos identifiants MySQL (`db`),
   - l'adresse qui doit recevoir les devis/messages et l'adresse d'expédition
     (`mail` — idéalement une adresse du même nom de domaine),
   - l'URL du site (`app.base_url`).
4. **Uploader tout le dépôt** (FTP/SFTP ou gestionnaire de fichiers) à la
   racine du compte hébergeur — `public/` doit correspondre au document
   racine configuré côté hébergeur (ex. OVH : Multisite → Dossier racine).
5. Vérifier que `public/includes/.htaccess` est bien pris en compte : ouvrez
   `https://votre-domaine/includes/config.php` dans un navigateur, vous
   devez obtenir une **erreur 403** (jamais le contenu du fichier). Si
   l'hébergeur n'utilise pas Apache ou ignore les `.htaccess`, contactez le
   support pour bloquer l'accès à ce dossier autrement.
6. Ouvrir `https://votre-domaine/admin/` : la page **de création du premier
   compte administrateur** s'affiche automatiquement tant qu'aucun compte
   n'existe. Une fois créé, cette page (`setup.php`) se désactive d'elle-même.
7. Vérifier l'envoi d'e-mail (remplir le formulaire de contact du site). En
   cas de non-réception : vérifier les spams, puis l'adresse `from` dans
   `config.php` (voir commentaire dans le fichier).

**Aperçu local sans PHP** : les pages `.html` s'ouvrent directement dans le
navigateur, mais `realisations.php`, les formulaires et tout `/admin/`
nécessitent un serveur PHP + MySQL (ex. WampServer/Laragon sous Windows,
`php -S localhost:8000` avec une base locale, ou un environnement de dev chez
l'hébergeur).

## Déploiement automatique (CI/CD)

Une fois la mise en ligne initiale faite (étapes 1 à 3 ci-dessus), les mises à
jour suivantes peuvent être automatisées par `.github/workflows/deploy-prod.yaml` :
à chaque tag `vX.Y.Z` poussé sur `main`, GitHub Actions vérifie la syntaxe PHP
de tous les fichiers, exécute `composer install --no-dev` (vendor/ n'est pas
commité), puis envoie tout le dépôt par **SFTP** sur l'hébergement — sans
jamais toucher à `public/includes/config.php`, `.env.local(.php)`,
`public/uploads/` ni `public/assets/images/projets/` (exclus du suivi Git via
`.gitignore`, donc ignorés par le déploiement).

⚠️ **Statut actuel : ce workflow échoue à l'étape de déploiement.** Les
runners GitHub Actions (IP Azure) n'arrivent pas à joindre le port SSH/SFTP
de cet hébergement OVH (timeout réseau) — probablement un filtrage des IP de
datacenters cloud côté OVH. En attendant une résolution (support OVH, ou
runner auto-hébergé sur une machine que l'hébergeur laisse passer), le
déploiement se fait manuellement en SFTP depuis une machine autorisée.

**Mise en place (une seule fois) :**

1. Créer le dépôt Git et le pousser sur GitHub (`git init`, premier commit,
   `git push` vers un repo `main`) — je peux le faire avec toi si tu veux,
   dis-le-moi.
2. Dans le repo GitHub → **Settings → Secrets and variables → Actions**,
   ajouter :
   | Secret | Valeur |
   |---|---|
   | `FTP_SERVER` | ex. `ftp.im-business-solutions.fr` |
   | `FTP_USERNAME` | identifiant FTP de l'hébergeur |
   | `FTP_PASSWORD` | mot de passe FTP |
   | `FTP_SERVER_DIR` | dossier racine web côté serveur (ex. `/www/` ou `/`) |

   (Ces identifiants ne doivent jamais être partagés en dehors des secrets
   GitHub — ni dans le code, ni en conversation.)
3. Faire la mise en ligne initiale manuellement une première fois (section
   précédente) : le workflow déploie les mises à jour, pas la création de la
   base de données ni le premier `config.php`.

**Pour publier une nouvelle version ensuite :**

```
git tag v1.0.0
git push origin v1.0.0
```

Le job `ci` vérifie le code, puis `deploy-prod` déploie automatiquement — mais
uniquement si le tag pointe bien sur un commit de `main` (sécurité reprise du
workflow d'origine).

## Sécurité — ce qui est en place

- Mots de passe hashés (`password_hash`/`password_verify`), jamais stockés en clair.
- Sessions PHP côté admin, régénérées à la connexion.
- **CSRF** : jeton de session vérifié sur toutes les actions admin qui modifient
  des données (`admin/includes/csrf.php`).
- **Requêtes préparées PDO** partout (aucune concaténation SQL).
- Upload d'image des réalisations : vérification du type MIME réel (pas
  seulement l'extension), taille max 5 Mo, nom de fichier régénéré.
- Formulaires publics (contact/devis) : pas de session requise, donc pas de
  CSRF classique ; protection anti-spam par **champ piège** (`website`,
  invisible, doit rester vide) + limite de 8 envois/heure par IP.
- `public/includes/` bloqué en accès direct via `.htaccess`
  (`Require all denied`).
- `admin/setup.php` se désactive automatiquement dès qu'un compte existe :
  impossible de s'en servir comme porte dérobée.

**Limites connues (v1)** : un seul compte admin prévu par le flux normal
(un second compte peut être ajouté directement en base si besoin) ; l'envoi
d'e-mail utilise `mail()` (suffisant chez la plupart des hébergeurs — en cas
de soucis de délivrabilité, remplacer `send_notification_email()` dans
`public/includes/mailer.php` par PHPMailer + SMTP, sans toucher au reste du code) ;
la photo de profil admin est un bouton visuel non encore branché.

## Modales (site public)

Toutes injectées par `js/main.js`, fermeture croix / overlay / `Échap`,
piège de focus + blocage du scroll.

- **Demander un devis** : ouverte par `#open-quote-modal` ou
  `[data-modal-open="quote-modal"]`. Envoie vers `api/devis.php`.
- **Domaines d'expertise** (page Services) : 3 modales générées à partir du
  tableau `EXPERTISE` en haut de `main.js` — **c'est là qu'on modifie les
  textes**.

## Réalisations

Deux systèmes coexistent (migration en cours vers Symfony, voir plus haut) :

- **PHP** (table `realisations`, pluriel) : gérées depuis
  `/admin/realisations.php` (créer/modifier/publier ou mettre en
  brouillon/supprimer, avec upload d'image). La page `realisations.php`
  n'est plus directement accessible (redirigée par `.htaccess` vers
  `/realisations`, servie par Symfony) mais reste la source de vérité de ce
  back-office PHP.
- **Symfony** (table `realisation`, singulier) : gérée via
  `src/Controller/RealisationController.php`. La page publique
  **`/realisations`** (`templates/realisation/public.html.twig`) affiche les
  réalisations au statut **« publié »**, les plus récentes en premier.

Dans les deux cas, la carte cliquable ouvre une modale de détail (image,
catégorie, titre, description complète) et les filtres (Communication /
Développement commercial / Apport d'affaires) restent gérés en JavaScript
côté client (`data-cat` / `data-filter`, dans `public/js/main.js`, partagé
par les deux systèmes).

## Formulaires publics

`contact.html` (formulaire) et la modale « Demander un devis » envoient en
JSON vers `api/contact.php` / `api/devis.php`, qui : valident les champs,
enregistrent la demande en base, puis envoient un e-mail de notification.
La page ne recharge pas — un message de succès ou d'erreur s'affiche à la
place du formulaire.

## Aperçu local (design uniquement, sans back-end)

Pour retoucher visuellement les pages statiques sans serveur PHP :

```
npx serve public
```

Les liens vers `realisations.php` et les envois de formulaires ne
fonctionneront pas dans ce mode (nécessitent PHP + MySQL, voir plus haut).

## Palette

| Variable        | Valeur    | Usage                      |
|-----------------|-----------|----------------------------|
| `--navy`        | `#031426` | Fond bleu nuit             |
| `--navy-dark`   | `#020c17` | Fonds très foncés / menu   |
| `--gold`        | `#d9a13b` | Doré principal             |
| `--gold-light`  | `#e5b34d` | Doré clair (hover, accents)|
| `--white`       | `#f7f7f5` | Blanc cassé                |
| `--text-dark`   | `#071426` | Texte sombre               |
