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
  - porosité (porosimètre, en secondes) avec évaluation automatique Conforme / À surveiller / Non conforme ;
  - résistance du tissu (Bettsomètre) et des suspentes (rupture mesurée vs minimum requis) ;
  - **calage** (voir ci-dessous) ;
  - contrôle visuel (12 points : extrados, intrados, cloisons, suspentes, élévateurs, maillons, accélérateur, freins…) ;
  - réparations, observations pour le client, notes internes ;
  - statut (demande → réceptionnée → en cours → terminée → rendue), verdict (navigable / avec réserves / non navigable), date du prochain contrôle proposée automatiquement.
- **Saisie à l'atelier** : quand le client apporte son matériel, *Contrôles → Nouveau contrôle* ouvre une fiche au statut « Aile réceptionnée » avec la date du jour. Le champ « Client ou aile déjà venus ? » retrouve un ancien contrôle (nom, e-mail, n° de série, modèle, référence) et reprend les coordonnées, l'équipement et, pour le calage, la structure du suspentage et les cotes usine. L'e-mail du client est facultatif (sans e-mail, aucun message n'est envoyé).
- **Liste des contrôles** : colonnes pilote, équipement, statut, verdict, échéance (en rouge si dépassée), filtres par statut et par échéance, recherche par référence / pilote / aile / n° de série.
- **Certificat / fiche imprimable** (A4, « enregistrer en PDF » depuis le navigateur). Lien public sécurisé par clé secrète envoyé au client ; les notes internes n'y apparaissent jamais.
- **Suivi client** (`[cp_suivi_controle]`) : le client saisit sa référence + son e-mail pour voir l'avancement, le résultat et accéder à sa fiche.
- **E-mails** : nouvelle demande (atelier + client), changement de statut (optionnel, case à cocher), **rappel automatique** X jours avant l'échéance du prochain contrôle (un seul rappel par échéance, pas de rappel si la même aile a déjà un contrôle plus récent).
- **Réglages** : coordonnées de l'atelier, n° d'agrément, préfixe des références, validité d'un contrôle (24 mois par défaut), délai de rappel, seuils de porosité, tolérance de calage, textes du formulaire et du certificat.

## Normes : PMA et charte FFVL ParachecK®

Le rapport suit la structure demandée par la charte FFVL ParachecK® (V3.01) et le PMA Standard « Periodical Inspection of Paragliders » (V 2024.12.1) :

- identification de l'atelier : nom, responsable, n° de police d'assurance RC pro, mention « signataire ParachecK » ;
- **type d'inspection** (révision périodique, intermédiaire, basique, mécanique, géométrique, visuelle/incident) : les tests non inclus apparaissent **« Non réalisé »**, avec la préconisation du constructeur en commentaire ;
- **synthèse** : interprétation de chacune des trois inspections (visuelle, mécanique, géométrique) et **curseur d'état global** (Neuf → Réforme), uniquement après une révision périodique complète ; aucun pourcentage d'usure ni durée de vie restante ;
- **porosité en l/m²/min** (ou secondes) : chaque point avec sa position, minimum, maximum, moyenne, valeurs d'alerte et de réforme ;
- **déchirure** (Bettsomètre, en g) et **résistance des suspentes** par étage (rupture en daN et seuil minimum) ;
- **calage** : longueurs mesurées sous la tension indiquée (5 daN selon le PMA) ;
- origine des seuils (constructeur → PMA → charte), modifiable pour chaque contrôle ;
- instruments de mesure et dates d'étalonnage (alerte dans l'atelier si l'étalonnage a plus de 12 mois).

> Les valeurs par défaut (seuils, listes, types d'inspection) sont une base de travail : vérifiez-les avec les textes officiels de la charte ParachecK et du standard PMA, et ajustez-les dans *Contrôles → Réglages*.

## Réglages

*Contrôles → Réglages* regroupe tout ce qui est modifiable, par onglets : **Atelier** (identité, assurance, logo, couleur), **Normes & seuils**, **Instruments**, **Textes du rapport** (titres, curseur d'état, mentions), **Listes** (types d'inspection, points visuels, points de mesure, prestations), **E-mails** (modèles avec variables `{client}`, `{reference}`, `{aile}`, `{lien}`…) et **Formulaire public**.

## Calage

1. **Structure & couleurs** : pour chaque rangée A, B, C, D (et les **freins**, à part), ajoutez les groupes en choisissant leur **couleur**, puis le nombre de suspentes de chaque groupe. Les suspentes sont numérotées A1, A2… dans l'ordre des groupes.
2. **Côtés** : gauche + droite, ou un seul côté.
3. **Offset de mesure** (mm) : ajouté à toutes les mesures, 1ère et finale (correction du banc, maillons, tension…).
4. **1ère mesure** : saisissez les cotes usine et les longueurs brutes lues sur le banc (Entrée = case suivante dans la colonne). Enregistrez ; cochez « 1ère mesure figée » pour ne plus pouvoir la modifier.
5. Travaillez sur le suspentage, puis faites la **mesure finale** (le bouton « Copier la 1ère mesure » pré-remplit les cases non retouchées).
6. **Résultat** : case **offset** et cases **élévateur** (A, B, C, D, freins, gauche/droite) avec boutons − / + ; « Proposer le réglage » calcule la valeur qui ramène l'écart moyen de chaque rangée à 0.
7. Le **tableau des décalages par groupe** donne pour chaque groupe et chaque côté : écart moyen à la 1ère mesure, **correction suggérée**, écart moyen final, ajustement réellement réalisé, écart maximum et état (conforme / hors tolérance).

Écart = mesure brute + offset − cote usine. Les deux tableaux (par groupe et par suspente) figurent sur la fiche imprimable.

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
