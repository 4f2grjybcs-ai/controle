# Contrôle Parapente — plugin WordPress

Plugin WordPress pour gérer les **contrôles (révisions) de parapentes** d'un atelier :
demandes en ligne, fiche de contrôle technique, certificat imprimable, suivi client et rappels automatiques.

## Espace atelier (nouveau)

À l'activation, le plugin crée une page **« Atelier »** (shortcode `[cp_atelier]`), réservée à l'équipe connectée. Elle s'affiche en plein écran avec son propre style, doux et chaleureux, indépendamment du thème. Un lien « Atelier » apparaît aussi dans la barre d'admin.

- **Liste des contrôles** : filtres « À l'atelier / Terminés / Rendus / Tous », recherche (référence, pilote, aile, n° de série). Tous les rapports restent stockés et consultables.
- **＋ Nouveau contrôle** : le numéro (ex. `CP-2026-0012`) est attribué immédiatement par le système.
- **Fiche à onglets** : *Client & aile* · *Atelier · mesures* (porosité, résistance, calage, visuel) · *Conclusion* · *Rapport client*. Panneau « Suivi » (statut, verdict, dates) toujours visible. Barre « Enregistrer » fixe, alerte si des modifications ne sont pas enregistrées, raccourci Ctrl/Cmd + S.
- **Rapport client** : aperçu exact de ce que reçoit le client, impression / PDF, envoi par e-mail, copie du lien.
- Le rapport affiche votre **logo**, vos **coordonnées** et votre **couleur d'accent** (*Contrôles → Réglages*).

## Fonctionnalités

- **Formulaire de demande** (`[cp_demande_controle]`) : coordonnées du pilote, équipement (marque, modèle, taille, n° de série, homologation…), prestations (contrôle, recalage, réparation, repliage secours), mode de dépôt. Une référence unique est attribuée (ex. `CP-2026-0001`) et des e-mails sont envoyés à l'atelier et au client. Protections : nonce, champ pot de miel anti-robots, limite de 5 demandes/heure par IP, consentement RGPD.
- **Fiche de contrôle** dans l'admin (menu *Contrôles*) :
  - porosité (porosimètre, en secondes) avec évaluation automatique Bon / Acceptable / Échec ;
  - résistance du tissu (Bettsomètre, en daN) et des suspentes (rupture mesurée vs minimum requis) ;
  - **calage** (voir ci-dessous) ;
  - contrôle visuel (12 points : extrados, intrados, cloisons, suspentes, élévateurs, maillons, accélérateur, freins…) ;
  - réparations, observations pour le client, notes internes ;
  - statut (demande → réceptionnée → en cours → terminée → rendue), verdict (navigable / avec réserves / non navigable), date du prochain contrôle proposée automatiquement.
- **Saisie à l'atelier** : quand le client apporte son matériel, *Contrôles → Nouveau contrôle* ouvre une fiche au statut « Aile réceptionnée » avec la date du jour. Le champ « Client ou aile déjà venus ? » retrouve un ancien contrôle (nom, e-mail, n° de série, modèle, référence) et reprend les coordonnées, l'équipement et, pour le calage, la structure du suspentage et les cotes usine. L'e-mail du client est facultatif (sans e-mail, aucun message n'est envoyé).
- **Liste des contrôles** : colonnes pilote, équipement, statut, verdict, échéance (en rouge si dépassée), filtres par statut et par échéance, recherche par référence / pilote / aile / n° de série.
- **Certificat / fiche imprimable** (A4, « enregistrer en PDF » depuis le navigateur). Lien public sécurisé par clé secrète envoyé au client ; les notes internes n'y apparaissent jamais.
- **Suivi client** (`[cp_suivi_controle]`) : le client saisit sa référence + son e-mail pour voir l'avancement, le résultat et accéder à sa fiche.
- **E-mails** : nouvelle demande (atelier + client), changement de statut (optionnel, case à cocher), **rappel automatique** X jours avant l'échéance du prochain contrôle (un seul rappel par échéance, pas de rappel si la même aile a déjà un contrôle plus récent).
- **Réglages** : coordonnées de l'atelier, n° d'agrément, préfixe des références, validité d'un contrôle (3 ans ou 150 h de vol, selon la PMA), délai de rappel, seuils de porosité, tolérance de calage, textes du formulaire et du certificat.

## Normes

Le rapport suit le PMA Standard « Periodical Inspection of Paragliders » (V 2024.12.1) et les recommandations en vigueur pour les ateliers de contrôle :

- identification de l'atelier : nom, responsable, n° de police d'assurance RC pro ;
- **type d'inspection** (révision périodique, intermédiaire, basique, mécanique, géométrique, visuelle/incident) : les tests non inclus apparaissent **« Non réalisé »**, avec la préconisation du constructeur en commentaire ;
- **synthèse** : interprétation de chacune des trois inspections (visuelle, mécanique, géométrique) et **curseur d'état global** (Neuf / Très bon / Bon / Acceptable / Limite / Réformé), avec la liste des tests sur lesquels il repose (même si tous n'ont pas été réalisés) ; aucun pourcentage d'usure ni durée de vie restante ;
- **porosité** saisie en secondes et convertie en **l/m²/min** sur le rapport (5400 ÷ secondes, sous 20 mbar) : 4 zones sur l'envergure, extrados entre 5 et 30 % de la corde ; moyenne par zone < 360 Bon, 360–540 Acceptable, > 540 Échec. Comme le recommande la PMA, les valeurs brutes ne sont pas affichées au client (option dans les réglages) ;
- **déchirure** (Bettsomètre, en **daN**) : < 0,6 Échec, 0,6–0,7 Acceptable, > 0,7 Bon ;
- **résistance des suspentes** : pour chaque suspente testée (niveaux A1 bas → haut), **type** choisi dans un catalogue (résistance à neuf et matière) ; le **minimum est calculé automatiquement** selon la PMA : *valeur à neuf × source (constructeur 1,00 / fournisseur 1,05) × matière (aramide / Technora / Vectran 0,45 ; Dyneema 0,65)*, ou saisi à la main si le constructeur donne un minimum. Le rapport indique le **% de la résistance à neuf** ;
- **contrôle visuel** avec les mêmes termes que l'état global : Neuf / Très bon / Bon / Acceptable / Limite / Réformé ;
- **conditions** (température 5–35 °C, humidité 30–80 %), date de conformité constructeur, consignes de sécurité, heures de vol (prochain contrôle = heures actuelles + 150 h) ;
- **contrôle partiel** : si les 5 tests ne sont pas tous réalisés, le rapport affiche un avertissement. Un test non réalisé peut être marqué **« Non nécessaire »** ou **« Non demandé »** : il est alors validé, l'avertissement disparaît et le rapport peut attester de la navigabilité (le test reste indiqué comme tel sur le rapport) ;
- **calage** : longueurs mesurées sous la tension indiquée (5 daN selon le PMA) ;
- origine des seuils (constructeur → PMA → référence de l'atelier), modifiable pour chaque contrôle ;
- instruments de mesure et dates d'étalonnage (alerte dans l'atelier si l'étalonnage a plus de 12 mois).

> Les valeurs par défaut (seuils, listes, types d'inspection) sont une base de travail : vérifiez-les avec les textes officiels (standard PMA, recommandations fédérales), et ajustez-les dans *Contrôles → Réglages*.

## Impression

Le rapport s'imprime (ou s'enregistre en PDF) sur des pages **A4** sans couper les cartes, les dessins, les petits tableaux ni les lignes ; les titres restent avec leur contenu et les en-têtes des longs tableaux sont répétés.

## Réglages

*Contrôles → Réglages* regroupe tout ce qui est modifiable, par onglets : **Atelier** (identité, assurance, logo, couleur), **Normes & seuils**, **Instruments**, **Textes du rapport** (titres, curseur d'état, mentions), **Listes** (types d'inspection, points visuels, points de mesure, prestations), **E-mails** (modèles avec variables `{client}`, `{reference}`, `{aile}`, `{lien}`…) et **Formulaire public**.

## Calage

Dans l'espace atelier, le calage a son propre onglet **« Calage »** dans la fiche. La saisie reprend la feuille de calage habituelle de l'atelier, en plus pratique.

1. **Structure & couleurs** : pour chaque rangée A, B, C, D (et les **freins**, à part), ajoutez les groupes en choisissant leur **couleur**, puis le nombre de suspentes de chaque groupe, du centre vers le bout d'aile.
   - **Cases vides** : chaque groupe peut avoir des cases vides, pour que les groupes restent face à face d'une rangée à l'autre (ex. 4 A, 4 B, 4 C mais 5 D). Le bouton **« Aligner les groupes »** les calcule automatiquement ; un clic dans le petit plan du groupe place la case vide **au début, au milieu ou à la fin**.
2. **Mesures usine** : les cotes du constructeur, saisies une seule fois pour la fiche, avec l'**élévateur** ; l'usine corrigée s'affiche à côté (*usine + élévateur*).
3. **Feuille de calage** : pour la 1ère ou la 2e mesure, côté gauche ou droit, seulement **Mesures voile → Résultat**, puis **Max / Min / Diff** (dernière colonne : la différence max − min entre les rangées).
   - En haut : **Tolérance ±** (12 mm par défaut selon la PMA, freins de 0 à +50 mm, boutons − / +) et **Offset** (ajouté à chaque mesure, alerte au-delà de ±1,5 % de la plus grande longueur), dates, 1ère mesure figée.
   - *Résultat = voile + offset − usine corrigée* : case verte dans la tolérance, **rouge dès qu'elle en sort** (la case de saisie se colore aussi).
   - Flèches et Entrée pour se déplacer ; collez une colonne (ou un bloc) depuis Excel, Google Sheets ou le logiciel du laser.
   - Sous la feuille : l'écart moyen par groupe et l'**aperçu du dessin client**, mis à jour pendant la saisie.

**Rapport client** : uniquement le **dessin de l'aile vue de dessus**, **avant intervention** (1ère mesure) et **après intervention** (mesure finale) ; chaque groupe y est placé sur ses points d'accroche avec son écart moyen, vert dans la tolérance, rouge au-delà. Les tableaux détaillés restent sur la fiche atelier imprimée.

## Installation

1. Copier le dossier `controle-parapente/` dans `wp-content/plugins/` (ou le zipper et l'installer via *Extensions → Ajouter → Téléverser*).
2. Activer **Contrôle Parapente**.
3. Aller dans *Contrôles → Réglages* pour renseigner l'atelier et les seuils.
4. (Facultatif) Créer une page « Demande de contrôle » contenant `[cp_demande_controle]` et une page « Suivi de mon contrôle » contenant `[cp_suivi_controle]`. Si les clients vous apportent directement leur matériel, vous pouvez vous passer de ces pages et tout saisir dans l'admin.

Pour créer le zip : `cd controle && zip -r controle-parapente.zip controle-parapente`

## Personnalisation

- Le certificat peut être surchargé en copiant `templates/certificate.php` dans `votre-theme/controle-parapente/certificate.php`.
- Filtre `cp_email` pour modifier les e-mails avant envoi.
- À la désinstallation, les réglages sont supprimés mais les fiches de contrôle sont conservées (historique). Définir `define( 'CP_DELETE_DATA', true );` dans `wp-config.php` pour tout supprimer.

## Pré-requis

WordPress 6.0+, PHP 7.4+. Les e-mails passent par `wp_mail()` : un plugin SMTP est recommandé pour une bonne délivrabilité.
